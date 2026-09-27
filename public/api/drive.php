<?php
/**
 * Liaison Google Drive
 *  GET                      : etat (configure, compte de service a inviter, dossier, derniere synchro)
 *  POST ?action=dossier     : enregistre le dossier Drive a synchroniser (administrateur)
 *  POST ?action=synchroniser: traite un lot de fichiers ; a rappeler tant que « restants » > 0
 */
require_once __DIR__ . '/_bootstrap.php';
foreach (['Fichiers', 'GoogleDrive', 'SyncDrive'] as $c) {
    require_once __DIR__ . "/../../src/{$c}.php";
}
$user = Auth::require();

$dossierId = fn () => Database::parametre('drive_dossier_id') ?: (getenv('GOOGLE_DRIVE_FOLDER_ID') ?: null);
$etat = function () use ($pdo, $dossierId) {
    $id = $dossierId();
    return [
        'configure' => GoogleDrive::configure(),
        'compte' => GoogleDrive::emailCompte(),
        'dossier_id' => $id,
        'dossier_nom' => Database::parametre('drive_dossier_nom'),
        'dossier_lien' => $id ? "https://drive.google.com/drive/folders/{$id}" : null,
        'derniere_synchro' => Database::parametre('drive_derniere_synchro'),
        'documents_lies' => (int) $pdo->query("SELECT COUNT(*) FROM documents WHERE drive_id IS NOT NULL AND drive_id <> ''")->fetchColumn(),
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::json($etat());
}

$action = $_GET['action'] ?? '';
try {
    if ($action === 'dossier') {
        Auth::requireAdmin();
        $id = GoogleDrive::idDepuisLien((string) (Response::body()['dossier'] ?? ''));
        if (!preg_match('/^[\w-]{10,}$/', $id)) {
            Response::error('Collez le lien du dossier Google Drive (https://drive.google.com/drive/folders/…)', 422);
        }
        $info = GoogleDrive::dossier($id); // verifie l'acces avant d'enregistrer
        Database::definirParametre('drive_dossier_id', $id);
        Database::definirParametre('drive_dossier_nom', $info['name'] ?? null);
        Response::json($etat());
    }

    if ($action === 'synchroniser') {
        Auth::requireWrite();
        @set_time_limit(120);
        if (!GoogleDrive::configure()) {
            Response::error("Google Drive n'est pas encore configuré (voir la procédure dans la fenêtre Google Drive).", 503);
        }
        $id = $dossierId() ?? Response::error("Aucun dossier Google Drive n'est choisi.", 422);
        $stats = SyncDrive::executer($pdo, GoogleDrive::lister($id), [GoogleDrive::class, 'telecharger']);
        if ($stats['restants'] <= 0) {
            Database::definirParametre('drive_derniere_synchro', date('c'));
        }
        Response::json($stats + ['etat' => $etat()]);
    }
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 502);
}

Response::error('Action inconnue', 400);
