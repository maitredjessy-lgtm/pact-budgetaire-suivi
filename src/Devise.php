<?php
/**
 * Taux de change EUR / FCFA (XOF, zone UEMOA).
 *
 * Le FCFA ouest-africain est arrime a l'euro a une parite fixe fixee par convention
 * monetaire (BCEAO / Tresor francais) : 1 EUR = 655,957 XOF, invariable depuis 1999.
 * Ce taux ne "flotte" donc pas. On interroge neanmoins une API de change gratuite a
 * intervalle regulier (avec mise en cache) pour :
 *  - afficher une actualisation reellement vivante plutot qu'une constante figee ;
 *  - detecter automatiquement un changement si la parite venait a etre revisee.
 * En cas d'echec (reseau, API indisponible), on revient silencieusement sur la parite
 * legale : l'affichage en FCFA reste toujours correct.
 */
class Devise
{
    public const TAUX_LEGAL = 655.957;
    private const CLE_CACHE = 'taux_fcfa';
    private const DUREE_CACHE = 300; // 5 min : rafraichissement frequent sans solliciter l'API a chaque requete

    /** @return array{taux: float, source: string, live: bool, maj: int} */
    public static function taux(bool $forcer = false): array
    {
        $cache = json_decode((string) Database::parametre(self::CLE_CACHE), true);
        if (!$forcer && is_array($cache) && time() - ($cache['maj'] ?? 0) < self::DUREE_CACHE) {
            return $cache;
        }

        $taux = self::TAUX_LEGAL;
        $source = 'Parité fixe UEMOA/BCEAO (convention de coopération monétaire, inchangée depuis 1999)';
        $live = false;

        try {
            $ch = curl_init('https://open.er-api.com/v6/latest/EUR');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $erreur = curl_errno($ch) ? curl_error($ch) : null;
            curl_close($ch);
            if ($body && !$erreur) {
                $data = json_decode($body, true);
                $xof = $data['rates']['XOF'] ?? null;
                // Garde-fou : la parite etant legalement fixe, on ignore toute valeur aberrante
                // (panne de l'API tierce renvoyant un nombre incoherent) et on garde la parite officielle.
                if (is_numeric($xof) && $xof > 500 && $xof < 800) {
                    $taux = (float) $xof;
                    $live = true;
                    $source = abs($taux - self::TAUX_LEGAL) < 0.5
                        ? 'open.er-api.com — confirme la parité fixe UEMOA/BCEAO'
                        : 'open.er-api.com (taux de marché)';
                }
            }
        } catch (Throwable $e) {
            // Reseau indisponible : on conserve la parite legale definie plus haut.
        }

        $resultat = ['taux' => round($taux, 3), 'source' => $source, 'live' => $live, 'maj' => time()];
        Database::definirParametre(self::CLE_CACHE, json_encode($resultat, JSON_UNESCAPED_UNICODE));
        return $resultat;
    }

    public static function eurVersFcfa(float $eur, ?float $taux = null): float
    {
        return round($eur * ($taux ?? self::taux()['taux']));
    }
}
