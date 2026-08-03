# 08 — Pruebas

## Entorno de prueba

- PHP 8.2.12, servidor embebido (`php -S localhost:8080` / `127.0.0.1:8089`).
- BD real `jowfbilo_contador` (pruebas de solo lectura) y BD desechable `conteo_test` (para el flujo de escritura). La BD de prueba fue eliminada al terminar.
- Verificación de sintaxis con `php -l` en todos los archivos modificados: **sin errores**.

## Resultados verificados — arquitectura modular (2026-08-02)

Entorno aislado: copia de la app en `Temp\opencode\conteo_test_app` + BD desechable `conteo_test` (usuario `root`), servidor embebido `php -S localhost:8080`. Los datos reales no se tocaron.

### Kiosco (público)

| Caso | HTTP | Resultado |
|---|---|---|
| `GET ?ruta=kiosco` | 200 | Botones con badges (TESTPERSON contador=5) |
| `POST ?ruta=kiosco/api&action=pulsar` (GET) | 405 | `Método no permitido` |
| `POST` sin `nombre` | 400 | `Nombre no proporcionado` |
| `POST nombre=NOEXISTE` | 404 | `La persona no existe` |
| `POST nombre=TESTPERSON` | 200 | `{"success":true,"contador":6}` |
| `GET ?ruta=noexiste` | 404 | Ruta desconocida |
| `GET /assets/css/kiosco.css` y `auth.css` | 200 | Assets servidos |

### Informes (protegido)

| Caso | HTTP | Resultado |
|---|---|---|
| `GET ?ruta=informes` sin sesión | 302 → `?ruta=login` | Redirige a login |
| `GET ?ruta=login` | 200 | Formulario con token CSRF |
| `POST ?ruta=login` (admin@test.com / Admin123) | 302 → `?ruta=informes` | Sesión creada |
| `GET ?ruta=informes` con sesión | 200 | Vista cargada |
| `GET ?ruta=dashboard` con sesión | 200 | Vista cargada |
| `GET ?ruta=servidor` con sesión | 200 | Tarjetas de datos del hosting |
| `GET ?ruta=informes/api&action=contadores` con sesión | 200 | JSON `{success:true,data:[...]}` |
| `GET ?ruta=informes/api&action=dashboard` | 200 | Agregados para gráficos |
| `GET ?ruta=informes/api&action=pulsaciones` | 200 | Filas de pulsaciones |
| `GET ?ruta=informes/api&action=servidor` | 200 | Datos del servidor |
| `GET ?ruta=informes/api&action=contadores&fecha_inicio=<maliciosa>` | 200 | Fecha inválida rechazada → valores por defecto |
| `GET ?ruta=informes/api` tras logout | 302 → `?ruta=login` | API protegida |
| `GET /assets/css/dashboard.css` | 200 | Asset servido |

### Autenticación (vista + API JSON)

| Caso | HTTP | Resultado |
|---|---|---|
| `POST ?ruta=registro` (usuario nuevo) | 302 → `?ruta=login&registrado=1` | Usuario creado con rol `usuario` |
| `POST ?ruta=recuperar` (email existente, medio=email) | 200 | Muestra enlace dev `restablecer&token=…` |
| `GET ?ruta=restablecer&token=<válido>` | 200 | Formulario de nueva contraseña |
| `POST ?ruta=restablecer` (token + clave nueva) | 302 → `?ruta=login&restablecida=1` | Clave actualizada |
| `POST ?ruta=login` con la clave nueva | 302 → `?ruta=informes` | Login correcto |
| `POST ?ruta=recuperar` (teléfono existente, medio=sms) | 200 | Muestra código dev de 6 dígitos |
| `POST ?ruta=login` **sin `csrf_token`** | 200 | Rechazado: "Sesión caducada o petición inválida" |
| `POST ?ruta=auth/api&action=login` (credenciales ok) | 200 | `{"success":true,"data":{"usuario":…}}` |
| `POST ?ruta=auth/api&action=registro` (usuario nuevo) | 201 | `{"success":true,"data":null}` |
| `POST ?ruta=auth/api&action=logout` | 200 | `{"success":true,"data":{"logout":true}}` |
| `POST ?ruta=auth/api&action=login` **sin CSRF** | 403 | Rechazado |
| `GET ?ruta=logout` | 302 → `?ruta=login` | Sesión destruida |
| `GET ?ruta=informes` tras logout | 302 → `?ruta=login` | Protegido |

### Notificaciones (modo local)

Con `SMTP_HOST` vacío se escribieron `storage/logs/correo_*.html` y, para WhatsApp, `storage/logs/whatsapp_*.txt` correctamente. La herramienta de `?ruta=servidor` genera enlaces `https://wa.me/<número>?text=<mensaje>` válidos y valida teléfono/email y CSRF.

## WhatsApp (vía enlaces) — recuperación

| Caso | HTTP | Resultado |
|---|---|---|
| `POST ?ruta=recuperar` (teléfono existente, medio=whatsapp) | 200 | Muestra enlace `wa.me` + código dev |
| Enlace generado | — | `https://wa.me/51000000000?text=Tu%20codigo…` |
| `POST ?ruta=recuperar` con `medio=sms` (ya eliminado) | 200 | Cae al flujo de correo (no existe opción sms en la vista) |
| Herramienta Servidor: `POST ?ruta=servidor` tipo=whatsapp | 200 | Muestra "Enlace generado" con el enlace enlazado |
| Herramienta Servidor: teléfono inválido | 200 | Rechazado: "Ingresa un teléfono válido…" |
| Herramienta Servidor: sin `csrf_token` | 200 | Rechazado: "Sesión caducada…" |

## Resultados verificados (fase previa — endpoint de pulsación)

| Caso | Request | HTTP esperado | Respuesta |
|---|---|---|---|
| Método no permitido | `GET` | 405 | `{"success":false,"message":"Método no permitido"}` |
| Nombre vacío | `POST` sin body | 400 | `{"success":false,"message":"Nombre no proporcionado"}` |
| Nombre inexistente | `POST nombre=NOEXISTE_TEST_XYZ` | 404 | `{"success":false,"message":"La persona no existe"}` |
| Nombre muy largo | `POST nombre=<53 A>` | 400 | `{"success":false,"message":"Nombre demasiado largo"}` |
| Pulsación 1 (BD de prueba) | `POST nombre=TESTPERSON` | 200 | `{"success":true,"contador":1}` |
| Pulsación 2 (BD de prueba) | `POST nombre=TESTPERSON` | 200 | `{"success":true,"contador":2}` |

**Verificación en BD tras el flujo positivo:** `contador = 2`, `pulsaciones = 2` filas con `ip_usuario = 127.0.0.1` y `user_agent = curl/8.14.1` correctamente persistidos.

## Resultados verificados (fase previa — autenticación y servidor)

| Caso | HTTP | Resultado |
|---|---|---|
| Acceso a informes sin sesión | 302 → `login.php` | Redirige correctamente |
| `GET login.php` | 200 | Formulario cargado |
| `POST login.php` credenciales correctas | 302 → `index.php` | Sesión creada |
| `server.php` con sesión | 200 | Muestra hora PHP, hora MySQL (NOW), SO, PHP, MySQL, disco, extensiones |
| `login.php?logout=1` | 302 → `login.php` | Sesión destruida |
| `POST login.php` credenciales incorrectas | 200 | Mensaje "Credenciales incorrectas." |

## Casos de regresión (para pruebas manuales futuras)

1. **Kiosco**: cada botón incrementa su badge en 1 sin recargar la página.
2. **Kiosco**: doble clic rápido no debe duplicar pulsaciones (el botón se deshabilita durante la petición).
3. **Kiosco**: `VIDALON` no aparece entre los botones.
4. **Informe**: el filtro de fechas devuelve solo el rango indicado.
5. **Dashboard**: los gráficos se renderizan y cambian con el filtro.
6. **Exportación**: los botones CSV/Excel/PDF de las tablas funcionan.
7. **Login**: sin sesión ninguna página de informe debe cargar (siempre redirige a login).
8. **Login**: tras cerrar sesión, volver atrás en el navegador no debe mostrar datos.
9. **Servidor**: el botón "Servidor" del header abre `?ruta=servidor` y muestra la hora del servidor.
10. **Registro**: al registrarse con un correo duplicado muestra "Ya existe una cuenta con ese correo.".
11. **Recuperación**: con un token/código caducado (esperar 30 min o alterar `token_expiracion` en BD) muestra "El enlace/código ha caducado.".
12. **Recuperación**: el enlace/código no debe funcionar dos veces (se limpia al usarlo).
13. **Recuperación**: `APP_DEBUG=false` debe ocultar el enlace/código de pantalla.
14. **Rutas**: `?ruta=noexiste` debe dar 404; las vistas del kiosco/login siguen siendo públicas.
15. **API auth**: las acciones de `?ruta=auth/api` deben devolver `403` sin token CSRF.

## Cómo reproducir las pruebas

```powershell
# 1. Levantar el servidor
C:\xampp\php\php.exe -S 127.0.0.1:8089 -t C:\xampp\htdocs\conteo

# 2. Endpoint (casos de error, no alteran datos reales)
curl "http://127.0.0.1:8089/index.php?ruta=kiosco/api"
curl -X POST -d "nombre=NOEXISTE" "http://127.0.0.1:8089/index.php?ruta=kiosco/api&action=pulsar"

# 3. Páginas
curl -o NUL -w "%{http_code}" "http://127.0.0.1:8089/index.php?ruta=kiosco"
curl -o NUL -w "%{http_code}" "http://127.0.0.1:8089/index.php?ruta=informes"
curl -o NUL -w "%{http_code}" "http://127.0.0.1:8089/index.php?ruta=dashboard"
```
