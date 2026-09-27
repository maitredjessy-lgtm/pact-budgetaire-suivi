<?php
/** Membres de l'equipe / personnes imputees aux activites et taches, avec leur charge */
require_once __DIR__ . '/_bootstrap.php';
Auth::require();

$today = date('Y-m-d');
$crud = new Crud($pdo, 'responsables', ['nom', 'email', 'telephone', 'fonction'],
    "SELECT x.*,
        (SELECT COUNT(*) FROM activites a WHERE a.responsable_id = x.id AND a.statut NOT IN ('termine','annule')) AS nb_activites,
        (SELECT COUNT(*) FROM taches t WHERE t.responsable_id = x.id AND t.statut <> 'termine') AS nb_taches,
        (SELECT COUNT(*) FROM documents d WHERE d.responsable_id = x.id AND d.statut NOT IN ('valide','envoye')) AS nb_documents,
        ((SELECT COUNT(*) FROM activites a WHERE a.responsable_id = x.id AND a.statut NOT IN ('termine','annule') AND a.date_echeance < '{$today}')
         + (SELECT COUNT(*) FROM taches t WHERE t.responsable_id = x.id AND t.statut <> 'termine' AND t.echeance < '{$today}')
         + (SELECT COUNT(*) FROM documents d WHERE d.responsable_id = x.id AND d.statut NOT IN ('valide','envoye') AND d.date_echeance < '{$today}')) AS nb_retards
     FROM responsables x", 'x.nom ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim(Response::body()['nom'] ?? '') === '') {
    Response::error('Le nom est requis', 422);
}
$crud->traiter();
