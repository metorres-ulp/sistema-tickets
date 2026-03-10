<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();
require_role([ROL_ADMIN]);

$pdo    = db();
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $editId > 0;
$pageTitle = $isEdit ? 'Editar Área' : 'Nueva Área';
$errors = [];
$data   = ['nombre' => '', 'descripcion' => '', 'activo' => 1];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM areas WHERE id=?");
    $stmt->execute([$editId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash_set('error', 'Área no encontrada.');
        header('Location: /dashboard/areas.php');
        exit;
    }
    $data = array_merge($data, $existing);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token inválido.';
    } else {
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $activo      = isset($_POST['activo']) ? 1 : 0;
        if (empty($nombre)) $errors[] = 'El nombre es obligatorio.';

        if (empty($errors)) {
            try {
                if ($isEdit) {
                    $pdo->prepare("UPDATE areas SET nombre=?, descripcion=?, activo=?, updated_at=NOW() WHERE id=?")
                        ->execute([$nombre, $descripcion ?: null, $activo, $editId]);
                    flash_set('success', 'Área actualizada.');
                } else {
                    $pdo->prepare("INSERT INTO areas (nombre, descripcion) VALUES (?,?)")
                        ->execute([$nombre, $descripcion ?: null]);
                    flash_set('success', 'Área creada.');
                }
                header('Location: /dashboard/areas.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Error al guardar.';
            }
        }
        $data = ['nombre' => $nombre, 'descripcion' => $descripcion, 'activo' => $activo];
    }
}

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard/areas.php">Áreas</a></li>
            <li class="breadcrumb-item active"><?= $isEdit ? 'Editar' : 'Nueva' ?></li>
        </ol>
    </nav>
    <h1><i class="bi bi-diagram-3 me-2 text-primary"></i><?= $pageTitle ?></h1>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required maxlength="100"
                            value="<?= htmlspecialchars($data['nombre']) ?>">
                        <div class="invalid-feedback">Obligatorio.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($data['descripcion'] ?? '') ?></textarea>
                    </div>
                    <?php if ($isEdit): ?>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="activo" name="activo" <?= $data['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Área activa</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Guardar Cambios' : 'Crear Área' ?>
                        </button>
                        <a href="<?= APP_URL ?>/dashboard/areas.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
