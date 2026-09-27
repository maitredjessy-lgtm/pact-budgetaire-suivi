<?php
/**
 * Pre-remplit une base vide avec les donnees reelles du projet PACT Budgetaire
 * (extraites du dossier "PACT BUDGETAIRE - Drive de suivi" et des canevas du projet).
 */
class Seeder
{
    private const NORMALISATION_RESPONSABLES = [
        'IBP / Social Justice' => 'Équipe Social Justice',
        'IBP / SJ' => 'Équipe Social Justice',
        'SJ / appui IBP' => 'Équipe Social Justice',
        'Social Justice' => 'Équipe Social Justice',
        'Social Justice (appui IBP)' => 'Équipe Social Justice',
        'SJ (à confirmer)' => 'Équipe Social Justice',
        'SJ / IBP' => 'Équipe Social Justice',
        'IBP' => 'IBP (appui technique)',
    ];

    public static function run(PDO $pdo, bool $contenu, bool $modeles): void
    {
        $dir = __DIR__ . '/../database/seed_data';
        $pdo->beginTransaction();
        try {
            if ($contenu) {
                self::seedContenu($pdo, $dir);
            }
            if ($modeles) {
                $stmt = $pdo->prepare('INSERT INTO modeles (type, nom, description, consignes, contenu, source) VALUES (?,?,?,?,?,?)');
                foreach (require $dir . '/modeles.php' as $m) {
                    $stmt->execute([$m['type'], $m['nom'], $m['description'], $m['consignes'], $m['contenu'], $m['source']]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function json(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($path));
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function typeActivite(string $titre): string
    {
        $t = mb_strtolower($titre);
        foreach ([
            'formation' => ['formation', 'module', 'renforcement'],
            'reunion' => ['réunion', 'copil', 'séance', 'rencontre', 'compte rendu', 'session'],
            'plaidoyer' => ['plaidoyer', 'mémorandum', 'convention', 'note de partenariat'],
            'communication' => ['capsule', 'médias', 'émission', 'caravane', 'prix', 'concours', 'hackathon', 'dissémination'],
            'etude' => ['diagnostic', 'référentiel', 'cartographie', 'étude'],
            'atelier' => ['atelier', 'journées'],
        ] as $type => $mots) {
            foreach ($mots as $mot) {
                if (str_contains($t, $mot)) {
                    return $type;
                }
            }
        }
        return 'autre';
    }

    private static function seedContenu(PDO $pdo, string $dir): void
    {
        $respIds = [];
        $stmt = $pdo->prepare('INSERT INTO responsables (nom, fonction) VALUES (?, ?)');
        foreach (self::json($dir . '/responsables_reels.json') as $r) {
            $stmt->execute([$r['nom'], $r['fonction'] ?? null]);
            $respIds[$r['nom']] = Database::lastId('responsables');
        }

        $activites = self::json($dir . '/activites_reelles.json');
        $actIds = [];
        $stmt = $pdo->prepare('INSERT INTO activites
            (code, titre, description, composante, type_activite, responsable_id, date_debut, date_echeance, statut, priorite, avancement)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($activites as $a) {
            $nom = trim($a['responsable'] ?? '');
            $nom = self::NORMALISATION_RESPONSABLES[$nom] ?? $nom;
            $statut = $a['statut'] ?? 'a_faire';
            $stmt->execute([
                $a['code'] ?? null,
                $a['titre'],
                $a['description'] ?? null,
                $a['composante'] ?? null,
                self::typeActivite($a['titre']),
                $respIds[$nom] ?? null,
                $a['date_debut'] ?? null,
                $a['date_echeance'] ?? null,
                $statut,
                $a['priorite'] ?? 'normale',
                ['termine' => 100, 'en_cours' => 50][$statut] ?? 0,
            ]);
            $actIds[$a['titre']] = Database::lastId('activites');
        }

        $stmt = $pdo->prepare('INSERT INTO documents (type, titre, activite_id, statut, date_finalisation, lien, notes) VALUES (?,?,?,?,?,?,?)');
        foreach (self::json($dir . '/documents_reels.json') as $d) {
            $stmt->execute([
                $d['type'],
                $d['titre'],
                self::matchActivite($d['titre'], $activites, $actIds),
                $d['statut'] ?? 'valide',
                $d['date_finalisation'] ?? null,
                $d['lien'] ?? null,
                $d['notes'] ?? null,
            ]);
        }

        $stmt = $pdo->prepare('INSERT INTO indicateurs (code, niveau, libelle, unite, valeur_reference, cible, valeur_actuelle, source_verification, commentaire) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach (require $dir . '/indicateurs.php' as $i) {
            $stmt->execute([
                $i['code'], $i['niveau'], $i['libelle'], $i['unite'] ?? null,
                $i['valeur_reference'] ?? null, $i['cible'] ?? null, $i['valeur_actuelle'] ?? null,
                $i['source_verification'] ?? null, $i['commentaire'] ?? null,
            ]);
        }
    }

    /** Rattache un document a son activite : par code (ex. "2.3.1") puis par mots-cles */
    private static function matchActivite(string $titre, array $activites, array $actIds): ?int
    {
        foreach ($activites as $a) {
            if (!empty($a['code']) && preg_match('/^\d/', $a['code']) && str_contains($titre, $a['code'])) {
                return $actIds[$a['titre']] ?? null;
            }
        }
        $t = mb_strtolower($titre);
        $regles = [
            [['bsg'], "Ateliers d'échange MFB / MFFE sur la budgétisation sensible au genre (BSG)"],
            [['ebo'], "Atelier de lancement officiel des résultats de l'Enquête sur le Budget Ouvert (EBO) 2025"],
            [['ogp'], 'Ateliers de partage des bonnes pratiques avec le Comité technique OGP'],
            [['dpbep'], "Session annuelle d'échanges sur les orientations du DPBEP"],
            [['cost budget'], 'Atelier de redynamisation et de formation du réseau COST Budget'],
            [['méthodologie', 'cartographie'], 'Atelier de présentation de la méthodologie de cartographie des OSC'],
            [['résultat', 'cartographie'], 'Atelier de présentation des résultats de la cartographie des OSC'],
            [['restitution', 'cartographie'], 'Atelier de présentation des résultats de la cartographie des OSC'],
            [['rteb'], "Sessions trimestrielles sur les rapports d'exécution du budget (RTEB)"],
            [['mémorandum', 'cour des comptes'], 'Mémorandum / plateforme de collaboration Cour des Comptes - société civile'],
            [['formation', 'cour des comptes'], "Formation de la société civile et des médias à l'exploitation des rapports d'audit de la Cour des Comptes"],
            [['10 juillet', 'cour des comptes'], 'Finalisation du compte rendu de la rencontre avec la Cour des Comptes du 10/07/2026'],
            [['lancement', 'pact budgetaire'], 'Atelier de lancement officiel du projet PACT Budgétaire'],
            [['première réunion copil'], 'Première réunion du Comité de Pilotage (COPIL)'],
            [['diagnostic organisationnel'], 'Diagnostic organisationnel de Social Justice'],
            [['sysbnl'], 'Formation de Social Justice sur le SYSBNL'],
        ];
        foreach ($regles as [$mots, $cible]) {
            $ok = true;
            foreach ($mots as $m) {
                if (!str_contains($t, $m)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok && isset($actIds[$cible])) {
                return $actIds[$cible];
            }
        }
        return null;
    }
}
