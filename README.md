# Sistema de Tickets — ULP Comunicación

Sistema web de gestión visual de proyectos (tipo Kanban) para el Área de Comunicación de la Universidad Nacional de La Punta.

## Características

- **Formulario público** de solicitud de requerimientos (`/nuevo-requerimiento.php`)
- **Consulta pública** de estado de ticket por número (`/buscar-ticket.php`)
- **Panel privado** con roles: Admin, Referente y Usuario
- **Tablero Kanban** con drag & drop
- **Notificaciones** en tiempo real para nuevos tickets
- **Email automático** al resolver tickets
- **Reportes y estadísticas** de tickets y usuarios
- **CRUD completo**: usuarios, áreas, tipos de trabajo

## Roles

| Rol | Permisos |
|-----|----------|
| **Admin** | CRUD completo de usuarios, áreas, tipos de trabajo; gestión total de tickets |
| **Referente** | Asignación de tickets, creación de tareas, gestión de su área |
| **Usuario** | Gestión de tickets asignados (solo avanza estados, nunca retrocede) |

## Estados de Tickets

`Ingresada → Asignada → Iniciada → En Proceso → Resuelta / Marcada`

## Áreas disponibles

- Multimedia - Web
- Diseño
- Prensa
- Producción Audiovisual
- Estudio de Grabación

## Instalación

### Requisitos
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache con `mod_rewrite`

### Pasos

1. **Clonar el repositorio** en el directorio web:
   ```
   git clone ... /var/www/html/tickets
   ```

2. **Crear la base de datos** e importar el esquema:
   ```sql
   mysql -u root -p < database.sql
   ```

3. **Configurar la aplicación** editando `config/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'sistema_tickets');
   define('DB_USER', 'tu_usuario');
   define('DB_PASS', 'tu_contraseña');
   define('APP_URL',  'https://tickets.ulp.edu.ar');
   ```

4. **Dar permisos de escritura** al directorio de uploads:
   ```bash
   chmod 755 uploads/
   chown www-data:www-data uploads/
   ```

5. **Acceder** a la aplicación:
   - Formulario público: `https://tickets.ulp.edu.ar/nuevo-requerimiento.php`
   - Panel privado: `https://tickets.ulp.edu.ar/login.php`
   - Credenciales iniciales: `admin@ulp.edu.ar` / `Admin1234!` (**cambiar inmediatamente**)

> **Importante:** Después del primer login, cambia la contraseña del administrador desde el perfil de usuario.

## Estructura del proyecto

```
/
├── config/          # Configuración de base de datos y app
├── includes/        # Funciones compartidas, auth, layouts
├── dashboard/       # Panel privado (todas las páginas del admin/referente/usuario)
├── api/             # Endpoints AJAX (notificaciones, actualización de estado)
├── assets/
│   ├── css/         # Estilos personalizados (base Bootstrap 5.3)
│   └── js/          # JavaScript (kanban, notificaciones, etc.)
├── uploads/         # Archivos adjuntos (excluidos del repo)
├── database.sql     # Esquema completo de la base de datos
├── nuevo-requerimiento.php  # Formulario público
├── buscar-ticket.php        # Consulta pública por número
├── login.php                # Acceso al panel
└── logout.php
```

## Stack Tecnológico

- **Backend**: PHP 8+ con PDO (MySQL)
- **Frontend**: Bootstrap 5.3, Bootstrap Icons, JavaScript vanilla
- **Base de datos**: MySQL / MariaDB