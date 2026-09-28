<?php
/**
 * Importe le classeur budgétaire UE dans le suivi financier et le range dans les documents.
 * (Même traitement que le bouton « Importer / mettre à jour le budget » du site.)
 *
 * Usage : php -d extension=zip database/importer_budget.php "C:\...\Budget.xlsx"
 *         (avec DATABASE_URL défini pour la base de production)
 */
declare(strict_types=1);

foreach (['Database', 'Response', 'Docx', 'Fichiers', 'Xlsx', 'Budget'] as $c) {
    require_once __DIR__ . "/../src/{$c}.php";
}
$fichier = $argv[1] ?? '';
if (!is_file($fichier)) {
    fwrite(STDERR, "Indiquez le chemin du fichier .xlsx du budget.\n");
    exit(1);
}
$pdo = Database::connect();
$r = Budget::importer($pdo, $fichier, basename($fichier));
printf("Budget importé depuis « %s » : %d lignes (%d nouvelles, %d mises à jour, %d retirées), total %s\n",
    $r['feuille'], $r['lignes'], $r['crees'], $r['mis_a_jour'], $r['retires'], Budget::eur($r['total']));

$titre = 'Budget UE du projet — ' . pathinfo($fichier, PATHINFO_FILENAME);
$s = $pdo->prepare('SELECT id FROM documents WHERE titre = ?');
$s->execute([$titre]);
if (!$s->fetchColumn()) {
    $pdo->prepare("INSERT INTO documents (type, titre, statut, date_finalisation, notes) VALUES ('autre', ?, 'valide', ?, ?)")
        ->execute([$titre, date('Y-m-d', filemtime($fichier)), 'Classeur budgétaire UE (Annexe III) — importé dans le suivi financier']);
    Fichiers::ajouter($pdo, Database::lastId('documents'), basename($fichier), $fichier);
    echo "Classeur ajouté aux documents du projet.\n";
}
