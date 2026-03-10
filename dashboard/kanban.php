<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();

$pageTitle = 'Tablero Kanban';
$user      = current_user();
$pdo       = db();

// ─── Filtros ────────────────────────────────────────────────
$filtroArea     = isset($_GET['area'])     ? (int)$_GET['area']     : 0;
$filtroAsignado = isset($_GET['asignado']) ? (int)$_GET['asignado'] : 0;

// Si es usuario normal, solo ver sus tickets asignados
$whereExtra = '';
$params     = [];
if ($user['rol'] === ROL_USUARIO) {
    $whereExtra = ' AND t.asignado_a = :uid';
    $params[':uid'] = $user['id'];
} elseif ($filtroAsignado > 0) {
    $whereExtra .= ' AND t.asignado_a = :uid';
    $params[':uid'] = $filtroAsignado;
}
if ($filtroArea > 0) {
    $whereExtra .= ' AND EXISTS (SELECT 1 FROM ticket_areas ta WHERE ta.ticket_id=t.id AND ta.area_id=:aid)';
    $params[':aid'] = $filtroArea;
}

// Cargar tickets por estado
$ticketsPorEstado = [];
foreach (ESTADOS_TICKET as $estado => $info) {
    $sql = "
        SELECT t.*,
               GROUP_CONCAT(DISTINCT ar.nombre ORDER BY ar.nombre SEPARATOR ', ') AS areas,
               CONCAT(u.nombre,' ',u.apellido) AS asignado_nombre
        FROM tickets t
        LEFT JOIN ticket_areas ta2 ON ta2.ticket_id = t.id
        LEFT JOIN areas ar ON ar.id = ta2.area_id
        LEFT JOIN usuarios u ON u.id = t.asignado_a
        WHERE t.estado = :estado {$whereExtra}
        GROUP BY t.id
        ORDER BY t.urgente DESC, t.prioridad DESC, t.created_at ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([':estado' => $estado], $params));
    $ticketsPorEstado[$estado] = $stmt->fetchAll();
}

// Para filtros
$areas   = $pdo->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();
$usuarios = [];
if (is_admin_or_referente()) {
    $usuarios = $pdo->query("SELECT id, nombre, apellido FROM usuarios WHERE activo=1 ORDER BY nombre")->fetchAll();
}

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-kanban me-2 text-primary"></i>Tablero Kanban</h1>
        <p class="text-muted mb-0">Vista visual de tickets por estado.</p>
    </div>
    <?php if (is_admin_or_referente()): ?>
    <a href="<?= APP_URL ?>/dashboard/tickets.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-list-task me-1"></i>Vista Lista
    </a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body py-2">
        <form method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Área</label>
                <select name="area" class="form-select form-select-sm">
                    <option value="">Todas las áreas</option>
                    <?php foreach ($areas as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $filtroArea == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($usuarios)): ?>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Asignado a</label>
                <select name="asignado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filtroAsignado == $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="<?= APP_URL ?>/dashboard/kanban.php" class="btn btn-sm btn-outline-secondary ms-1">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tablero -->
<div class="kanban-board">
    <?php foreach (ESTADOS_TICKET as $estado => $info): ?>
    <?php
    $tickets = $ticketsPorEstado[$estado] ?? [];
    $count   = count($tickets);
    $bgMap   = [
        'secondary' => 'bg-secondary',
        'info'      => 'bg-info',
        'primary'   => 'bg-primary',
        'warning'   => 'bg-warning',
        'success'   => 'bg-success',
        'danger'    => 'bg-danger',
    ];
    $bg = $bgMap[$info['color']] ?? 'bg-secondary';
    ?>
    <div class="kanban-column">
        <!-- Encabezado de columna -->
        <div class="kanban-column-header d-flex justify-content-between align-items-center <?= $bg ?> text-white">
            <span><i class="bi <?= $info['icon'] ?> me-1"></i><?= $info['label'] ?></span>
            <span class="badge bg-white text-dark"><?= $count ?></span>
        </div>
        <!-- Cuerpo de columna -->
        <div class="kanban-column-body" data-status="<?= $estado ?>">
            <?php if (empty($tickets)): ?>
            <div class="text-center text-muted small py-3">
                <i class="bi bi-inbox d-block mb-1"></i>Sin tickets
            </div>
            <?php else: ?>
            <?php foreach ($tickets as $t): ?>
            <div class="ticket-card <?= $t['urgente'] ? 'urgente' : '' ?>"
                 data-ticket-id="<?= $t['id'] ?>"
                 draggable="<?= is_admin_or_referente() ? 'true' : 'false' ?>"
                 onclick="window.location='<?= APP_URL ?>/dashboard/ticket-detalle.php?id=<?= $t['id'] ?>'">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="ticket-number"><?= htmlspecialchars($t['numero']) ?></span>
                    <?php if ($t['urgente']): ?>
                    <span class="badge bg-danger ms-1" title="Urgente"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <?php endif; ?>
                </div>
                <p class="ticket-title">
                    <?= htmlspecialchars($t['solicitante_nombre'] . ' ' . $t['solicitante_apellido']) ?>
                </p>
                <p class="text-muted small mb-1" style="font-size:.75rem;line-height:1.3">
                    <?= htmlspecialchars(mb_strimwidth($t['descripcion'], 0, 60, '…')) ?>
                </p>
                <?php if (!empty($t['areas'])): ?>
                <div class="mb-1">
                    <span class="badge bg-light text-dark border" style="font-size:.7rem">
                        <?= htmlspecialchars(mb_strimwidth($t['areas'], 0, 35, '…')) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="ticket-meta">
                    <span>
                        <?php if (!empty($t['asignado_nombre'])): ?>
                        <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars(mb_strimwidth($t['asignado_nombre'], 0, 18, '…')) ?>
                        <?php else: ?>
                        <span class="text-muted"><i class="bi bi-person me-1"></i>Sin asignar</span>
                        <?php endif; ?>
                    </span>
                    <span><?= badge_prioridad($t['prioridad']) ?></span>
                </div>
                <div class="text-end mt-1">
                    <span class="text-muted" style="font-size:.7rem"><?= tiempo_transcurrido($t['created_at']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
