# Spec Kit — Control de Asistencia

Documentación técnica completa del proyecto **Control de Asistencia** (sistema de conteo de pulsaciones), ubicado en `C:\xampp\htdocs\conteo`.

## Índice

| Documento | Contenido |
|---|---|
| [01-vision-general.md](01-vision-general.md) | Propósito, alcance y estado del proyecto |
| [02-arquitectura.md](02-arquitectura.md) | Arquitectura, estructura de carpetas y flujo de datos |
| [03-base-de-datos.md](03-base-de-datos.md) | Esquema MySQL: tablas, columnas y relaciones |
| [04-api-endpoints.md](04-api-endpoints.md) | Endpoints, métodos, parámetros y respuestas |
| [05-interfaz-usuario.md](05-interfaz-usuario.md) | Vistas y comportamiento de la UI |
| [06-seguridad.md](06-seguridad.md) | Medidas de seguridad aplicadas y recomendaciones |
| [07-configuracion-y-despliegue.md](07-configuracion-y-despliegue.md) | Configuración local, producción y puesta en marcha |
| [08-pruebas.md](08-pruebas.md) | Casos de prueba y resultados verificados |
| [09-sms-gratuitos.md](09-sms-gratuitos.md) | Investigación de métodos gratuitos para SMS |

## Resumen ejecutivo

- **Tipo:** Aplicación web en PHP puro (sin framework) + MySQL, con front controller y módulos.
- **Función:** Registrar asistencia por medio de "pulsaciones" (clics en botones por persona) y generar informes.
- **Tres módulos** (en `modules/`, enrutados por `index.php?ruta=…`):
  1. **Kiosco de pulsación** (`?ruta=kiosco`) — botones por persona para registrar asistencia en tiempo real (acceso público).
  2. **Informes** (`?ruta=informes` y `?ruta=dashboard`) — tablas exportables (DataTables) y dashboard con gráficos (Chart.js). **Protegido con login.** Las vistas cargan datos vía API JSON (`?ruta=informes/api`).
  3. **Servidor** (`?ruta=servidor`) — datos del servidor: hora, zona horaria, PHP, MySQL, red, disco y extensiones. Accesible desde el botón "Servidor" del header.
- **Cuentas:** registro de usuarios (`?ruta=registro`), login con sesiones, y recuperación de contraseña por **email** (enlace) o **SMS** (código) con tokens de un solo uso.
- **Datos:** tablas `contadores` (total acumulado por persona), `pulsaciones` (histórico con IP y user agent) y `usuarios` (login + tokens de recuperación).
- **Stack:** PHP 8.2, MySQL/MariaDB, Bootstrap 5, jQuery, DataTables, Chart.js, Font Awesome.

## Estado del proyecto

| Área | Estado |
|---|---|
| Estructura | **Arquitectura modular**: front controller `index.php` + `core/` (bootstrap, Database, Session, Response, Notificaciones) + módulos `kiosco`, `auth` e `informes` (servicio + vistas + API JSON) |
| Rutas | `?ruta=kiosco / login / logout / registro / recuperar / restablecer / informes / dashboard / servidor` (+ APIs `kiosco/api`, `auth/api`, `informes/api`); rutas desconocidas → 404 |
| Vista principal (kiosco) | Basada en la antigua `index2.php`; `index.php` es ahora el único punto de entrada |
| Conexión a BD | Centralizada en `.env` + `core/bootstrap.php` (antes credenciales duplicadas en 4 archivos) |
| Inyección SQL | Corregida con *prepared statements* en todos los archivos |
| XSS | Corregido con `htmlspecialchars()` y flags JSON seguros |
| Validación de entrada | Añadida en `KioscoService::pulsar` (método, vacío, longitud, persona inexistente) |
| Rendimiento | Informes por defecto limitados a los últimos 7 días (antes cargaba ~48 MB) |
| Autenticación | **Implementada** en el módulo informes (login + sesiones). El kiosco público sigue abierto |
| Registro de usuarios | **Implementado** (`?ruta=registro`, rol `usuario`, desactivable con `APP_REGISTRO_ABIERTO`) |
| Recuperación de contraseña | **Implementada** por email (enlace) y SMS (código), con tokens de un solo uso de 30 min y protección CSRF |
| Página del servidor | Añadida `?ruta=servidor` con toda la información del hosting y la hora de las pulsaciones |
| Migración | Todo `informe_asis/` fue reemplazado por `modules/`; los archivos sueltos de la raíz (`registrar_pulsacion.php`, `crear_usuario.php`, `config.php`) fueron eliminados o movidos a `cli/` |
