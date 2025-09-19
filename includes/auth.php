<?php
require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Login por email + password, usando: users.password_hash y users.role_id → roles.name
 */
function auth_login(string $email, string $password): bool
{
    $cn = db();
    $e  = mysqli_real_escape_string($cn, trim($email));
    $sql = "SELECT u.id, u.name, u.email, u.password_hash, u.department, u.role_id, u.is_active,
                   r.name AS role_name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email = '$e'
            LIMIT 1";
    $rows = db_select_all($sql);
    if (empty($rows) || isset($rows['_error'])) return false;

    $u = $rows[0];
    if ((int)$u['is_active'] !== 1) return false;
    if (!password_verify($password, $u['password_hash'])) return false;

    $_SESSION['user'] = [
        'id'        => (int)$u['id'],
        'name'      => $u['name'],
        'email'     => $u['email'],
        'department' => $u['department'],
        'role'      => $u['role_name'], // ej. 'admin', 'inventarios', 'rrhh'
    ];
    return true;
}

function auth_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
}

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}
function auth_check(): bool
{
    return isset($_SESSION['user']);
}
function auth_require_login(): void
{
    if (!auth_check()) {
        header('Location: /intranet-CEPESP/modules/auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/** Revisa contra roles.name (ej. 'admin', 'inventarios', 'rrhh') */
function auth_has_role(string $roleName): bool
{
    $u = auth_user();
    if (!$u) return false;
    return strtolower($u['role'] ?? '') === strtolower($roleName);
}
