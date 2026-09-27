<?php
/**
 * Fichiers originaux joints aux documents. Ils sont stockes en base (base64) car le
 * disque des services Render gratuits est efface a chaque redemarrage.
 */
class Fichiers
{
    public const TAILLE_MAX = 10 * 1024 * 1024;

    private const MIMES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ppt' => 'application/vnd.ms-powerpoint',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'txt' => 'text/plain', 'csv' => 'text/csv',
    ];

    public static function extensionAutorisee(string $nom): bool
    {
        return isset(self::MIMES[strtolower(pathinfo($nom, PATHINFO_EXTENSION))]);
    }

    /**
     * Enregistre un fichier pour un document. Si c'est un .docx et que le document n'a pas
     * encore de contenu redige, son texte est extrait pour etre lisible directement sur le site.
     * @return array{id:int, texte_extrait:bool}
     */
    public static function ajouter(PDO $pdo, int $documentId, string $nom, string $chemin): array
    {
        $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
        $taille = filesize($chemin);
        if ($taille > self::TAILLE_MAX) {
            throw new RuntimeException('Fichier trop volumineux (10 Mo maximum). Pour un gros fichier, renseignez plutôt un lien Drive / OneDrive.');
        }
        $pdo->prepare('INSERT INTO fichiers (document_id, nom, mime, taille, donnees) VALUES (?,?,?,?,?)')
            ->execute([$documentId, $nom, self::MIMES[$ext] ?? 'application/octet-stream', $taille, base64_encode(file_get_contents($chemin))]);
        $id = Database::lastId('fichiers');

        $texteExtrait = false;
        if ($ext === 'docx' && Docx::disponible()) {
            $s = $pdo->prepare('SELECT contenu FROM documents WHERE id = ?');
            $s->execute([$documentId]);
            if (trim((string) $s->fetchColumn()) === '') {
                $texte = Docx::texteDepuisDocx($chemin);
                if ($texte !== '') {
                    $pdo->prepare('UPDATE documents SET contenu = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$texte, $documentId]);
                    $texteExtrait = true;
                }
            }
        }
        return ['id' => $id, 'texte_extrait' => $texteExtrait];
    }

    public static function lister(PDO $pdo, int $documentId): array
    {
        $s = $pdo->prepare('SELECT id, nom, mime, taille, created_at FROM fichiers WHERE document_id = ? ORDER BY id');
        $s->execute([$documentId]);
        return $s->fetchAll();
    }

    /** Envoie le fichier au navigateur ; les PDF et images s'affichent directement */
    public static function envoyer(PDO $pdo, int $id): void
    {
        $s = $pdo->prepare('SELECT nom, mime, donnees FROM fichiers WHERE id = ?');
        $s->execute([$id]);
        $f = $s->fetch() ?: Response::error('Fichier introuvable', 404);
        $inline = str_starts_with($f['mime'], 'image/') || $f['mime'] === 'application/pdf' || $f['mime'] === 'text/plain';
        $ascii = preg_replace('/[^\w.\-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $f['nom']) ?: 'fichier');
        header('Content-Type: ' . $f['mime']);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($f['nom']));
        header('X-Content-Type-Options: nosniff');
        if ($f['mime'] !== 'application/pdf') {
            // (le lecteur PDF des navigateurs ne fonctionne pas dans un contexte "sandbox")
            header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        }
        header('Cache-Control: private, max-age=3600');
        echo base64_decode($f['donnees']);
        exit;
    }
}
