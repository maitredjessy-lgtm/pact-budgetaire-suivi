<?php
/**
 * Lecture d'un dossier Google Drive via un compte de service (acces en LECTURE SEULE).
 *
 * Configuration (Render > Environment) :
 *   GOOGLE_SERVICE_ACCOUNT_JSON = contenu complet du fichier de cle JSON du compte de service
 *   GOOGLE_DRIVE_FOLDER_ID      = (facultatif) identifiant du dossier ; sinon reglable depuis le site
 * Le dossier Drive doit etre partage (role « Lecteur ») avec l'adresse e-mail du compte de service.
 */
class GoogleDrive
{
    private const API = 'https://www.googleapis.com/drive/v3';
    private const DOSSIER = 'application/vnd.google-apps.folder';

    /** Fichiers Google natifs : format d'export */
    public const EXPORTS = [
        'application/vnd.google-apps.document' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
        'application/vnd.google-apps.spreadsheet' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'application/vnd.google-apps.presentation' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx'],
    ];

    public static function compte(): ?array
    {
        $brut = trim((string) getenv('GOOGLE_SERVICE_ACCOUNT_JSON'));
        if ($brut === '') {
            return null;
        }
        if ($brut[0] !== '{') {
            $brut = (string) base64_decode($brut, true); // accepte aussi le JSON encode en base64
        }
        $c = json_decode($brut, true);
        return (is_array($c) && !empty($c['client_email']) && !empty($c['private_key'])) ? $c : null;
    }

    public static function configure(): bool
    {
        return self::compte() !== null;
    }

    public static function emailCompte(): ?string
    {
        return self::compte()['client_email'] ?? null;
    }

    /** Extrait l'identifiant d'un dossier depuis un lien Drive (ou renvoie l'identifiant tel quel) */
    public static function idDepuisLien(string $lien): string
    {
        if (preg_match('#/folders/([\w-]{10,})#', $lien, $m) || preg_match('#[?&]id=([\w-]{10,})#', $lien, $m)) {
            return $m[1];
        }
        return trim($lien);
    }

    private static function jeton(): string
    {
        $cache = sys_get_temp_dir() . '/pact_gdrive_jeton_' . md5((string) self::emailCompte()) . '.json';
        if (is_file($cache)) {
            $j = json_decode((string) file_get_contents($cache), true);
            if (($j['expire'] ?? 0) > time() + 120) {
                return $j['jeton'];
            }
        }
        $c = self::compte() ?? throw new RuntimeException("Google Drive n'est pas configuré (variable GOOGLE_SERVICE_ACCOUNT_JSON absente ou invalide).");
        $b64 = fn ($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $maintenant = time();
        $entete = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $charge = $b64(json_encode([
            'iss' => $c['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'aud' => $c['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $maintenant,
            'exp' => $maintenant + 3600,
        ]));
        $cle = openssl_pkey_get_private(str_replace('\n', "\n", $c['private_key']));
        if (!$cle || !openssl_sign("{$entete}.{$charge}", $signature, $cle, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Clé privée du compte de service illisible : recopiez tout le fichier JSON dans GOOGLE_SERVICE_ACCOUNT_JSON.');
        }
        $ch = curl_init($c['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "{$entete}.{$charge}." . $b64($signature)]),
            CURLOPT_TIMEOUT => 30,
        ]);
        $r = json_decode((string) curl_exec($ch), true) ?? [];
        curl_close($ch);
        if (empty($r['access_token'])) {
            throw new RuntimeException('Connexion à Google refusée : ' . ($r['error_description'] ?? $r['error'] ?? 'réponse inattendue'));
        }
        @file_put_contents($cache, json_encode(['jeton' => $r['access_token'], 'expire' => $maintenant + (int) ($r['expires_in'] ?? 3600)]));
        return $r['access_token'];
    }

    /** Appel HTTP authentifie ; renvoie le JSON decode, ou le contenu brut si $brut */
    private static function api(string $url, bool $brut = false)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . self::jeton()],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $corps = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($corps === false) {
            throw new RuntimeException('Google Drive injoignable : ' . $err);
        }
        if ($code >= 400) {
            $msg = json_decode($corps, true)['error']['message'] ?? substr($corps, 0, 200);
            if ($code === 404) {
                $msg = "Dossier ou fichier introuvable. Vérifiez que le dossier Drive est bien partagé avec " . self::emailCompte() . " ({$msg})";
            } elseif ($code === 403 && stripos($msg, 'has not been used') !== false) {
                $msg = "L'API Google Drive n'est pas activée dans votre projet Google Cloud. ({$msg})";
            }
            throw new RuntimeException("Erreur Google Drive ({$code}) : {$msg}", $code);
        }
        return $brut ? $corps : (json_decode($corps, true) ?? []);
    }

    /** Informations sur un dossier (verifie aussi que le partage fonctionne) */
    public static function dossier(string $id): array
    {
        return self::api(self::API . '/files/' . rawurlencode($id) . '?supportsAllDrives=true&fields=id,name,mimeType,webViewLink');
    }

    /**
     * Liste recursivement les fichiers d'un dossier.
     * @return array<int, array{id:string, nom:string, mime:string, modifie:string, taille:int, chemin:string, lien:string}>
     */
    public static function lister(string $racineId): array
    {
        $fichiers = [];
        $file = [[$racineId, '']];
        while ($file) {
            [$dossierId, $chemin] = array_shift($file);
            $page = null;
            do {
                $q = rawurlencode("'{$dossierId}' in parents and trashed = false");
                $url = self::API . "/files?q={$q}&pageSize=1000&supportsAllDrives=true&includeItemsFromAllDrives=true"
                    . '&fields=' . rawurlencode('nextPageToken,files(id,name,mimeType,modifiedTime,size,webViewLink)')
                    . ($page ? '&pageToken=' . rawurlencode($page) : '');
                $r = self::api($url);
                foreach ($r['files'] ?? [] as $f) {
                    $cheminF = ($chemin === '' ? '' : $chemin . '/') . $f['name'];
                    if ($f['mimeType'] === self::DOSSIER) {
                        $file[] = [$f['id'], $cheminF];
                        continue;
                    }
                    $fichiers[] = [
                        'id' => $f['id'], 'nom' => $f['name'], 'mime' => $f['mimeType'], 'modifie' => $f['modifiedTime'],
                        'taille' => (int) ($f['size'] ?? 0), 'chemin' => $cheminF, 'lien' => $f['webViewLink'] ?? "https://drive.google.com/file/d/{$f['id']}/view",
                    ];
                }
                $page = $r['nextPageToken'] ?? null;
            } while ($page);
        }
        return $fichiers;
    }

    /**
     * Telecharge un fichier dans un fichier temporaire (les documents Google natifs sont exportes en Office).
     * @return array{chemin:string, nom:string}
     */
    public static function telecharger(array $f): array
    {
        if (isset(self::EXPORTS[$f['mime']])) {
            [$mimeExport, $ext] = self::EXPORTS[$f['mime']];
            $data = self::api(self::API . '/files/' . rawurlencode($f['id']) . '/export?mimeType=' . rawurlencode($mimeExport), true);
            $nom = preg_replace('/\.' . $ext . '$/i', '', $f['nom']) . ".{$ext}";
        } else {
            $data = self::api(self::API . '/files/' . rawurlencode($f['id']) . '?alt=media&supportsAllDrives=true', true);
            $nom = $f['nom'];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'gdrive');
        file_put_contents($tmp, $data);
        return ['chemin' => $tmp, 'nom' => $nom];
    }
}
