<?php
require_once __DIR__ . '/../../includes/auth.php'; // usa users/roles y sessions
header('Content-Type: text/html; charset=UTF-8');

$err  = '';
$next = $_GET['next'] ?? '/intranet-CEPESP/modules/uniformes/catalogo.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if ($email === '' || $pass === '') {
        $err = 'Email y contraseña son obligatorios.';
    } else {
        // Valida contra la BD: users.password_hash y role_id → roles.name
        if (auth_login($email, $pass)) {
            header('Location: ' . $next);
            exit;
        } else {
            $err = 'Credenciales inválidas o usuario inactivo.';
        }
    }
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Intranet · Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/intranet-CEPESP/assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f6f7f9
        }

        .card {
            border-radius: 12px;
            max-width: 420px;
            margin: 10vh auto
        }
    </style>
</head>

<body>
    <div class="card shadow-sm">
        <div class="card-body">
            <h1 class="h5 mb-3 text-center">Intranet · Acceso</h1>

            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php?next=<?= urlencode($next) ?>">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@local" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button class="btn btn-primary w-100">Entrar</button>
            </form>
        </div>
    </div>
    <script src="/intranet-CEPESP/assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>