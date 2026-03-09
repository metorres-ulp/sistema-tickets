<?php
// ============================================================
// Configuración de la base de datos
// ============================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'sistema_tickets');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// Configuración general de la aplicación
// ============================================================
define('APP_NAME', 'Sistema de Tickets - ULP');
define('APP_URL', 'http://tickets.ulp.edu.ar');
define('APP_ROOT', dirname(__DIR__));
define('UPLOADS_DIR', APP_ROOT . '/uploads');
define('UPLOADS_URL', APP_URL . '/uploads');

// Tamaño máximo de archivo (10 MB)
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Tipos de archivo permitidos
define('ALLOWED_MIME_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain',
    'application/zip',
    'application/x-zip-compressed',
]);

// ============================================================
// Configuración de correo electrónico
// ============================================================
define('MAIL_FROM', 'noreply@ulp.edu.ar');
define('MAIL_FROM_NAME', 'Sistema de Tickets ULP');
define('MAIL_REPLY_TO', 'comunicacion@ulp.edu.ar');

// ============================================================
// Configuración de sesión
// ============================================================
define('SESSION_NAME', 'ulp_tickets');
define('SESSION_LIFETIME', 3600 * 8); // 8 horas

// ============================================================
// Roles
// ============================================================
define('ROL_ADMIN',    'admin');
define('ROL_REFERENTE','referente');
define('ROL_USUARIO',  'usuario');

// ============================================================
// Estados de tickets
// ============================================================
define('ESTADOS_TICKET', [
    'ingresada'  => ['label' => 'Ingresada',         'color' => 'secondary', 'icon' => 'bi-inbox'],
    'asignada'   => ['label' => 'Asignada',          'color' => 'info',      'icon' => 'bi-person-check'],
    'iniciada'   => ['label' => 'Iniciada',          'color' => 'primary',   'icon' => 'bi-play-circle'],
    'en_proceso' => ['label' => 'En Proceso',        'color' => 'warning',   'icon' => 'bi-gear'],
    'resuelta'   => ['label' => 'Resuelta',          'color' => 'success',   'icon' => 'bi-check-circle'],
    'marcada'    => ['label' => 'Marcada',           'color' => 'danger',    'icon' => 'bi-flag'],
]);

// Flujo de estados (usuario solo puede avanzar)
define('FLUJO_ESTADOS', [
    'ingresada'  => ['asignada', 'marcada'],
    'asignada'   => ['iniciada', 'marcada'],
    'iniciada'   => ['en_proceso', 'marcada'],
    'en_proceso' => ['resuelta', 'marcada'],
    'resuelta'   => ['marcada'],
    'marcada'    => [],
]);

// ============================================================
// Prioridades
// ============================================================
define('PRIORIDADES_TICKET', [
    'baja'    => ['label' => 'Baja',    'color' => 'secondary'],
    'normal'  => ['label' => 'Normal',  'color' => 'info'],
    'alta'    => ['label' => 'Alta',    'color' => 'warning'],
    'urgente' => ['label' => 'Urgente', 'color' => 'danger'],
]);

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Manejo de errores (desactivar en producción)
ini_set('display_errors', 1);
error_reporting(E_ALL);
