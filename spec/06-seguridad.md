# 06 — Seguridad

## Medidas aplicadas en el saneamiento

### 1. Credenciales fuera del código
- Antes: credenciales hardcodeadas en `index.php`, `index2.php`, `registrar_pulsacion.php` y `.env` de `informe_asis`.
- Ahora: **único punto** de configuración en `.env` (raíz) leído por `core/bootstrap.php`. El `.env` duplicado de `informe_asis` fue eliminado.
- **Recomendación:** no subir `.env` a repositorios; en producción usar variables de entorno reales del servidor.

### 2. Inyección SQL (crítica, corregida)
- Antes: `informe_asis/index.php` y `dashboard.php` concatenaban `$_GET['fecha_inicio']` y `$_GET['fecha_fin']` directamente en SQL.
- Ahora: 100% de las consultas con parámetros usan *prepared statements* (`bind_param`) o valores internos seguros. Todo el acceso a BD pasa por `core/Database.php` (singleton `mysqli`).
- Defensa adicional: validación estricta de fechas (`DateTime::createFromFormat('Y-m-d')`); fechas inválidas se descartan y se usan los valores por defecto.

### 3. XSS (corregido)
- Antes: `informe_asis/index.php` imprimía valores de BD sin escapar.
- Ahora: toda salida de datos pasa por `htmlspecialchars()` (atributos y celdas). Las vistas del módulo informes renderizan tablas vacías y cargan datos vía API JSON (el render lo hace DataTables), reduciendo la superficie.
- Los datos JSON inyectados en `<script>` usan `json_encode($arr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)` para impedir ruptura del script.

### 4. Validación de entrada en el endpoint
`KioscoService::pulsar` (vía `?ruta=kiosco/api&action=pulsar`):
- Solo acepta `POST` (405 en otro método, `Respuesta::requiereMetodo`).
- `nombre` obligatorio, con `trim`, no vacío, máximo 50 caracteres.
- Verifica que la persona exista (`affected_rows == 0` → rollback + 404), evitando huérfanos en `pulsaciones`.

### 5. Errores sin fuga de información
- Errores de BD devuelven mensajes genéricos (no se exponen detalles internos).
- `mysqli` en modo estricto, pero la conexión fallida termina con un mensaje neutro.

### 6. Limpieza de artefactos
- Eliminados: `informe_asis/error_log` (con trazas del servidor de producción), el `index2.php` duplicado, y posteriormente todo `informe_asis/` al migrar a `modules/`.

### 7. Autenticación
- Login obligatorio en el módulo informes: `modules/informes/views/layout/header.php` llama a `Session::requiereLogin()`.
- Contraseñas con `password_hash` (bcrypt); verificación con `password_verify`.
- Cookies de sesión `HttpOnly` + `SameSite=Lax`; `session_regenerate_id(true)` al iniciar sesión (`Session::iniciar`).
- Timeout de sesión por inactividad: 30 minutos.
- Freno básico tras 3 intentos fallidos de login (sleep 1 s).
- La página `servidor` expone información sensible del hosting y por tanto queda tras el login.
- Los endpoints JSON de `informes` también exigen sesión (`Session::requiereLogin()` → `302`).

### 8. CSRF
- Helpers en `core/Session.php`: `csrfToken()`, `csrfValido()`, `campoCsrf()`.
- Token aleatorio de 32 bytes en sesión, con `hash_equals()` para comparar y vida de 30 minutos.
- Todos los formularios POST (`login`, `registro`, `recuperar`, `restablecer`) incluyen el campo oculto y verifican el token; sin token válido la petición se rechaza.
- Los endpoints de `auth/api` también exigen CSRF → `403` sin token válido.

### 9. Recuperación de contraseña (nuevo)
- No revela si la cuenta existe (respuesta genérica para cuentas existentes e inexistentes).
- Token de enlace: 42 bytes aleatorios (`random_bytes`); código SMS: 6 dígitos (`random_int`).
- Caducidad de 30 minutos (`token_expiracion`); el token/código se limpia al usarlo (no reutilizable).
- El enlace/código solo se muestra en pantalla con `APP_DEBUG=true` (modo local); en producción se desactiva.
- La nueva contraseña se guarda con `password_hash` y los tokens se invalidan al cambiar la clave.

## Recomendaciones pendientes

| Prioridad | Recomendación |
|---|---|
| Media | Indexar `pulsaciones.fecha_hora` y consultar rangos con `BETWEEN` sobre el timestamp completo (evitar `DATE(col)`) para volumen >200k filas. |
| Media | Añadir `headers` de seguridad: `X-Content-Type-Options: nosniff`, `X-Frame-Options`, CSP. |
| Media | **Zona horaria**: `pulsaciones.fecha_hora` se guarda con la hora del sistema MySQL (en el entorno de prueba aparece UTC), mientras que PHP muestra `Europe/Berlin`. Conviene alinear `time_zone` en MySQL y `date.timezone` en PHP. |
| Media | Bloquear `cli/crear_usuario.php` en el servidor web (solo CLI; ya se bloquea por `PHP_SAPI`) y no exponer `.env`. |
| Media | **SMS real**: la recuperación por SMS requiere un proveedor de pago (TextBelt/Twilio) o el método email-a-SMS; ver `09-sms-gratuitos.md`. |
| Baja | Limitar tamaño de body en el endpoint para prevenir abuso. |
| Baja | Rate limiting o captcha si el kiosco estará expuesto a internet. |
| Baja | Rate limiting en `recuperar.php` para evitar abuso de envío de correos/SMS. |
| Baja | Considerar mover `.env` fuera de la raíz web (ej. `~/.env`) o restringir acceso vía servidor web. |
| Ops | Rota las contraseñas actuales de MySQL que quedaron expuestas en el historial del proyecto. |
