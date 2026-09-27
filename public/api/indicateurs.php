<?php
require_once __DIR__ . '/_bootstrap.php';
Auth::require();

$crud = new Crud($pdo, 'indicateurs',
    ['code', 'niveau', 'libelle', 'unite', 'valeur_reference', 'cible', 'valeur_actuelle', 'source_verification', 'commentaire'],
    'SELECT x.* FROM indicateurs x',
    "CASE x.niveau WHEN 'impact' THEN 1 WHEN 'effet' THEN 2 ELSE 3 END, x.code",
    ['niveau' => 'produit']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim(Response::body()['libelle'] ?? '') === '') {
    Response::error('Le libellé est requis', 422);
}

$crud->traiter(null, function ($r) {
    $r['taux'] = null;
    if ($r['cible'] !== null && $r['valeur_actuelle'] !== null && (float) $r['cible'] != (float) ($r['valeur_reference'] ?? 0)) {
        $ref = (float) ($r['valeur_reference'] ?? 0);
        $r['taux'] = round(100 * ((float) $r['valeur_actuelle'] - $ref) / ((float) $r['cible'] - $ref), 1);
    }
    return $r;
});
