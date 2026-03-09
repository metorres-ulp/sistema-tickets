<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();
require_role([ROL_ADMIN, ROL_REFERENTE]);

$pageTitle = 'Reportes y Estadísticas';
$pdo       = db();

// ─── Estadísticas generales ────────────────────────────────
$totalTickets    = (int)$pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$ticketsMes      = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
$ticketsResueltos = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE estado='resuelta'")->fetchColumn();
$porcentajeRes   = $totalTickets > 0 ? round($ticketsResueltos * 100 / $totalTickets, 1) : 0;

// Por estado
$porEstado = $pdo->query("SELECT estado, COUNT(*) AS total FROM tickets GROUP BY estado ORDER BY FIELD(estado,'ingresada','asignada','iniciada','en_proceso','resuelta','marcada')")->fetchAll();

// Por área (cantidad de tickets activos)
$porArea = $pdo->query("
    SELECT a.nombre, COUNT(DISTINCT ta.ticket_id) AS total
    FROM areas a
    LEFT JOIN ticket_areas ta ON ta.area_id = a.id
    LEFT JOIN tickets t ON t.id = ta.ticket_id
    GROUP BY a.id, a.nombre
    ORDER BY total DESC
")->fetchAll();

// Por mes (últimos 12 meses)
$porMes = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS mes,
           DATE_FORMAT(created_at, '%b %Y') AS mes_label,
           COUNT(*) AS total
    FROM tickets
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY mes, mes_label
    ORDER BY mes ASC
")->fetchAll();

// Top usuarios (por tickets resueltos)
$topUsuarios = $pdo->query("
    SELECT CONCAT(u.nombre,' ',u.apellido) AS nombre, COUNT(t.id) AS total
    FROM usuarios u
    JOIN tickets t ON t.asignado_a=u.id AND t.estado='resuelta'
    GROUP BY u.id
    ORDER BY total DESC
    LIMIT 8
")->fetchAll();

// Tiempo promedio resolución (días)
$tiempoPromedio = $pdo->query("
    SELECT AVG(DATEDIFF(fecha_resolucion, created_at)) AS promedio
    FROM tickets
    WHERE estado='resuelta' AND fecha_resolucion IS NOT NULL
")->fetchColumn();
$tiempoPromedio = $tiempoPromedio ? round((float)$tiempoPromedio, 1) : null;

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header">
    <h1><i class="bi bi-bar-chart-line me-2 text-primary"></i>Reportes y Estadísticas</h1>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-primary"><?= $totalTickets ?></div>
            <div class="text-muted small">Total Tickets</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-info"><?= $ticketsMes ?></div>
            <div class="text-muted small">Este Mes</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-success"><?= $porcentajeRes ?>%</div>
            <div class="text-muted small">Tasa Resolución</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-warning"><?= $tiempoPromedio !== null ? $tiempoPromedio . ' días' : 'N/A' ?></div>
            <div class="text-muted small">Tiempo Promedio</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Distribución por estado -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-pie-chart me-2"></i>Distribución por Estado</h6>
            </div>
            <div class="card-body">
                <?php foreach ($porEstado as $row): ?>
                <?php
                $info = ESTADOS_TICKET[$row['estado']] ?? ['label' => $row['estado'], 'color' => 'secondary'];
                $pct  = $totalTickets > 0 ? round($row['total'] * 100 / $totalTickets, 1) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span><?= badge_estado($row['estado']) ?></span>
                        <span><?= $row['total'] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-<?= $info['color'] ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Por área -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Tickets por Área</h6>
            </div>
            <div class="card-body">
                <?php
                $maxArea = !empty($porArea) ? max(array_column($porArea, 'total')) : 0;
                $maxArea = max($maxArea, 1);
                $colors  = ['primary','success','warning','info','danger'];
                foreach ($porArea as $idx => $row):
                    $color = $colors[$idx % count($colors)];
                    $pct   = round(($row['total'] / $maxArea) * 100);
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span><?= htmlspecialchars($row['nombre']) ?></span>
                        <span class="text-<?= $color ?>"><?= $row['total'] ?></span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tendencia mensual -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-graph-up me-2"></i>Tickets por Mes (últimos 12 meses)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($porMes)): ?>
                <p class="text-muted text-center small">Sin datos.</p>
                <?php else: ?>
                <?php
                $maxMes = max(array_column($porMes, 'total'));
                $maxMes = max($maxMes, 1);
                ?>
                <div class="d-flex align-items-end gap-2" style="height:120px">
                    <?php foreach ($porMes as $m):
                        $h = round(($m['total'] / $maxMes) * 100);
                    ?>
                    <div class="flex-grow-1 d-flex flex-column align-items-center">
                        <span class="text-muted mb-1" style="font-size:.7rem"><?= $m['total'] ?></span>
                        <div class="bg-primary rounded-top w-100" style="height:<?= max($h, 4) ?>%"></div>
                        <span class="text-muted mt-1" style="font-size:.65rem;writing-mode:vertical-rl;transform:rotate(180deg)"><?= htmlspecialchars($m['mes_label']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top usuarios -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-trophy me-2"></i>Top Usuarios (Resueltos)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($topUsuarios)): ?>
                <p class="text-muted text-center small">Sin datos.</p>
                <?php else: ?>
                <ol class="list-unstyled mb-0">
                    <?php foreach ($topUsuarios as $idx => $u): ?>
                    <li class="d-flex align-items-center justify-content-between py-1 border-bottom">
                        <span class="small">
                            <?php if ($idx === 0): ?><i class="bi bi-trophy-fill text-warning me-1"></i>
                            <?php elseif ($idx === 1): ?><i class="bi bi-trophy-fill text-secondary me-1"></i>
                            <?php elseif ($idx === 2): ?><i class="bi bi-trophy-fill text-warning-emphasis me-1"></i>
                            <?php else: ?><span class="text-muted me-2"><?= $idx + 1 ?>.</span><?php endif; ?>
                            <?= htmlspecialchars($u['nombre']) ?>
                        </span>
                        <span class="badge bg-success"><?= $u['total'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /row -->

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
