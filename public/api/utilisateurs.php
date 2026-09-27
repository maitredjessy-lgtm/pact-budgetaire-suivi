<?php
/** Gestion des comptes (administrateurs uniquement) */
require_once __DIR__ . '/_bootstrap.php';
$admin = Auth::requireAdmin();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$roles = ['admin', 'editeur', 'lecteur'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        Response::json($pdo->query('SELECT id, email, nom, role, created_at FROM users ORDER BY nom')->fetchAll());
        // no break
    case 'POST':
        $b = Response::body();
        $email = strtolower(trim($b['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim($b['nom'] ?? '') === '') {
            Response::error('Nom et e-mail valides requis', 422);
        }
        if (strlen($b['password'] ?? '') < 8) {
            Response::error('Mot de passe : 8 caractères minimum', 422);
        }
        $existe = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $existe->execute([$email]);
        if ($existe->fetchColumn() > 0) {
            Response::error('Un compte existe déjà avec cet e-mail', 409);
        }
        $role = in_array($b['role'] ?? '', $roles, true) ? $b['role'] : 'editeur';
        $pdo->prepare('INSERT INTO users (email, password_hash, nom, role) VALUES (?,?,?,?)')
            ->execute([$email, password_hash($b['password'], PASSWORD_DEFAULT), trim($b['nom']), $role]);
        Response::json(['ok' => true], 201);
        // no break
    case 'PUT':
        $b = Response::body();
        if (!$id) {
            Response::error('id requis', 422);
        }
        if (isset($b['role']) && in_array($b['role'], $roles, true)) {
            if ($id === $admin['id'] && $b['role'] !== 'admin') {
                Response::error('Vous ne pouvez pas retirer vos propres droits administrateur', 422);
            }
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$b['role'], $id]);
        }
        if (!empty($b['password'])) {
            if (strlen($b['password']) < 8) {
                Response::error('Mot de passe : 8 caractères minimum', 422);
            }
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($b['password'], PASSWORD_DEFAULT), $id]);
        }
        Response::json(['ok' => true]);
        // no break
    case 'DELETE':
        if (!$id || $id === $admin['id']) {
            Response::error('Suppression impossible', 422);
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        Response::json(['ok' => true]);
        // no break
    default:
        Response::error('Méthode non supportée', 405);
}
