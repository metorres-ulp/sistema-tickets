<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start_secure();
require_login();

$pageTitle = 'Tickets';
$user      = current_user();
$pdo       = db();

// ─── Filtros y paginación ────────────────────────────────────
$filtroEstado    = $_GET['estado']    ?? '';
$filtroPrioridad = $_GET['prioridad'] ?? '';
$filtroArea      = isset($_GET['area']) ? (int)$_GET['area'] : 0;
$filtroBusqueda  = trim($_GET['q']    ?? '');
$paginaActual    = max(1, (int)($_GET['p'] ?? 1));
$porPagina       = 20;

$where  = ['1=1'];
$params = [];

// Restricción por rol
if ($user['rol'] === ROL_USUARIO) {
    $where[]  = 't.asignado_a = :uid';
    $params[':uid'] = $user['id'];
}

if ($filtroEstado && array_key_exists($filtroEstado, ESTADOS_TICKET)) {
    $where[]  = 't.estado = :estado';
    $params[':estado'] = $filtroEstado;
}
if ($filtroPrioridad && array_key_exists($filtroPrioridad, PRIORIDADES_TICKET)) {
    $where[]  = 't.prioridad = :prioridad';
    $params[':prioridad'] = $filtroPrioridad;
}
if ($filtroArea > 0) {
    $where[]  = 'EXISTS (SELECT 1 FROM ticket_areas ta WHERE ta.ticket_id=t.id AND ta.area_id=:area_id)';
    $params[':area_id'] = $filtroArea;
}
if ($filtroBusqueda !== '') {
    $where[]  = '(t.numero LIKE :q OR t.solicitante_nombre LIKE :q OR t.solicitante_apellido LIKE :q OR t.solicitante_email LIKE :q OR t.descripcion LIKE :q)';
    $params[':q'] = '%' . $filtroBusqueda . '%';
}

$whereStr = implode(' AND ', $where);

// Total
$countSql = "SELECT COUNT(*) FROM tickets t WHERE {$whereStr}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

$pag    = paginar($total, $porPagina, $paginaActual);
$offset = $pag['offset'];

// Datos
$sql = "
    SELECT t.*,
           GROUP_CONCAT(DISTINCT ar.nombre ORDER BY ar.nombre SEPARATOR ', ') AS areas,
           CONCAT(u.nombre,' ',u.apellido) AS asignado_nombre
    FROM tickets t
    LEFT JOIN ticket_areas ta2 ON ta2.ticket_id = t.id
    LEFT JOIN areas ar ON ar.id = ta2.area_id
    LEFT JOIN usuarios u ON u.id = t.asignado_a
    WHERE {$whereStr}
    GROUP BY t.id
    ORDER BY t.urgente DESC, t.created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();

// Para filtros select
$areas = $pdo->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/../includes/header-dashboard.php';
?>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-list-task me-2 text-primary"></i>Todos los Tickets</h1>
        <p class="text-muted mb-0"><?= $total ?> ticket<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= APP_URL ?>/dashboard/kanban.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-kanban me-1"></i>Vista Kanban
    </a>
</div>

<!-- Flash -->
<?= flash_render() ?>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" id="filterForm" class="row g-2 align-items-end flex-wrap">
            <div class="col-sm-auto flex-grow-1">
                <input type="text" name="q" class="form-control form-control-sm"
                    placeholder="Buscar por número, nombre, email..." value="<?= htmlspecialchars($filtroBusqueda) ?>">
            </div>
            <div class="col-sm-auto">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos los estados</option>
                    <?php foreach (ESTADOS_TICKET as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filtroEstado === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-auto">
                <select name="prioridad" class="form-select form-select-sm">
                    <option value="">Todas las prioridades</option>
                    <?php foreach (PRIORIDADES_TICKET as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filtroPrioridad === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-auto">
                <select name="area" class="form-select form-select-sm">
                    <option value="">Todas las áreas</option>
                    <?php foreach ($areas as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $filtroArea == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-auto">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Filtrar</button>
                <a href="<?= APP_URL ?>/dashboard/tickets.php" class="btn btn-outline-secondary btn-sm ms-1">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de tickets -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($tickets)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No hay tickets que coincidan con los filtros.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nº Ticket</th>
                        <th>Solicitante</th>
                        <th>Área Solicitante</th>
                        <th>Áreas Comunicación</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Asignado a</th>
                        <th>Fecha</th>
                        <th class="pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td class="ps-3">
                            <a href="<?= APP_URL ?>/dashboard/ticket-detalle.php?id=<?= $t['id'] ?>" class="fw-bold text-primary text-decoration-none font-monospace">
                                <?= htmlspecialchars($t['numero']) ?>
                            </a>
                            <?php if ($t['urgente']): ?>
                            <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Urgente"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($t['solicitante_nombre'] . ' ' . $t['solicitante_apellido']) ?>
                            <br><small class="text-muted"><?= htmlspecialchars($t['solicitante_email']) ?></small>
                        </td>
                        <td><small><?= htmlspecialchars($t['solicitante_area']) ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars($t['areas'] ?? '-') ?></small></td>
                        <td><?= badge_estado($t['estado']) ?></td>
                        <td><?= badge_prioridad($t['prioridad']) ?></td>
                        <td>
                            <?php if ($t['asignado_nombre']): ?>
                            <small><?= htmlspecialchars($t['asignado_nombre']) ?></small>
                            <?php else: ?>
                            <small class="text-muted fst-italic">Sin asignar</small>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-nowrap"><?= format_fecha($t['created_at']) ?></small></td>
                        <td class="pe-3">
                            <div class="d-flex gap-1">
                                <a href="<?= APP_URL ?>/dashboard/ticket-detalle.php?id=<?= $t['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (is_admin_or_referente() && $t['estado'] === 'ingresada'): ?>
                                <a href="<?= APP_URL ?>/dashboard/ticket-asignar.php?id=<?= $t['id'] ?>"
                                   class="btn btn-sm btn-outline-success" title="Asignar">
                                    <i class="bi bi-person-plus"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($pag['total_paginas'] > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
            <small class="text-muted">
                Mostrando <?= ($offset + 1) ?>-<?= min($offset + $porPagina, $total) ?> de <?= $total ?>
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $qs = http_build_query(array_merge($_GET, ['p' => max(1, $paginaActual - 1)]));
                    echo '<li class="page-item' . ($paginaActual <= 1 ? ' disabled' : '') . '"><a class="page-link" href="?' . $qs . '"><i class="bi bi-chevron-left"></i></a></li>';
                    for ($i = max(1, $paginaActual - 2); $i <= min($pag['total_paginas'], $paginaActual + 2); $i++):
                        $qs = http_build_query(array_merge($_GET, ['p' => $i]));
                    ?>
                    <li class="page-item<?= $i === $paginaActual ? ' active' : '' ?>">
                        <a class="page-link" href="?<?= $qs ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php
                    $qs = http_build_query(array_merge($_GET, ['p' => min($pag['total_paginas'], $paginaActual + 1)]));
                    echo '<li class="page-item' . ($paginaActual >= $pag['total_paginas'] ? ' disabled' : '') . '"><a class="page-link" href="?' . $qs . '"><i class="bi bi-chevron-right"></i></a></li>';
                    ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-dashboard.php'; ?>
