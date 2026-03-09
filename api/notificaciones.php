<?php
// API: Notificaciones check (AJAX polling desde el dashboard)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
session_start_secure();

if (!is_logged_in()) {
    echo json_encode(['count' => 0, 'new_tickets' => []]);
    exit;
}

$user = current_user();
$pdo  = db();

try {
    // Contar notificaciones no leídas
    $count = contar_notificaciones_no_leidas((int)$user['id']);

    // Nuevos tickets sin notificar (solo para admins/referentes)
    $newTickets = [];
    if (is_admin_or_referente()) {
        $stmt = $pdo->prepare("
            SELECT id, numero, solicitante_nombre, solicitante_apellido
            FROM tickets
            WHERE notificado = 0 AND estado = 'ingresada'
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute();
        $rawTickets = $stmt->fetchAll();

        foreach ($rawTickets as $t) {
            $newTickets[] = [
                'id'         => $t['id'],
                'numero'     => $t['numero'],
                'solicitante'=> $t['solicitante_nombre'] . ' ' . $t['solicitante_apellido'],
            ];
        }

        // Marcar como notificados
        if (!empty($rawTickets)) {
            $ids          = array_column($rawTickets, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtMark     = $pdo->prepare("UPDATE tickets SET notificado=1 WHERE id IN ({$placeholders})");
            $stmtMark->execute($ids);
        }
    }

    echo json_encode([
        'count'       => $count,
        'new_tickets' => $newTickets,
    ]);
} catch (Exception $e) {
    echo json_encode(['count' => 0, 'new_tickets' => []]);
}
