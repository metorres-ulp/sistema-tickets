<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();
require_role([ROL_ADMIN]);

$pageTitle = 'Áreas';
$pdo       = db();

// Eliminar área
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) { flash_set('error', 'Token inválido.'); header('Location: /dashboard/areas.php'); exit; }
    $delId = (int)($_POST['id'] ?? 0);
    if ($_POST['action'] === 'eliminar' && $delId > 0) {
        $pdo->prepare("UPDATE areas SET activo=0 WHERE id=?")->execute([$delId]);
        flash_set('success', 'Área desactivada.');
    } elseif ($_POST['action'] === 'activar' && $delId > 0) {
        $pdo->prepare("UPDATE areas SET activo=1 WHERE id=?")->execute([$delId]);
        flash_set('success', 'Área activada.');
    }
    header('Location: /dashboard/areas.php');
    exit;
}

$areas = $pdo->query("SELECT a.*, COUNT(u.id) AS num_usuarios FROM areas a LEFT JOIN usuarios u ON u.area_id=a.id AND u.activo=1 GROUP BY a.id ORDER BY a.activo DESC, a.nombre")->fetchAll();

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1><i class="bi bi-diagram-3 me-2 text-primary"></i>Áreas</h1>
    <a href="<?= APP_URL ?>/dashboard/area-form.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nueva Área
    </a>
</div>

<?= flash_render() ?>

<div class="row g-3">
    <?php foreach ($areas as $area): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 <?= !$area['activo'] ? 'opacity-50' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($area['nombre']) ?></h6>
                    <span class="badge bg-<?= $area['activo'] ? 'success' : 'secondary' ?> ms-2">
                        <?= $area['activo'] ? 'Activa' : 'Inactiva' ?>
                    </span>
                </div>
                <?php if ($area['descripcion']): ?>
                <p class="text-muted small mb-2"><?= htmlspecialchars($area['descripcion']) ?></p>
                <?php endif; ?>
                <p class="text-muted small mb-0">
                    <i class="bi bi-people me-1"></i><?= $area['num_usuarios'] ?> usuario<?= $area['num_usuarios'] != 1 ? 's' : '' ?>
                </p>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <a href="<?= APP_URL ?>/dashboard/area-form.php?id=<?= $area['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>
                <a href="<?= APP_URL ?>/dashboard/tipos-trabajo.php?area=<?= $area['id'] ?>" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-tags me-1"></i>Tipos
                </a>
                <form method="POST" class="d-inline ms-auto">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $area['id'] ?>">
                    <input type="hidden" name="action" value="<?= $area['activo'] ? 'eliminar' : 'activar' ?>">
                    <button type="submit" class="btn btn-sm btn-outline-<?= $area['activo'] ? 'danger' : 'success' ?>"
                        data-confirm="<?= $area['activo'] ? '¿Desactivar esta área?' : '¿Activar esta área?' ?>">
                        <i class="bi bi-<?= $area['activo'] ? 'x-circle' : 'check-circle' ?>"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
