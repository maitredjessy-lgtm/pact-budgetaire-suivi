<?php
require_once __DIR__ . '/_bootstrap.php';
Auth::require();

$today = date('Y-m-d');
$in7 = date('Y-m-d', strtotime('+7 days'));
$in30 = date('Y-m-d', strtotime('+30 days'));
$ouvert = "statut NOT IN ('termine','annule')";

$n = fn (string $sql, array $p = []) => (function () use ($pdo, $sql, $p) {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return (int) $s->fetchColumn();
})();

$total = $n('SELECT COUNT(*) FROM activites');
$termine = $n("SELECT COUNT(*) FROM activites WHERE statut = 'termine'");
$annule = $n("SELECT COUNT(*) FROM activites WHERE statut = 'annule'");

$parComposante = $pdo->query("SELECT COALESCE(composante, 'Non classé') AS composante, COUNT(*) AS total,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS termine,
        SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) AS en_cours,
        ROUND(AVG(avancement)) AS avancement
    FROM activites WHERE statut <> 'annule' GROUP BY COALESCE(composante, 'Non classé') ORDER BY 1")->fetchAll();

$parStatut = $pdo->query('SELECT statut, COUNT(*) AS n FROM activites GROUP BY statut')->fetchAll(PDO::FETCH_KEY_PAIR);
$docsParType = $pdo->query("SELECT type, COUNT(*) AS total, SUM(CASE WHEN statut IN ('valide','envoye') THEN 1 ELSE 0 END) AS finalises FROM documents GROUP BY type ORDER BY total DESC")->fetchAll();

$prochaines = $pdo->prepare("SELECT a.id, a.code, a.titre, a.date_echeance, a.statut, a.avancement, r.nom AS responsable_nom
    FROM activites a LEFT JOIN responsables r ON r.id = a.responsable_id
    WHERE a.{$ouvert} AND a.date_echeance IS NOT NULL AND a.date_echeance <= ? ORDER BY a.date_echeance LIMIT 8");
$prochaines->execute([$in30]);

$avgAvancement = (float) $pdo->query("SELECT AVG(avancement) FROM activites WHERE statut <> 'annule'")->fetchColumn();

Response::json([
    'aujourdhui' => $today,
    'kpi' => [
        'activites' => $total,
        'terminees' => $termine,
        'taux_realisation' => ($total - $annule) > 0 ? round(100 * $termine / ($total - $annule)) : 0,
        'avancement_moyen' => round($avgAvancement),
        'en_retard' => $n("SELECT COUNT(*) FROM activites WHERE {$ouvert} AND date_echeance < ?", [$today]),
        'semaine' => $n("SELECT COUNT(*) FROM activites WHERE {$ouvert} AND date_echeance BETWEEN ? AND ?", [$today, $in7]),
        'docs_a_produire' => $n("SELECT COUNT(*) FROM documents WHERE statut NOT IN ('valide','envoye')"),
        'docs_en_retard' => $n("SELECT COUNT(*) FROM documents WHERE statut NOT IN ('valide','envoye') AND date_echeance < ?", [$today]),
        'taches_ouvertes' => $n("SELECT COUNT(*) FROM taches WHERE statut <> 'termine'"),
        'taches_en_retard' => $n("SELECT COUNT(*) FROM taches WHERE statut <> 'termine' AND echeance < ?", [$today]),
        'documents' => $n('SELECT COUNT(*) FROM documents'),
    ],
    'par_statut' => $parStatut,
    'par_composante' => $parComposante,
    'documents_par_type' => $docsParType,
    'prochaines' => $prochaines->fetchAll(),
]);
