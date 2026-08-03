# 07 — Configuración y Despliegue

## Configuración local (XAMPP)

### 1. Base de datos

La BD `jowfbilo_contador` ya debe existir con las tablas `contadores`, `pulsaciones` y `usuarios` (ver `03-base-de-datos.md`).

### 2. Variables de entorno — `.env` (raíz del proyecto)

```ini
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DATABASE=jowfbilo_contador
MYSQL_USER=usuario_bd
MYSQL_PASSWORD=cambia_esta_clave

# Aplicación
APP_URL=http://localhost/conteo
APP_REGISTRO_ABIERTO=true
APP_DEBUG=true

# Correo
SMTP_HOST=                 # vacío = usa mail() de PHP (cPanel)
SMTP_PORT=587
SMTP_SECURE=starttls
SMTP_USER=
SMTP_PASS=
SMTP_FROM=no-reply@localhost
SMTP_FROM_NAME=Control de Asistencia

# SMS (ya no se usa; reemplazado por WhatsApp)
# SMS_METODO=dev           # dev | textbelt | email-gateway | twilio
# SMS_TEXTBELT_APIKEY=textbelt
# SMS_EMAIL_GATEWAY=txt.att.net
# TWILIO_SID=
# TWILIO_TOKEN=
# TWILIO_FROM=

# WhatsApp (vía enlaces wa.me) — recuperación y herramienta de prueba
WHATSAPP_OPERADOR=        # número sin + (ej. 51000000000); vacío = enlace apunta al receptor
```

- `APP_URL`: URL pública de la app; se usa para construir el enlace de recuperación (`APP_URL/index.php?ruta=restablecer&token=…`).
- `APP_DEBUG`: con `true`, si el correo no se puede enviar (modo local), el enlace se muestra en pantalla. En producción debe ser `false`.
- `WHATSAPP_OPERADOR`: número de WhatsApp (sin `+`) que entrega los códigos de recuperación. La recuperación por WhatsApp usa enlaces `wa.me` (sin costo ni API): al abrir el enlace se abre WhatsApp con el mensaje listo para enviar. Si está vacío, el enlace apunta al número del receptor.

`core/bootstrap.php` carga `.env`, define los valores por defecto y deja la conexión lista (se conecta bajo demanda vía `Database::conexion()`).

### 3. Crear usuarios del login

El módulo de informes requiere usuario. El usuario se crea con el script CLI:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\conteo\cli\crear_usuario.php admin@ejemplo.com "MiClave123" "Administrador" admin "+51000000000"
C:\xampp\php\php.exe C:\xampp\htdocs\conteo\cli\crear_usuario.php --lista
```

El script crea la tabla `usuarios` automáticamente si no existe (con `telefono` y `codigo_recuperacion`). Los usuarios también pueden registrarse desde `?ruta=registro` si `APP_REGISTRO_ABIERTO=true`.

### 4. Probar la recuperación en local

Con `SMTP_HOST` vacío (sin `mail()` funcional), `recuperar.php` escribe el correo en `storage/logs/correo_*.html` y el enlace de WhatsApp en `storage/logs/whatsapp_*.txt` y, con `APP_DEBUG=true`, muestra el enlace/código directamente en pantalla. En producción configurar SMTP real (o `mail()` de cPanel). La página `?ruta=servidor` incluye una herramienta para probar el envío de correo y la generación de enlaces de WhatsApp.

### 5. Levantar la aplicación

**Opción A — Apache (XAMPP):** copiar el proyecto a `C:\xampp\htdocs\conteo` y acceder a `http://localhost/conteo/index.php?ruta=kiosco`.

**Opción B — Servidor embebido de PHP (desarrollo):**

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8089 -t C:\xampp\htdocs\conteo
# Kiosco:    http://127.0.0.1:8089/index.php?ruta=kiosco
# Informes:  http://127.0.0.1:8089/index.php?ruta=informes
# Dashboard: http://127.0.0.1:8089/index.php?ruta=dashboard
```

### 6. Requisitos de PHP

- PHP >= 8.0 (probado con 8.2.12).
- Extensión `mysqli`.
- `mbstring` recomendada (usada en `mb_strlen` para validación).
- Acceso a CDN (Bootstrap, jQuery, DataTables, Chart.js, Font Awesome) para estilos y librerías.

## Despliegue en producción

Estructura esperada (basada en el despliegue original en cPanel):

```
/home/<usuario>/asistencia.<dominio>/conteo/
├── .env                     ← credenciales reales de producción
├── index.php                ← front controller (único punto de entrada)
├── core/                    ← bootstrap, Database, Session, Response, Notificaciones
├── modules/                 ← kiosco, auth, informes (vistas + API)
├── assets/css/              ← estilos globales
├── cli/crear_usuario.php    ← gestión de usuarios (CLI)
└── storage/logs/            ← logs de correo/SMS (creación automática)
```

Pasos:

1. Subir los archivos respetando la estructura.
2. Editar `.env` con las credenciales de producción (usuario de BD con privilegios sobre `jowfbilo_contador`).
3. Verificar permisos: `.env` legible por PHP, no expuesto por el servidor web.
4. Comprobar que la extensión `mysqli` esté habilitada en el hosting.
5. Crear el usuario administrador con `cli/crear_usuario.php` (CLI).
6. Editar `.env`: poner `APP_URL`, `APP_DEBUG=false` y configurar SMTP/SMS reales.
7. Probar: `?ruta=kiosco`, `?ruta=login` (login), `?ruta=registro`, `?ruta=recuperar` (envío real), `?ruta=restablecer`, `?ruta=informes`, `?ruta=dashboard`, `?ruta=servidor`.

## Verificación rápida tras desplegar

| Prueba | Esperado |
|---|---|
| `GET index.php?ruta=kiosco` | HTTP 200 con botones de personas |
| `POST index.php?ruta=kiosco/api&action=pulsar` (sin nombre) | HTTP 400 JSON |
| `GET index.php?ruta=informes` (sin sesión) | HTTP 302 → `?ruta=login` |
| `POST index.php?ruta=login` (credenciales correctas) | HTTP 302 → `?ruta=informes` |
| `GET index.php?ruta=servidor` (con sesión) | HTTP 200 con datos del servidor |
| `POST index.php?ruta=login` (sin `csrf_token`) | HTTP 200 con mensaje de sesión caducada |
| `GET index.php?ruta=registro` | HTTP 200 con formulario |
| `POST index.php?ruta=recuperar` (email existente) | HTTP 200 con aviso genérico |
| `POST index.php?ruta=restablecer` (token válido) | HTTP 302 → `?ruta=login&restablecida=1` |
| `GET index.php?ruta=noexiste` | HTTP 404 |
