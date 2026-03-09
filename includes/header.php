<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <?= $extraHead ?? '' ?>
</head>
<body class="bg-light">

<!-- Navbar pública -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= APP_URL ?>/">
            <i class="bi bi-ticket-detailed-fill me-2 fs-4"></i>
            <span>Tickets ULP</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPublic">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navPublic">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link<?= (basename($_SERVER['PHP_SELF']) === 'nuevo-requerimiento.php') ? ' active' : '' ?>" href="<?= APP_URL ?>/nuevo-requerimiento.php">
                        <i class="bi bi-plus-circle me-1"></i>Nuevo Requerimiento
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= (basename($_SERVER['PHP_SELF']) === 'buscar-ticket.php') ? ' active' : '' ?>" href="<?= APP_URL ?>/buscar-ticket.php">
                        <i class="bi bi-search me-1"></i>Buscar Ticket
                    </a>
                </li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-outline-light btn-sm" href="<?= APP_URL ?>/login.php">
                        <i class="bi bi-lock me-1"></i>Acceso Privado
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="py-4">
    <div class="container">
