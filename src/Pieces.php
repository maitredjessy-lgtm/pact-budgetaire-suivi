<?php
/**
 * Pieces ajoutees apres le premier import (courriers, TDR, notes) : chacune est creee comme document,
 * rattachee a son activite (par code) et son fichier original est joint. Idempotent.
 */
class Pieces
{
    private const VERSION = 'v1';

    private const PIECES = [
        ['fichier' => 'TDR_2.2.1.1_Atelier_criteres_concours_ecoles_universites.docx', 'activite' => '2.2.1.1', 'type' => 'tdr',
         'titre' => 'TDR 2.2.1.1 – Atelier d\'élaboration des critères de sélection des écoles et universités (15-16 octobre 2026)', 'date' => '2026-09-21'],
        ['fichier' => 'Courrier_MENAET_Autorisation_Concours_Transparence_Budgetaire.docx', 'activite' => '2.2.1.1', 'type' => 'courrier',
         'titre' => 'Courrier au MENAET – Autorisation du concours de la transparence budgétaire (enseignement secondaire)', 'date' => '2026-09-21'],
        ['fichier' => 'Courrier_MESRS_Autorisation_Concours_Transparence_Budgetaire.docx', 'activite' => '2.2.1.1', 'type' => 'courrier',
         'titre' => 'Courrier au MESRS – Autorisation du concours de la transparence budgétaire (universités et grandes écoles)', 'date' => '2026-09-21'],
        ['fichier' => 'Proposition_composition_jury_concours_transparence_budgetaire.docx', 'activite' => '2.2.1.1', 'type' => 'note',
         'titre' => 'Proposition de composition du jury du concours de la transparence budgétaire', 'date' => '2026-09-21'],
        ['fichier' => 'TDR_2.2.1.3_Atelier_lancement_concours_national.docx', 'activite' => '2.2.1.3', 'type' => 'tdr',
         'titre' => 'TDR 2.2.1.3 – Atelier de lancement du concours national de la transparence budgétaire (29 octobre 2026)', 'date' => '2026-09-21'],
    ];

    /** Rattache aux activites les documents deja presents qui n'en ont pas (jamais d'ecrasement d'un lien existant) */
    public static function rattacher(PDO $pdo): void
    {
        if (Database::parametre('rattachement_documents') === self::VERSION) {
            return;
        }
        $norme = static fn (string $s): string => preg_replace('/[^a-z0-9% ]+/', '', strtr(preg_replace('/[\x{0300}-\x{036F}]/u', '', mb_strtolower($s)), ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe']));
        $liens = require __DIR__ . '/../database/seed_data/rattachements.php';
        $orphelins = $pdo->query('SELECT id, titre FROM documents WHERE activite_id IS NULL')->fetchAll();
        $act = $pdo->prepare('SELECT id FROM activites WHERE code = ? OR titre = ? ORDER BY id LIMIT 1');
        $maj = $pdo->prepare('UPDATE documents SET activite_id = ? WHERE id = ? AND activite_id IS NULL');
        foreach ($liens as $cle => $titres) {
            $act->execute([(string) $cle, (string) $cle]);
            $id = $act->fetchColumn();
            if (!$id) {
                continue;
            }
            foreach ($titres as $t) {
                $prefixe = str_ends_with($t, '%');
                $t = $norme(rtrim($t, '%'));
                foreach ($orphelins as $o) {
                    $titre = $norme($o['titre']);
                    if ($prefixe ? str_starts_with($titre, $t) : $titre === $t) {
                        $maj->execute([$id, $o['id']]);
                    }
                }
            }
        }
        Database::definirParametre('rattachement_documents', self::VERSION);
    }

    public static function importer(PDO $pdo): void
    {
        if (Database::parametre('pieces_importees') === self::VERSION || !Docx::disponible()) {
            return;
        }
        foreach (self::PIECES as $p) {
            $chemin = __DIR__ . '/../database/seed_data/pieces/' . $p['fichier'];
            if (!is_file($chemin)) {
                continue;
            }
            $a = $pdo->prepare('SELECT id FROM activites WHERE code = ?');
            $a->execute([$p['activite']]);
            $activite = $a->fetchColumn() ?: null;

            $d = $pdo->prepare('SELECT id FROM documents WHERE titre = ?');
            $d->execute([$p['titre']]);
            $id = $d->fetchColumn();
            if (!$id) {
                $pdo->prepare("INSERT INTO documents (type, titre, activite_id, statut, date_finalisation, notes) VALUES (?,?,?,?,?,?)")
                    ->execute([$p['type'], $p['titre'], $activite, 'brouillon', $p['date'], 'Fichier local : ' . $p['fichier']]);
                $id = Database::lastId('documents');
            } elseif ($activite) {
                $pdo->prepare('UPDATE documents SET activite_id = ? WHERE id = ?')->execute([$activite, $id]);
            }
            $f = $pdo->prepare('SELECT COUNT(*) FROM fichiers WHERE document_id = ?');
            $f->execute([$id]);
            if ((int) $f->fetchColumn() === 0) {
                Fichiers::ajouter($pdo, (int) $id, $p['fichier'], $chemin);
            }
        }
        Database::definirParametre('pieces_importees', self::VERSION);
    }
}
