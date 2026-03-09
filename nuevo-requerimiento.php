<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

session_start();

$pageTitle   = 'Nuevo Requerimiento';
$success     = false;
$ticketNumero = null;
$errors      = [];

// ─── Cargar áreas activas con sus tipos de trabajo ───────────
$areas = db()->query("SELECT * FROM areas WHERE activo = 1 ORDER BY nombre")->fetchAll();
$tiposTrabajoByArea = [];
$allTipos = db()->query("SELECT * FROM tipos_trabajo WHERE activo = 1 ORDER BY nombre")->fetchAll();
foreach ($allTipos as $t) {
    $tiposTrabajoByArea[$t['area_id']][] = $t;
}

// ─── Procesamiento del formulario ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {
        $data = sanitize_input($_POST, [
            'nombre', 'apellido', 'area_solicitante', 'email',
            'telefono', 'descripcion', 'fecha_entrega', 'prioridad', 'observaciones'
        ]);

        // Validaciones
        if (empty($data['nombre']))          $errors[] = 'El nombre es obligatorio.';
        if (empty($data['apellido']))         $errors[] = 'El apellido es obligatorio.';
        if (empty($data['area_solicitante'])) $errors[] = 'El área solicitante es obligatoria.';
        if (empty($data['email']))            $errors[] = 'El email es obligatorio.';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido.';
        if (empty($data['descripcion']))      $errors[] = 'La descripción es obligatoria.';
        if (empty($_POST['areas']) || !is_array($_POST['areas'])) $errors[] = 'Debes seleccionar al menos un área.';

        if (empty($errors)) {
            try {
                $pdo = db();
                $pdo->beginTransaction();

                // Generar número de ticket
                $numero   = generar_numero_ticket();
                $prioridad = in_array($data['prioridad'], ['baja','normal','alta','urgente']) ? $data['prioridad'] : 'normal';
                $urgente   = $prioridad === 'urgente' ? 1 : 0;
                $fechaEntrega = !empty($data['fecha_entrega']) ? $data['fecha_entrega'] : null;

                // Insertar ticket
                $stmt = $pdo->prepare("
                    INSERT INTO tickets (numero, solicitante_nombre, solicitante_apellido,
                        solicitante_area, solicitante_email, solicitante_telefono,
                        descripcion, fecha_entrega_solicitada, urgente, prioridad, observaciones, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ingresada')
                ");
                $stmt->execute([
                    $numero,
                    $data['nombre'],
                    $data['apellido'],
                    $data['area_solicitante'],
                    $data['email'],
                    $data['telefono'] ?: null,
                    $data['descripcion'],
                    $fechaEntrega,
                    $urgente,
                    $prioridad,
                    $data['observaciones'] ?: null,
                ]);
                $ticketId = (int)$pdo->lastInsertId();

                // Áreas seleccionadas
                $stmtArea = $pdo->prepare("INSERT INTO ticket_areas (ticket_id, area_id) VALUES (?, ?)");
                $areasSeleccionadas = array_filter(array_map('intval', $_POST['areas']));
                foreach ($areasSeleccionadas as $areaId) {
                    if ($areaId > 0) $stmtArea->execute([$ticketId, $areaId]);
                }

                // Tipos de trabajo seleccionados
                if (!empty($_POST['tipos_trabajo']) && is_array($_POST['tipos_trabajo'])) {
                    $stmtTipo = $pdo->prepare("INSERT INTO ticket_tipos_trabajo (ticket_id, tipo_trabajo_id) VALUES (?, ?)");
                    foreach (array_filter(array_map('intval', $_POST['tipos_trabajo'])) as $tipoId) {
                        if ($tipoId > 0) $stmtTipo->execute([$ticketId, $tipoId]);
                    }
                }

                // Archivos adjuntos
                if (!empty($_FILES['archivos']['name'][0])) {
                    $stmtArchivo = $pdo->prepare("
                        INSERT INTO ticket_archivos (ticket_id, nombre_original, nombre_almacenado, tipo_mime, tamanio)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $fileCount = count($_FILES['archivos']['name']);
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($_FILES['archivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $singleFile = [
                            'name'     => $_FILES['archivos']['name'][$i],
                            'type'     => $_FILES['archivos']['type'][$i],
                            'tmp_name' => $_FILES['archivos']['tmp_name'][$i],
                            'error'    => $_FILES['archivos']['error'][$i],
                            'size'     => $_FILES['archivos']['size'][$i],
                        ];
                        $res = upload_archivo($singleFile);
                        if ($res['success']) {
                            $stmtArchivo->execute([
                                $ticketId,
                                $res['nombre_original'],
                                $res['nombre_almacenado'],
                                $res['tipo_mime'],
                                $res['tamanio'],
                            ]);
                        }
                    }
                }

                // Links de referencia
                if (!empty($_POST['links']) && is_array($_POST['links'])) {
                    $stmtLink = $pdo->prepare("INSERT INTO links_referencia (ticket_id, url, descripcion) VALUES (?, ?, ?)");
                    foreach ($_POST['links'] as $idx => $url) {
                        $url = trim($url);
                        if (empty($url)) continue;
                        $desc = trim($_POST['links_desc'][$idx] ?? '');
                        $stmtLink->execute([$ticketId, $url, $desc ?: null]);
                    }
                }

                // Historial
                $pdo->prepare("INSERT INTO ticket_historial (ticket_id, accion, estado_nuevo, comentario) VALUES (?, 'creacion', 'ingresada', ?)")
                    ->execute([$ticketId, 'Ticket creado desde el formulario público.']);

                // Notificar a admins y referentes
                notificar_todos_admins_referentes(
                    $ticketId,
                    'nuevo_ticket',
                    "Nuevo ticket {$numero} de {$data['nombre']} {$data['apellido']}"
                );

                $pdo->commit();
                $success      = true;
                $ticketNumero = $numero;

            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Ocurrió un error al enviar el formulario. Por favor, intenta nuevamente.';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?>
<!-- Confirmación de envío -->
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 text-center fade-in-up">
        <div class="card form-public-card p-4 p-md-5">
            <div class="mb-4">
                <div class="text-success" style="font-size: 5rem;"><i class="bi bi-check-circle-fill"></i></div>
                <h2 class="fw-bold mt-3">¡Requerimiento enviado!</h2>
                <p class="text-muted mb-3">Tu solicitud fue recibida correctamente. Guarda tu número de ticket para dar seguimiento.</p>
                <div class="alert alert-success fs-4 fw-bold letter-spacing">
                    <i class="bi bi-ticket-detailed me-2"></i><?= htmlspecialchars($ticketNumero) ?>
                </div>
                <p class="text-muted small">Puedes consultar el estado de tu ticket en cualquier momento.</p>
            </div>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="<?= APP_URL ?>/buscar-ticket.php?numero=<?= urlencode($ticketNumero) ?>" class="btn btn-primary">
                    <i class="bi bi-search me-2"></i>Ver estado
                </a>
                <a href="<?= APP_URL ?>/nuevo-requerimiento.php" class="btn btn-outline-secondary">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo requerimiento
                </a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Formulario de nuevo requerimiento -->
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-8">

        <div class="text-center mb-4">
            <h1 class="fw-bold"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Nuevo Requerimiento</h1>
            <p class="text-muted">Completa el formulario para solicitar trabajo al Área de Comunicación de la ULP.</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Por favor corrige los siguientes errores:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card form-public-card">
            <div class="card-body p-4 p-md-5">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <?= csrf_field() ?>

                    <!-- Paso 1: Datos del solicitante -->
                    <div class="form-step-title">
                        <span class="badge bg-primary rounded-pill me-1">1</span>
                        Datos del Solicitante
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="nombre">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre"
                                value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required maxlength="100">
                            <div class="invalid-feedback">El nombre es obligatorio.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="apellido">Apellido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="apellido" name="apellido"
                                value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>" required maxlength="100">
                            <div class="invalid-feedback">El apellido es obligatorio.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="area_solicitante">Área que solicita el trabajo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="area_solicitante" name="area_solicitante"
                                value="<?= htmlspecialchars($_POST['area_solicitante'] ?? '') ?>"
                                required maxlength="150" placeholder="Ej: Secretaría Académica">
                            <div class="invalid-feedback">Este campo es obligatorio.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required maxlength="150">
                            <div class="invalid-feedback">Ingresa un email válido.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="telefono">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono"
                                value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" maxlength="50"
                                placeholder="Ej: +54 266 4123456">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="prioridad">Prioridad del requerimiento</label>
                            <select class="form-select" id="prioridad" name="prioridad">
                                <option value="normal"  <?= (($_POST['prioridad'] ?? 'normal') === 'normal')  ? 'selected' : '' ?>>Normal</option>
                                <option value="baja"    <?= (($_POST['prioridad'] ?? '') === 'baja')    ? 'selected' : '' ?>>Baja</option>
                                <option value="alta"    <?= (($_POST['prioridad'] ?? '') === 'alta')    ? 'selected' : '' ?>>Alta</option>
                                <option value="urgente" <?= (($_POST['prioridad'] ?? '') === 'urgente') ? 'selected' : '' ?>>Urgente</option>
                            </select>
                        </div>
                    </div>

                    <!-- Paso 2: Áreas de trabajo -->
                    <div class="form-step-title">
                        <span class="badge bg-primary rounded-pill me-1">2</span>
                        Áreas y Tipos de Trabajo <span class="text-danger">*</span>
                    </div>
                    <p class="text-muted small mb-3">Selecciona el/los departamento(s) a los que va dirigido el pedido y el tipo de trabajo requerido.</p>

                    <div class="row g-3 mb-4">
                        <?php foreach ($areas as $area): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="area-checkbox-card <?= in_array((string)$area['id'], (array)($_POST['areas'] ?? [])) ? 'selected' : '' ?>"
                                 data-area-id="<?= $area['id'] ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <input type="checkbox" class="form-check-input mt-0" name="areas[]"
                                        value="<?= $area['id'] ?>" id="area_<?= $area['id'] ?>"
                                        <?= in_array((string)$area['id'], (array)($_POST['areas'] ?? [])) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold w-100" for="area_<?= $area['id'] ?>" style="cursor:pointer">
                                        <?= htmlspecialchars($area['nombre']) ?>
                                    </label>
                                </div>
                                <?php if (!empty($area['descripcion'])): ?>
                                <p class="text-muted small mb-0 mt-1"><?= htmlspecialchars($area['descripcion']) ?></p>
                                <?php endif; ?>
                            </div>
                            <!-- Tipos de trabajo de esta área -->
                            <?php if (!empty($tiposTrabajoByArea[$area['id']])): ?>
                            <div class="tipos-trabajo-group mt-2 ps-2 <?= !in_array((string)$area['id'], (array)($_POST['areas'] ?? [])) ? 'd-none' : '' ?>"
                                 data-area-id="<?= $area['id'] ?>">
                                <p class="text-muted small fw-semibold mb-1">Tipo de trabajo:</p>
                                <?php foreach ($tiposTrabajoByArea[$area['id']] as $tipo): ?>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" name="tipos_trabajo[]"
                                        value="<?= $tipo['id'] ?>" id="tipo_<?= $tipo['id'] ?>"
                                        <?= in_array((string)$tipo['id'], (array)($_POST['tipos_trabajo'] ?? [])) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="tipo_<?= $tipo['id'] ?>">
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paso 3: Descripción -->
                    <div class="form-step-title">
                        <span class="badge bg-primary rounded-pill me-1">3</span>
                        Descripción del Trabajo
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="descripcion">Descripción detallada <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="5" required
                            placeholder="Describe en detalle qué necesitas: objetivos, contenido, referencias, formatos, etc."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                        <div class="invalid-feedback">La descripción es obligatoria.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="fecha_entrega">Fecha de entrega solicitada</label>
                            <input type="date" class="form-control" id="fecha_entrega" name="fecha_entrega"
                                value="<?= htmlspecialchars($_POST['fecha_entrega'] ?? '') ?>"
                                min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="observaciones">Observaciones adicionales</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="2"
                                placeholder="Cualquier información adicional relevante..."><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Paso 4: Archivos -->
                    <div class="form-step-title">
                        <span class="badge bg-primary rounded-pill me-1">4</span>
                        Archivos Adjuntos
                    </div>

                    <div class="mb-3">
                        <div class="upload-zone mb-2" id="uploadZone">
                            <i class="bi bi-cloud-upload d-block mb-2"></i>
                            <p class="mb-1 fw-semibold">Arrastra archivos aquí o haz clic para seleccionar</p>
                            <p class="text-muted small mb-0">Imágenes, PDF, Word, Excel, ZIP &mdash; Máx. 10 MB por archivo</p>
                        </div>
                        <input type="file" class="d-none" id="archivos" name="archivos[]" multiple
                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
                        <div id="fileList"></div>
                    </div>

                    <!-- Links de referencia -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Links de referencia (opcional)</label>
                        <div id="linksContainer">
                            <div class="link-row d-flex gap-2 mb-2">
                                <input type="url" class="form-control form-control-sm" name="links[]" placeholder="https://ejemplo.com">
                                <input type="text" class="form-control form-control-sm" name="links_desc[]" placeholder="Descripción (opcional)">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-link"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <button type="button" id="addLink" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-plus me-1"></i>Agregar link
                        </button>
                    </div>

                    <!-- Submit -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <p class="text-muted small mb-0"><span class="text-danger">*</span> Campos obligatorios</p>
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-send me-2"></i>Enviar Requerimiento
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
