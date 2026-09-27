<?php
/**
 * Toutes les echeances (activites, documents, taches) non terminees, classees par urgence.
 * ?format=ics : export calendrier (Outlook / Google Agenda) ; ?responsable_id= pour filtrer.
 */
require_once __DIR__ . '/_bootstrap.php';
Auth::require();

$today = date('Y-m-d');
$resp = !empty($_GET['responsable_id']) ? (int) $_GET['responsable_id'] : null;
$filtre = fn (string $col) => $resp ? " AND {$col} = {$resp}" : '';

$items = [];
$rows = $pdo->query("SELECT a.id, a.code, a.titre, a.date_echeance AS echeance, a.statut, r.id AS rid, r.nom AS responsable_nom, r.email AS responsable_email
    FROM activites a LEFT JOIN responsables r ON r.id = a.responsable_id
    WHERE a.statut NOT IN ('termine','annule') AND a.date_echeance IS NOT NULL" . $filtre('a.responsable_id'))->fetchAll();
foreach ($rows as $r) {
    $items[] = $r + ['nature' => 'activite', 'contexte' => $r['code'] ? "Activité {$r['code']}" : 'Activité'];
}
$rows = $pdo->query("SELECT d.id, d.type, d.titre, d.date_echeance AS echeance, d.statut, a.titre AS contexte, a.id AS activite_id, r.id AS rid, r.nom AS responsable_nom, r.email AS responsable_email
    FROM documents d LEFT JOIN activites a ON a.id = d.activite_id LEFT JOIN responsables r ON r.id = d.responsable_id
    WHERE d.statut NOT IN ('valide','envoye') AND d.date_echeance IS NOT NULL" . $filtre('d.responsable_id'))->fetchAll();
foreach ($rows as $r) {
    $items[] = $r + ['nature' => 'document'];
}
$rows = $pdo->query("SELECT t.id, t.titre, t.echeance, t.statut, a.titre AS contexte, a.id AS activite_id, r.id AS rid, r.nom AS responsable_nom, r.email AS responsable_email
    FROM taches t LEFT JOIN activites a ON a.id = t.activite_id LEFT JOIN responsables r ON r.id = t.responsable_id
    WHERE t.statut <> 'termine' AND t.echeance IS NOT NULL" . $filtre('t.responsable_id'))->fetchAll();
foreach ($rows as $r) {
    $items[] = $r + ['nature' => 'tache'];
}

foreach ($items as &$it) {
    $it['jours'] = (int) round((strtotime($it['echeance']) - strtotime($today)) / 86400);
    $it['urgence'] = $it['jours'] < 0 ? 'retard' : ($it['jours'] <= 7 ? 'semaine' : ($it['jours'] <= 30 ? 'mois' : 'plus_tard'));
}
unset($it);
usort($items, fn ($a, $b) => strcmp($a['echeance'], $b['echeance']));

if (($_GET['format'] ?? '') === 'ics') {
    $esc = fn ($s) => addcslashes(str_replace(["\r", "\n"], ' ', (string) $s), ',;\\');
    $lignes = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Social Justice//PACT Budgetaire//FR', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:Échéances PACT Budgétaire'];
    foreach ($items as $it) {
        $d = str_replace('-', '', $it['echeance']);
        $lignes[] = 'BEGIN:VEVENT';
        $lignes[] = "UID:pact-{$it['nature']}-{$it['id']}@socialjustice";
        $lignes[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $lignes[] = "DTSTART;VALUE=DATE:{$d}";
        $lignes[] = 'SUMMARY:' . $esc('[PACT] ' . $it['titre']);
        $lignes[] = 'DESCRIPTION:' . $esc(ucfirst($it['nature']) . ' — Responsable : ' . ($it['responsable_nom'] ?: 'non assigné') . ($it['contexte'] ? " — {$it['contexte']}" : ''));
        $lignes[] = 'BEGIN:VALARM';
        $lignes[] = 'TRIGGER:-P3D';
        $lignes[] = 'ACTION:DISPLAY';
        $lignes[] = 'DESCRIPTION:' . $esc('Échéance dans 3 jours : ' . $it['titre']);
        $lignes[] = 'END:VALARM';
        $lignes[] = 'END:VEVENT';
    }
    $lignes[] = 'END:VCALENDAR';
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="echeances-pact-budgetaire.ics"');
    echo implode("\r\n", $lignes);
    exit;
}

Response::json($items);
