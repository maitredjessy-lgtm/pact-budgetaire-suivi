<?php
/**
 * Importe le contenu des documents du projet depuis le dossier Drive local, pour qu'ils
 * soient lisibles directement sur la plateforme :
 *  - le texte des fichiers Word (.docx) est extrait dans le champ "contenu" (lecture, recherche, IA)
 *  - le fichier original est joint s'il fait moins de 5 Mo (consultation / telechargement)
 *
 * Usage (avec la base de production) :
 *   set DATABASE_URL=postgresql://...        (Windows)   |   export DATABASE_URL=...  (Linux/Mac)
 *   php -d extension=zip database/importer_documents.php "C:\...\PACT BUDGETAIRE - Drive de suivi"
 *
 * Idempotent : un document deja rempli ou ayant deja un fichier joint n'est pas modifie.
 */
declare(strict_types=1);

foreach (['Database', 'Response', 'Docx', 'Fichiers'] as $c) {
    require_once __DIR__ . "/../src/{$c}.php";
}

$racine = rtrim($argv[1] ?? '', '\\/');
if ($racine === '' || !is_dir($racine)) {
    fwrite(STDERR, "Indiquez le dossier « PACT BUDGETAIRE - Drive de suivi » en argument.\n");
    exit(1);
}
if (!Docx::disponible()) {
    fwrite(STDERR, "Extension zip requise : lancez avec  php -d extension=zip ...\n");
    exit(1);
}
const TAILLE_JOINTE_MAX = 5 * 1024 * 1024;

function cleNom(string $nom): string
{
    if (class_exists('Normalizer')) {
        $nom = Normalizer::normalize($nom) ?: $nom;
    }
    return mb_strtolower($nom);
}

$pdo = Database::connect();

// Index de secours par nom de fichier (certains fichiers ont ete deplaces dans le dossier projet)
$index = [];
$projet = dirname($racine);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projet, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->isFile() && !str_contains($f->getPathname(), 'site-suivi-pact')) {
        $index[cleNom($f->getFilename())] ??= $f->getPathname();
    }
}

$docs = $pdo->query("SELECT d.id, d.titre, d.notes, d.contenu,
        (SELECT COUNT(*) FROM fichiers f WHERE f.document_id = d.id) AS nb_fichiers
    FROM documents d WHERE d.notes LIKE 'Fichier local : %' ORDER BY d.id")->fetchAll();

$stats = ['textes' => 0, 'fichiers' => 0, 'introuvables' => 0, 'trop_gros' => 0, 'deja' => 0];
foreach ($docs as $d) {
    $relatif = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, substr($d['notes'], strlen('Fichier local : ')));
    $chemin = $racine . DIRECTORY_SEPARATOR . $relatif;
    if (!is_file($chemin)) {
        $chemin = $index[cleNom(basename($relatif))] ?? null;
    }
    if (!$chemin) {
        $stats['introuvables']++;
        echo "  introuvable : {$relatif}\n";
        continue;
    }

    $ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
    $aTexte = trim((string) $d['contenu']) !== '';
    if ($d['nb_fichiers'] > 0 && $aTexte) {
        $stats['deja']++;
        continue;
    }

    try {
        if ($d['nb_fichiers'] == 0 && filesize($chemin) <= TAILLE_JOINTE_MAX) {
            // Joint l'original (et extrait le texte d'un .docx au passage)
            $r = Fichiers::ajouter($pdo, (int) $d['id'], basename($chemin), $chemin);
            $stats['fichiers']++;
            $stats['textes'] += $r['texte_extrait'] ? 1 : 0;
            $aTexte = $aTexte || $r['texte_extrait'];
        } elseif ($d['nb_fichiers'] == 0) {
            $stats['trop_gros']++;
        }
        if (!$aTexte && $ext === 'docx') {
            $texte = Docx::texteDepuisDocx($chemin);
            if ($texte !== '') {
                $pdo->prepare('UPDATE documents SET contenu = ? WHERE id = ?')->execute([$texte, $d['id']]);
                $stats['textes']++;
            }
        }
        echo '.';
    } catch (Throwable $e) {
        echo "\n  erreur sur {$d['titre']} : {$e->getMessage()}\n";
    }
}

printf("\n\nTerminé : %d textes extraits, %d fichiers joints, %d trop volumineux (> 5 Mo, texte seul), %d déjà importés, %d introuvables.\n",
    $stats['textes'], $stats['fichiers'], $stats['trop_gros'], $stats['deja'], $stats['introuvables']);
