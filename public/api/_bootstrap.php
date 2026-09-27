<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set(getenv('TZ') ?: 'Africa/Abidjan');

foreach (['Database', 'Response', 'Auth', 'Crud', 'AI', 'Contexte', 'Docx', 'Redaction'] as $classe) {
    require_once __DIR__ . "/../../src/{$classe}.php";
}

set_exception_handler(function (Throwable $e) {
    error_log('[PACT] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error('Erreur serveur : ' . $e->getMessage(), 500);
});

Auth::start();
Auth::checkWriteRequest();
$pdo = Database::connect();
