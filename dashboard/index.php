<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();

$pageTitle = 'Dashboard';
$user      = current_user();

// ─── Estadísticas generales ────────────────────────────────
$pdo = db();

$stats = [];

// Total por estado
$stmtStats = $pdo->query("SELECT estado, COUNT(*) as total FROM tickets GROUP BY estado");
$byEstado  = [];
foreach ($stmtStats->fetchAll() as $row) {
    $byEstado[$row['estado']] = (int)$row['total'];
}

$stats['total']      = array_sum($byEstado);
$stats['ingresadas'] = $byEstado['ingresada'] ?? 0;
$stats['asignadas']  = $byEstado['asignada'] ?? 0;
$stats['en_proceso'] = ($byEstado['iniciada'] ?? 0) + ($byEstado['en_proceso'] ?? 0);
$stats['resueltas']  = $byEstado['resuelta'] ?? 0;
$stats['urgentes']   = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE urgente=1 AND estado NOT IN ('resuelta','marcada')")->fetchColumn();
$stats['marcadas']   = $byEstado['marcada'] ?? 0;
$stats['hoy']        = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(created_at)=CURDATE()")->fetchColumn();

// Si es usuario normal, solo sus tickets
if ($user['rol'] === ROL_USUARIO) {
    $stmtMine = $pdo->prepare("SELECT estado, COUNT(*) as total FROM tickets WHERE asignado_a=? GROUP BY estado");
    $stmtMine->execute([$user['id']]);
    $byEstadoMine = [];
    foreach ($stmtMine->fetchAll() as $row) {
        $byEstadoMine[$row['estado']] = (int)$row['total'];
    }
    $stats['mis_tickets'] = array_sum($byEstadoMine);
    $stats['mis_en_proceso'] = ($byEstadoMine['iniciada'] ?? 0) + ($byEstadoMine['en_proceso'] ?? 0);
    $stats['mis_resueltas'] = $byEstadoMine['resuelta'] ?? 0;
}

// Últimos tickets ingresados
if ($user['rol'] === ROL_USUARIO) {
    $stmtRecent = $pdo->prepare("
        SELECT t.*, GROUP_CONCAT(DISTINCT a.nombre SEPARATOR ', ') as areas
        FROM tickets t
        LEFT JOIN ticket_areas ta ON ta.ticket_id = t.id
        LEFT JOIN areas a ON a.id = ta.area_id
        WHERE t.asignado_a = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC LIMIT 5
    ");
    $stmtRecent->execute([$user['id']]);
} else {
    $stmtRecent = $pdo->query("
        SELECT t.*, GROUP_CONCAT(DISTINCT a.nombre SEPARATOR ', ') as areas
        FROM tickets t
        LEFT JOIN ticket_areas ta ON ta.ticket_id = t.id
        LEFT JOIN areas a ON a.id = ta.area_id
        GROUP BY t.id
        ORDER BY t.created_at DESC LIMIT 8
    ");
}
$recentTickets = $stmtRecent->fetchAll();

// Tickets urgentes activos
$urgentTickets = $pdo->query("
    SELECT t.*, GROUP_CONCAT(DISTINCT a.nombre SEPARATOR ', ') as areas
    FROM tickets t
    LEFT JOIN ticket_areas ta ON ta.ticket_id = t.id
    LEFT JOIN areas a ON a.id = ta.area_id
    WHERE t.urgente = 1 AND t.estado NOT IN ('resuelta','marcada')
    GROUP BY t.id
    ORDER BY t.created_at ASC LIMIT 5
")->fetchAll();

// Gráfico por áreas (para admins/referentes)
$areaStats = [];
if (is_admin_or_referente()) {
    $areaStats = $pdo->query("
        SELECT ar.nombre, COUNT(ta.ticket_id) as total
        FROM areas ar
        LEFT JOIN ticket_areas ta ON ta.area_id = ar.id
        LEFT JOIN tickets t ON t.id = ta.ticket_id AND t.estado NOT IN ('resuelta','marcada')
        GROUP BY ar.id, ar.nombre
        ORDER BY total DESC
    ")->fetchAll();
}

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header d-flex justify-content-between align-items-start">
    <div>
        <h1><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h1>
        <p class="text-muted mb-0">Bienvenido/a, <strong><?= htmlspecialchars($user['nombre']) ?></strong> &mdash; <?= date('l, d \d\e F \d\e Y') ?></p>
    </div>
    <?php if (is_admin_or_referente()): ?>
    <a href="<?= APP_URL ?>/dashboard/tickets.php" class="btn btn-primary btn-sm">
        <i class="bi bi-eye me-1"></i>Ver todos los tickets
    </a>
    <?php endif; ?>
</div>

<!-- ─── Estadísticas ────────────────────────────────────── -->
<?php if ($user['rol'] === ROL_USUARIO): ?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card card text-white" style="background: linear-gradient(135deg,#667eea,#764ba2)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-white bg-opacity-25"><i class="bi bi-list-task"></i></div>
                <div>
                    <div class="stat-number"><?= $stats['mis_tickets'] ?? 0 ?></div>
                    <div class="stat-label text-white-75">Mis Tickets</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card card text-white" style="background: linear-gradient(135deg,#f093fb,#f5576c)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-white bg-opacity-25"><i class="bi bi-gear-wide-connected"></i></div>
                <div>
                    <div class="stat-number"><?= $stats['mis_en_proceso'] ?? 0 ?></div>
                    <div class="stat-label text-white-75">En Proceso</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card card text-white" style="background: linear-gradient(135deg,#43e97b,#38f9d7)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-white bg-opacity-25"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-number"><?= $stats['mis_resueltas'] ?? 0 ?></div>
                    <div class="stat-label text-white-75">Resueltas</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card card text-white" style="background: linear-gradient(135deg,#667eea,#764ba2)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-white bg-opacity-25"><i class="bi bi-inbox"></i></div>
                <div>
                    <div class="stat-number"><?= $stats['ingresadas'] + $stats['asignadas'] ?></div>
                    <div class="stat-label text-white-75">Nuevos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card card text-white" style="background: linear-gradient(135deg,#4facfe,#00f2fe)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-white bg-opacity-25"><i class="bi bi-gear"></i></div>
                <div>
                    <div class="stat-number"><?= $stats['en_proceso'] ?></div>
                    <div class="stat-label text-white-75">En Proceso</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card card text-white" style="background: linear-gradient(135deg,#43e97b,#38f9d7)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-white bg-opacity-25"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-number"><?= $stats['resueltas'] ?></div>
                    <div class="stat-label text-white-75">Resueltas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card card text-white" style="background: linear-gradient(135deg,#f5576c,#f093fb)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-white bg-opacity-25"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-number"><?= $stats['urgentes'] ?></div>
                    <div class="stat-label text-white-75">Urgentes</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-info"><?= $stats['total'] ?></div>
                <div class="text-muted small">Total tickets</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-warning"><?= $stats['marcadas'] ?></div>
                <div class="text-muted small">Marcadas</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-primary"><?= $stats['hoy'] ?></div>
                <div class="text-muted small">Ingresados hoy</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ─── Tickets urgentes ─────────────────────────────────── -->
<?php if (!empty($urgentTickets) && is_admin_or_referente()): ?>
<div class="card border-danger mb-4">
    <div class="card-header bg-danger text-white d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Tickets Urgentes Activos</strong>
        <span class="badge bg-white text-danger ms-1"><?= count($urgentTickets) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ticket</th>
                        <th>Solicitante</th>
                        <th>Áreas</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($urgentTickets as $t): ?>
                    <tr>
                        <td class="fw-bold text-danger font-monospace"><?= htmlspecialchars($t['numero']) ?></td>
                        <td><?= htmlspecialchars($t['solicitante_nombre'] . ' ' . $t['solicitante_apellido']) ?></td>
                        <td><small><?= htmlspecialchars($t['areas'] ?? '-') ?></small></td>
                        <td><?= badge_estado($t['estado']) ?></td>
                        <td><small><?= format_fecha($t['created_at']) ?></small></td>
                        <td>
                            <a href="<?= APP_URL ?>/dashboard/ticket-detalle.php?id=<?= $t['id'] ?>" class="btn btn-danger btn-sm">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ─── Últimas actividades y estadísticas por área ─────── -->
<div class="row g-4">
    <!-- Últimos tickets -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Últimas Solicitudes</h6>
                <a href="<?= APP_URL ?>/dashboard/tickets.php" class="btn btn-outline-primary btn-sm">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentTickets)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>No hay tickets registrados.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nº Ticket</th>
                                <th>Solicitante</th>
                                <th>Áreas</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTickets as $t): ?>
                            <tr>
                                <td>
                                    <a href="<?= APP_URL ?>/dashboard/ticket-detalle.php?id=<?= $t['id'] ?>" class="fw-bold text-primary text-decoration-none font-monospace">
                                        <?= htmlspecialchars($t['numero']) ?>
                                    </a>
                                    <?php if ($t['urgente']): ?><i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Urgente"></i><?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= htmlspecialchars($t['solicitante_nombre'] . ' ' . $t['solicitante_apellido']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($t['areas'] ?? '-') ?></small></td>
                                <td><?= badge_estado($t['estado']) ?></td>
                                <td><?= badge_prioridad($t['prioridad']) ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/dashboard/ticket-detalle.php?id=<?= $t['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats por área -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Carga por Área</h6>
            </div>
            <div class="card-body">
                <?php if (empty($areaStats)): ?>
                <p class="text-muted small text-center">Sin datos</p>
                <?php else: ?>
                <?php
                $maxArea = max(array_column($areaStats, 'total'));
                $maxArea = max($maxArea, 1);
                $colors  = ['primary','success','warning','info','danger'];
                foreach ($areaStats as $idx => $as):
                    $color = $colors[$idx % count($colors)];
                    $pct   = round(($as['total'] / $maxArea) * 100);
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span><?= htmlspecialchars($as['nombre']) ?></span>
                        <span class="text-<?= $color ?>"><?= $as['total'] ?></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /row -->

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
