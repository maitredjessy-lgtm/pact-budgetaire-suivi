<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../src/Checklist.php';
Auth::require();

$today = date('Y-m-d');
$like = Database::like();

$crud = new Crud($pdo, 'activites',
    ['code', 'titre', 'description', 'composante', 'type_activite', 'responsable_id', 'lieu', 'objectif', 'resultats', 'acteurs', 'date_debut', 'date_echeance', 'statut', 'priorite', 'avancement', 'budget_prevu', 'budget_depense'],
    "SELECT x.*, r.nom AS responsable_nom, r.email AS responsable_email,
        (SELECT COUNT(*) FROM documents d WHERE d.activite_id = x.id) AS nb_documents,
        (SELECT COUNT(*) FROM documents d WHERE d.activite_id = x.id AND d.statut IN ('valide','envoye')) AS nb_documents_ok,
        (SELECT COUNT(*) FROM taches t WHERE t.activite_id = x.id) AS nb_taches,
        (SELECT COUNT(*) FROM taches t WHERE t.activite_id = x.id AND t.statut = 'termine') AS nb_taches_ok
     FROM activites x LEFT JOIN responsables r ON r.id = x.responsable_id",
    '(x.date_echeance IS NULL), x.date_echeance ASC',
    ['statut' => 'a_faire', 'priorite' => 'normale', 'avancement' => 0, 'type_activite' => 'atelier']
);

$decorer = function (array $r) use ($today) {
    $r['en_retard'] = !empty($r['date_echeance']) && $r['date_echeance'] < $today && !in_array($r['statut'], ['termine', 'annule'], true);
    return $r;
};

$action = $_GET['action'] ?? null;
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Checklist des documents et actions requis pour realiser l'activite
if ($action === 'checklist') {
    $a = $crud->trouver((int) $id) ?? Response::error('Activité introuvable', 404);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Response::json(Checklist::proposer($a));
    }
    Auth::requireWrite();
    $b = Response::body();
    $insDoc = $pdo->prepare("INSERT INTO documents (type, titre, activite_id, responsable_id, statut, date_echeance) VALUES (?,?,?,?, 'a_faire', ?)");
    foreach ($b['documents'] ?? [] as $d) {
        $insDoc->execute([$d['type'], $d['titre'] . ' — ' . $a['titre'], $a['id'], $a['responsable_id'], Response::orNull($d['date_echeance'] ?? null)]);
    }
    $insTache = $pdo->prepare("INSERT INTO taches (activite_id, titre, responsable_id, echeance, statut) VALUES (?,?,?,?, 'a_faire')");
    foreach ($b['taches'] ?? [] as $t) {
        $insTache->execute([$a['id'], $t['titre'], $a['responsable_id'], Response::orNull($t['echeance'] ?? null)]);
    }
    Response::json(['ok' => true, 'documents' => count($b['documents'] ?? []), 'taches' => count($b['taches'] ?? [])]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim(Response::body()['titre'] ?? '') === '') {
    Response::error('Le titre est requis', 422);
}

$crud->traiter(function () use ($like) {
    $where = [];
    $p = [];
    foreach (['statut', 'composante', 'type_activite'] as $f) {
        if (!empty($_GET[$f])) {
            $where[] = "x.{$f} = ?";
            $p[] = $_GET[$f];
        }
    }
    if (!empty($_GET['responsable_id'])) {
        $where[] = 'x.responsable_id = ?';
        $p[] = (int) $_GET['responsable_id'];
    }
    if (!empty($_GET['q'])) {
        $where[] = "(x.titre {$like} ? OR x.description {$like} ? OR x.code {$like} ?)";
        array_push($p, "%{$_GET['q']}%", "%{$_GET['q']}%", "%{$_GET['q']}%");
    }
    return [$where, $p];
}, $decorer);
