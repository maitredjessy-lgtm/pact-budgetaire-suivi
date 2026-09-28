<?php
/**
 * Suivi financier.
 * GET  ?vue=synthese[&debut&fin] | ?vue=depenses[&ligne_id&activite_id&debut&fin] | ?vue=financements
 * GET  ?action=piece&pid=        : piece justificative ;  ?action=export_csv[&debut&fin] : journal pour Excel
 * POST/PUT/DELETE ?action=depense[&id] | ?action=financement[&id]
 * POST ?action=piece&depense_id= : joindre un justificatif ;  DELETE ?action=piece&pid=
 * POST ?action=import            : importer / mettre a jour le budget depuis le classeur UE (.xlsx)
 * POST ?action=rapport           : generer un rapport financier (document redige dans la plateforme)
 */
require_once __DIR__ . '/_bootstrap.php';
foreach (['Xlsx', 'Budget', 'Fichiers'] as $c) {
    require_once __DIR__ . "/../../src/{$c}.php";
}
$user = Auth::require();
$methode = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null;

try {
    // ------------------------------------------------------------ lectures
    if ($methode === 'GET' && $action === '') {
        switch ($_GET['vue'] ?? 'synthese') {
            case 'synthese':
                Response::json(Budget::synthese($pdo, $date($_GET['debut'] ?? null), $date($_GET['fin'] ?? null)) + [
                    'annees_def' => [Budget::annee(1), Budget::annee(2), Budget::annee(3)],
                    'projet_debut' => Budget::debutProjet(),
                ]);
                // no break
            case 'depenses':
                $where = ['1=1'];
                $p = [];
                foreach (['ligne_id', 'activite_id'] as $f) {
                    if (!empty($_GET[$f])) {
                        $where[] = "d.{$f} = ?";
                        $p[] = (int) $_GET[$f];
                    }
                }
                if ($d = $date($_GET['debut'] ?? null)) {
                    $where[] = 'd.date_depense >= ?';
                    $p[] = $d;
                }
                if ($f = $date($_GET['fin'] ?? null)) {
                    $where[] = 'd.date_depense <= ?';
                    $p[] = $f;
                }
                $s = $pdo->prepare('SELECT d.*, b.libelle AS ligne_libelle, b.rubrique, b.groupe AS ligne_groupe, a.titre AS activite_titre, a.code AS activite_code,
                        (SELECT COUNT(*) FROM pieces_justificatives j WHERE j.depense_id = d.id) AS nb_pieces
                    FROM depenses d LEFT JOIN budget_lignes b ON b.id = d.ligne_id LEFT JOIN activites a ON a.id = d.activite_id
                    WHERE ' . implode(' AND ', $where) . ' ORDER BY d.date_depense DESC, d.id DESC');
                $s->execute($p);
                Response::json($s->fetchAll());
                // no break
            case 'financements':
                Response::json($pdo->query('SELECT * FROM financements ORDER BY date_reception DESC, id DESC')->fetchAll());
                // no break
            case 'pieces':
                $s = $pdo->prepare('SELECT id, nom, mime, taille, created_at FROM pieces_justificatives WHERE depense_id = ? ORDER BY id');
                $s->execute([(int) ($_GET['depense_id'] ?? 0)]);
                Response::json($s->fetchAll());
        }
        Response::error('Vue inconnue', 400);
    }

    if ($methode === 'GET' && $action === 'piece') {
        $s = $pdo->prepare('SELECT nom, mime, donnees FROM pieces_justificatives WHERE id = ?');
        $s->execute([(int) ($_GET['pid'] ?? 0)]);
        $f = $s->fetch() ?: Response::error('Pièce introuvable', 404);
        $inline = $f['mime'] === 'application/pdf' || str_starts_with($f['mime'], 'image/');
        header('Content-Type: ' . $f['mime']);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($f['nom']));
        header('X-Content-Type-Options: nosniff');
        echo base64_decode($f['donnees']);
        exit;
    }

    if ($methode === 'GET' && $action === 'export_csv') {
        $debut = $date($_GET['debut'] ?? null) ?? '1900-01-01';
        $fin = $date($_GET['fin'] ?? null) ?? '2999-12-31';
        $s = $pdo->prepare('SELECT d.*, b.rubrique, b.groupe, b.libelle AS ligne_libelle, a.code AS activite_code FROM depenses d
            LEFT JOIN budget_lignes b ON b.id = d.ligne_id LEFT JOIN activites a ON a.id = d.activite_id
            WHERE d.date_depense BETWEEN ? AND ? ORDER BY d.date_depense, d.id');
        $s->execute([$debut, $fin]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="journal-depenses-pact-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM : Excel reconnait l'UTF-8
        $sep = ';';
        fputcsv($out, ['Date', 'N° pièce', 'Libellé', 'Rubrique', 'Sous-section', 'Ligne budgétaire', 'Activité', 'Partenaire', 'Fournisseur', 'Mode de paiement', 'Statut', 'Montant EUR', 'Montant FCFA'], $sep);
        foreach ($s->fetchAll() as $d) {
            fputcsv($out, [
                date('d/m/Y', strtotime($d['date_depense'])), $d['piece'], $d['libelle'], $d['rubrique'], $d['groupe'], $d['ligne_libelle'], $d['activite_code'],
                $d['partenaire'], $d['fournisseur'], $d['mode_paiement'], $d['statut'],
                number_format((float) $d['montant_eur'], 2, ',', ''), number_format((float) ($d['montant_fcfa'] ?: $d['montant_eur'] * Budget::TAUX_FCFA), 0, ',', ''),
            ], $sep);
        }
        exit;
    }

    // ------------------------------------------------------------ ecritures
    Auth::requireWrite();
    $b = Response::body();

    if ($action === 'depense') {
        if ($methode === 'DELETE') {
            $pdo->prepare('DELETE FROM depenses WHERE id = ?')->execute([$id]);
            Budget::majBudgetActivites($pdo);
            Response::json(['ok' => true]);
        }
        $eur = is_numeric($b['montant_eur'] ?? null) ? (float) $b['montant_eur'] : null;
        $fcfa = is_numeric($b['montant_fcfa'] ?? null) ? (float) $b['montant_fcfa'] : null;
        if ($eur === null && $fcfa !== null) {
            $eur = round($fcfa / Budget::TAUX_FCFA, 2);
        }
        if ($fcfa === null && $eur !== null) {
            $fcfa = round($eur * Budget::TAUX_FCFA);
        }
        if (!$date($b['date_depense'] ?? null) || trim($b['libelle'] ?? '') === '' || !$eur || $eur <= 0) {
            Response::error('Date, libellé et montant (en € ou en FCFA) sont obligatoires', 422);
        }
        $ligne = null;
        if (!empty($b['ligne_id'])) {
            $s = $pdo->prepare('SELECT id, activite_id, partenaire FROM budget_lignes WHERE id = ?');
            $s->execute([(int) $b['ligne_id']]);
            $ligne = $s->fetch() ?: null;
        }
        $valeurs = [
            $ligne['id'] ?? null,
            Response::orNull($b['activite_id'] ?? null) ?? ($ligne['activite_id'] ?? null),
            $b['date_depense'], trim($b['libelle']), $eur, $fcfa,
            Response::orNull(trim($b['fournisseur'] ?? '')), Response::orNull(trim($b['piece'] ?? '')), Response::orNull($b['mode_paiement'] ?? null),
            in_array($b['statut'] ?? '', ['engagee', 'payee', 'justifiee'], true) ? $b['statut'] : 'payee',
            Response::orNull($b['partenaire'] ?? null) ?? ($ligne['partenaire'] ?? 'SJ'),
            Response::orNull(trim($b['notes'] ?? '')),
        ];
        $cols = 'ligne_id, activite_id, date_depense, libelle, montant_eur, montant_fcfa, fournisseur, piece, mode_paiement, statut, partenaire, notes';
        if ($methode === 'PUT' && $id) {
            $set = implode(', ', array_map(fn ($c) => "{$c} = ?", explode(', ', $cols)));
            $pdo->prepare("UPDATE depenses SET {$set}, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([...$valeurs, $id]);
        } else {
            $pdo->prepare("INSERT INTO depenses ({$cols}) VALUES (" . implode(',', array_fill(0, 12, '?')) . ')')->execute($valeurs);
            $id = Database::lastId('depenses');
        }
        Budget::majBudgetActivites($pdo);
        Response::json(['ok' => true, 'id' => $id], $methode === 'PUT' ? 200 : 201);
    }

    if ($action === 'financement') {
        if ($methode === 'DELETE') {
            $pdo->prepare('DELETE FROM financements WHERE id = ?')->execute([$id]);
            Response::json(['ok' => true]);
        }
        if (!$date($b['date_reception'] ?? null) || trim($b['source'] ?? '') === '' || !is_numeric($b['montant_eur'] ?? null)) {
            Response::error('Date, source et montant sont obligatoires', 422);
        }
        $v = [$b['date_reception'], trim($b['source']), Response::orNull(trim($b['libelle'] ?? '')), (float) $b['montant_eur'], Response::orNull(trim($b['notes'] ?? ''))];
        if ($methode === 'PUT' && $id) {
            $pdo->prepare('UPDATE financements SET date_reception = ?, source = ?, libelle = ?, montant_eur = ?, notes = ? WHERE id = ?')->execute([...$v, $id]);
        } else {
            $pdo->prepare('INSERT INTO financements (date_reception, source, libelle, montant_eur, notes) VALUES (?,?,?,?,?)')->execute($v);
        }
        Response::json(['ok' => true]);
    }

    if ($action === 'piece') {
        if ($methode === 'DELETE') {
            $pdo->prepare('DELETE FROM pieces_justificatives WHERE id = ?')->execute([(int) ($_GET['pid'] ?? 0)]);
            Response::json(['ok' => true]);
        }
        $f = $_FILES['fichier'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            Response::error('Aucun fichier reçu (10 Mo maximum)', 422);
        }
        if (!Fichiers::extensionAutorisee($f['name']) || $f['size'] > Fichiers::TAILLE_MAX) {
            Response::error('Type de fichier non accepté ou fichier trop volumineux (PDF, image, Word, Excel ; 10 Mo maximum)', 422);
        }
        $mime = Fichiers::mime($f['name']);
        $pdo->prepare('INSERT INTO pieces_justificatives (depense_id, nom, mime, taille, donnees) VALUES (?,?,?,?,?)')
            ->execute([(int) ($_GET['depense_id'] ?? 0), basename($f['name']), $mime, $f['size'], base64_encode(file_get_contents($f['tmp_name']))]);
        // Une depense avec justificatif est consideree comme justifiee
        $pdo->prepare("UPDATE depenses SET statut = 'justifiee' WHERE id = ? AND statut = 'payee'")->execute([(int) ($_GET['depense_id'] ?? 0)]);
        Response::json(['ok' => true], 201);
    }

    if ($action === 'import') {
        Auth::requireAdmin();
        @set_time_limit(120);
        $f = $_FILES['fichier'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            Response::error('Choisissez le fichier budget au format .xlsx', 422);
        }
        $r = Budget::importer($pdo, $f['tmp_name'], basename($f['name']));
        // Le classeur est aussi range dans les documents du projet
        $pdo->prepare("INSERT INTO documents (type, titre, statut, date_finalisation, notes) VALUES ('autre', ?, 'valide', ?, ?)")
            ->execute(['Budget UE du projet — ' . pathinfo($f['name'], PATHINFO_FILENAME), date('Y-m-d'), 'Classeur budgétaire importé dans le suivi financier']);
        Fichiers::ajouter($pdo, Database::lastId('documents'), basename($f['name']), $f['tmp_name']);
        Response::json($r);
    }

    if ($action === 'rapport') {
        @set_time_limit(240);
        $debut = $date($b['debut'] ?? null) ?? Response::error('Période invalide', 422);
        $fin = $date($b['fin'] ?? null) ?? Response::error('Période invalide', 422);
        $intitule = trim($b['intitule'] ?? '') ?: 'du ' . date('d/m/Y', strtotime($debut)) . ' au ' . date('d/m/Y', strtotime($fin));
        $md = Budget::rapport($pdo, $debut, $fin, $intitule);

        if (!empty($b['analyse_ia']) && AI::isConfigured()) {
            $s = Budget::synthese($pdo, $debut, $fin);
            unset($s['lignes']);
            $analyse = AI::generate(
                "Tu es responsable administratif et financier d'un projet financé par l'Union européenne (règles PRAG). Français soigné, factuel, sans inventer de chiffre.",
                [['role' => 'user', 'content' => "Rédige en Markdown (2 à 4 paragraphes, sans titre) le commentaire d'exécution financière de la période, à partir de ces données JSON : "
                    . json_encode($s, JSON_UNESCAPED_UNICODE) . "\nAborde : niveau d'exécution global et par rubrique, rythme de consommation par rapport au temps écoulé, écarts significatifs et leurs causes probables (à confirmer), "
                    . "trésorerie, mesures recommandées. Pour les justifications non connues, écris [À compléter]."]],
                0.3,
                3000
            );
            $md = str_replace('[À compléter : commentaires sur le rythme d\'exécution, les reports d\'activités et les mesures prévues]', trim($analyse), $md);
        }
        $pdo->prepare("INSERT INTO documents (type, titre, statut, contenu, notes) VALUES ('rapport', ?, 'brouillon', ?, ?)")
            ->execute(["Rapport financier — {$intitule}", $md, 'Généré depuis le suivi financier le ' . date('d/m/Y')]);
        Response::json(['document_id' => Database::lastId('documents')], 201);
    }

    if ($action === 'parametres') {
        Auth::requireAdmin();
        if ($d = $date($b['projet_debut'] ?? null)) {
            Database::definirParametre('projet_debut', $d);
        }
        Response::json(['ok' => true]);
    }
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 422);
}

Response::error('Action inconnue', 400);
