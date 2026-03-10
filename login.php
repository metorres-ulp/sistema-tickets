<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

session_start_secure();

// Si ya está logueado, redirigir al dashboard
if (is_logged_in()) {
    header('Location: /dashboard/index.php');
    exit;
}

$error    = '';
$pageTitle = 'Acceso al Panel';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Por favor completa todos los campos.';
        } else {
            $result = login($email, $password);
            if ($result['success']) {
                $redirect = $_GET['redirect'] ?? '/dashboard/index.php';
                // Sanitize redirect URL to prevent open redirect
                if (!str_starts_with($redirect, '/')) $redirect = '/dashboard/index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle . ' | ' . APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>
        body { background: linear-gradient(135deg, #0056b3 0%, #003d82 100%); min-height: 100vh; display: flex; align-items: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-9 col-md-7 col-lg-5">

            <div class="text-center mb-4">
                <i class="bi bi-ticket-detailed-fill text-white" style="font-size: 3rem;"></i>
                <h2 class="text-white fw-bold mt-2">Sistema de Tickets</h2>
                <p class="text-white-50">Universidad Nacional de La Punta</p>
            </div>

            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h4 class="fw-bold mb-4 text-center">Iniciar Sesión</h4>

                    <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email institucional</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    placeholder="usuario@ulp.edu.ar" required autofocus autocomplete="email">
                                <div class="invalid-feedback">Ingresa tu email.</div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password"
                                    required autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePass" title="Mostrar/ocultar">
                                    <i class="bi bi-eye" id="togglePassIcon"></i>
                                </button>
                                <div class="invalid-feedback">Ingresa tu contraseña.</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
                        </button>
                    </form>

                    <hr class="my-4">
                    <div class="text-center">
                        <a href="<?= APP_URL ?>/nuevo-requerimiento.php" class="text-decoration-none text-muted small">
                            <i class="bi bi-arrow-left me-1"></i>Volver al formulario público
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePass').addEventListener('click', function() {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('togglePassIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
    }
});
// Bootstrap form validation
document.querySelector('form.needs-validation').addEventListener('submit', function(e) {
    if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
    this.classList.add('was-validated');
});
</script>
</body>
</html>
