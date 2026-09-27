<?php
require_once __DIR__ . '/_bootstrap.php';
Auth::require();

$today = date('Y-m-d');
$crud = new Crud($pdo, 'taches', ['activite_id', 'titre', 'responsable_id', 'echeance', 'statut'],
    'SELECT x.*, a.titre AS activite_titre, a.code AS activite_code, r.nom AS responsable_nom, r.email AS responsable_email
     FROM taches x LEFT JOIN activites a ON a.id = x.activite_id LEFT JOIN responsables r ON r.id = x.responsable_id',
    '(x.echeance IS NULL), x.echeance ASC',
    ['statut' => 'a_faire']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim(Response::body()['titre'] ?? '') === '') {
    Response::error('Le titre est requis', 422);
}

$crud->traiter(function () {
    $where = [];
    $p = [];
    foreach (['activite_id', 'responsable_id'] as $f) {
        if (!empty($_GET[$f])) {
            $where[] = "x.{$f} = ?";
            $p[] = (int) $_GET[$f];
        }
    }
    if (!empty($_GET['statut'])) {
        $where[] = 'x.statut = ?';
        $p[] = $_GET['statut'];
    }
    return [$where, $p];
}, function ($r) use ($today) {
    $r['en_retard'] = !empty($r['echeance']) && $r['echeance'] < $today && $r['statut'] !== 'termine';
    return $r;
});
