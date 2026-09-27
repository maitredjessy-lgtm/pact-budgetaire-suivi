<?php

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 7,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => $https,
            'httponly' => true,
        ]);
        session_name('pact_session');
        session_start();
    }

    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $user['id'], 'nom' => $user['nom'], 'email' => $user['email'], 'role' => $user['role']];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    public static function require(): array
    {
        $u = self::user();
        if ($u === null) {
            Response::error('Non authentifié', 401);
        }
        return $u;
    }

    /** Les lecteurs peuvent consulter mais pas modifier */
    public static function requireWrite(): array
    {
        $u = self::require();
        if ($u['role'] === 'lecteur') {
            Response::error('Action réservée aux éditeurs', 403);
        }
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::require();
        if ($u['role'] !== 'admin') {
            Response::error('Action réservée aux administrateurs', 403);
        }
        return $u;
    }

    /** Protection CSRF : toute ecriture doit etre un appel JSON emis par l'application (meme origine) */
    public static function checkWriteRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'pact-app') {
            Response::error('Requête refusée', 403);
        }
    }
}
