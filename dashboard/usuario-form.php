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

$pageTitle = $isEdit ? 'Editar Usuario' : 'Nuevo Usuario';
$errors    = [];
$data      = [
    'nombre'   => '',
    'apellido' => '',
    'email'    => '',
    'rol'      => 'usuario',
    'area_id'  => '',
    'activo'   => 1,
];

// Cargar datos si es edición
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
    $stmt->execute([$editId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash_set('error', 'Usuario no encontrado.');
        header('Location: /dashboard/usuarios.php');
        exit;
    }
    $data = array_merge($data, $existing);
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $input = sanitize_input($_POST, ['nombre','apellido','email','rol','area_id']);
        $password    = $_POST['password'] ?? '';
        $passConfirm = $_POST['password_confirm'] ?? '';

        if (empty($input['nombre']))   $errors[] = 'El nombre es obligatorio.';
        if (empty($input['apellido'])) $errors[] = 'El apellido es obligatorio.';
        if (empty($input['email']))    $errors[] = 'El email es obligatorio.';
        elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido.';
        if (!in_array($input['rol'], [ROL_ADMIN, ROL_REFERENTE, ROL_USUARIO])) $errors[] = 'Rol inválido.';

        // Verificar email único
        if (empty($errors)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email=? AND id!=?");
            $stmtCheck->execute([$input['email'], $editId]);
            if ($stmtCheck->fetch()) $errors[] = 'Ya existe un usuario con ese email.';
        }

        // Validar contraseña solo si se ingresa o es nuevo
        if (!$isEdit && empty($password)) {
            $errors[] = 'La contraseña es obligatoria para usuarios nuevos.';
        }
        if (!empty($password)) {
            if (strlen($password) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
            if ($password !== $passConfirm) $errors[] = 'Las contraseñas no coinciden.';
        }

        if (empty($errors)) {
            try {
                $areaId = !empty($input['area_id']) ? (int)$input['area_id'] : null;
                $activo = isset($_POST['activo']) ? 1 : 0;

                if ($isEdit) {
                    $sql = "UPDATE usuarios SET nombre=?, apellido=?, email=?, rol=?, area_id=?, activo=?, updated_at=NOW()";
                    $params = [$input['nombre'], $input['apellido'], $input['email'], $input['rol'], $areaId, $activo];
                    if (!empty($password)) {
                        $sql .= ", password=?";
                        $params[] = password_hash($password, PASSWORD_BCRYPT);
                    }
                    $sql .= " WHERE id=?";
                    $params[] = $editId;
                    $pdo->prepare($sql)->execute($params);
                    flash_set('success', 'Usuario actualizado correctamente.');
                } else {
                    $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, password, rol, area_id, activo) VALUES (?,?,?,?,?,?,?)")
                        ->execute([
                            $input['nombre'],
                            $input['apellido'],
                            $input['email'],
                            password_hash($password, PASSWORD_BCRYPT),
                            $input['rol'],
                            $areaId,
                            1,
                        ]);
                    flash_set('success', 'Usuario creado correctamente.');
                }
                header('Location: /dashboard/usuarios.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Error al guardar el usuario.';
            }
        }

        // Llenar datos del POST si hay errores
        $data = array_merge($data, $input);
    }
}

$areas = $pdo->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard/usuarios.php">Usuarios</a></li>
            <li class="breadcrumb-item active"><?= $isEdit ? 'Editar' : 'Nuevo' ?></li>
        </ol>
    </nav>
    <h1><i class="bi bi-person<?= $isEdit ? '-gear' : '-plus' ?> me-2 text-primary"></i><?= $pageTitle ?></h1>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required maxlength="100"
                                value="<?= htmlspecialchars($data['nombre']) ?>">
                            <div class="invalid-feedback">Obligatorio.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Apellido *</label>
                            <input type="text" name="apellido" class="form-control" required maxlength="100"
                                value="<?= htmlspecialchars($data['apellido']) ?>">
                            <div class="invalid-feedback">Obligatorio.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email *</label>
                        <input type="email" name="email" class="form-control" required maxlength="150"
                            value="<?= htmlspecialchars($data['email']) ?>">
                        <div class="invalid-feedback">Email válido requerido.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rol *</label>
                            <select name="rol" class="form-select" required>
                                <option value="usuario"   <?= $data['rol'] === 'usuario'   ? 'selected' : '' ?>>Usuario</option>
                                <option value="referente" <?= $data['rol'] === 'referente' ? 'selected' : '' ?>>Referente</option>
                                <option value="admin"     <?= $data['rol'] === 'admin'     ? 'selected' : '' ?>>Administrador</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Área</label>
                            <select name="area_id" class="form-select">
                                <option value="">-- Sin área --</option>
                                <?php foreach ($areas as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= $data['area_id'] == $a['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contraseña <?= $isEdit ? '' : '*' ?></label>
                            <?php if ($isEdit): ?>
                            <p class="form-text text-muted small mb-1">Dejar vacío para no cambiar</p>
                            <?php endif; ?>
                            <input type="password" name="password" class="form-control"
                                <?= !$isEdit ? 'required' : '' ?> minlength="8" autocomplete="new-password">
                            <div class="invalid-feedback">Mínimo 8 caracteres.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirmar Contraseña</label>
                            <input type="password" name="password_confirm" class="form-control"
                                autocomplete="new-password">
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1"
                                <?= $data['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Usuario activo</label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 pt-2 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Guardar Cambios' : 'Crear Usuario' ?>
                        </button>
                        <a href="<?= APP_URL ?>/dashboard/usuarios.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
