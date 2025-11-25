<?php
require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
1. auth_login(string $email, string $password): bool
Descripción: Esta función maneja el inicio de sesión de un usuario utilizando su correo electrónico y contraseña.
Funcionamiento:
Conecta a la base de datos.
Escapa el correo electrónico para prevenir inyecciones SQL.
Realiza una consulta SQL para obtener los datos del usuario correspondiente al correo electrónico proporcionado.
Verifica si el usuario está activo y si la contraseña proporcionada coincide con el hash almacenado en la base de datos.
Si la autenticación es exitosa, almacena la información del usuario en la sesión.
Retorno: Devuelve true si el inicio de sesión fue exitoso, de lo contrario, devuelve false
*/
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

/*
2. auth_logout(): void
Descripción: Esta función cierra la sesión del usuario.
Funcionamiento:
Inicia la sesión si no está activa.
Limpia la variable de sesión.
Si se utilizan cookies para la sesión, elimina la cookie de sesión.
Destruye la sesión.
Retorno: No devuelve ningún valor.
*/
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

/*
3. auth_user(): ?array
Descripción: Devuelve los datos del usuario actualmente autenticado.
Funcionamiento:
Retorna el array de usuario almacenado en la sesión, o null si no hay un usuario autenticado.
Retorno: Un array con los datos del usuario o null.
*/
function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/*
4. auth_check(): bool
Descripción: Verifica si hay un usuario autenticado en la sesión.
Funcionamiento:
Comprueba si la variable de sesión user está establecida.
Retorno: Devuelve true si el usuario está autenticado, de lo contrario, false.
*/
function auth_check(): bool
{
    return isset($_SESSION['user']);
}

/*
5. auth_require_login(): void
Descripción: Redirige al usuario a la página de inicio de sesión si no está autenticado.
Funcionamiento:
Llama a auth_check() para verificar la autenticación.
Si el usuario no está autenticado, redirige a la página de inicio de sesión con la URL de la página actual como parámetro next.
Retorno: No devuelve ningún valor.
*/
function auth_require_login(): void
{
    if (!auth_check()) {
        header('Location: /intranet-CEPESP/modules/auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/*
6. auth_has_role(string $roleName): bool
Descripción: Verifica si el usuario autenticado tiene un rol específico.
Funcionamiento:
Obtiene los datos del usuario actual.
Compara el rol del usuario con el rol proporcionado (sin importar mayúsculas/minúsculas).
Retorno: Devuelve true si el usuario tiene el rol especificado, de lo contrario, false.
*/
/** Revisa contra roles.name (ej. 'admin', 'inventarios', 'rrhh') */
function auth_has_role(string $roleName): bool
{
    $u = auth_user();
    if (!$u) return false;
    return strtolower($u['role'] ?? '') === strtolower($roleName);
}

// === Compatibilidad con funciones usadas por nuevos módulos ===

/*
7. Compatibilidad con nuevas funciones
auth_is_logged_in(): bool
Descripción: Retorna true si hay una sesión activa.
*/
// Retorna true si hay sesión activa
if (!function_exists('auth_is_logged_in')) {
    function auth_is_logged_in(): bool {
        return !empty($_SESSION['user']);
    }
}

/*
8. Compatibilidad con nuevas funciones
auth_current_user(): array
Descripción: Devuelve los datos del usuario actual o un array por defecto si no hay un usuario autenticado.
*/
// Devuelve los datos del usuario actual
if (!function_exists('auth_current_user')) {
    function auth_current_user(): array {
        return $_SESSION['user'] ?? ['email' => 'sistema'];
    }
}

