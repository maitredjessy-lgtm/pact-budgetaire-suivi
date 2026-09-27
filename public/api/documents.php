<?php
require_once __DIR__ . '/_bootstrap.php';
Auth::require();

$today = date('Y-m-d');
$like = Database::like();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Liste sans le contenu complet (peut etre volumineux) ; le detail le renvoie
$colonnes = isset($_GET['id']) ? 'x.*' : 'x.id, x.type, x.titre, x.activite_id, x.responsable_id, x.modele_id, x.statut, x.date_echeance, x.date_finalisation, x.lien, x.notes, x.created_at, x.updated_at, (CASE WHEN x.contenu IS NULL OR x.contenu = \'\' THEN 0 ELSE 1 END) AS a_contenu';

$crud = new Crud($pdo, 'documents',
    ['type', 'titre', 'activite_id', 'responsable_id', 'modele_id', 'statut', 'date_echeance', 'date_finalisation', 'lien', 'notes', 'contenu'],
    "SELECT {$colonnes}, a.titre AS activite_titre, a.code AS activite_code, r.nom AS responsable_nom
     FROM documents x
     LEFT JOIN activites a ON a.id = x.activite_id
     LEFT JOIN responsables r ON r.id = x.responsable_id",
    '(x.date_echeance IS NULL), x.date_echeance ASC, x.updated_at DESC',
    ['statut' => 'brouillon', 'type' => 'autre']
);

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
        $where[] = "(x.titre {$like} ? OR x.notes {$like} ?)";
        array_push($p, "%{$_GET['q']}%", "%{$_GET['q']}%");
    }
    return [$where, $p];
}, function ($r) use ($today) {
    $r['en_retard'] = !empty($r['date_echeance']) && $r['date_echeance'] < $today && !in_array($r['statut'], ['valide', 'envoye'], true);
    return $r;
});
