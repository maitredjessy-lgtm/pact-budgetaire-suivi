<?php
/**
 * Synchronisation Google Drive -> plateforme (sens unique, le Drive n'est jamais modifie).
 *
 *  - document deja present sur le site (importe du dossier local) : il est relie a son fichier Drive ;
 *  - nouveau fichier Drive : un document est cree (texte extrait, fichier original joint, lien Drive) ;
 *  - fichier modifie dans le Drive : le texte et l'original sont mis a jour, SAUF si le texte a ete
 *    retouche sur le site entre-temps (on ne perd jamais une modification faite sur la plateforme).
 *
 * Traite les fichiers par lots (appels successifs) pour rester sous les delais du serveur.
 */
class SyncDrive
{
    private const TYPES_ACCEPTES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    private const CHEMINS_IGNORES = ['Anciennes_versions', 'Hors_projet', 'Logos_et_charte'];
    private const TAILLE_TEXTE_MAX = 10 * 1024 * 1024;
    private const TAILLE_JOINTE_MAX = 5 * 1024 * 1024;

    public static function norm(string $s): string
    {
        $s = str_replace('\\', '/', mb_strtolower(trim($s)));
        if (class_exists('Normalizer')) {
            $s = Normalizer::normalize($s) ?: $s;
        }
        return preg_replace('/\s+/u', ' ', $s);
    }

    private static function sansExtension(string $nom): string
    {
        return preg_replace('/\.[a-z0-9]{2,5}$/i', '', $nom);
    }

    public static function typeDepuisChemin(string $chemin): string
    {
        $c = mb_strtolower($chemin);
        return match (true) {
            str_contains($c, '02_tdr') => 'tdr',
            str_contains($c, '03_courriers') => 'courrier',
            str_contains($c, '4.3_comptes_rendus') => 'compte_rendu',
            str_contains($c, '04_rapports') => 'rapport',
            str_contains($c, '06_notes') => 'note',
            str_contains($c, 'communiques') => 'communique',
            default => 'autre',
        };
    }

    /** Fichiers du Drive a prendre en compte */
    public static function fichiersUtiles(array $fichiers): array
    {
        return array_values(array_filter($fichiers, function ($f) {
            // Fichiers temporaires de Word (~$…) et fichiers techniques (_A_REMPLIR…)
            if (str_starts_with($f['nom'], '~$') || str_starts_with($f['nom'], '_')) {
                return false;
            }
            foreach (self::CHEMINS_IGNORES as $ign) {
                if (str_contains($f['chemin'], $ign)) {
                    return false;
                }
            }
            $ext = strtolower(pathinfo($f['nom'], PATHINFO_EXTENSION));
            return isset(GoogleDrive::EXPORTS[$f['mime']]) || in_array($ext, self::TYPES_ACCEPTES, true);
        }));
    }

    /**
     * @param array $fichiers liste issue de GoogleDrive::lister()
     * @param callable $telecharger fn(array $fichier): array{chemin, nom}
     */
    public static function executer(PDO $pdo, array $fichiers, callable $telecharger, int $max = 15, int $secondesMax = 45): array
    {
        $debut = time();
        $fichiers = self::fichiersUtiles($fichiers);
        usort($fichiers, fn ($a, $b) => strcmp($a['chemin'], $b['chemin']));

        $docs = $pdo->query('SELECT id, titre, notes, drive_id, drive_modifie FROM documents')->fetchAll();
        $parDrive = $parChemin = $parTitre = [];
        foreach ($docs as $d) {
            if ($d['drive_id']) {
                $parDrive[$d['drive_id']] = $d;
                continue;
            }
            if ($d['notes'] && str_starts_with($d['notes'], 'Fichier local : ')) {
                $parChemin[self::norm(substr($d['notes'], strlen('Fichier local : ')))] = $d;
            }
            $parTitre[self::norm($d['titre'])] ??= $d;
        }
        $codes = $pdo->query("SELECT id, code FROM activites WHERE code IS NOT NULL AND code <> ''")->fetchAll(PDO::FETCH_KEY_PAIR);

        $aTraiter = array_values(array_filter($fichiers, fn ($f) => !isset($parDrive[$f['id']]) || ($parDrive[$f['id']]['drive_modifie'] ?? '') < $f['modifie']));
        $stats = ['total_drive' => count($fichiers), 'restants' => count($aTraiter), 'lies' => 0, 'crees' => 0, 'mis_a_jour' => 0, 'preserves' => 0, 'erreurs' => []];

        foreach ($aTraiter as $f) {
            if ($max-- <= 0 || time() - $debut > $secondesMax) {
                break;
            }
            try {
                $doc = $parDrive[$f['id']]
                    ?? $parChemin[self::norm($f['chemin'])]
                    ?? $parTitre[self::norm(self::sansExtension($f['nom']))]
                    ?? null;
                // Un meme document ne peut etre relie qu'a un seul fichier Drive
                if ($doc) {
                    unset($parChemin[self::norm(substr((string) $doc['notes'], strlen('Fichier local : ')))], $parTitre[self::norm($doc['titre'])]);
                }
                $resultat = self::traiter($pdo, $f, $doc, $telecharger, $codes);
                $stats[$resultat]++;
            } catch (Throwable $e) {
                $stats['erreurs'][] = "{$f['chemin']} : {$e->getMessage()}";
                // Marque tout de meme le fichier pour ne pas bloquer la synchronisation sur une erreur recurrente
                if (isset($doc['id'])) {
                    $pdo->prepare('UPDATE documents SET drive_id = ?, drive_modifie = ? WHERE id = ?')->execute([$f['id'], $f['modifie'], $doc['id']]);
                }
            }
            $stats['restants']--;
        }
        return $stats;
    }

    private static function traiter(PDO $pdo, array $f, ?array $doc, callable $telecharger, array $codes): string
    {
        $ext = isset(GoogleDrive::EXPORTS[$f['mime']]) ? GoogleDrive::EXPORTS[$f['mime']][1] : strtolower(pathinfo($f['nom'], PATHINFO_EXTENSION));
        $natif = isset(GoogleDrive::EXPORTS[$f['mime']]);
        $premiereLiaison = $doc && empty($doc['drive_id']);

        $etat = ['contenu' => '', 'empreinte' => null, 'nb_fichiers' => 0];
        if ($doc) {
            $s = $pdo->prepare('SELECT contenu, drive_empreinte, (SELECT COUNT(*) FROM fichiers x WHERE x.document_id = d.id) AS nb FROM documents d WHERE id = ?');
            $s->execute([$doc['id']]);
            $r = $s->fetch();
            $etat = ['contenu' => (string) $r['contenu'], 'empreinte' => $r['drive_empreinte'], 'nb_fichiers' => (int) $r['nb']];
        }

        // Premiere liaison d'un document deja complet (texte + original importes) : simple rattachement
        if ($premiereLiaison && trim($etat['contenu']) !== '' && $etat['nb_fichiers'] > 0) {
            $pdo->prepare('UPDATE documents SET drive_id = ?, drive_modifie = ?, drive_empreinte = ?, lien = COALESCE(NULLIF(lien, \'\'), ?) WHERE id = ?')
                ->execute([$f['id'], $f['modifie'], md5($etat['contenu']), $f['lien'], $doc['id']]);
            return 'lies';
        }

        // Telechargement si utile (texte extractible et/ou original a joindre)
        $local = null;
        if ($natif || $f['taille'] <= self::TAILLE_TEXTE_MAX) {
            $local = $telecharger($f);
        }
        try {
            $texte = '';
            if ($local && $ext === 'docx' && Docx::disponible()) {
                $texte = Docx::texteDepuisDocx($local['chemin']);
            }

            if (!$doc) {
                $code = null;
                foreach ($codes as $idAct => $c) {
                    if (preg_match('/(^|[^\d.])' . preg_quote($c, '/') . '([^\d]|$)/', $f['nom'])) {
                        $code = $idAct;
                        break;
                    }
                }
                $pdo->prepare('INSERT INTO documents (type, titre, activite_id, statut, date_finalisation, lien, notes, contenu, drive_id, drive_modifie, drive_empreinte)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([
                    self::typeDepuisChemin($f['chemin']), self::sansExtension($f['nom']), $code, 'valide', substr($f['modifie'], 0, 10),
                    $f['lien'], 'Google Drive : ' . $f['chemin'], $texte, $f['id'], $f['modifie'], $texte !== '' ? md5($texte) : null,
                ]);
                $docId = Database::lastId('documents');
                $resultat = 'crees';
            } else {
                $docId = (int) $doc['id'];
                // Le texte n'est remplace que s'il n'a pas ete retouche sur le site depuis le dernier import
                $intact = trim($etat['contenu']) === '' || ($etat['empreinte'] && md5($etat['contenu']) === $etat['empreinte']);
                if ($texte !== '' && $intact) {
                    $pdo->prepare('UPDATE documents SET contenu = ?, drive_empreinte = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$texte, md5($texte), $docId]);
                }
                $pdo->prepare('UPDATE documents SET drive_id = ?, drive_modifie = ?, lien = COALESCE(NULLIF(lien, \'\'), ?) WHERE id = ?')
                    ->execute([$f['id'], $f['modifie'], $f['lien'], $docId]);
                $resultat = $premiereLiaison ? 'lies' : ($intact || $texte === '' ? 'mis_a_jour' : 'preserves');
            }

            // Original joint (remplace la version precedente du meme fichier)
            if ($local && filesize($local['chemin']) <= self::TAILLE_JOINTE_MAX) {
                $pdo->prepare('DELETE FROM fichiers WHERE document_id = ? AND nom = ?')->execute([$docId, $local['nom']]);
                Fichiers::ajouter($pdo, $docId, $local['nom'], $local['chemin']);
            }
            return $resultat;
        } finally {
            if ($local) {
                @unlink($local['chemin']);
            }
        }
    }
}
