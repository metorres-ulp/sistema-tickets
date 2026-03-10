<?php
// API: Actualizar estado de ticket (drag & drop Kanban)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
session_start_secure();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
    exit;
}

$ticketId  = (int)($input['ticket_id'] ?? 0);
$nuevoEstado = trim($input['estado'] ?? '');
$user      = current_user();
$pdo       = db();

if ($ticketId <= 0 || !array_key_exists($nuevoEstado, ESTADOS_TICKET)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

try {
    // Cargar ticket
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id=?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket no encontrado.']);
        exit;
    }

    // Verificar acceso
    if ($user['rol'] === ROL_USUARIO && $ticket['asignado_a'] != $user['id']) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso.']);
        exit;
    }

    $estadoActual = $ticket['estado'];
    if ($estadoActual === $nuevoEstado) {
        echo json_encode(['success' => true, 'message' => 'Sin cambios.']);
        exit;
    }

    // Validar flujo para usuarios
    if ($user['rol'] === ROL_USUARIO) {
        $flujo = FLUJO_ESTADOS[$estadoActual] ?? [];
        if (!in_array($nuevoEstado, $flujo)) {
            echo json_encode(['success' => false, 'message' => 'Cambio de estado no permitido.']);
            exit;
        }
    }

    // Actualizar
    $fechasSql = '';
    if ($nuevoEstado === 'iniciada') $fechasSql = 'fecha_inicio = NOW(), ';
    if ($nuevoEstado === 'resuelta') $fechasSql = 'fecha_resolucion = NOW(), ';

    $pdo->prepare("UPDATE tickets SET estado=?, {$fechasSql}updated_at=NOW() WHERE id=?")
        ->execute([$nuevoEstado, $ticketId]);

    $pdo->prepare("INSERT INTO ticket_historial (ticket_id, usuario_id, estado_anterior, estado_nuevo, accion) VALUES (?,?,?,?,'cambio_estado')")
        ->execute([$ticketId, $user['id'], $estadoActual, $nuevoEstado]);

    // Email si resuelto
    if ($nuevoEstado === 'resuelta' && !$ticket['email_enviado']) {
        if (enviar_email_resolucion($ticket)) {
            $pdo->prepare("UPDATE tickets SET email_enviado=1 WHERE id=?")->execute([$ticketId]);
        }
    }

    $info = ESTADOS_TICKET[$nuevoEstado];
    $badgeHtml = "<span class=\"badge bg-{$info['color']}\"><i class=\"bi {$info['icon']} me-1\"></i>{$info['label']}</span>";

    echo json_encode([
        'success'    => true,
        'message'    => 'Estado actualizado.',
        'badge_html' => $badgeHtml,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
