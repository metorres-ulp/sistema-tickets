<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ============================================================
// Generación de número de ticket
// ============================================================

function generar_numero_ticket(): string {
    $year  = date('Y');
    $month = date('m');
    $stmt  = db()->query("SELECT COUNT(*) FROM tickets WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())");
    $count = (int)$stmt->fetchColumn();
    return sprintf('TKT-%s%s-%04d', $year, $month, $count + 1);
}

// ============================================================
// Flash messages
// ============================================================

function flash_set(string $key, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_' . $key] = $message;
}

function flash_get(string $key): ?string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $msg = $_SESSION['flash_' . $key] ?? null;
    unset($_SESSION['flash_' . $key]);
    return $msg;
}

function flash_render(): string {
    $html = '';
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        $msg = flash_get($type);
        if ($msg) {
            $bsType = $type === 'error' ? 'danger' : $type;
            $icon = match($type) {
                'success' => 'bi-check-circle-fill',
                'error'   => 'bi-x-circle-fill',
                'warning' => 'bi-exclamation-triangle-fill',
                default   => 'bi-info-circle-fill',
            };
            $html .= "<div class=\"alert alert-{$bsType} alert-dismissible fade show d-flex align-items-center\" role=\"alert\">
                <i class=\"bi {$icon} me-2\"></i>
                <span>" . htmlspecialchars($msg) . "</span>
                <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>
            </div>";
        }
    }
    return $html;
}

// ============================================================
// Sanitización y validación
// ============================================================

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function sanitize_input(array $data, array $keys): array {
    $clean = [];
    foreach ($keys as $key) {
        $clean[$key] = isset($data[$key]) ? trim($data[$key]) : '';
    }
    return $clean;
}

// ============================================================
// Formateo de fechas
// ============================================================

function format_fecha(string $fecha, string $format = 'd/m/Y'): string {
    if (empty($fecha) || $fecha === '0000-00-00') return '-';
    return date($format, strtotime($fecha));
}

function format_fecha_hora(string $fecha): string {
    if (empty($fecha) || $fecha === '0000-00-00 00:00:00') return '-';
    return date('d/m/Y H:i', strtotime($fecha));
}

function tiempo_transcurrido(string $fecha): string {
    $diff = time() - strtotime($fecha);
    if ($diff < 60) return 'hace un momento';
    if ($diff < 3600) return 'hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'hace ' . floor($diff / 3600) . ' h';
    if ($diff < 604800) return 'hace ' . floor($diff / 86400) . ' días';
    return date('d/m/Y', strtotime($fecha));
}

// ============================================================
// Etiquetas de estado y prioridad
// ============================================================

function badge_estado(string $estado): string {
    $estados = ESTADOS_TICKET;
    if (!isset($estados[$estado])) return "<span class=\"badge bg-secondary\">{$estado}</span>";
    $e = $estados[$estado];
    return "<span class=\"badge bg-{$e['color']}\"><i class=\"bi {$e['icon']} me-1\"></i>{$e['label']}</span>";
}

function badge_prioridad(string $prioridad): string {
    $prioridades = PRIORIDADES_TICKET;
    if (!isset($prioridades[$prioridad])) return "<span class=\"badge bg-secondary\">{$prioridad}</span>";
    $p = $prioridades[$prioridad];
    return "<span class=\"badge bg-{$p['color']}\">{$p['label']}</span>";
}

// ============================================================
// Paginación
// ============================================================

function paginar(int $total, int $porPagina, int $paginaActual, string $url = ''): array {
    $totalPaginas = (int)ceil($total / $porPagina);
    $offset       = ($paginaActual - 1) * $porPagina;
    return [
        'total'        => $total,
        'por_pagina'   => $porPagina,
        'pagina_actual'=> $paginaActual,
        'total_paginas'=> $totalPaginas,
        'offset'       => $offset,
        'url'          => $url,
    ];
}

// ============================================================
// Notificaciones
// ============================================================

function crear_notificacion(int $usuarioId, int $ticketId, string $tipo, string $mensaje): void {
    try {
        db()->prepare("INSERT INTO notificaciones (usuario_id, ticket_id, tipo, mensaje) VALUES (?, ?, ?, ?)")
            ->execute([$usuarioId, $ticketId, $tipo, $mensaje]);
    } catch (PDOException $e) {
        // No interrumpir el flujo por error de notificación
    }
}

function notificar_todos_admins_referentes(int $ticketId, string $tipo, string $mensaje): void {
    try {
        $stmt = db()->query("SELECT id FROM usuarios WHERE rol IN ('admin','referente') AND activo = 1");
        $usuarios = $stmt->fetchAll();
        $stmtIns = db()->prepare("INSERT INTO notificaciones (usuario_id, ticket_id, tipo, mensaje) VALUES (?, ?, ?, ?)");
        foreach ($usuarios as $u) {
            $stmtIns->execute([$u['id'], $ticketId, $tipo, $mensaje]);
        }
    } catch (PDOException $e) {}
}

function contar_notificaciones_no_leidas(int $userId): int {
    try {
        $stmt = db()->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// ============================================================
// Envío de email
// ============================================================

function enviar_email_resolucion(array $ticket): bool {
    $to      = $ticket['solicitante_email'];
    $nombre  = $ticket['solicitante_nombre'] . ' ' . $ticket['solicitante_apellido'];
    $numero  = $ticket['numero'];
    $subject = "[{$numero}] Tu solicitud fue resuelta - Sistema de Tickets ULP";

    $body = "Estimado/a {$nombre},\n\n";
    $body .= "Te informamos que tu solicitud con número de ticket {$numero} ha sido resuelta.\n\n";
    $body .= "Descripción: " . $ticket['descripcion'] . "\n\n";
    $body .= "Si tienes alguna consulta, no dudes en contactarnos.\n\n";
    $body .= "Saludos,\nÁrea de Comunicación - ULP";

    $headers  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_REPLY_TO . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return @mail($to, $subject, $body, $headers);
}

// ============================================================
// Uploads
// ============================================================

function upload_archivo(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo.'];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'El archivo supera el tamaño máximo permitido (10 MB).'];
    }
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido.'];
    }
    $ext          = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nombreAlmac  = uniqid('file_', true) . '.' . strtolower($ext);
    $destino      = UPLOADS_DIR . '/' . $nombreAlmac;
    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        return ['success' => false, 'message' => 'No se pudo guardar el archivo.'];
    }
    return [
        'success'          => true,
        'nombre_original'  => $file['name'],
        'nombre_almacenado'=> $nombreAlmac,
        'tipo_mime'        => $mimeType,
        'tamanio'          => $file['size'],
    ];
}

function formatear_bytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ============================================================
// CSRF
// ============================================================

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// Helpers de arrays
// ============================================================

function array_to_options(array $items, string $valueKey, string $labelKey, $selected = null): string {
    $html = '';
    foreach ($items as $item) {
        $sel   = ($selected !== null && (string)$item[$valueKey] === (string)$selected) ? ' selected' : '';
        $val   = htmlspecialchars($item[$valueKey]);
        $label = htmlspecialchars($item[$labelKey]);
        $html .= "<option value=\"{$val}\"{$sel}>{$label}</option>";
    }
    return $html;
}

// ============================================================
// Obtener ticket por número
// ============================================================

function obtener_ticket_publico(string $numero): ?array {
    try {
        $stmt = db()->prepare("
            SELECT t.*,
                   GROUP_CONCAT(DISTINCT a.nombre ORDER BY a.nombre SEPARATOR ', ') AS areas_nombres,
                   GROUP_CONCAT(DISTINCT tt.nombre ORDER BY tt.nombre SEPARATOR ', ') AS tipos_nombres
            FROM tickets t
            LEFT JOIN ticket_areas ta ON ta.ticket_id = t.id
            LEFT JOIN areas a ON a.id = ta.area_id
            LEFT JOIN ticket_tipos_trabajo ttt ON ttt.ticket_id = t.id
            LEFT JOIN tipos_trabajo tt ON tt.id = ttt.tipo_trabajo_id
            WHERE t.numero = ?
            GROUP BY t.id
        ");
        $stmt->execute([strtoupper(trim($numero))]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}
