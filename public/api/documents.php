<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../src/Fichiers.php';
Auth::require();

$today = date('Y-m-d');
$like = Database::like();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Liste sans le contenu complet (peut etre volumineux) ; le detail le renvoie
$colonnes = isset($_GET['id']) ? 'x.*' : 'x.id, x.type, x.titre, x.activite_id, x.responsable_id, x.modele_id, x.statut, x.date_echeance, x.date_finalisation, x.lien, x.notes, x.created_at, x.updated_at, (CASE WHEN x.contenu IS NULL OR x.contenu = \'\' THEN 0 ELSE 1 END) AS a_contenu';
$colonnes .= ', (SELECT COUNT(*) FROM fichiers f WHERE f.document_id = x.id) AS nb_fichiers';

$crud = new Crud($pdo, 'documents',
    ['type', 'titre', 'activite_id', 'responsable_id', 'modele_id', 'statut', 'date_echeance', 'date_finalisation', 'lien', 'notes', 'contenu'],
    "SELECT {$colonnes}, a.titre AS activite_titre, a.code AS activite_code, r.nom AS responsable_nom
     FROM documents x
     LEFT JOIN activites a ON a.id = x.activite_id
     LEFT JOIN responsables r ON r.id = x.responsable_id",
    '(x.date_echeance IS NULL), x.date_echeance ASC, x.updated_at DESC',
    ['statut' => 'brouillon', 'type' => 'autre']
);

// Fichiers originaux joints : consultation, ajout, suppression
$action = $_GET['action'] ?? '';
if ($action === 'fichier' && !empty($_GET['fid'])) {
    Fichiers::envoyer($pdo, (int) $_GET['fid']);
}
if ($action === 'joindre' && $id) {
    Auth::requireWrite();
    $crud->trouver($id) ?? Response::error('Document introuvable', 404);
    $f = $_FILES['fichier'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        Response::error($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Fichier trop volumineux (10 Mo maximum)' : 'Aucun fichier reçu', 422);
    }
    if (!Fichiers::extensionAutorisee($f['name'])) {
        Response::error('Type de fichier non accepté (PDF, Word, Excel, PowerPoint, images, texte)', 422);
    }
    try {
        $r = Fichiers::ajouter($pdo, $id, basename($f['name']), $f['tmp_name']);
    } catch (RuntimeException $e) {
        Response::error($e->getMessage(), 422);
    }
    Response::json($r + ['fichiers' => Fichiers::lister($pdo, $id)], 201);
}
if ($action === 'supprimer_fichier' && !empty($_GET['fid']) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    Auth::requireWrite();
    $pdo->prepare('DELETE FROM fichiers WHERE id = ?')->execute([(int) $_GET['fid']]);
    Response::json(['ok' => true]);
}

// Export Word (.docx) du contenu redige dans la plateforme
if (($_GET['action'] ?? '') === 'export' && $id) {
    $doc = $crud->trouver($id) ?? Response::error('Introuvable', 404);
    $nomFichier = preg_replace('/[^\w\-]+/u', '_', mb_substr($doc['titre'], 0, 80));
    $contenu = $doc['contenu'] ?: "# {$doc['titre']}\n\n(Aucun contenu rédigé)";
    if (Docx::disponible()) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header("Content-Disposition: attachment; filename=\"{$nomFichier}.docx\"; filename*=UTF-8''" . rawurlencode($nomFichier . '.docx'));
        echo Docx::depuisMarkdown($contenu, $doc['titre']);
    } else {
        // Repli : document HTML ouvert par Word
        header('Content-Type: application/msword; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nomFichier}.doc\"");
        echo '<html><head><meta charset="utf-8"></head><body>' . nl2br(htmlspecialchars($contenu)) . '</body></html>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim(Response::body()['titre'] ?? '') === '') {
    Response::error('Le titre est requis', 422);
}

$crud->traiter(function () use ($like) {
    $where = [];
    $p = [];
    foreach (['type', 'statut'] as $f) {
        if (!empty($_GET[$f])) {
            $where[] = "x.{$f} = ?";
            $p[] = $_GET[$f];
        }
    }
    if (!empty($_GET['activite_id'])) {
        $where[] = 'x.activite_id = ?';
        $p[] = (int) $_GET['activite_id'];
    }
    if (!empty($_GET['q'])) {
        $where[] = "(x.titre {$like} ? OR x.notes {$like} ? OR x.contenu {$like} ?)";
        array_push($p, "%{$_GET['q']}%", "%{$_GET['q']}%", "%{$_GET['q']}%");
    }
    return [$where, $p];
}, function ($r) use ($today, $pdo) {
    if (isset($_GET['id'])) {
        $r['fichiers'] = Fichiers::lister($pdo, (int) $r['id']);
    }
    $r['en_retard'] = !empty($r['date_echeance']) && $r['date_echeance'] < $today && !in_array($r['statut'], ['valide', 'envoye'], true);
    return $r;
});
