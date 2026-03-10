<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();

$pageTitle = 'Mi Perfil';
$user      = current_user();
$pdo       = db();
$errors    = [];

// Cargar datos actuales
$stmt = $pdo->prepare("SELECT u.*, a.nombre AS area_nombre FROM usuarios u LEFT JOIN areas a ON a.id=u.area_id WHERE u.id=?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

// Actualizar perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token inválido.';
    } else {
        $nombre   = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $password = $_POST['password'] ?? '';
        $passConf = $_POST['password_confirm'] ?? '';

        if (empty($nombre))   $errors[] = 'El nombre es obligatorio.';
        if (empty($apellido)) $errors[] = 'El apellido es obligatorio.';

        if (!empty($password)) {
            if (strlen($password) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
            if ($password !== $passConf) $errors[] = 'Las contraseñas no coinciden.';
        }

        if (empty($errors)) {
            try {
                $sql    = "UPDATE usuarios SET nombre=?, apellido=?, updated_at=NOW()";
                $params = [$nombre, $apellido];
                if (!empty($password)) {
                    $sql .= ", password=?";
                    $params[] = password_hash($password, PASSWORD_BCRYPT);
                }
                $sql     .= " WHERE id=?";
                $params[] = $user['id'];
                $pdo->prepare($sql)->execute($params);

                // Actualizar sesión
                $_SESSION['user_nombre'] = $nombre . ' ' . $apellido;
                flash_set('success', 'Perfil actualizado correctamente.');
                header('Location: /dashboard/perfil.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Error al actualizar el perfil.';
            }
        }
    }
}

// Estadísticas del usuario
$misTickets = $pdo->prepare("SELECT estado, COUNT(*) AS cnt FROM tickets WHERE asignado_a=? GROUP BY estado");
$misTickets->execute([$user['id']]);
$ticketStats = [];
foreach ($misTickets->fetchAll() as $row) $ticketStats[$row['estado']] = $row['cnt'];

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header">
    <h1><i class="bi bi-person-circle me-2 text-primary"></i>Mi Perfil</h1>
</div>

<?= flash_render() ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Info del usuario -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-4">
                <div class="avatar-circle mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;background:var(--ulp-primary)">
                    <?= mb_strtoupper(mb_substr($userData['nombre'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold mb-0"><?= htmlspecialchars($userData['nombre'] . ' ' . $userData['apellido']) ?></h5>
                <p class="text-muted small"><?= htmlspecialchars($userData['email']) ?></p>
                <?php $rolColors = ['admin'=>'danger','referente'=>'warning','usuario'=>'info']; ?>
                <span class="badge bg-<?= $rolColors[$userData['rol']] ?? 'secondary' ?>">
                    <?= ucfirst($userData['rol']) ?>
                </span>
                <?php if ($userData['area_nombre']): ?>
                <p class="text-muted small mt-2"><i class="bi bi-diagram-3 me-1"></i><?= htmlspecialchars($userData['area_nombre']) ?></p>
                <?php endif; ?>
                <hr>
                <p class="text-muted small mb-0">
                    <i class="bi bi-clock me-1"></i>
                    Último acceso: <?= $userData['ultimo_login'] ? format_fecha_hora($userData['ultimo_login']) : 'Nunca' ?>
                </p>
            </div>
        </div>

        <!-- Mis estadísticas -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-2"></i>Mis Estadísticas</h6>
            </div>
            <div class="card-body">
                <?php foreach (ESTADOS_TICKET as $k => $v): ?>
                <?php if (isset($ticketStats[$k]) && $ticketStats[$k] > 0): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <?= badge_estado($k) ?>
                    <span class="fw-bold"><?= $ticketStats[$k] ?></span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if (empty($ticketStats)): ?>
                <p class="text-muted small text-center">Sin tickets asignados.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formulario de edición -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-pencil me-2"></i>Editar Información</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required
                                value="<?= htmlspecialchars($userData['nombre']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Apellido *</label>
                            <input type="text" name="apellido" class="form-control" required
                                value="<?= htmlspecialchars($userData['apellido']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" disabled>
                        <div class="form-text text-muted">El email no puede modificarse desde aquí.</div>
                    </div>
                    <hr>
                    <p class="fw-semibold mb-2">Cambiar Contraseña</p>
                    <p class="text-muted small mb-3">Dejar vacío para mantener la contraseña actual.</p>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nueva Contraseña</label>
                            <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirmar</label>
                            <input type="password" name="password_confirm" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
