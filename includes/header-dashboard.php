<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(($pageTitle ?? 'Panel') . ' | ' . APP_NAME) ?></title>
    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <?= $extraHead ?? '' ?>
</head>
<body>

<?php
$user        = current_user();
$userName    = htmlspecialchars($user['nombre'] ?? '');
$userRol     = $user['rol'] ?? '';
$notifCount  = contar_notificaciones_no_leidas((int)($user['id'] ?? 0));
$rolLabels   = ['admin' => 'Administrador', 'referente' => 'Referente', 'usuario' => 'Usuario'];
$rolLabel    = $rolLabels[$userRol] ?? $userRol;
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function nav_active(string $file, string $dir = 'dashboard'): string {
    global $currentFile, $currentDir;
    return ($currentFile === $file && $currentDir === $dir) ? ' active' : '';
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= APP_URL ?>/dashboard/index.php">
            <i class="bi bi-ticket-detailed-fill me-2 fs-5"></i>
            <span class="d-none d-md-inline">Tickets ULP</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navDash">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navDash">
            <ul class="navbar-nav me-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('index.php') ?>" href="<?= APP_URL ?>/dashboard/index.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('kanban.php') ?>" href="<?= APP_URL ?>/dashboard/kanban.php">
                        <i class="bi bi-kanban me-1"></i>Kanban
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('tickets.php') ?>" href="<?= APP_URL ?>/dashboard/tickets.php">
                        <i class="bi bi-list-task me-1"></i>Tickets
                    </a>
                </li>
                <?php if (is_admin_or_referente()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i>Gestión
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (is_admin()): ?>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/dashboard/usuarios.php"><i class="bi bi-people me-2"></i>Usuarios</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/dashboard/areas.php"><i class="bi bi-diagram-3 me-2"></i>Áreas</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/dashboard/tipos-trabajo.php"><i class="bi bi-tags me-2"></i>Tipos de Trabajo</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/dashboard/reportes.php"><i class="bi bi-bar-chart me-2"></i>Reportes</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-center">
                <!-- Notificaciones -->
                <li class="nav-item me-2">
                    <a class="nav-link position-relative" href="<?= APP_URL ?>/dashboard/notificaciones.php" title="Notificaciones">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notif-badge">
                            <?= $notifCount > 99 ? '99+' : $notifCount ?>
                        </span>
                        <?php else: ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notif-badge">0</span>
                        <?php endif; ?>
                    </a>
                </li>
                <!-- Usuario -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <div class="avatar-circle me-2">
                            <?= mb_strtoupper(mb_substr($user['nombre'] ?? 'U', 0, 1)) ?>
                        </div>
                        <span class="d-none d-md-inline"><?= $userName ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text small text-muted">
                                <i class="bi bi-shield-check me-1"></i><?= $rolLabel ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/dashboard/perfil.php"><i class="bi bi-person me-2"></i>Mi Perfil</a></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main wrapper -->
<div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar bg-white border-end shadow-sm" id="sidebar">
        <div class="p-3">
            <p class="text-muted small fw-semibold text-uppercase letter-spacing mb-2 px-1">Principal</p>
            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('index.php') ?>" href="<?= APP_URL ?>/dashboard/index.php">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('kanban.php') ?>" href="<?= APP_URL ?>/dashboard/kanban.php">
                        <i class="bi bi-kanban me-2"></i>Tablero Kanban
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('tickets.php') ?>" href="<?= APP_URL ?>/dashboard/tickets.php">
                        <i class="bi bi-list-task me-2"></i>Todos los Tickets
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('notificaciones.php') ?>" href="<?= APP_URL ?>/dashboard/notificaciones.php">
                        <i class="bi bi-bell me-2"></i>Notificaciones
                        <?php if ($notifCount > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $notifCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <?php if (is_admin_or_referente()): ?>
            <p class="text-muted small fw-semibold text-uppercase letter-spacing mb-2 px-1 mt-3">Gestión</p>
            <ul class="nav flex-column gap-1">
                <?php if (is_admin()): ?>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('usuarios.php') ?>" href="<?= APP_URL ?>/dashboard/usuarios.php">
                        <i class="bi bi-people me-2"></i>Usuarios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('areas.php') ?>" href="<?= APP_URL ?>/dashboard/areas.php">
                        <i class="bi bi-diagram-3 me-2"></i>Áreas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('tipos-trabajo.php') ?>" href="<?= APP_URL ?>/dashboard/tipos-trabajo.php">
                        <i class="bi bi-tags me-2"></i>Tipos de Trabajo
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('reportes.php') ?>" href="<?= APP_URL ?>/dashboard/reportes.php">
                        <i class="bi bi-bar-chart me-2"></i>Reportes
                    </a>
                </li>
            </ul>
            <?php endif; ?>

            <p class="text-muted small fw-semibold text-uppercase letter-spacing mb-2 px-1 mt-3">Cuenta</p>
            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('perfil.php') ?>" href="<?= APP_URL ?>/dashboard/perfil.php">
                        <i class="bi bi-person me-2"></i>Mi Perfil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="<?= APP_URL ?>/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Salir
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content flex-grow-1 p-4 bg-light">
