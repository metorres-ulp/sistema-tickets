<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();
require_role([ROL_ADMIN]);

$pageTitle = 'Gestión de Usuarios';
$pdo       = db();

// Eliminar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    if (csrf_verify()) {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId > 0 && $delId != current_user()['id']) {
            $pdo->prepare("UPDATE usuarios SET activo=0 WHERE id=?")->execute([$delId]);
            flash_set('success', 'Usuario desactivado correctamente.');
        }
    }
    header('Location: /dashboard/usuarios.php');
    exit;
}

// Reactivar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activar') {
    if (csrf_verify()) {
        $actId = (int)($_POST['id'] ?? 0);
        if ($actId > 0) {
            $pdo->prepare("UPDATE usuarios SET activo=1 WHERE id=?")->execute([$actId]);
            flash_set('success', 'Usuario activado correctamente.');
        }
    }
    header('Location: /dashboard/usuarios.php');
    exit;
}

// Lista de usuarios
$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT u.*, a.nombre AS area_nombre FROM usuarios u LEFT JOIN areas a ON a.id=u.area_id";
$params = [];
if ($busqueda) {
    $sql .= " WHERE (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ?)";
    $params = ["%$busqueda%", "%$busqueda%", "%$busqueda%"];
}
$sql .= " ORDER BY u.activo DESC, u.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1><i class="bi bi-people me-2 text-primary"></i>Usuarios</h1>
    <a href="<?= APP_URL ?>/dashboard/usuario-form.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nuevo Usuario
    </a>
</div>

<?= flash_render() ?>

<!-- Búsqueda -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por nombre o email..." value="<?= htmlspecialchars($busqueda) ?>">
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
            <?php if ($busqueda): ?><a href="<?= APP_URL ?>/dashboard/usuarios.php" class="btn btn-outline-secondary btn-sm">Limpiar</a><?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($usuarios)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-people fs-1 d-block mb-2"></i>No hay usuarios registrados.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Área</th>
                        <th>Último Acceso</th>
                        <th>Estado</th>
                        <th class="pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr class="<?= !$u['activo'] ? 'opacity-50' : '' ?>">
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="background: <?= $u['activo'] ? '#0056b3' : '#6c757d' ?>; width:34px;height:34px;font-size:.85rem">
                                    <?= mb_strtoupper(mb_substr($u['nombre'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><small><?= htmlspecialchars($u['email']) ?></small></td>
                        <td>
                            <?php $rolColors = ['admin'=>'danger','referente'=>'warning','usuario'=>'info']; ?>
                            <span class="badge bg-<?= $rolColors[$u['rol']] ?? 'secondary' ?>"><?= ucfirst($u['rol']) ?></span>
                        </td>
                        <td><small><?= htmlspecialchars($u['area_nombre'] ?? '-') ?></small></td>
                        <td><small class="text-muted"><?= $u['ultimo_login'] ? format_fecha_hora($u['ultimo_login']) : 'Nunca' ?></small></td>
                        <td>
                            <span class="badge bg-<?= $u['activo'] ? 'success' : 'secondary' ?>">
                                <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="pe-3">
                            <div class="d-flex gap-1">
                                <a href="<?= APP_URL ?>/dashboard/usuario-form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($u['id'] != current_user()['id']): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <?php if ($u['activo']): ?>
                                    <input type="hidden" name="action" value="eliminar">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar"
                                        data-confirm="¿Desactivar al usuario <?= htmlspecialchars($u['nombre']) ?>?">
                                        <i class="bi bi-person-x"></i>
                                    </button>
                                    <?php else: ?>
                                    <input type="hidden" name="action" value="activar">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Activar">
                                        <i class="bi bi-person-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
