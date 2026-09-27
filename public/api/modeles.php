<?php
require_once __DIR__ . '/_bootstrap.php';
Auth::require();

$crud = new Crud($pdo, 'modeles', ['type', 'nom', 'description', 'consignes', 'contenu', 'source'],
    'SELECT x.* FROM modeles x', 'x.type ASC, x.nom ASC');

// Import d'un fichier Word existant (.docx) comme nouveau modele
if (($_GET['action'] ?? '') === 'import') {
    Auth::requireWrite();
    if (!Docx::disponible()) {
        Response::error("L'extension PHP zip est requise pour importer des .docx (elle est incluse dans l'image Docker).", 501);
    }
    $f = $_FILES['fichier'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        Response::error('Fichier manquant ou trop volumineux', 422);
    }
    if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'docx') {
        Response::error('Seuls les fichiers .docx sont acceptés', 422);
    }
    $texte = Docx::texteDepuisDocx($f['tmp_name']);
    if ($texte === '') {
        Response::error('Aucun texte trouvé dans ce document', 422);
    }
    $nom = trim($_POST['nom'] ?? '') ?: pathinfo($f['name'], PATHINFO_FILENAME);
    Response::json($crud->creer([
        'type' => $_POST['type'] ?? 'autre',
        'nom' => $nom,
        'description' => 'Importé depuis ' . $f['name'],
        'consignes' => $_POST['consignes'] ?? "Reprendre fidèlement la structure, le ton et la mise en forme de ce document de référence.",
        'contenu' => $texte,
        'source' => $f['name'],
    ]), 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = Response::body();
    if (trim($b['nom'] ?? '') === '' || trim($b['contenu'] ?? '') === '') {
        Response::error('Nom et contenu requis', 422);
    }
}

$crud->traiter(function () {
    return !empty($_GET['type']) ? [['x.type = ?'], [$_GET['type']]] : [[], []];
});
