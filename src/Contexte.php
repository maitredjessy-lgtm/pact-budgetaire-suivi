<?php
/**
 * Resume textuel de l'etat du projet, injecte dans les requetes IA pour que
 * les reponses et documents generes s'appuient sur les donnees reelles.
 */
class Contexte
{
    public const PRESENTATION = "Projet PACT BUDGÉTAIRE (Participation Active de la Société Civile pour la Transparence Budgétaire en Côte d'Ivoire), financé par l'Union européenne, mis en œuvre par l'ONG Social Justice (Abidjan) en partenariat avec IBP Sénégal (International Budget Partnership). Partenaires institutionnels : DGBF, Ministère des Finances et du Budget, Assemblée nationale (CAEF), Cour des Comptes, MFFE, Comité technique OGP, réseau d'OSC COST Budget. Contacts Social Justice : 0769634062 — socialjustice.ci@gmail.com.";

    public static function projet(PDO $pdo, bool $complet = false): string
    {
        $today = date('Y-m-d');
        $l = ["Date du jour : {$today}", self::PRESENTATION];

        $stats = $pdo->query('SELECT statut, COUNT(*) AS n FROM activites GROUP BY statut')->fetchAll();
        $l[] = "\nRépartition des activités par statut : " . implode(', ', array_map(fn ($s) => "{$s['statut']}={$s['n']}", $stats));

        $limit = $complet ? 200 : 60;
        $rows = $pdo->query("SELECT a.code, a.titre, a.composante, a.statut, a.avancement, a.date_echeance, a.description, r.nom AS resp
            FROM activites a LEFT JOIN responsables r ON r.id = a.responsable_id
            ORDER BY a.composante, a.code LIMIT {$limit}")->fetchAll();
        $l[] = "\nActivités (code | titre | composante | statut | avancement | échéance | responsable | note) :";
        foreach ($rows as $a) {
            $retard = ($a['date_echeance'] && $a['date_echeance'] < $today && !in_array($a['statut'], ['termine', 'annule'])) ? ' [EN RETARD]' : '';
            $desc = $a['description'] ? mb_substr($a['description'], 0, 160) : '';
            $l[] = "- " . ($a['code'] ?: '—') . " | {$a['titre']} | {$a['composante']} | {$a['statut']}{$retard} | {$a['avancement']}% | " . ($a['date_echeance'] ?: 'n.d.') . ' | ' . ($a['resp'] ?: 'non assigné') . " | {$desc}";
        }

        $ind = $pdo->query('SELECT code, niveau, libelle, unite, valeur_reference, cible, valeur_actuelle FROM indicateurs ORDER BY id')->fetchAll();
        if ($ind) {
            $l[] = "\nIndicateurs du cadre logique (code | niveau | libellé | référence | cible | valeur actuelle) :";
            foreach ($ind as $i) {
                $v = fn ($x) => $x === null ? 'n.d.' : rtrim(rtrim((string) $x, '0'), '.');
                $l[] = "- {$i['code']} | {$i['niveau']} | {$i['libelle']} | {$v($i['valeur_reference'])} | {$v($i['cible'])} | {$v($i['valeur_actuelle'])} {$i['unite']}";
            }
        }

        $taches = $pdo->prepare("SELECT t.titre, t.echeance, r.nom AS resp FROM taches t LEFT JOIN responsables r ON r.id = t.responsable_id
            WHERE t.statut <> 'termine' ORDER BY (t.echeance IS NULL), t.echeance LIMIT 30");
        $taches->execute();
        $t = $taches->fetchAll();
        if ($t) {
            $l[] = "\nTâches ouvertes :";
            foreach ($t as $x) {
                $l[] = "- {$x['titre']} | échéance " . ($x['echeance'] ?: 'n.d.') . ' | ' . ($x['resp'] ?: 'non assigné');
            }
        }

        $docs = $pdo->query("SELECT type, titre, statut, date_echeance FROM documents WHERE statut NOT IN ('valide','envoye')
            ORDER BY (date_echeance IS NULL), date_echeance LIMIT 30")->fetchAll();
        if ($docs) {
            $l[] = "\nDocuments à produire / en cours :";
            foreach ($docs as $d) {
                $l[] = "- [{$d['type']}] {$d['titre']} | {$d['statut']} | échéance " . ($d['date_echeance'] ?: 'n.d.');
            }
        }
        return implode("\n", $l);
    }

    /**
     * Recherche dans le TEXTE des documents du projet les passages utiles pour repondre a une question
     * (ex. « quel hôtel pour le lancement ? » -> courrier d'invitation qui mentionne l'hôtel Azalaï).
     */
    public static function documentsPertinents(PDO $pdo, string $question, int $max = 4): string
    {
        $vides = ['peux', 'peut', 'pouvez', 'quel', 'quelle', 'quels', 'quelles', 'quand', 'comment', 'pourquoi', 'combien', 'etait', 'était', 'sont',
            'cette', 'avoir', 'faire', 'fait', 'préciser', 'preciser', 'précise', 'donner', 'donne', 'dire', 'merci', 'stp', 'svp', 'moi', 'nous', 'vous', 'ont', 'eu', 'lieu'];
        $mots = array_slice(array_values(array_filter(
            array_map([self::class, 'sansAccents'], Redaction::mots($question)),
            fn ($m) => !in_array($m, $vides, true)
        )), 0, 8);
        if (!$mots) {
            return '';
        }
        $like = Database::like();
        $conditions = implode(' OR ', array_fill(0, count($mots), "(titre {$like} ? OR contenu {$like} ?)"));
        $params = [];
        foreach ($mots as $m) {
            // Voyelles remplacees par « _ » (un caractere quelconque) : « hotel » trouve aussi « hôtel »
            $motif = '%' . preg_replace('/[aeiouyc]/', '_', $m) . '%';
            array_push($params, $motif, $motif);
        }
        $s = $pdo->prepare("SELECT id, type, titre, contenu FROM documents WHERE contenu IS NOT NULL AND contenu <> '' AND ({$conditions}) LIMIT 60");
        $s->execute($params);

        $resultats = [];
        foreach ($s->fetchAll() as $d) {
            $texte = self::sansAccents($d['contenu']);
            $titre = self::sansAccents($d['titre']);
            $score = 0;
            $positions = [];
            foreach ($mots as $m) {
                $n = mb_substr_count($texte, $m);
                $score += min($n, 5) + (str_contains($titre, $m) ? 4 : 0) + ($n > 0 ? 3 : 0); // favorise les documents qui couvrent plusieurs mots
                if (($p = mb_strpos($texte, $m)) !== false) {
                    $positions[] = $p;
                }
            }
            $resultats[] = ['score' => $score, 'doc' => $d, 'positions' => $positions];
        }
        usort($resultats, fn ($a, $b) => $b['score'] <=> $a['score']);

        $out = [];
        foreach (array_slice($resultats, 0, $max) as $r) {
            $d = $r['doc'];
            // Extraits autour des mots trouves (+ debut du document, souvent le plus informatif)
            $extraits = [mb_substr($d['contenu'], 0, 700)];
            sort($r['positions']);
            $dernier = 700;
            foreach ($r['positions'] as $p) {
                if ($p > $dernier) {
                    $extraits[] = '… ' . mb_substr($d['contenu'], max(0, $p - 350), 800) . ' …';
                    $dernier = $p + 450;
                }
                if (count($extraits) >= 4) {
                    break;
                }
            }
            $out[] = "--- Document « {$d['titre']} » ({$d['type']}) ---\n" . implode("\n", $extraits);
        }
        return implode("\n\n", $out);
    }

    /** Minuscules sans accents, en conservant la longueur (les positions restent valables dans le texte d'origine) */
    public static function sansAccents(string $s): string
    {
        return strtr(mb_strtolower($s), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ÿ' => 'y', 'œ' => 'o',
        ]);
    }

    public static function activite(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT a.*, r.nom AS responsable_nom FROM activites a LEFT JOIN responsables r ON r.id = a.responsable_id WHERE a.id = ?');
        $stmt->execute([$id]);
        $a = $stmt->fetch();
        if (!$a) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT type, titre, statut, contenu FROM documents WHERE activite_id = ?');
        $stmt->execute([$id]);
        $a['documents'] = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT t.titre, t.statut, t.echeance, r.nom AS resp FROM taches t LEFT JOIN responsables r ON r.id = t.responsable_id WHERE t.activite_id = ?');
        $stmt->execute([$id]);
        $a['taches'] = $stmt->fetchAll();
        return $a;
    }

    public static function activiteTexte(array $a): string
    {
        $l = [
            "Activité : {$a['titre']}",
            'Code : ' . ($a['code'] ?: 'n.d.'),
            'Composante : ' . ($a['composante'] ?: 'n.d.'),
            'Type : ' . ($a['type_activite'] ?: 'n.d.'),
            'Lieu : ' . ($a['lieu'] ?: 'n.d.'),
            'Dates : ' . ($a['date_debut'] ?: '?') . ' → ' . ($a['date_echeance'] ?: '?'),
            "Statut : {$a['statut']} ({$a['avancement']}%)",
            'Responsable : ' . ($a['responsable_nom'] ?: 'non assigné'),
            'Description / notes : ' . ($a['description'] ?: 'n.d.'),
        ];
        foreach ($a['taches'] ?? [] as $t) {
            $l[] = "Tâche : {$t['titre']} ({$t['statut']}, échéance " . ($t['echeance'] ?: 'n.d.') . ')';
        }
        foreach ($a['documents'] ?? [] as $d) {
            $l[] = "Document lié : [{$d['type']}] {$d['titre']} ({$d['statut']})";
            // Le contenu d'un TDR deja redige est tres utile pour rediger le rapport correspondant
            if (!empty($d['contenu']) && in_array($d['type'], ['tdr', 'compte_rendu', 'note'], true)) {
                $l[] = "--- Contenu de « {$d['titre']} » ---\n" . mb_substr($d['contenu'], 0, 6000) . "\n--- fin ---";
            }
        }
        return implode("\n", $l);
    }
}
