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
