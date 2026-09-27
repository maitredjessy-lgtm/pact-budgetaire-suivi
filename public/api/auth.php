<?php
/**
 * action=status : { configure: bool, user: {...}|null, ia: bool }
 * action=setup  : cree le premier compte administrateur (uniquement si aucun utilisateur n'existe)
 * action=login / logout
 */
require_once __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? 'status';
$nbUsers = fn () => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

switch ($action) {
    case 'status':
        Response::json([
            'configure' => $nbUsers() > 0,
            'user' => Auth::user(),
            'ia' => AI::isConfigured(),
            'ia_modele' => AI::isConfigured() ? AI::provider() . ' / ' . AI::model() : null,
            'docx' => Docx::disponible(),
            'persistant' => Database::driver() === 'pgsql' || (bool) getenv('SQLITE_PATH'),
        ]);
        // no break
    case 'setup':
        if ($nbUsers() > 0) {
            Response::error('La plateforme est déjà configurée', 409);
        }
        $b = Response::body();
        $email = strtolower(trim($b['email'] ?? ''));
        $nom = trim($b['nom'] ?? '');
        $mdp = $b['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $nom === '') {
            Response::error('Nom et e-mail valides requis', 422);
        }
        if (strlen($mdp) < 8) {
            Response::error('Le mot de passe doit contenir au moins 8 caractères', 422);
        }
        $pdo->prepare('INSERT INTO users (email, password_hash, nom, role) VALUES (?,?,?,?)')
            ->execute([$email, password_hash($mdp, PASSWORD_DEFAULT), $nom, 'admin']);
        $user = ['id' => Database::lastId('users'), 'email' => $email, 'nom' => $nom, 'role' => 'admin'];
        Auth::login($user);
        Response::json(['user' => Auth::user()]);
        // no break
    case 'login':
        $b = Response::body();
        // Limitation simple des tentatives par session
        $_SESSION['tentatives'] = ($_SESSION['tentatives'] ?? 0) + 1;
        if ($_SESSION['tentatives'] > 10) {
            Response::error('Trop de tentatives, réessayez plus tard', 429);
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($b['email'] ?? ''))]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($b['password'] ?? '', $u['password_hash'])) {
            Response::error('E-mail ou mot de passe incorrect', 401);
        }
        Auth::login($u);
        Response::json(['user' => Auth::user()]);
        // no break
    case 'logout':
        Auth::logout();
        Response::json(['ok' => true]);
        // no break
    case 'password':
        $u = Auth::require();
        $b = Response::body();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$u['id']]);
        if (!password_verify($b['ancien'] ?? '', (string) $stmt->fetchColumn())) {
            Response::error('Mot de passe actuel incorrect', 422);
        }
        if (strlen($b['nouveau'] ?? '') < 8) {
            Response::error('Le nouveau mot de passe doit contenir au moins 8 caractères', 422);
        }
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($b['nouveau'], PASSWORD_DEFAULT), $u['id']]);
        Response::json(['ok' => true]);
        // no break
    default:
        Response::error('Action inconnue', 400);
}
