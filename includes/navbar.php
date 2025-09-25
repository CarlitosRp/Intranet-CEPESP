<?php
// includes/navbar.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php'; // para BASE_URL

$u = auth_user();
$isLogged = auth_check();

function nav_active(string $needle): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return (stripos($uri, $needle) !== false) ? 'active' : '';
}

// Roles con permiso “operativo”
$canAdminUniformes = auth_has_role('admin') || auth_has_role('inventarios') || auth_has_role('almacen');

// Atajos de URL
$BASE = rtrim(BASE_URL, '/');
$URL_CATALOGO = $BASE . '/modules/uniformes/catalogo.php';
$URL_LISTADO  = $BASE . '/modules/uniformes/index.php';
$URL_LOGIN    = $BASE . '/modules/auth/login.php';
$URL_LOGOUT   = $BASE . '/modules/auth/logout.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= htmlspecialchars($URL_CATALOGO) ?>">Intranet CEPESP</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link <?= nav_active('catalogo.php') ?>" href="<?= htmlspecialchars($URL_CATALOGO) ?>">
                        Catálogo
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= nav_active('modules/uniformes/index.php') ?>" href="<?= htmlspecialchars($URL_LISTADO) ?>">
                        Listado
                    </a>
                </li>

                <?php if ($canAdminUniformes): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= (nav_active('editar.php') ? 'active' : '') ?>" href="#" data-bs-toggle="dropdown">
                            Administración
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <h6 class="dropdown-header">Uniformes</h6>
                            </li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($URL_LISTADO) ?>">Gestionar catálogo</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($BASE . '/modules/inventario/existencias/index.php') ?>">Existencias</a></li>

                            <!-- Páginas futuras -->
                            <li><a class="dropdown-item disabled" href="#">Usuarios (pronto)</a></li>
                            <li><a class="dropdown-item disabled" href="#">Roles (pronto)</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

            </ul>

            <ul class="navbar-nav ms-auto">
                <?php if ($isLogged): ?>
                    <li class="nav-item">
                        <span class="navbar-text me-2">
                            👋 <?= htmlspecialchars($u['name'] ?? $u['email'] ?? 'Usuario') ?>
                            <small class="text-muted">(<?= htmlspecialchars($u['role'] ?? '') ?>)</small>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm" href="<?= htmlspecialchars($URL_LOGOUT) ?>">Salir</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($URL_LOGIN) ?>">Entrar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>