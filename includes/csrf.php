<?php
// includes/csrf.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
1. csrf_token_get(): string
Descripción: Genera y devuelve un token CSRF único para la sesión actual.
Funcionamiento:
Verifica si el token CSRF ya está almacenado en la sesión.
Si no existe, genera un nuevo token utilizando random_bytes() y lo convierte a hexadecimal.
Almacena el token en la sesión para su uso posterior.
Retorno: Devuelve el token CSRF como una cadena.
*/
function csrf_token_get(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

/*
2. csrf_field(): string
Descripción: Crea un campo oculto HTML que contiene el token CSRF.
Funcionamiento:
Llama a csrf_token_get() para obtener el token CSRF.
Escapa el token utilizando htmlspecialchars() para prevenir inyecciones de HTML.
Devuelve un elemento <input> de tipo oculto que incluye el token como valor.
Retorno: Devuelve una cadena que representa el campo oculto HTML.
*/
function csrf_field(): string
{
    $t = htmlspecialchars(csrf_token_get(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $t . '">';
}

/*
3. csrf_validate(): bool
Descripción: Valida el token CSRF recibido en una solicitud.
Funcionamiento:
Inicia la sesión si no está activa.
Recupera el token CSRF enviado en la solicitud (ya sea por POST o GET).
Compara el token enviado con el token almacenado en la sesión utilizando hash_equals() para prevenir ataques de temporización.
Retorno: Devuelve true si el token es válido, de lo contrario, devuelve false.
*/
function csrf_validate(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sent = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    return is_string($sent) && hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}
