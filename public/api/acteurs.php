<?php
/**
 * Acteurs institutionnels (GET) :
 *   acteurs.php                       -> referentiel + activites liees a chacun
 *   acteurs.php?action=actualites&cle=dgbf[&forcer=1] -> dernieres actualites du site officiel
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../src/Institutions.php';
Auth::require();

$registre = Institutions::registre();

if (($_GET['action'] ?? '') === 'actualites') {
    $cle = $_GET['cle'] ?? '';
    if (!isset($registre[$cle])) {
        Response::error('Acteur inconnu', 404);
    }
    @set_time_limit(40);
    Response::json(Institutions::actualites($cle, !empty($_GET['forcer'])));
}

$liees = [];
foreach ($pdo->query('SELECT id, code, titre, statut, composante, acteurs FROM activites WHERE acteurs IS NOT NULL')->fetchAll() as $a) {
    foreach (array_filter(explode(',', $a['acteurs'])) as $cle) {
        $liees[$cle][] = ['id' => (int) $a['id'], 'code' => $a['code'], 'titre' => $a['titre'], 'statut' => $a['statut']];
    }
}
$sortie = [];
foreach ($registre as $cle => $a) {
    $sortie[] = ['cle' => $cle, 'activites' => $liees[$cle] ?? []] + $a;
}
Response::json($sortie);
