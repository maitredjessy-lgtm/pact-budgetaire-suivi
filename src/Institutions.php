<?php
/**
 * Acteurs institutionnels du projet : referentiel, rattachement aux activites (objectif, resultats, acteurs)
 * et veille sur leurs sites officiels (flux RSS, API WordPress ou, a defaut, liens d'actualites de la page).
 */
class Institutions
{
    private const VERSION_ENRICHISSEMENT = 'v1';
    private const DUREE_CACHE = 10800; // 3 h

    public static function registre(): array
    {
        static $r = null;
        return $r ??= require __DIR__ . '/../database/seed_data/acteurs.php';
    }

    /** Remplit objectif / resultats / acteurs des activites du plan de travail (champs vides uniquement) */
    public static function enrichirActivites(PDO $pdo): void
    {
        $cle = 'enrichissement_activites';
        if (Database::parametre($cle) === self::VERSION_ENRICHISSEMENT) {
            return;
        }
        $data = require __DIR__ . '/../database/seed_data/enrichissement_activites.php';
        $maj = $pdo->prepare("UPDATE activites SET
            objectif = CASE WHEN objectif IS NULL OR objectif = '' THEN ? ELSE objectif END,
            resultats = CASE WHEN resultats IS NULL OR resultats = '' THEN ? ELSE resultats END,
            acteurs = CASE WHEN acteurs IS NULL OR acteurs = '' THEN ? ELSE acteurs END
            WHERE titre = ?");
        foreach ($data as $titre => $e) {
            $maj->execute([$e['objectif'], $e['resultats'], implode(',', $e['acteurs']), $titre]);
        }
        Database::definirParametre($cle, self::VERSION_ENRICHISSEMENT);
    }

    /** Fiche de l'acteur pour le prompt de redaction d'un courrier */
    public static function pourCourrier(string $cle): ?array
    {
        $a = self::registre()[$cle] ?? null;
        return $a && $a['destinataire'] !== '' ? $a : null;
    }

    // ------------------------------------------------------------------ veille

    /** @return array{items: array, maj: int, erreur: ?string, source: string} */
    public static function actualites(string $cle, bool $forcer = false): array
    {
        $a = self::registre()[$cle] ?? null;
        if (!$a || ($a['actualites'] ?? '') === '') {
            return ['items' => [], 'maj' => 0, 'erreur' => 'Aucun site à consulter pour cet acteur.', 'source' => ''];
        }
        $cache = json_decode((string) Database::parametre('veille_' . $cle), true);
        if (!$forcer && is_array($cache) && time() - ($cache['maj'] ?? 0) < self::DUREE_CACHE) {
            return $cache;
        }
        $erreur = null;
        $items = [];
        $source = '';
        try {
            [$items, $source] = self::collecter($a);
        } catch (RuntimeException $e) {
            $erreur = $e->getMessage();
        }
        if (!$items && is_array($cache) && !empty($cache['items'])) {
            // Site momentanement injoignable : on conserve la derniere veille reussie
            $cache['erreur'] = ($erreur ?: 'Aucune actualité détectée') . ' — dernière veille conservée.';
            return $cache;
        }
        $res = ['items' => $items, 'maj' => time(), 'erreur' => $items ? null : ($erreur ?: 'Aucune actualité détectée sur la page.'), 'source' => $source];
        if ($items) { // un echec n'est jamais memorise : la prochaine consultation reessaie
            Database::definirParametre('veille_' . $cle, json_encode($res, JSON_UNESCAPED_UNICODE));
        }
        return $res;
    }

    private static function collecter(array $a): array
    {
        $tentatives = [];
        // 1. Flux RSS/Atom
        if (!empty($a['flux'])) {
            try {
                $items = self::depuisFlux(self::telecharger($a['flux']));
                if ($items) {
                    return [$items, 'flux'];
                }
            } catch (RuntimeException $e) {
                $tentatives[] = $e->getMessage();
            }
        }
        // 2. Page d'actualites : flux annonce dans le HTML, sinon liens d'articles
        $html = self::telecharger($a['actualites']);
        if (preg_match('#<link[^>]+type=["\']application/(?:rss|atom)\+xml["\'][^>]*href=["\']([^"\']+)["\']#i', $html, $m)
            || preg_match('#<link[^>]+href=["\']([^"\']+)["\'][^>]+type=["\']application/(?:rss|atom)\+xml["\']#i', $html, $m)) {
            try {
                $items = self::depuisFlux(self::telecharger(self::absolue($m[1], $a['actualites'])));
                if ($items) {
                    return [$items, 'flux'];
                }
            } catch (RuntimeException $e) {
                $tentatives[] = $e->getMessage();
            }
        }
        return [self::depuisLiens($html, $a['actualites']), 'page'];
    }

    private static function telecharger(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PACT-Veille/1.0; +suivi projet PACT Budgétaire)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/rss+xml,application/xml;q=0.9,*/*;q=0.8'],
        ]);
        $corps = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($corps === false || $code >= 400 || $code === 0) {
            throw new RuntimeException('Site injoignable (' . (parse_url($url, PHP_URL_HOST) ?: $url) . ($err ? " : {$err}" : ", HTTP {$code}") . ')');
        }
        return (string) $corps;
    }

    private static function depuisFlux(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);
        if (!$doc) {
            return [];
        }
        $items = [];
        $noeuds = isset($doc->channel) ? $doc->channel->item : ($doc->entry ?? []);
        foreach ($noeuds as $n) {
            $lien = (string) ($n->link['href'] ?? $n->link);
            $titre = trim(html_entity_decode(strip_tags((string) $n->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($titre === '' || !preg_match('#^https?://#', $lien)) {
                continue;
            }
            $date = strtotime((string) ($n->pubDate ?? $n->updated ?? $n->published ?? ''));
            $resume = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($n->description ?? $n->summary ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $items[] = ['titre' => $titre, 'lien' => $lien, 'date' => $date ? date('Y-m-d', $date) : null, 'resume' => mb_substr($resume, 0, 220)];
            if (count($items) >= 8) {
                break;
            }
        }
        return $items;
    }

    /** Dernier recours : liens de la page dont l'intitule ressemble a un titre d'article */
    private static function depuisLiens(string $html, string $base): array
    {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_use_internal_errors($prev);
        $hote = preg_replace('/^www\./', '', (string) parse_url($base, PHP_URL_HOST));
        $items = [];
        $vus = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            $texte = trim(preg_replace('/\s+/u', ' ', $a->textContent));
            $href = trim($a->getAttribute('href'));
            if ($href === '' || $href[0] === '#' || str_starts_with($href, 'javascript') || str_starts_with($href, 'mailto') || preg_match('/\.(jpe?g|png|gif)$/i', $href)) {
                continue;
            }
            // Titre d'article : phrase d'au moins 6 mots (exclut menus et boutons)
            if (mb_strlen($texte) < 35 || count(explode(" ", $texte)) < 6) {
                continue;
            }
            $lien = self::absolue($href, $base);
            $h = preg_replace('/^www\./', '', (string) parse_url($lien, PHP_URL_HOST));
            if ($h !== $hote || isset($vus[$lien]) || isset($vus[$texte])) {
                continue;
            }
            $vus[$lien] = $vus[$texte] = true;
            $items[] = ['titre' => mb_substr($texte, 0, 200), 'lien' => $lien, 'date' => null, 'resume' => ''];
            if (count($items) >= 8) {
                break;
            }
        }
        return $items;
    }

    private static function absolue(string $href, string $base): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($base);
        $origine = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
        if (str_starts_with($href, '//')) {
            return ($p['scheme'] ?? 'https') . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origine . $href;
        }
        $dir = rtrim(dirname($p['path'] ?? '/') === '.' ? '' : dirname($p['path'] ?? '/'), '/');
        return $origine . $dir . '/' . $href;
    }
}
