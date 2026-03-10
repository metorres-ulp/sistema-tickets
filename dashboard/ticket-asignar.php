<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();
require_role([ROL_ADMIN, ROL_REFERENTE]);

$pageTitle = 'Asignar Ticket';
$user      = current_user();
$pdo       = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /dashboard/tickets.php');
    exit;
}

$ticket = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
$ticket->execute([$id]);
$ticket = $ticket->fetch();

if (!$ticket) {
    flash_set('error', 'Ticket no encontrado.');
    header('Location: /dashboard/tickets.php');
    exit;
}

// Usuarios disponibles (si es referente, solo del su área)
if ($user['rol'] === ROL_ADMIN) {
    $usuarios = $pdo->query("SELECT u.*, a.nombre AS area_nombre FROM usuarios u LEFT JOIN areas a ON a.id=u.area_id WHERE u.activo=1 ORDER BY u.nombre")->fetchAll();
} else {
    $stmtU = $pdo->prepare("SELECT u.*, a.nombre AS area_nombre FROM usuarios u LEFT JOIN areas a ON a.id=u.area_id WHERE u.activo=1 AND u.area_id=? ORDER BY u.nombre");
    $stmtU->execute([$user['area']]);
    $usuarios = $stmtU->fetchAll();
}

// Procesar asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Token de seguridad inválido.');
        header("Location: /dashboard/ticket-asignar.php?id={$id}");
        exit;
    }
    $asignarA  = (int)$_POST['asignado_a'];
    $comentario = trim($_POST['comentario'] ?? '');

    // Validar que el usuario existe
    $stmtCheck = $pdo->prepare("SELECT id, nombre, apellido FROM usuarios WHERE id=? AND activo=1");
    $stmtCheck->execute([$asignarA]);
    $usuarioAsignado = $stmtCheck->fetch();

    if (!$usuarioAsignado) {
        flash_set('error', 'Usuario inválido.');
    } else {
        try {
            $estadoAnterior = $ticket['estado'];
            $nuevoEstado    = in_array($estadoAnterior, ['ingresada']) ? 'asignada' : $estadoAnterior;

            $pdo->prepare("
                UPDATE tickets SET asignado_a=?, asignado_por=?, fecha_asignacion=NOW(), estado=?, updated_at=NOW() WHERE id=?
            ")->execute([$asignarA, $user['id'], $nuevoEstado, $id]);

            $pdo->prepare("INSERT INTO ticket_historial (ticket_id, usuario_id, estado_anterior, estado_nuevo, accion, comentario) VALUES (?,?,?,?,'asignacion',?)")
                ->execute([$id, $user['id'], $estadoAnterior, $nuevoEstado, $comentario ?: "Asignado a {$usuarioAsignado['nombre']} {$usuarioAsignado['apellido']}"]);

            // Notificar al usuario asignado
            crear_notificacion(
                $asignarA, $id, 'asignacion',
                "Se te asignó el ticket {$ticket['numero']}"
            );

            flash_set('success', "Ticket asignado a {$usuarioAsignado['nombre']} {$usuarioAsignado['apellido']} correctamente.");
            header("Location: /dashboard/ticket-detalle.php?id={$id}");
            exit;
        } catch (Exception $e) {
            flash_set('error', 'Error al asignar el ticket.');
        }
    }
}

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard/tickets.php">Tickets</a></li>
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard/ticket-detalle.php?id=<?= $id ?>"><?= htmlspecialchars($ticket['numero']) ?></a></li>
            <li class="breadcrumb-item active">Asignar</li>
        </ol>
    </nav>
    <h1><i class="bi bi-person-plus me-2 text-primary"></i>Asignar Ticket</h1>
</div>

<?= flash_render() ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($ticket['numero']) ?></h6>
                    <?= badge_estado($ticket['estado']) ?>
                </div>
                <p class="text-muted small mb-0 mt-1"><?= htmlspecialchars(mb_strimwidth($ticket['descripcion'], 0, 100, '…')) ?></p>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Asignar a <span class="text-danger">*</span></label>
                        <select name="asignado_a" class="form-select" required>
                            <option value="">-- Seleccionar usuario --</option>
                            <!-- Asignar a sí mismo -->
                            <optgroup label="Yo mismo">
                                <option value="<?= $user['id'] ?>" <?= $ticket['asignado_a'] == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['nombre']) ?> (yo)
                                </option>
                            </optgroup>
                            <!-- Otros usuarios -->
                            <?php if (!empty($usuarios)): ?>
                            <optgroup label="Otros usuarios">
                                <?php foreach ($usuarios as $u): ?>
                                <?php if ($u['id'] == $user['id']) continue; ?>
                                <option value="<?= $u['id'] ?>" <?= $ticket['asignado_a'] == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>
                                    <?= $u['area_nombre'] ? '(' . htmlspecialchars($u['area_nombre']) . ')' : '' ?>
                                    &mdash; <?= ucfirst($u['rol']) ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                        <div class="invalid-feedback">Selecciona un usuario.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Comentario (opcional)</label>
                        <textarea name="comentario" class="form-control" rows="3"
                            placeholder="Instrucciones o comentarios para el usuario asignado..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-person-check me-2"></i>Confirmar Asignación
                        </button>
                        <a href="<?= APP_URL ?>/dashboard/ticket-detalle.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
