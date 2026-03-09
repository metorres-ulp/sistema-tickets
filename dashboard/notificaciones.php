<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();

$pageTitle = 'Notificaciones';
$user      = current_user();
$pdo       = db();

// Marcar como leídas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        flash_set('error', 'Token de seguridad inválido.');
        header('Location: /dashboard/notificaciones.php');
        exit;
    }
    if ($_POST['action'] === 'marcar_leidas') {
        $pdo->prepare("UPDATE notificaciones SET leida=1 WHERE usuario_id=?")->execute([$user['id']]);
        flash_set('success', 'Todas las notificaciones marcadas como leídas.');
    } elseif ($_POST['action'] === 'marcar_leida' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE notificaciones SET leida=1 WHERE id=? AND usuario_id=?")
            ->execute([(int)$_POST['id'], $user['id']]);
    }
    header('Location: /dashboard/notificaciones.php');
    exit;
}

// Obtener notificaciones del usuario
$notifs = $pdo->prepare("
    SELECT n.*, t.numero AS ticket_numero
    FROM notificaciones n
    LEFT JOIN tickets t ON t.id = n.ticket_id
    WHERE n.usuario_id = ?
    ORDER BY n.created_at DESC
    LIMIT 100
");
$notifs->execute([$user['id']]);
$notifs = $notifs->fetchAll();

$noLeidas = array_filter($notifs, fn($n) => !$n['leida']);

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1>
        <i class="bi bi-bell me-2 text-primary"></i>Notificaciones
        <?php if ($noLeidas): ?>
        <span class="badge bg-danger"><?= count($noLeidas) ?></span>
        <?php endif; ?>
    </h1>
    <?php if ($noLeidas): ?>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="marcar_leidas">
        <button class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-check-all me-1"></i>Marcar todas como leídas
        </button>
    </form>
    <?php endif; ?>
</div>

<?= flash_render() ?>

<?php if (empty($notifs)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
        No tienes notificaciones.
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            <?php foreach ($notifs as $n): ?>
            <?php
            $tipoIcons = [
                'nuevo_ticket'  => 'bi-ticket-detailed text-primary',
                'asignacion'    => 'bi-person-check text-success',
                'cambio_estado' => 'bi-arrow-repeat text-info',
                'resolucion'    => 'bi-check-circle text-success',
                'mensaje'       => 'bi-chat-left-text text-secondary',
            ];
            $icon = $tipoIcons[$n['tipo']] ?? 'bi-bell text-muted';
            ?>
            <li class="list-group-item d-flex align-items-start gap-3 py-3 <?= !$n['leida'] ? 'bg-blue-50' : '' ?>"
                style="<?= !$n['leida'] ? 'background:#f0f4ff' : '' ?>">
                <div class="flex-shrink-0 mt-1">
                    <i class="bi <?= $icon ?> fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <p class="mb-1 <?= !$n['leida'] ? 'fw-semibold' : '' ?>"><?= htmlspecialchars($n['mensaje']) ?></p>
                    <div class="d-flex align-items-center gap-3">
                        <small class="text-muted"><?= tiempo_transcurrido($n['created_at']) ?></small>
                        <?php if ($n['ticket_numero']): ?>
                        <a href="<?= APP_URL ?>/dashboard/ticket-detalle.php?id=<?= $n['ticket_id'] ?>" class="small text-primary text-decoration-none">
                            <i class="bi bi-ticket-detailed me-1"></i><?= htmlspecialchars($n['ticket_numero']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$n['leida']): ?>
                <form method="POST" class="flex-shrink-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="marcar_leida">
                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Marcar como leída">
                        <i class="bi bi-check"></i>
                    </button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
