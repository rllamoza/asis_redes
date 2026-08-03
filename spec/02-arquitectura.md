# 02 — Arquitectura

## Patrón

Aplicación PHP **modular** con front controller y API JSON por módulo. Un único punto de entrada (`index.php`) despacha rutas hacia los módulos, cada módulo tiene su propia carpeta con **servicio** (lógica), **vistas** (HTML renderizado en servidor) y **API** (endpoints JSON). No hay framework ni ORM: se usa `mysqli` directamente con *prepared statements*.

## Estructura de carpetas

```
C:\xampp\htdocs\conteo\
├── .env                     Credenciales y configuración (no subir a repos)
├── index.php                FRONT CONTROLLER — único punto de entrada (enruta ?ruta=…)
├── core\                    Infraestructura compartida (bootstrap, BD, sesión, utilidades)
│   ├── bootstrap.php        Carga .env, registra autoloader, inicia sesión
│   ├── Database.php         Conexión singleton (mysqli, utf8mb4, errores estrictos)
│   ├── Session.php          Sesión, usuarioActual(), requiereLogin(), CSRF
│   ├── Response.php         Respuestas JSON uniformes (Respuesta::ok/error)
│   ├── helpers.php          config(), base_url(), esFechaValida()
│   └── Notificaciones.php   Envío de correo (SMTP/mail/log) y SMS (dev/TextBelt/email-gateway/Twilio)
├── modules\
│   ├── kiosco\              Módulo público: kiosco de pulsación
│   │   ├── KioscoService.php  listar personas, pulsar (transacción)
│   │   ├── views\kiosco.php   vista del kiosco (botones)
│   │   └── api\kiosco.php     POST pulsar (JSON)
│   ├── auth\                Módulo de autenticación
│   │   ├── AuthService.php    login, registro, recuperación, restablecer
│   │   ├── views\            login, registro, recuperar, restablecer, logout
│   │   └── api\auth.php       POST login/registro/recuperar/restablecer/logout (JSON)
│   └── informes\            Módulo protegido: informes, dashboard y servidor
│       ├── InformesService.php  contadores, pulsaciones, dashboard, datosServidor
│       ├── views\
│       │   ├── layout\header.php  exige login + sidebar + botón "Servidor" + logout
│       │   ├── layout\footer.php
│       │   ├── informes.php       tablas DataTables (cargan datos vía API)
│       │   ├── dashboard.php      gráficos Chart.js (cargan datos vía API)
│       │   └── servidor.php       tarjetas de datos del hosting
│       └── api\informes.php       GET contadores/pulsaciones/dashboard/servidor (JSON)
├── assets\css\              CSS globales: kiosco.css, auth.css, style/menu/dashboard.css
├── cli\crear_usuario.php    Script CLI para crear/actualizar usuarios del login
├── storage\logs\            Logs de correo/SMS en modo local (se crea automáticamente)
└── spec\                    Este spec kit (documentación)
```

## Rutas (front controller `index.php`)

| `?ruta=` | Tipo | Módulo | Archivo |
|---|---|---|---|
| `kiosco` | vista | kiosco | `modules/kiosco/views/kiosco.php` |
| `kiosco/api` | api | kiosco | `modules/kiosco/api/kiosco.php` |
| `login` / `logout` / `registro` / `recuperar` / `restablecer` | vista | auth | `modules/auth/views/*.php` |
| `auth/api` | api | auth | `modules/auth/api/auth.php` |
| `informes` / `dashboard` / `servidor` | vista | informes | `modules/informes/views/*.php` |
| `informes/api` | api | informes | `modules/informes/api/informes.php` |

Cualquier otra ruta devuelve `404`. Los endpoints API de `informes` y el layout del módulo informes exigen login (`Session::requiereLogin()` → `302` a `?ruta=login`).

## Flujo de datos

### 1. Carga de una página

```
Navegador → index.php?ruta=kiosco
  → core/bootstrap.php
      → lee .env (credenciales)
      → autoloader de clases (Database, Session, servicios)
      → Session::start()
  → despacha → modules/kiosco/views/kiosco.php
      → KioscoService::personas()  (SELECT contadores)
  → renderiza HTML (botones con badge de contador)
```

### 2. Pulsación (AJAX)

```
Clic en botón → jQuery ($.ajax POST)
  → index.php?ruta=kiosco/api&action=pulsar
      → Respuesta::requiereMetodo('POST')  (405 si no es POST)
      → KioscoService::pulsar($nombre)
          → transacción:
              1. UPDATE contadores SET contador = contador + 1 WHERE nombre = ?
                 (si affected_rows = 0 → rollback + 404 "La persona no existe")
              2. INSERT INTO pulsaciones (nombre_persona, ip_usuario, user_agent)
              3. SELECT contador actualizado
          → commit
      → JSON { success, contador }
  → jQuery actualiza el badge del botón
```

### 3. Login (módulo auth)

```
Navegador → index.php?ruta=informes (sin sesión)
  → layout/header.php → Session::requiereLogin()
      → usuarioActual() = null → redirige a index.php?ruta=login
  → POST index.php?ruta=login (email + password + csrf)
      → Session::csrfValido() + AuthService::login()
      → verifica contra tabla usuarios (password_hash / password_verify)
      → Session::iniciar() (session_regenerate_id + variables de sesión)
      → redirige a index.php?ruta=informes
  → páginas protegidas (informes/dashboard/servidor) requieren sesión activa
  → "Salir" → index.php?ruta=logout destruye la sesión
```

### 4. Informes (vistas consumen la API del módulo)

```
Navegador → index.php?ruta=informes (con sesión)
  → layout/header.php (exige login)
  → la vista renderiza tablas vacías con DataTables
  → JS llama a index.php?ruta=informes/api&action=contadores&fecha_inicio=…&fecha_fin=…
      → InformesService::contadores() (prepared statements, rango validado)
      → JSON { success, data: [...] }
  → DataTables rellena las tablas; botones de exportación intactos
  → dashboard.php igual pero con action=dashboard (Chart.js)
```

### 5. Página del servidor

```
Navegador → index.php?ruta=servidor (con sesión)
  → InformesService::datosServidor() devuelve un array:
      hora PHP, hora MySQL (NOW = hora de pulsación), SHOW GLOBAL STATUS,
      php_uname(), $_SERVER, ini_get(), disk_free_space(), get_loaded_extensions()
  → la vista renderiza tarjetas (también disponible como JSON en informes/api&action=servidor)
```

## Módulos y responsabilidades

| Archivo | Responsabilidad |
|---|---|
| `core/bootstrap.php` | Cargar `.env`, autoloader, iniciar sesión |
| `core/Database.php` | Conexión `mysqli` singleton (`Database::conexion()`) |
| `core/Session.php` | Sesión segura, `usuarioActual()`, `requiereLogin()`, token CSRF |
| `core/Response.php` | `Respuesta::json/ok/error/requiereMetodo` |
| `core/Notificaciones.php` | Enviar correo (SMTP/mail) y SMS (dev/TextBelt/email-gateway/Twilio) |
| `modules/kiosco/KioscoService.php` | Listar personas (excluye `VIDALON`), pulsar en transacción |
| `modules/kiosco/api/kiosco.php` | Endpoint JSON `pulsar` |
| `modules/auth/AuthService.php` | Login, registro, solicitar/consumir recuperación |
| `modules/auth/views/*.php` | Formularios de login/registro/recuperar/restablecer + logout |
| `modules/auth/api/auth.php` | Endpoints JSON `login/registro/recuperar/restablecer/logout` |
| `modules/informes/InformesService.php` | Consultas contadores, pulsaciones, dashboard, datos del servidor |
| `modules/informes/views/*.php` | Informes (DataTables vía API), dashboard (Chart.js vía API), servidor |
| `modules/informes/api/informes.php` | Endpoints JSON `contadores/pulsaciones/dashboard/servidor` |
| `index.php` | Front controller: mapa de rutas → despacho a vista o API |
| `cli/crear_usuario.php` | Script CLI: crear/actualizar usuarios del login (tabla `usuarios`) |

## Dependencias (CDN)

- **Bootstrap 5.3.0** (CSS + JS) — kiosco
- **jQuery 3.6.0 / 3.5.1** — AJAX del kiosco y DataTables
- **Font Awesome 6.x** — iconos
- **Google Fonts Poppins** — tipografía del kiosco
- **DataTables 1.11.5 + Buttons 2.2.2** — tablas y exportación
- **JSZip, pdfmake** — exportación Excel/PDF
- **Chart.js** — gráficos del dashboard

> Nota: las dependencias se cargan desde CDN; requieren conexión a internet.
