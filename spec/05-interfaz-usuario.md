# 05 — Interfaz de Usuario

> Todas las vistas se sirven vía front controller: `index.php?ruta=kiosco|login|registro|recuperar|restablecer|informes|dashboard|servidor|logout`.

## Kiosco — `?ruta=kiosco`

Vista limpia de **solo botones** (basada en la antigua `index2.php`). Es la pantalla que el cliente pidió como principal.

### Elementos

1. **Encabezado**: título "Control de Asistencia" con degradado azul/violeta.
2. **Rejilla de botones** (`row g-3`): un botón por persona, excluyendo las de la lista `$personasExcluidas` (`['VIDALON']`).
   - Cada botón muestra: icono de usuario, nombre (truncado a 80px) y un badge rojo con el contador acumulado.
   - Layout responsive: 2 columnas (móvil) hasta 6 columnas (desktop).
3. **Comportamiento al pulsar**:
   - El botón se deshabilita y muestra un spinner (`btn-loading`) mientras se envía el AJAX.
   - Si hay éxito: el badge se actualiza con el nuevo contador y el botón parpadea en verde 1 segundo.
   - Si hay error: muestra `alert()` con el mensaje de la API.
   - Al terminar, el botón se rehabilita.

### Notas

- La exclusión de personas se configura en el array `$personasExcluidas` al inicio del archivo.
- No hay buscador ni estadísticas (fueron retirados en la vista acordada).

## Informes — `?ruta=informes`

- **Filtro de fechas**: formulario GET con `fecha_inicio` y `fecha_fin` (por defecto últimos 7 días).
- **Tabla Contadores**: ID, Nombre, Contador, Última Pulsación.
- **Tabla Pulsaciones**: ID, Nombre Persona, Fecha y Hora, IP, User Agent.
- Ambas tablas usan **DataTables** con botones de exportación: copiar, CSV, Excel, PDF e imprimir.

## Dashboard — `?ruta=dashboard`

- **Filtro de fechas** (mismo comportamiento).
- **Top 5 Personas**: gráfico de barras apiladas (primer servicio púrpura, segundo servicio rosa).
- **Asistencias por Día**: gráfico de líneas.
- **Distribución de Servicios**: gráfico de dona (Primer vs Segundo Servicio, 07:00–14:00).
- **Total Asistencias**: tarjeta con indicador circular.

### Definición de servicios

| Servicio | Franja horaria |
|---|---|
| Primer Servicio | 07:00:00 – 11:00:00 |
| Segundo Servicio | 11:01:00 – 14:00:00 |

## Login — `?ruta=login`

- Pantalla independiente con degradado púrpura y tarjeta centrada.
- Campos: correo electrónico y contraseña + token CSRF oculto.
- Muestra mensaje de error si las credenciales son incorrectas.
- Enlace "Salir" (en el header) redirige a `login.php?logout=1`.
- Muestra avisos de éxito: `login.php?restablecida=1` ("Contraseña restablecida") y `login.php?registrado=1` ("Cuenta creada").
- Enlaces al pie: "¿Olvidaste tu contraseña?" (→ `recuperar.php`) y "Registrarse" (→ `registro.php`, solo si `APP_REGISTRO_ABIERTO=true`).

## Registro — `?ruta=registro`

- Misma tarjeta visual que el login, icono `user-plus`, título "Crear cuenta".
- Campos: nombre, correo, teléfono (opcional, para WhatsApp), contraseña (mín. 6) y confirmación.
- Errores de validación en pantalla (email inválido, contraseñas que no coinciden, correo ya registrado, teléfono mal formado).
- Éxito: redirige a `login.php?registrado=1`.
- Inaccesible si `APP_REGISTRO_ABIERTO=false` (redirige a login).

## Recuperar — `?ruta=recuperar`

- Tarjeta centrada con icono `key`, título "Recuperar contraseña".
- Selector de medio: **Por correo (enlace)** o **Por WhatsApp (vía enlace)**; el campo de abajo cambia de `email` a `telefono` según la selección (JS).
- Nunca revela si la cuenta existe: siempre muestra "Si tu correo o teléfono está registrado, recibirás las instrucciones…".
- Con `APP_DEBUG=true` muestra una franja amarilla con el enlace (correo) o el enlace de WhatsApp + código para probar en local.
- Enlace de vuelta al login.

## Restablecer — `?ruta=restablecer`

- Tarjeta centrada con icono `lock`, título "Restablecer contraseña".
- Recibe `?token=…` (enlace del correo) o `?codigo=…&email=…` (WhatsApp).
- Campos: nueva contraseña (mín. 6) y confirmación.
- Errores: enlace/código inválido o caducado, contraseñas que no coinciden.
- Éxito: redirige a `login.php?restablecida=1`.

## Layout del módulo de informes

- Sidebar con iconos (Inicio → `?ruta=informes`, Gráficos → `?ruta=dashboard`, **Servidor → `?ruta=servidor`**).
- Header superior (parte de arriba) con:
  - Pestañas: **Informes**, **Gráficos** y un **botón "Servidor"** (píldora con icono de servidor) que abre `?ruta=servidor`.
  - A la derecha: usuario logueado (email) y enlace **Salir**.
- CSS en `assets/css/` (estilos de menú `.navbar` sin uso en el layout actual).

## Página del servidor — `?ruta=servidor`

Tarjetas de información:

| Tarjeta | Datos |
|---|---|
| **Hora del Servidor** (destacada, degradado) | Hora actual PHP, **hora MySQL (NOW = la que se guarda en cada pulsación)**, zona horaria PHP y MySQL, desplazamiento UTC |
| Base de Datos | Nombre BD, versión MySQL, conexión, hostname, charset, uptime, conexiones activas y máximas |
| Servidor Web | Software, SAPI, host, nombre, IP del servidor y del cliente, document root, archivo ejecutado |
| Sistema y PHP | OS (`php_uname`), versión PHP, binario, memory_limit, max_execution_time, subida/POST máx., error_log, display_errors, date.timezone |
| Almacenamiento | Ruta de la app, espacio total/libre y % de uso del disco |
| Extensiones PHP | Chips con todas las extensiones cargadas (39 en el entorno de prueba) |

## Herramienta de envío (en la página Servidor)

Tarjeta "Herramientas de envío" (arriba de las tarjetas de información) con dos formularios POST (protegidos con login + CSRF):

- **Probar correo**: destinatario + asunto opcional → `Notificaciones::enviarCorreo()`. Muestra éxito/error; en modo local indica que no se envió y muestra la ruta y el contenido del log en `storage/logs/`.
- **Probar WhatsApp**: número de teléfono + mensaje opcional → `Notificaciones::enviarWhatsApp()` genera un enlace `https://wa.me/<número>?text=<mensaje>`; la página muestra el enlace enlazado para abrirlo en WhatsApp (el mensaje queda listo para enviar). En modo local también escribe `storage/logs/whatsapp_*.txt`.
