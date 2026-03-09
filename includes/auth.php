<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ============================================================
// Gestión de sesión
// ============================================================

function session_start_secure(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function login(string $email, string $password): array {
    try {
        $stmt = db()->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'] . ' ' . $user['apellido'];
            $_SESSION['user_email']  = $user['email'];
            $_SESSION['user_rol']    = $user['rol'];
            $_SESSION['user_area']   = $user['area_id'];
            $_SESSION['login_time']  = time();
            // Actualizar último login
            db()->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")
                ->execute([$user['id']]);
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Email o contraseña incorrectos.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error al intentar iniciar sesión.'];
    }
}

function logout(): void {
    session_start_secure();
    $_SESSION = [];
    session_destroy();
}

function is_logged_in(): bool {
    session_start_secure();
    return isset($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'     => $_SESSION['user_id'],
        'nombre' => $_SESSION['user_nombre'],
        'email'  => $_SESSION['user_email'],
        'rol'    => $_SESSION['user_rol'],
        'area'   => $_SESSION['user_area'],
    ];
}

function require_login(string $redirect = '/login.php'): void {
    if (!is_logged_in()) {
        $current = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$redirect}?redirect={$current}");
        exit;
    }
}

function require_role(array $roles, string $redirect = '/dashboard/index.php'): void {
    require_login();
    $user = current_user();
    if (!in_array($user['rol'], $roles)) {
        $_SESSION['flash_error'] = 'No tienes permisos para acceder a esa sección.';
        header("Location: {$redirect}");
        exit;
    }
}

function is_admin(): bool {
    $u = current_user();
    return $u && $u['rol'] === ROL_ADMIN;
}

function is_referente(): bool {
    $u = current_user();
    return $u && $u['rol'] === ROL_REFERENTE;
}

function is_admin_or_referente(): bool {
    $u = current_user();
    return $u && in_array($u['rol'], [ROL_ADMIN, ROL_REFERENTE]);
}
