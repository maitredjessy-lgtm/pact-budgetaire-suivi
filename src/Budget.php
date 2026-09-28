<?php
/**
 * Suivi financier : import du budget UE (Annexe III, feuille « 1a. Budget fondé sur les coûts »),
 * calculs budget / realise et generation des rapports financiers.
 * Tous les montants sont en EUR ; conversion FCFA a parite fixe.
 */
class Budget
{
    public const TAUX_FCFA = 655.957;
    public const RUBRIQUES = [
        '1' => 'Ressources humaines',
        '2' => 'Voyages',
        '3' => 'Équipement et fournitures',
        '4' => 'Bureau de projet',
        '5' => 'Autres coûts, services',
        '6' => 'Autres (activités et visibilité)',
        '8' => 'Coûts indirects (7 %)',
    ];

    // ------------------------------------------------------------------ import

    private static function nettoyer(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        // Retire les numeros d'appel de note colles en fin de titre (« Voyages6 », « Bureau de projet14 »)
        return preg_replace('/(?<=[\p{L}\)])\d{1,2}$/u', '', $s);
    }

    private static function partenaire(string $texte): string
    {
        return preg_match('/\bIBP\b/u', $texte) ? 'IBP' : 'SJ';
    }

    /** Importe (ou met a jour) le budget depuis le classeur UE. Les depenses deja saisies sont conservees. */
    public static function importer(PDO $pdo, string $chemin, string $nomFichier): array
    {
        $x = new Xlsx($chemin);
        $feuille = $x->feuilleContenant('1a') ?? $x->feuilleContenant('Budget') ?? throw new RuntimeException("Feuille « 1a. Budget fondé sur les coûts » introuvable dans ce classeur.");
        $lignes = $x->lignes($feuille);

        $codesActivites = $pdo->query("SELECT code, id FROM activites WHERE code IS NOT NULL AND code <> ''")->fetchAll(PDO::FETCH_KEY_PAIR);
        $titresActivites = $pdo->query('SELECT id, titre FROM activites')->fetchAll(PDO::FETCH_KEY_PAIR);
        // Rattachement d'une sous-section budgetaire a une activite du plan de travail :
        // code exact, puis code parent (2.2.2.1 -> 2.2.2), puis mots-cles pour les ateliers de gouvernance (6.x)
        $trouverActivite = function (?string $groupe) use ($codesActivites, $titresActivites): ?int {
            if (!$groupe) {
                return null;
            }
            if (preg_match('/^(\d+(?:\.\d+){1,3})\s/u', $groupe, $m) && !str_starts_with($m[1], '6.')) {
                for ($code = $m[1]; substr_count($code, '.') >= 1; $code = substr($code, 0, strrpos($code, '.'))) {
                    if (isset($codesActivites[$code])) {
                        return (int) $codesActivites[$code];
                    }
                }
            }
            $g = mb_strtolower($groupe);
            foreach ([['lancement du projet', 'lancement officiel du projet'], ['revue et de reflexion', 'revue'], ['suvi semestriels', 'suivi semestrielle'], ['suivi semestriels', 'suivi semestrielle']] as [$motBudget, $motActivite]) {
                if (str_contains($g, $motBudget)) {
                    foreach ($titresActivites as $id => $titre) {
                        if (str_contains(mb_strtolower($titre), $motActivite)) {
                            return (int) $id;
                        }
                    }
                }
            }
            return null;
        };
        $rubrique = null;
        $groupe = null;
        $postes = [];
        $vus = [];
        $ordre = 0;
        foreach ($lignes as $n => $c) {
            if ($n < 3) {
                continue;
            }
            $a = self::nettoyer((string) ($c['A'] ?? ''));
            if ($a === '') {
                continue;
            }
            $montant = isset($c['E']) && is_numeric($c['E']) ? (float) $c['E'] : 0.0;
            if (preg_match('/^(9|1[0-3])\.\s/u', $a) || str_starts_with($a, '7.')) {
                if (str_starts_with($a, '9.')) {
                    break; // fin des rubriques budgetaires
                }
                continue;
            }
            if (preg_match('/^([1-68])\.\s+\D/u', $a, $m)) {
                $rubrique = $m[1];
                $groupe = null;
                if ($m[1] !== '8' || $montant <= 0) {
                    continue;
                }
            }
            if ($rubrique === null || preg_match('/^(Sous-total|Total)/iu', $a)) {
                continue;
            }
            if ($montant <= 0) {
                $groupe = $a; // intitule de sous-section (pas de montant)
                continue;
            }
            $code = preg_match('/^(\d+(?:\.\d+){1,3})\s/u', $a, $m) ? $m[1] : null;
            $cle = "{$rubrique}|{$groupe}|{$a}";
            $vus[$cle] = ($vus[$cle] ?? 0) + 1;
            $postes[] = [
                'cle' => md5($cle . '|' . $vus[$cle]),
                'rubrique' => $rubrique,
                'rubrique_libelle' => self::RUBRIQUES[$rubrique] ?? null,
                'groupe' => $groupe,
                'code' => $code,
                'libelle' => $a,
                'unite' => $c['B'] ?? null,
                'quantite' => is_numeric($c['C'] ?? null) ? (float) $c['C'] : null,
                'cout_unitaire' => is_numeric($c['D'] ?? null) ? (float) $c['D'] : null,
                'montant' => round($montant, 2),
                'montant_a1' => round((float) ($c['I'] ?? 0), 2),
                'montant_a2' => round((float) ($c['M'] ?? 0), 2),
                'montant_a3' => round((float) ($c['Q'] ?? 0), 2),
                'partenaire' => self::partenaire($a . ' ' . $groupe),
                // Seules les activites de la rubrique 6 correspondent au plan de travail
                'activite_id' => $rubrique === '6' ? $trouverActivite($groupe) : null,
                'ordre' => ++$ordre,
            ];
        }
        if (!$postes) {
            throw new RuntimeException("Aucune ligne budgétaire reconnue dans la feuille « {$feuille} ».");
        }

        $pdo->beginTransaction();
        try {
            $existantes = $pdo->query('SELECT cle, id FROM budget_lignes')->fetchAll(PDO::FETCH_KEY_PAIR);
            $champs = ['rubrique', 'rubrique_libelle', 'groupe', 'code', 'libelle', 'unite', 'quantite', 'cout_unitaire', 'montant', 'montant_a1', 'montant_a2', 'montant_a3', 'partenaire', 'activite_id', 'ordre'];
            $maj = $pdo->prepare('UPDATE budget_lignes SET ' . implode(', ', array_map(fn ($f) => "{$f} = ?", $champs)) . ', obsolete = 0 WHERE cle = ?');
            $ins = $pdo->prepare('INSERT INTO budget_lignes (cle, ' . implode(', ', $champs) . ') VALUES (?' . str_repeat(', ?', count($champs)) . ')');
            $stats = ['crees' => 0, 'mis_a_jour' => 0, 'retires' => 0];
            foreach ($postes as $p) {
                $valeurs = array_map(fn ($f) => $p[$f], $champs);
                if (isset($existantes[$p['cle']])) {
                    $maj->execute([...$valeurs, $p['cle']]);
                    $stats['mis_a_jour']++;
                    unset($existantes[$p['cle']]);
                } else {
                    $ins->execute([$p['cle'], ...$valeurs]);
                    $stats['crees']++;
                }
            }
            // Lignes disparues du nouveau budget : supprimees si aucune depense, sinon marquees obsoletes
            foreach ($existantes as $id) {
                $n = $pdo->prepare('SELECT COUNT(*) FROM depenses WHERE ligne_id = ?');
                $n->execute([$id]);
                $n->fetchColumn() > 0
                    ? $pdo->prepare('UPDATE budget_lignes SET obsolete = 1 WHERE id = ?')->execute([$id])
                    : $pdo->prepare('DELETE FROM budget_lignes WHERE id = ?')->execute([$id]);
                $stats['retires']++;
            }

            // Sources de financement et repartition officielle IBP / SJ (feuilles 3 et « Breakdown »)
            $sources = [];
            if ($f = $x->feuilleContenant('Sources')) {
                foreach ($x->lignes($f) as $c) {
                    $lib = trim((string) ($c['A'] ?? ''));
                    if (is_numeric($c['C'] ?? null) && (float) $c['C'] > 0) {
                        if (stripos($lib, 'Contribution de l') === 0) {
                            $sources[] = ['source' => 'Union européenne', 'montant' => (float) $c['C']];
                        } elseif (!preg_match('/total|revenus|contributions/i', $lib) && $lib !== '') {
                            $sources[] = ['source' => $lib, 'montant' => (float) $c['C']];
                        }
                    }
                }
            }
            $repartition = [];
            if ($f = $x->feuilleContenant('Breakdown')) {
                foreach ($x->lignes($f) as $c) {
                    $lib = trim((string) ($c['A'] ?? ''));
                    if (in_array($lib, ['IBP', 'Social Justice'], true) && is_numeric($c['B'] ?? null)) {
                        $repartition[$lib === 'IBP' ? 'IBP' : 'SJ'] = round((float) $c['B'], 2);
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Database::definirParametre('budget_source', $nomFichier . ' (importé le ' . date('d/m/Y') . ')');
        if ($sources) {
            Database::definirParametre('budget_sources', json_encode($sources, JSON_UNESCAPED_UNICODE));
        }
        if ($repartition) {
            Database::definirParametre('budget_repartition', json_encode($repartition));
        }
        self::majBudgetActivites($pdo);
        $total = (float) $pdo->query('SELECT SUM(montant) FROM budget_lignes WHERE obsolete = 0')->fetchColumn();
        return $stats + ['lignes' => count($postes), 'total' => round($total, 2), 'feuille' => $feuille];
    }

    /** Reporte sur chaque activite son budget prevu et ses depenses (en EUR) */
    public static function majBudgetActivites(PDO $pdo): void
    {
        $pdo->exec('UPDATE activites SET
            budget_prevu = (SELECT SUM(b.montant) FROM budget_lignes b WHERE b.activite_id = activites.id AND b.obsolete = 0),
            budget_depense = (SELECT SUM(d.montant_eur) FROM depenses d WHERE d.activite_id = activites.id AND d.statut <> \'engagee\')');
    }

    // ------------------------------------------------------------------ periodes

    public static function debutProjet(): string
    {
        return Database::parametre('projet_debut', '2025-07-01');
    }

    /** Annee budgetaire n (1 a 3) : [debut, fin] */
    public static function annee(int $n): array
    {
        $d = new DateTime(self::debutProjet());
        $d->modify('+' . ($n - 1) . ' year');
        $f = (clone $d)->modify('+1 year -1 day');
        return [$d->format('Y-m-d'), $f->format('Y-m-d')];
    }

    // ------------------------------------------------------------------ synthese

    public static function synthese(PDO $pdo, ?string $debut = null, ?string $fin = null): array
    {
        $debut ??= '1900-01-01';
        $fin ??= '2999-12-31';
        $reel = "statut <> 'engagee'"; // les engagements ne sont pas encore des depenses realisees

        $lignes = $pdo->query("SELECT b.*, a.titre AS activite_titre, a.code AS activite_code FROM budget_lignes b
            LEFT JOIN activites a ON a.id = b.activite_id WHERE b.obsolete = 0 OR EXISTS (SELECT 1 FROM depenses d WHERE d.ligne_id = b.id) ORDER BY b.ordre")->fetchAll();
        $cumul = $pdo->query("SELECT ligne_id, SUM(montant_eur) FROM depenses WHERE {$reel} GROUP BY ligne_id")->fetchAll(PDO::FETCH_KEY_PAIR);
        $s = $pdo->prepare("SELECT ligne_id, SUM(montant_eur) FROM depenses WHERE {$reel} AND date_depense BETWEEN ? AND ? GROUP BY ligne_id");
        $s->execute([$debut, $fin]);
        $periode = $s->fetchAll(PDO::FETCH_KEY_PAIR);
        $engage = $pdo->query("SELECT ligne_id, SUM(montant_eur) FROM depenses WHERE statut = 'engagee' GROUP BY ligne_id")->fetchAll(PDO::FETCH_KEY_PAIR);

        $rubriques = [];
        $partenaires = [];
        foreach ($lignes as &$l) {
            $l['depense'] = round((float) ($cumul[$l['id']] ?? 0), 2);
            $l['depense_periode'] = round((float) ($periode[$l['id']] ?? 0), 2);
            $l['engage'] = round((float) ($engage[$l['id']] ?? 0), 2);
            $l['taux'] = $l['montant'] > 0 ? round(100 * $l['depense'] / $l['montant'], 1) : null;
            $r = $l['rubrique'];
            $rubriques[$r] ??= ['rubrique' => $r, 'libelle' => self::RUBRIQUES[$r] ?? $r, 'budget' => 0, 'a1' => 0, 'a2' => 0, 'a3' => 0, 'depense' => 0, 'depense_periode' => 0, 'engage' => 0];
            foreach (['budget' => 'montant', 'a1' => 'montant_a1', 'a2' => 'montant_a2', 'a3' => 'montant_a3', 'depense' => 'depense', 'depense_periode' => 'depense_periode', 'engage' => 'engage'] as $k => $src) {
                $rubriques[$r][$k] += (float) $l[$src];
            }
            $p = $l['partenaire'] ?: 'SJ';
            $partenaires[$p] ??= ['partenaire' => $p, 'budget' => 0, 'depense' => 0];
            $partenaires[$p]['budget'] += (float) $l['montant'];
            $partenaires[$p]['depense'] += $l['depense'];
        }
        unset($l);
        ksort($rubriques);
        foreach ($rubriques as &$r) {
            $r['taux'] = $r['budget'] > 0 ? round(100 * $r['depense'] / $r['budget'], 1) : null;
        }
        unset($r);

        $total = array_sum(array_column($rubriques, 'budget'));
        $depense = array_sum(array_column($rubriques, 'depense'));

        // Partenaires : budget officiel du classeur (feuille « Breakdown ») si disponible,
        // depenses selon le partenaire indique sur chaque depense (a defaut, celui de la ligne)
        $officiel = json_decode(Database::parametre('budget_repartition', '{}'), true) ?: [];
        $parPart = $pdo->query("SELECT COALESCE(NULLIF(d.partenaire, ''), b.partenaire, 'SJ') AS p, SUM(d.montant_eur)
            FROM depenses d LEFT JOIN budget_lignes b ON b.id = d.ligne_id WHERE d.{$reel} GROUP BY 1")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach (['IBP', 'SJ'] as $p) {
            $partenaires[$p] = ['partenaire' => $p, 'budget' => (float) ($officiel[$p] ?? ($partenaires[$p]['budget'] ?? 0)), 'depense' => (float) ($parPart[$p] ?? 0)];
        }

        // Execution par annee budgetaire
        $annees = [];
        for ($n = 1; $n <= 3; $n++) {
            [$d, $f] = self::annee($n);
            $q = $pdo->prepare("SELECT COALESCE(SUM(montant_eur), 0) FROM depenses WHERE {$reel} AND date_depense BETWEEN ? AND ?");
            $q->execute([$d, $f]);
            $budgetAnnee = array_sum(array_column($rubriques, "a{$n}"));
            $dep = (float) $q->fetchColumn();
            $annees[] = ['annee' => $n, 'debut' => $d, 'fin' => $f, 'budget' => round($budgetAnnee, 2), 'depense' => round($dep, 2), 'taux' => $budgetAnnee > 0 ? round(100 * $dep / $budgetAnnee, 1) : null];
        }

        // Rythme : part du budget consommee vs part de la duree ecoulee (36 mois)
        $debutProjet = new DateTime(self::debutProjet());
        $moisEcoules = max(0, min(36, ($debutProjet->diff(new DateTime())->y * 12) + $debutProjet->diff(new DateTime())->m));

        $recu = (float) $pdo->query('SELECT COALESCE(SUM(montant_eur), 0) FROM financements')->fetchColumn();
        $paye = (float) $pdo->query("SELECT COALESCE(SUM(montant_eur), 0) FROM depenses WHERE {$reel}")->fetchColumn();
        $engageTotal = (float) $pdo->query("SELECT COALESCE(SUM(montant_eur), 0) FROM depenses WHERE statut = 'engagee'")->fetchColumn();

        // Alertes
        $alertes = [];
        foreach ($lignes as $l) {
            if ($l['montant'] > 0 && $l['depense'] > $l['montant']) {
                $alertes[] = ['niveau' => 'rouge', 'texte' => "Dépassement : « {$l['libelle']} » ({$l['groupe']}) — " . self::eur($l['depense']) . ' dépensés pour ' . self::eur($l['montant']) . ' prévus.'];
            } elseif ($l['montant'] > 0 && $l['depense'] + $l['engage'] > $l['montant']) {
                $alertes[] = ['niveau' => 'orange', 'texte' => "Les engagements feront dépasser la ligne « {$l['libelle']} » ({$l['groupe']}) : " . self::eur($l['depense'] + $l['engage']) . ' pour ' . self::eur($l['montant']) . ' prévus.'];
            } elseif ($l['montant'] > 0 && $l['depense'] + $l['engage'] >= 0.9 * $l['montant']) {
                $alertes[] = ['niveau' => 'orange', 'texte' => "Ligne presque épuisée (" . round(100 * ($l['depense'] + $l['engage']) / $l['montant']) . " % avec engagements) : « {$l['libelle']} » ({$l['groupe']})."];
            }
        }
        foreach ($rubriques as $r) {
            if ($r['budget'] > 0 && $r['depense'] > 1.25 * $r['budget']) {
                $alertes[] = ['niveau' => 'rouge', 'texte' => "Rubrique {$r['rubrique']} ({$r['libelle']}) dépassée de plus de 25 % : un avenant au contrat UE est nécessaire."];
            } elseif ($r['budget'] > 0 && $r['depense'] > $r['budget']) {
                $alertes[] = ['niveau' => 'orange', 'texte' => "Rubrique {$r['rubrique']} ({$r['libelle']}) dépassée : transfert possible jusqu'à 25 % entre rubriques, à justifier dans le rapport."];
            }
        }
        if ($recu > 0 && $paye > $recu) {
            $alertes[] = ['niveau' => 'rouge', 'texte' => 'Trésorerie négative : les dépenses (' . self::eur($paye) . ') dépassent les fonds reçus (' . self::eur($recu) . ').'];
        }

        return [
            'total' => round($total, 2),
            'depense' => round($depense, 2),
            'depense_periode' => round(array_sum(array_column($rubriques, 'depense_periode')), 2),
            'engage' => round($engageTotal, 2),
            'taux' => $total > 0 ? round(100 * $depense / $total, 1) : 0,
            'mois_ecoules' => $moisEcoules,
            'taux_temps' => round(100 * $moisEcoules / 36, 1),
            'recu' => round($recu, 2),
            'tresorerie' => round($recu - $paye, 2),
            'sources' => json_decode(Database::parametre('budget_sources', '[]'), true) ?: [['source' => 'Union européenne', 'montant' => 950000], ['source' => 'Fondation Hewlett', 'montant' => 50000]],
            'repartition_officielle' => json_decode(Database::parametre('budget_repartition', '{}'), true) ?: null,
            'source_budget' => Database::parametre('budget_source'),
            'rubriques' => array_values(array_map(fn ($r) => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $r), $rubriques)),
            'partenaires' => array_values(array_map(fn ($p) => ['partenaire' => $p['partenaire'], 'budget' => round($p['budget'], 2), 'depense' => round($p['depense'], 2)], $partenaires)),
            'annees' => $annees,
            'lignes' => $lignes,
            'alertes' => $alertes,
            'taux_fcfa' => self::TAUX_FCFA,
        ];
    }

    public static function eur(float $v): string
    {
        return number_format($v, 2, ',', ' ') . ' €';
    }

    // ------------------------------------------------------------------ rapport

    public static function rapport(PDO $pdo, string $debut, string $fin, string $intitule): string
    {
        $s = self::synthese($pdo, $debut, $fin);
        $e = fn ($v) => self::eur((float) $v);
        $pct = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ') . ' %';
        $dateFr = fn ($d) => date('d/m/Y', strtotime($d));
        $md = [];
        $md[] = "# RAPPORT FINANCIER — {$intitule}";
        $md[] = "**Projet PACT BUDGÉTAIRE** — Amélioration de la transparence, de la participation et de la redevabilité budgétaires en Côte d'Ivoire";
        $md[] = "Financé par l'Union européenne — Mis en œuvre par International Budget Partnership (IBP Sénégal) et l'ONG Social Justice";
        $md[] = "\n**Période couverte :** du {$dateFr($debut)} au {$dateFr($fin)} — **Établi le :** " . date('d/m/Y');

        $md[] = "\n## 1. Informations générales";
        $md[] = "| Rubrique | Information |\n|---|---|";
        $md[] = "| Coordonnateur de la subvention | International Budget Partnership (IBP) |";
        $md[] = "| Partenaire de mise en œuvre | ONG Social Justice |";
        $md[] = "| Numéro du contrat | [À compléter] |";
        $md[] = "| Budget total de l'action | {$e($s['total'])} |";
        $md[] = "| Durée | 36 mois (depuis le {$dateFr(self::debutProjet())}) |";
        $md[] = "| Devise de rapport | EUR (taux fixe : 1 € = " . number_format(self::TAUX_FCFA, 3, ',', ' ') . " FCFA) |";

        $md[] = "\n## 2. Sources de financement";
        $md[] = "| Source | Montant prévu | Part |\n|---|---|---|";
        $totalSources = array_sum(array_column($s['sources'], 'montant')) ?: 1;
        foreach ($s['sources'] as $src) {
            $md[] = "| {$src['source']} | {$e($src['montant'])} | {$pct(100 * $src['montant'] / $totalSources)} |";
        }
        $md[] = "\n**Fonds reçus à ce jour :** {$e($s['recu'])} — **Trésorerie disponible :** {$e($s['tresorerie'])}";

        $md[] = "\n## 3. État d'exécution par rubrique budgétaire";
        $md[] = "| Rubrique | Budget total | Dépenses de la période | Dépenses cumulées | Taux d'exécution | Solde disponible |\n|---|---|---|---|---|---|";
        foreach ($s['rubriques'] as $r) {
            $md[] = "| {$r['rubrique']}. {$r['libelle']} | {$e($r['budget'])} | {$e($r['depense_periode'])} | {$e($r['depense'])} | {$pct($r['taux'])} | {$e($r['budget'] - $r['depense'])} |";
        }
        $md[] = "| **TOTAL** | **{$e($s['total'])}** | **{$e($s['depense_periode'])}** | **{$e($s['depense'])}** | **{$pct($s['taux'])}** | **{$e($s['total'] - $s['depense'])}** |";

        $md[] = "\n## 4. Exécution par année budgétaire";
        $md[] = "| Année | Période | Budget | Dépenses | Taux |\n|---|---|---|---|---|";
        foreach ($s['annees'] as $a) {
            $md[] = "| Année {$a['annee']} | {$dateFr($a['debut'])} – {$dateFr($a['fin'])} | {$e($a['budget'])} | {$e($a['depense'])} | {$pct($a['taux'])} |";
        }
        $md[] = "\nConsommation du budget : **{$pct($s['taux'])}** pour **{$pct($s['taux_temps'])}** de la durée écoulée ({$s['mois_ecoules']} mois sur 36).";

        $md[] = "\n## 5. Exécution par partenaire";
        $md[] = "| Partenaire | Budget | Dépenses cumulées | Taux |\n|---|---|---|---|";
        foreach ($s['partenaires'] as $p) {
            $nom = $p['partenaire'] === 'IBP' ? 'IBP Sénégal' : 'Social Justice';
            $md[] = "| {$nom} | {$e($p['budget'])} | {$e($p['depense'])} | {$pct($p['budget'] > 0 ? 100 * $p['depense'] / $p['budget'] : null)} |";
        }

        $md[] = "\n## 6. Détail des lignes budgétaires mouvementées";
        $mouv = array_filter($s['lignes'], fn ($l) => $l['depense'] > 0 || $l['engage'] > 0);
        if ($mouv) {
            $md[] = "| Rubrique / sous-section | Ligne | Budget | Dépenses période | Cumul | Taux |\n|---|---|---|---|---|---|";
            foreach ($mouv as $l) {
                $md[] = "| {$l['rubrique']}. " . str_replace('|', '/', (string) $l['groupe']) . " | " . str_replace('|', '/', $l['libelle']) . " | {$e($l['montant'])} | {$e($l['depense_periode'])} | {$e($l['depense'])} | {$pct($l['taux'])} |";
            }
        } else {
            $md[] = "Aucune dépense enregistrée à ce jour.";
        }

        $md[] = "\n## 7. Écarts significatifs et justifications";
        $ecarts = array_filter($s['alertes'], fn ($a) => $a['niveau'] === 'rouge' || str_contains($a['texte'], 'Rubrique'));
        if ($ecarts) {
            foreach ($ecarts as $a) {
                $md[] = "- {$a['texte']} Justification : [À compléter]";
            }
        } else {
            $md[] = "Aucun dépassement de ligne ni de rubrique n'est constaté sur la période.";
        }
        $md[] = "\n[À compléter : commentaires sur le rythme d'exécution, les reports d'activités et les mesures prévues]";

        $md[] = "\n## 8. Liste des dépenses de la période";
        $q = $pdo->prepare("SELECT d.*, b.libelle AS ligne_libelle, b.rubrique FROM depenses d LEFT JOIN budget_lignes b ON b.id = d.ligne_id
            WHERE d.date_depense BETWEEN ? AND ? ORDER BY d.date_depense, d.id LIMIT 400");
        $q->execute([$debut, $fin]);
        $dep = $q->fetchAll();
        if ($dep) {
            $md[] = "| Date | Pièce | Libellé | Ligne budgétaire | Montant (€) | Montant (FCFA) | Statut |\n|---|---|---|---|---|---|---|";
            foreach ($dep as $d) {
                $md[] = "| {$dateFr($d['date_depense'])} | " . ($d['piece'] ?: '—') . ' | ' . str_replace('|', '/', $d['libelle']) . ' | '
                    . ($d['rubrique'] ? "{$d['rubrique']}. " : '') . str_replace('|', '/', (string) $d['ligne_libelle']) . " | {$e($d['montant_eur'])} | "
                    . number_format((float) ($d['montant_fcfa'] ?: $d['montant_eur'] * self::TAUX_FCFA), 0, ',', ' ') . " | {$d['statut']} |";
            }
        } else {
            $md[] = "Aucune dépense sur la période.";
        }

        $md[] = "\n## 9. Attestation";
        $md[] = "Nous certifions que les informations financières présentées dans ce rapport sont exactes et que les dépenses ont été engagées conformément au contrat de subvention et aux règles de l'Union européenne.";
        $md[] = "\n| Responsable Administratif et Financier | Coordonnateur du projet |\n|---|---|\n| Nom : [À compléter] | Nom : [À compléter] |\n| Signature et date : | Signature et date : |";
        return implode("\n", $md);
    }
}
