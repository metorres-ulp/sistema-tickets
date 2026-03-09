<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Buscar Ticket';
$ticket    = null;
$numero    = '';
$searched  = false;

if (!empty($_GET['numero'])) {
    $numero   = strtoupper(trim($_GET['numero']));
    $ticket   = obtener_ticket_publico($numero);
    $searched = true;
}

// Obtener historial del ticket si existe
$historial = [];
if ($ticket) {
    $stmtH = db()->prepare("
        SELECT h.*, CONCAT(u.nombre,' ',u.apellido) AS usuario_nombre
        FROM ticket_historial h
        LEFT JOIN usuarios u ON u.id = h.usuario_id
        WHERE h.ticket_id = ?
        ORDER BY h.created_at ASC
    ");
    $stmtH->execute([$ticket['id']]);
    $historial = $stmtH->fetchAll();
}

// Orden de estados para el tracker
$estadosOrden = ['ingresada','asignada','iniciada','en_proceso','resuelta'];

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">

        <div class="text-center mb-4">
            <h1 class="fw-bold"><i class="bi bi-search text-primary me-2"></i>Consultar Estado de Ticket</h1>
            <p class="text-muted">Ingresa el número de ticket para conocer su estado actual.</p>
        </div>

        <div class="card form-public-card mb-4">
            <div class="card-body p-4">
                <form method="GET" class="needs-validation" novalidate>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="bi bi-ticket-detailed text-primary"></i></span>
                        <input type="text" class="form-control" name="numero" id="numero"
                            value="<?= htmlspecialchars($numero) ?>"
                            placeholder="Ej: TKT-202603-0001"
                            required pattern="[Tt][Kk][Tt]-\d{6}-\d{4}"
                            style="text-transform:uppercase">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search me-1"></i>Buscar
                        </button>
                        <div class="invalid-feedback">Ingresa un número de ticket válido (ej: TKT-202603-0001).</div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($searched): ?>

            <?php if (!$ticket): ?>
            <div class="alert alert-warning d-flex align-items-center gap-3">
                <i class="bi bi-question-circle-fill fs-4"></i>
                <div>
                    <strong>Ticket no encontrado</strong><br>
                    <span class="small">No existe ningún ticket con el número <strong><?= htmlspecialchars($numero) ?></strong>. Verifica el número e intenta nuevamente.</span>
                </div>
            </div>

            <?php else: ?>
            <!-- Detalle del ticket -->
            <div class="card form-public-card fade-in-up">
                <div class="card-header bg-white py-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0 fw-bold">
                            <span class="text-primary font-monospace"><?= htmlspecialchars($ticket['numero']) ?></span>
                        </h5>
                        <div class="d-flex gap-2 align-items-center">
                            <?= badge_prioridad($ticket['prioridad']) ?>
                            <?= badge_estado($ticket['estado']) ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">

                    <!-- Tracker visual de estado -->
                    <?php if ($ticket['estado'] !== 'marcada'): ?>
                    <div class="status-tracker mb-4">
                        <?php foreach ($estadosOrden as $idx => $st):
                            $estadoActual = $ticket['estado'];
                            $posActual    = array_search($estadoActual, $estadosOrden);
                            $posThis      = $idx;
                            $class = '';
                            if ($posThis < $posActual) $class = 'done';
                            elseif ($posThis === $posActual) $class = 'active';
                            $label = ESTADOS_TICKET[$st]['label'] ?? $st;
                        ?>
                        <div class="status-step <?= $class ?>">
                            <?php if ($posThis < $posActual): ?><i class="bi bi-check me-1"></i><?php endif; ?>
                            <?= $label ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-flag-fill"></i>
                        <strong>Ticket Marcado:</strong> Este ticket requiere atención especial. Por favor contacta al Área de Comunicación.
                    </div>
                    <?php endif; ?>

                    <!-- Datos del ticket -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <p class="text-muted small fw-semibold mb-1">SOLICITANTE</p>
                            <p class="mb-0 fw-semibold"><?= htmlspecialchars($ticket['solicitante_nombre'] . ' ' . $ticket['solicitante_apellido']) ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted small fw-semibold mb-1">ÁREA SOLICITANTE</p>
                            <p class="mb-0"><?= htmlspecialchars($ticket['solicitante_area']) ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted small fw-semibold mb-1">ÁREAS DE COMUNICACIÓN</p>
                            <p class="mb-0"><?= htmlspecialchars($ticket['areas_nombres'] ?: '-') ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted small fw-semibold mb-1">TIPO DE TRABAJO</p>
                            <p class="mb-0"><?= htmlspecialchars($ticket['tipos_nombres'] ?: '-') ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted small fw-semibold mb-1">FECHA DE SOLICITUD</p>
                            <p class="mb-0"><?= format_fecha_hora($ticket['created_at']) ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted small fw-semibold mb-1">FECHA ENTREGA SOLICITADA</p>
                            <p class="mb-0"><?= format_fecha($ticket['fecha_entrega_solicitada'] ?? '') ?></p>
                        </div>
                        <div class="col-12">
                            <p class="text-muted small fw-semibold mb-1">DESCRIPCIÓN</p>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($ticket['descripcion'])) ?></p>
                        </div>
                        <?php if ($ticket['estado'] === 'resuelta'): ?>
                        <div class="col-12">
                            <div class="alert alert-success d-flex align-items-center gap-2 mb-0">
                                <i class="bi bi-check-circle-fill fs-5"></i>
                                <div>
                                    <strong>¡Tu requerimiento fue resuelto!</strong>
                                    <?php if (!empty($ticket['fecha_resolucion'])): ?>
                                    <br><small>Resuelto el <?= format_fecha_hora($ticket['fecha_resolucion']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Historial público simplificado -->
                    <?php if (!empty($historial)): ?>
                    <div class="border-top pt-3">
                        <p class="text-muted small fw-semibold mb-2">HISTORIAL DE ESTADOS</p>
                        <ul class="timeline">
                            <?php foreach ($historial as $h): ?>
                            <li class="timeline-item">
                                <div class="timeline-icon bg-primary-subtle text-primary">
                                    <i class="bi bi-arrow-right-circle-fill"></i>
                                </div>
                                <div class="timeline-content">
                                    <strong><?= htmlspecialchars($h['accion']) ?></strong>
                                    <?php if (!empty($h['estado_nuevo'])): ?>
                                    &rarr; <?= badge_estado($h['estado_nuevo']) ?>
                                    <?php endif; ?>
                                    <br>
                                    <span class="ts"><?= format_fecha_hora($h['created_at']) ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                </div><!-- /card-body -->
            </div><!-- /card -->
            <?php endif; ?>

        <?php endif; // searched ?>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
