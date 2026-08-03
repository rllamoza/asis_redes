# 04 — API / Endpoints

> Todas las rutas pasan por el front controller `index.php` con el parámetro `?ruta=…`.
> Los endpoints JSON usan el envoltorio `Respuesta` (`{"success":true|false,"message":…,"data":…}`).
> Los endpoints del módulo `informes` y el layout de informes exigen sesión: sin login responden `302 → ?ruta=login`.

## `POST index.php?ruta=kiosco/api&action=pulsar`

Registra una pulsación para una persona: incrementa su contador e inserta una fila en `pulsaciones`. Devuelve JSON.

### Request

| Campo | Tipo | Obligatorio | Reglas |
|---|---|---|---|
| `nombre` | string (form-urlencoded) | Sí | Trim; no vacío; máximo 50 caracteres; debe existir en `contadores` |

Otros datos capturados automáticamente: `REMOTE_ADDR` (IP) y `HTTP_USER_AGENT` (truncado a 255).

### Responses

| Código | Body | Caso |
|---|---|---|
| `200` | `{"success":true,"contador":<int>}` | Pulsación registrada |
| `400` | `{"success":false,"message":"Nombre no proporcionado"}` | Sin `nombre` o vacío |
| `400` | `{"success":false,"message":"Nombre demasiado largo"}` | Más de 50 caracteres |
| `404` | `{"success":false,"message":"La persona no existe"}` | El nombre no está en `contadores` |
| `405` | `{"success":false,"message":"Método no permitido"}` | No es POST |
| `500` | `{"success":false,"message":"Error al registrar la pulsación"}` | Error de BD (se revierte la transacción) |

### Comportamiento transaccional (`KioscoService::pulsar`)

1. `UPDATE contadores SET contador = contador + 1 WHERE nombre = ?`
2. Si `affected_rows == 0` → **rollback**, responde `404`.
3. `INSERT INTO pulsaciones (nombre_persona, ip_usuario, user_agent) VALUES (?, ?, ?)`
4. `SELECT contador` actualizado.
5. `commit` y respuesta `200`.

## `POST index.php?ruta=auth/api&action=…`

| `action` | Función | Devuelve |
|---|---|---|
| `login` | Inicia sesión (`email`, `password`) | `200 {"success":true,"data":{"usuario":{…}}}`; error `401` |
| `registro` | Crea usuario (`nombre`, `email`, `password`, `password2`, `telefono?`) | `201 {"success":true,"data":null}` |
| `recuperar` | Solicita recuperación (`medio=email|whatsapp`, `email` o `telefono`) | `200` con aviso genérico |
| `restablecer` | Guarda clave nueva (`token` o `codigo`+`email`, `password`, `password2`) | `200` |
| `logout` | Cierra la sesión | `200 {"success":true,"data":{"logout":true}}` |

Todas las acciones requieren token CSRF (`csrf_token` en el body); sin token válido → `403`.

## `GET index.php?ruta=informes/api&action=…` (requiere sesión)

| `action` | Parámetros | Devuelve |
|---|---|---|
| `contadores` | `fecha_inicio`, `fecha_fin` (opcionales, formato `YYYY-MM-DD`) | Contadores con total y última pulsación en el rango |
| `pulsaciones` | `fecha_inicio`, `fecha_fin` | Filas de `pulsaciones` en el rango |
| `dashboard` | `fecha_inicio`, `fecha_fin` | Agregados para los 4 gráficos (top 5, por día, servicios, total) |
| `servidor` | — | Datos del servidor (misma fuente que la vista `servidor`) |

### Reglas de filtro de fechas

- Valores por defecto: últimos 7 días (`fecha_inicio` = hoy − 7, `fecha_fin` = hoy).
- Si una fecha no cumple el formato estricto `YYYY-MM-DD` (`DateTime::createFromFormat`), se ignora y se usan los valores por defecto (anula intentos de inyección).
- Las fechas válidas se pasan como parámetros en *prepared statements*.

## Páginas (GET)

| Ruta | Descripción | Protegida |
|---|---|---|
| `index.php?ruta=kiosco` | Kiosco de pulsación (botones por persona) | No |
| `index.php?ruta=login` | Formulario de login | No |
| `index.php?ruta=logout` | Cierra la sesión y redirige a login | No |
| `index.php?ruta=registro` | Registro de nuevos usuarios | No |
| `index.php?ruta=recuperar` | Solicita recuperación de contraseña (email o WhatsApp) | No |
| `index.php?ruta=restablecer` | Guarda la nueva contraseña (`?token=` o `?codigo=&email=`) | No |
| `index.php?ruta=informes` | Informes tabulares | Sí |
| `index.php?ruta=dashboard` | Dashboard con gráficos | Sí |
| `index.php?ruta=servidor` | Datos del servidor | Sí |

Cualquier otra ruta responde `404`.

### Login

- `POST index.php?ruta=login` — campos `email` y `password`, además del campo oculto `csrf_token`.
- Verificación contra la tabla `usuarios` con `password_verify()` (`AuthService::login`).
- Éxito: `session_regenerate_id(true)` (`Session::iniciar`), variables de sesión y `302 → ?ruta=informes`.
- Fracaso: mensaje "Credenciales incorrectas." y, tras 3 intentos, un freno de 1 s.
- Sesión con cookie `HttpOnly` + `SameSite=Lax` y caducidad por inactividad de 30 minutos.
- Todos los POST de los formularios requieren token CSRF (sesión + `hash_equals`, vida de 30 min); sin token válido se rechaza con mensaje de sesión caducada.

### Registro — `POST index.php?ruta=registro`

| Campo | Tipo | Obligatorio | Reglas |
|---|---|---|---|
| `csrf_token` | string | Sí | Token CSRF válido |
| `nombre` | string | Sí | Trim; no vacío |
| `email` | string | Sí | Debe ser un email válido y único en `usuarios` |
| `password` | string | Sí | Mínimo 6 caracteres |
| `password2` | string | Sí | Debe coincidir con `password` |
| `telefono` | string | No | `\+?[0-9 \-]{7,20}` (para recuperación por WhatsApp) |

Éxito: `INSERT` con rol `usuario` y `password_hash()`, `302 → ?ruta=login&registrado=1`.
La página se desactiva con `APP_REGISTRO_ABIERTO=false`.

### Recuperar — `POST index.php?ruta=recuperar`

| Campo | Tipo | Reglas |
|---|---|---|
| `csrf_token` | string | Token CSRF válido |
| `medio` | string | `email` (enlace) o `whatsapp` (vía enlace `wa.me`) |
| `email` | string | Usado si `medio=email` |
| `telefono` | string | Usado si `medio=whatsapp` |

- No revela si el email/teléfono existe: siempre muestra "Si tu correo o teléfono está registrado, recibirás las instrucciones…".
- Guarda token (42 hex) + código (6 dígitos) + expiración (30 min) en `usuarios`.
- `email` → `Notificaciones::correo()` con el enlace `APP_URL/index.php?ruta=restablecer&token=…`.
- `whatsapp` → `Notificaciones::enviarWhatsApp()` genera un enlace `https://wa.me/<numero>?text=<código>` (sin costo ni API); apunta al número configurado en `WHATSAPP_OPERADOR` o al del receptor. Requiere `telefono` en el usuario; si no lo tiene, cae al correo.
- Con `APP_DEBUG=true` se muestra el enlace/código en pantalla y se escribe en `storage/logs/`.

### Restablecer — `POST index.php?ruta=restablecer`

| Campo | Tipo | Reglas |
|---|---|---|
| `csrf_token` | string | Token CSRF válido |
| `token` | string | Enlace por correo (alternativo al código) |
| `codigo` + `email` | string | Código por WhatsApp (alternativo al token) |
| `password` / `password2` | string | Mínimo 6 y deben coincidir |

- Verifica que el token/código exista y no haya expirado; caducado → mensaje "El enlace/código ha caducado".
- Al guardar: `password_hash()`, limpia `token_recuperacion`, `codigo_recuperacion` y `token_expiracion` (no reutilizable), `302 → ?ruta=login&restablecida=1`.

## Script CLI — `cli/crear_usuario.php`

Gestión de usuarios del login desde la línea de comandos (no ejecutable vía web):

```powershell
# Crear o actualizar un usuario (si existe, actualiza password/nombre/rol/telefono)
php cli\crear_usuario.php <email> <password> [nombre] [rol] [telefono]    # rol: admin | usuario

# Listar usuarios
php cli\crear_usuario.php --lista

# Ejemplo
php cli\crear_usuario.php admin@ejemplo.com MiClave123 "Administrador" admin "+51000000000"
```

- Crea la tabla `usuarios` si no existe (incluye `telefono` y `codigo_recuperacion`).
- Valida email, longitud mínima de contraseña (6) y rol.
- Guarda el hash con `password_hash(PASSWORD_DEFAULT)`.
- `--lista` muestra `id, email, nombre, rol, telefono, fecha_creacion`.

### Ejemplos

```
# Informe de la última semana (sin parámetros)
GET /conteo/index.php?ruta=informes

# Informe de un rango específico
GET /conteo/index.php?ruta=informes&fecha_inicio=2026-07-01&fecha_fin=2026-07-31

# Dashboard de un rango específico
GET /conteo/index.php?ruta=dashboard&fecha_inicio=2026-07-01&fecha_fin=2026-08-02
```
