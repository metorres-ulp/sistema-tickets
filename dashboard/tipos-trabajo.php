<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();
require_role([ROL_ADMIN]);

$pageTitle = 'Tipos de Trabajo';
$pdo       = db();

$filtroArea = isset($_GET['area']) ? (int)$_GET['area'] : 0;

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) { flash_set('error', 'Token inválido.'); header('Location: /dashboard/tipos-trabajo.php'); exit; }
    $tid = (int)($_POST['id'] ?? 0);
    if ($_POST['action'] === 'eliminar' && $tid > 0) {
        $pdo->prepare("UPDATE tipos_trabajo SET activo=0 WHERE id=?")->execute([$tid]);
        flash_set('success', 'Tipo de trabajo desactivado.');
    } elseif ($_POST['action'] === 'activar' && $tid > 0) {
        $pdo->prepare("UPDATE tipos_trabajo SET activo=1 WHERE id=?")->execute([$tid]);
        flash_set('success', 'Tipo de trabajo activado.');
    } elseif ($_POST['action'] === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $areaId = (int)($_POST['area_id'] ?? 0);
        $desc   = trim($_POST['descripcion'] ?? '');
        if ($nombre && $areaId) {
            $pdo->prepare("INSERT INTO tipos_trabajo (area_id, nombre, descripcion) VALUES (?,?,?)")
                ->execute([$areaId, $nombre, $desc ?: null]);
            flash_set('success', 'Tipo de trabajo creado.');
        }
    } elseif ($_POST['action'] === 'editar') {
        $tid    = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        if ($tid && $nombre) {
            $pdo->prepare("UPDATE tipos_trabajo SET nombre=?, descripcion=?, updated_at=NOW() WHERE id=?")
                ->execute([$nombre, $desc ?: null, $tid]);
            flash_set('success', 'Tipo actualizado.');
        }
    }
    $qs = $filtroArea ? "?area={$filtroArea}" : '';
    header("Location: /dashboard/tipos-trabajo.php{$qs}");
    exit;
}

$areas = $pdo->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();

$sql    = "SELECT t.*, a.nombre AS area_nombre FROM tipos_trabajo t JOIN areas a ON a.id=t.area_id WHERE 1=1";
$params = [];
if ($filtroArea) { $sql .= " AND t.area_id=?"; $params[] = $filtroArea; }
$sql .= " ORDER BY a.nombre, t.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tipos = $stmt->fetchAll();

// Agrupar por área
$tiposPorArea = [];
foreach ($tipos as $t) {
    $tiposPorArea[$t['area_nombre']][] = $t;
}

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1><i class="bi bi-tags me-2 text-primary"></i>Tipos de Trabajo</h1>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevo">
        <i class="bi bi-plus-lg me-1"></i>Nuevo Tipo
    </button>
</div>

<?= flash_render() ?>

<!-- Filtro área -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <select name="area" class="form-select form-select-sm w-auto">
                <option value="">Todas las áreas</option>
                <?php foreach ($areas as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $filtroArea === $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
            <?php if ($filtroArea): ?><a href="<?= APP_URL ?>/dashboard/tipos-trabajo.php" class="btn btn-outline-secondary btn-sm">Limpiar</a><?php endif; ?>
        </form>
    </div>
</div>

<!-- Tipos agrupados por área -->
<?php if (empty($tiposPorArea)): ?>
<div class="text-center py-5 text-muted"><i class="bi bi-tags fs-1 d-block mb-2"></i>No hay tipos de trabajo.</div>
<?php else: ?>
<?php foreach ($tiposPorArea as $areaNombre => $tiposlista): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-primary text-white py-2">
        <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i><?= htmlspecialchars($areaNombre) ?></h6>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Nombre</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th class="pe-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tiposlista as $t): ?>
                <tr class="<?= !$t['activo'] ? 'opacity-50' : '' ?>">
                    <td class="ps-3 fw-semibold"><?= htmlspecialchars($t['nombre']) ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($t['descripcion'] ?? '-') ?></small></td>
                    <td><span class="badge bg-<?= $t['activo'] ? 'success' : 'secondary' ?>"><?= $t['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td class="pe-3">
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#modalEditar"
                                data-id="<?= $t['id'] ?>" data-nombre="<?= htmlspecialchars($t['nombre']) ?>"
                                data-descripcion="<?= htmlspecialchars($t['descripcion'] ?? '') ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="action" value="<?= $t['activo'] ? 'eliminar' : 'activar' ?>">
                                <?php if ($filtroArea): ?><input type="hidden" name="_area" value="<?= $filtroArea ?>"><?php endif; ?>
                                <button type="submit" class="btn btn-sm btn-outline-<?= $t['activo'] ? 'danger' : 'success' ?>">
                                    <i class="bi bi-<?= $t['activo'] ? 'x' : 'check' ?>"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Modal Nuevo -->
<div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Tipo de Trabajo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="crear">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Área *</label>
                        <select name="area_id" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $filtroArea === $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required maxlength="150" placeholder="Ej: Diseño de flyer">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Tipo de Trabajo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre *</label>
                        <input type="text" name="nombre" id="editNombre" class="form-control" required maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" id="editDescripcion" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
document.getElementById('modalEditar').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editId').value = btn.dataset.id;
    document.getElementById('editNombre').value = btn.dataset.nombre;
    document.getElementById('editDescripcion').value = btn.dataset.descripcion;
});
</script>
JS;
?>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
