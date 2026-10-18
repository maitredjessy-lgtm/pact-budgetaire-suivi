<?php
/**
 * Taux de change EUR / FCFA en vigueur, actualise a intervalle regulier (voir src/Devise.php).
 * ?forcer=1 : ignore le cache et relance immediatement l'interrogation de l'API de change.
 */
require_once __DIR__ . '/_bootstrap.php';
Auth::require();

Response::json(Devise::taux(!empty($_GET['forcer'])));
