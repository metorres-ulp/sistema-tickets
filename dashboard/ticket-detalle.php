<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();

$pageTitle = 'Detalle de Ticket';
$user      = current_user();
$pdo       = db();

// ─── Obtener ticket ──────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /dashboard/tickets.php');
    exit;
}

$stmtT = $pdo->prepare("
    SELECT t.*,
           GROUP_CONCAT(DISTINCT ar.nombre ORDER BY ar.nombre SEPARATOR ', ') AS areas_nombres,
           GROUP_CONCAT(DISTINCT ar.id ORDER BY ar.id SEPARATOR ',') AS areas_ids,
           GROUP_CONCAT(DISTINCT tt.nombre ORDER BY tt.nombre SEPARATOR ', ') AS tipos_nombres,
           CONCAT(ua.nombre,' ',ua.apellido) AS asignado_nombre,
           CONCAT(up.nombre,' ',up.apellido) AS asignado_por_nombre
    FROM tickets t
    LEFT JOIN ticket_areas ta ON ta.ticket_id = t.id
    LEFT JOIN areas ar ON ar.id = ta.area_id
    LEFT JOIN ticket_tipos_trabajo ttt ON ttt.ticket_id = t.id
    LEFT JOIN tipos_trabajo tt ON tt.id = ttt.tipo_trabajo_id
    LEFT JOIN usuarios ua ON ua.id = t.asignado_a
    LEFT JOIN usuarios up ON up.id = t.asignado_por
    WHERE t.id = ?
    GROUP BY t.id
");
$stmtT->execute([$id]);
$ticket = $stmtT->fetch();

if (!$ticket) {
    flash_set('error', 'Ticket no encontrado.');
    header('Location: /dashboard/tickets.php');
    exit;
}

// Restricción de acceso: usuario solo puede ver sus tickets
if ($user['rol'] === ROL_USUARIO && $ticket['asignado_a'] != $user['id']) {
    flash_set('error', 'No tienes permiso para ver este ticket.');
    header('Location: /dashboard/tickets.php');
    exit;
}

// Archivos adjuntos
$archivos = $pdo->prepare("SELECT * FROM ticket_archivos WHERE ticket_id = ? ORDER BY created_at");
$archivos->execute([$id]);
$archivos = $archivos->fetchAll();

// Links de referencia
$links = $pdo->prepare("SELECT * FROM links_referencia WHERE ticket_id = ? ORDER BY id");
$links->execute([$id]);
$links = $links->fetchAll();

// Historial
$historial = $pdo->prepare("
    SELECT h.*, CONCAT(u.nombre,' ',u.apellido) AS usuario_nombre
    FROM ticket_historial h
    LEFT JOIN usuarios u ON u.id = h.usuario_id
    WHERE h.ticket_id = ?
    ORDER BY h.created_at ASC
");
$historial->execute([$id]);
$historial = $historial->fetchAll();

// ─── Cambio de estado ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_estado') {
    if (!csrf_verify()) {
        flash_set('error', 'Token de seguridad inválido.');
        header("Location: /dashboard/ticket-detalle.php?id={$id}");
        exit;
    }
    $nuevoEstado = $_POST['nuevo_estado'] ?? '';
    $comentario  = trim($_POST['comentario'] ?? '');
    $estadoActual = $ticket['estado'];

    // Validar si el estado es permitido para este usuario
    $flujo   = FLUJO_ESTADOS[$estadoActual] ?? [];
    $esAdmin = is_admin_or_referente();

    if (!in_array($nuevoEstado, array_keys(ESTADOS_TICKET))) {
        flash_set('error', 'Estado inválido.');
    } elseif (!$esAdmin && !in_array($nuevoEstado, $flujo)) {
        flash_set('error', 'No puedes cambiar a ese estado.');
    } elseif ($nuevoEstado === $estadoActual) {
        flash_set('warning', 'El ticket ya está en ese estado.');
    } else {
        try {
            $fechas = [];
            if ($nuevoEstado === 'iniciada') $fechas['fecha_inicio = NOW(),'] = '';
            if ($nuevoEstado === 'resuelta') $fechas['fecha_resolucion = NOW(),'] = '';
            $fechasSql = implode(' ', array_keys($fechas));

            $pdo->prepare("UPDATE tickets SET estado = ?, {$fechasSql} updated_at = NOW() WHERE id = ?")
                ->execute([$nuevoEstado, $id]);

            $pdo->prepare("INSERT INTO ticket_historial (ticket_id, usuario_id, estado_anterior, estado_nuevo, accion, comentario) VALUES (?,?,?,?,'cambio_estado',?)")
                ->execute([$id, $user['id'], $estadoActual, $nuevoEstado, $comentario ?: null]);

            // Notificar
            if ($ticket['asignado_a'] && $ticket['asignado_a'] != $user['id']) {
                crear_notificacion(
                    (int)$ticket['asignado_a'], $id, 'cambio_estado',
                    "El ticket {$ticket['numero']} cambió de estado a: " . (ESTADOS_TICKET[$nuevoEstado]['label'] ?? $nuevoEstado)
                );
            }

            // Email si resuelto
            if ($nuevoEstado === 'resuelta' && !$ticket['email_enviado']) {
                if (enviar_email_resolucion($ticket)) {
                    $pdo->prepare("UPDATE tickets SET email_enviado=1 WHERE id=?")->execute([$id]);
                }
            }

            flash_set('success', 'Estado actualizado a: ' . (ESTADOS_TICKET[$nuevoEstado]['label'] ?? $nuevoEstado));
        } catch (Exception $e) {
            flash_set('error', 'Error al cambiar el estado.');
        }
        header("Location: /dashboard/ticket-detalle.php?id={$id}");
        exit;
    }
}

// ─── Agregar comentario al historial ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comentario') {
    if (csrf_verify()) {
        $comentario = trim($_POST['comentario'] ?? '');
        if (!empty($comentario)) {
            $pdo->prepare("INSERT INTO ticket_historial (ticket_id, usuario_id, accion, comentario) VALUES (?,?,'comentario',?)")
                ->execute([$id, $user['id'], $comentario]);
            flash_set('success', 'Comentario agregado.');
        }
    }
    header("Location: /dashboard/ticket-detalle.php?id={$id}");
    exit;
}

// Estados posibles para cambio
$estadoActual = $ticket['estado'];
$flujoEstados = FLUJO_ESTADOS[$estadoActual] ?? [];
// Admin/referente pueden ir a cualquier estado excepto el actual
$estadosDisponibles = [];
if (is_admin_or_referente()) {
    foreach (ESTADOS_TICKET as $k => $v) {
        if ($k !== $estadoActual) $estadosDisponibles[$k] = $v;
    }
} else {
    foreach ($flujoEstados as $k) {
        $estadosDisponibles[$k] = ESTADOS_TICKET[$k];
    }
}

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard/tickets.php">Tickets</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($ticket['numero']) ?></li>
            </ol>
        </nav>
        <h1 class="d-flex align-items-center gap-2">
            <span class="font-monospace"><?= htmlspecialchars($ticket['numero']) ?></span>
            <?= badge_estado($estadoActual) ?>
            <?= badge_prioridad($ticket['prioridad']) ?>
            <?php if ($ticket['urgente']): ?><span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Urgente</span><?php endif; ?>
        </h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (is_admin_or_referente() && in_array($estadoActual, ['ingresada','asignada'])): ?>
        <a href="<?= APP_URL ?>/dashboard/ticket-asignar.php?id=<?= $id ?>" class="btn btn-success btn-sm">
            <i class="bi bi-person-plus me-1"></i>Asignar
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/dashboard/tickets.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?= flash_render() ?>

<div class="row g-4">
    <!-- Columna principal -->
    <div class="col-lg-8">

        <!-- Datos del solicitante -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-person me-2"></i>Datos del Solicitante</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">NOMBRE COMPLETO</p>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($ticket['solicitante_nombre'] . ' ' . $ticket['solicitante_apellido']) ?></p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">ÁREA SOLICITANTE</p>
                        <p class="mb-0"><?= htmlspecialchars($ticket['solicitante_area']) ?></p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">EMAIL</p>
                        <p class="mb-0"><a href="mailto:<?= htmlspecialchars($ticket['solicitante_email']) ?>"><?= htmlspecialchars($ticket['solicitante_email']) ?></a></p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">TELÉFONO</p>
                        <p class="mb-0"><?= htmlspecialchars($ticket['solicitante_telefono'] ?: '-') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle del requerimiento -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-file-text me-2"></i>Descripción del Requerimiento</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">ÁREAS DE COMUNICACIÓN</p>
                        <p class="mb-0"><?= htmlspecialchars($ticket['areas_nombres'] ?: '-') ?></p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">TIPOS DE TRABAJO</p>
                        <p class="mb-0"><?= htmlspecialchars($ticket['tipos_nombres'] ?: '-') ?></p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">FECHA ENTREGA SOLICITADA</p>
                        <p class="mb-0"><?= format_fecha($ticket['fecha_entrega_solicitada'] ?? '') ?></p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">FECHA DE SOLICITUD</p>
                        <p class="mb-0"><?= format_fecha_hora($ticket['created_at']) ?></p>
                    </div>
                </div>
                <p class="text-muted small mb-1">DESCRIPCIÓN</p>
                <div class="bg-light rounded p-3">
                    <?= nl2br(htmlspecialchars($ticket['descripcion'])) ?>
                </div>
                <?php if (!empty($ticket['observaciones'])): ?>
                <p class="text-muted small mb-1 mt-3">OBSERVACIONES</p>
                <div class="bg-light rounded p-3">
                    <?= nl2br(htmlspecialchars($ticket['observaciones'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Archivos adjuntos -->
        <?php if (!empty($archivos)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-paperclip me-2"></i>Archivos Adjuntos (<?= count($archivos) ?>)</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <?php foreach ($archivos as $arch): ?>
                    <div class="col-sm-6">
                        <a href="<?= APP_URL ?>/uploads/<?= htmlspecialchars($arch['nombre_almacenado']) ?>"
                           class="d-flex align-items-center gap-2 p-2 border rounded text-decoration-none text-body hover-bg-light" target="_blank">
                            <i class="bi bi-file-earmark fs-4 text-primary"></i>
                            <div class="overflow-hidden">
                                <div class="text-truncate small fw-semibold"><?= htmlspecialchars($arch['nombre_original']) ?></div>
                                <div class="text-muted" style="font-size:.72rem"><?= formatear_bytes((int)$arch['tamanio']) ?></div>
                            </div>
                            <i class="bi bi-download ms-auto text-muted small"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Links de referencia -->
        <?php if (!empty($links)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-link-45deg me-2"></i>Links de Referencia</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <?php foreach ($links as $lnk): ?>
                    <li class="mb-2">
                        <a href="<?= htmlspecialchars($lnk['url']) ?>" target="_blank" rel="noopener noreferrer" class="d-flex align-items-center gap-2">
                            <i class="bi bi-box-arrow-up-right text-muted small"></i>
                            <span class="small"><?= htmlspecialchars($lnk['descripcion'] ?: $lnk['url']) ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Historial / Comentarios -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-clock-history me-2"></i>Historial</h6>
            </div>
            <div class="card-body">
                <?php if (empty($historial)): ?>
                <p class="text-muted text-center small">Sin actividad registrada.</p>
                <?php else: ?>
                <ul class="timeline">
                    <?php foreach ($historial as $h): ?>
                    <li class="timeline-item">
                        <div class="timeline-icon bg-primary-subtle text-primary">
                            <i class="bi <?= $h['accion'] === 'comentario' ? 'bi-chat-left-text-fill' : 'bi-arrow-right-circle-fill' ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <strong><?= htmlspecialchars($h['usuario_nombre'] ?? 'Sistema') ?></strong>
                            &mdash;
                            <?php if ($h['accion'] === 'cambio_estado'): ?>
                            Cambió estado <?php if ($h['estado_anterior']): ?>de <?= badge_estado($h['estado_anterior']) ?><?php endif; ?>
                            a <?= badge_estado($h['estado_nuevo']) ?>
                            <?php elseif ($h['accion'] === 'creacion'): ?>
                            Ticket creado &rarr; <?= badge_estado($h['estado_nuevo']) ?>
                            <?php elseif ($h['accion'] === 'asignacion'): ?>
                            Ticket asignado
                            <?php else: ?>
                            <?= htmlspecialchars($h['accion']) ?>
                            <?php endif; ?>
                            <?php if (!empty($h['comentario'])): ?>
                            <div class="mt-1 fst-italic small text-secondary">"<?= htmlspecialchars($h['comentario']) ?>"</div>
                            <?php endif; ?>
                            <br><span class="ts"><?= format_fecha_hora($h['created_at']) ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <!-- Agregar comentario -->
                <hr>
                <form method="POST" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="comentario">
                    <label class="form-label fw-semibold small">Agregar comentario</label>
                    <div class="d-flex gap-2">
                        <textarea name="comentario" class="form-control form-control-sm" rows="2"
                            placeholder="Escribe un comentario sobre este ticket..." required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm align-self-end">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- Columna lateral -->
    <div class="col-lg-4">

        <!-- Cambiar estado -->
        <?php if (!empty($estadosDisponibles)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-arrow-repeat me-2"></i>Cambiar Estado</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Estado actual: <?= badge_estado($estadoActual) ?></p>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cambiar_estado">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nuevo estado</label>
                        <select name="nuevo_estado" class="form-select form-select-sm" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($estadosDisponibles as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Comentario (opcional)</label>
                        <textarea name="comentario" class="form-control form-control-sm" rows="2"
                            placeholder="Motivo del cambio de estado..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-check2 me-1"></i>Actualizar Estado
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Asignación -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-person-check me-2"></i>Asignación</h6>
            </div>
            <div class="card-body">
                <?php if ($ticket['asignado_nombre']): ?>
                <p class="small mb-1 text-muted">ASIGNADO A</p>
                <p class="fw-semibold mb-1"><?= htmlspecialchars($ticket['asignado_nombre']) ?></p>
                <p class="small text-muted mb-0">
                    <?= $ticket['asignado_por_nombre'] ? 'Por ' . htmlspecialchars($ticket['asignado_por_nombre']) : '' ?>
                    <?= $ticket['fecha_asignacion'] ? '&mdash; ' . format_fecha_hora($ticket['fecha_asignacion']) : '' ?>
                </p>
                <?php if (is_admin_or_referente()): ?>
                <a href="<?= APP_URL ?>/dashboard/ticket-asignar.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm mt-2 w-100">
                    <i class="bi bi-arrow-repeat me-1"></i>Reasignar
                </a>
                <?php endif; ?>
                <?php else: ?>
                <p class="text-muted small fst-italic">Sin asignar</p>
                <?php if (is_admin_or_referente()): ?>
                <a href="<?= APP_URL ?>/dashboard/ticket-asignar.php?id=<?= $id ?>" class="btn btn-success btn-sm w-100">
                    <i class="bi bi-person-plus me-1"></i>Asignar ticket
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fechas y metadata -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-info-circle me-2"></i>Información</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-5 text-muted">Ingresado</dt>
                    <dd class="col-sm-7"><?= format_fecha_hora($ticket['created_at']) ?></dd>
                    <?php if (!empty($ticket['fecha_asignacion'])): ?>
                    <dt class="col-sm-5 text-muted">Asignado</dt>
                    <dd class="col-sm-7"><?= format_fecha_hora($ticket['fecha_asignacion']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($ticket['fecha_inicio'])): ?>
                    <dt class="col-sm-5 text-muted">Iniciado</dt>
                    <dd class="col-sm-7"><?= format_fecha_hora($ticket['fecha_inicio']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($ticket['fecha_resolucion'])): ?>
                    <dt class="col-sm-5 text-muted">Resuelto</dt>
                    <dd class="col-sm-7"><?= format_fecha_hora($ticket['fecha_resolucion']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($ticket['fecha_entrega_solicitada'])): ?>
                    <dt class="col-sm-5 text-muted">Entrega solic.</dt>
                    <dd class="col-sm-7"><?= format_fecha($ticket['fecha_entrega_solicitada']) ?></dd>
                    <?php endif; ?>
                    <dt class="col-sm-5 text-muted">Email env.</dt>
                    <dd class="col-sm-7"><?= $ticket['email_enviado'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?></dd>
                </dl>
            </div>
        </div>

    </div><!-- /col-lg-4 -->
</div><!-- /row -->

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
