# 01 — Visión General

## Propósito

Sistema web para **control de asistencia por pulsaciones**: cada persona registra su presencia tocando un botón con su nombre. El sistema acumula el número total de pulsaciones por persona y guarda el historial con fecha, hora, IP y agente de usuario. Un módulo de informes permite consultar y exportar los datos.

## Usuarios

- **Operadores del kiosco**: pulsan los botones por cada persona que participa.
- **Administradores/reportes**: consultan informes y gráficos en `/informe_asis/`.

## Alcance

### Incluido
- Registro de pulsación por persona (kiosco).
- Contador acumulado por persona.
- Historial completo de pulsaciones con metadatos (IP, user agent).
- Informes tabulares con filtro por rango de fechas y exportación (copiar, CSV, Excel, PDF, imprimir).
- Dashboard con gráficos: Top 5 personas, asistencias por día, distribución por servicio y total.

### No incluido
- CRUD de personas (las personas se administran directamente en la tabla `contadores`).
- Multi-idioma.
- Autenticación en el kiosco público de pulsación (el kiosco es de acceso abierto; el login protege el módulo de informes).

## Requisitos

- PHP >= 8.0 (desarrollado y probado con PHP 8.2).
- MySQL o MariaDB.
- Servidor web (Apache en XAMPP, o el servidor embebido de PHP para desarrollo).
- Extensiones PHP: `mysqli`, `mbstring` (opcional pero recomendado para validaciones).

## Decisiones de diseño

1. **Vista de kiosco limpia**: la vista deseada por el cliente era la de la antigua `index2.php` (solo botones de personas). `index2.php` fue fusionado en `index.php` y borrado; `index.php` es ahora el **front controller** del proyecto.
2. **Configuración centralizada**: las credenciales de BD viven únicamente en `.env` y se cargan en `core/bootstrap.php`. Antes estaban hardcodeadas en 4 archivos.
3. **Arquitectura modular**: un único punto de entrada (`index.php`) con rutas `?ruta=…`; cada módulo (`kiosco`, `auth`, `informes`) vive en `modules/` con su servicio, vistas y API JSON. La lógica de negocio está en `core/`.
4. **Seguridad por defecto**: todas las consultas con *prepared statements*, salida escapada, validación de fechas y entradas, tokens CSRF en todos los formularios.
5. **Acceso controlado**: el kiosco de pulsación es público; el módulo de informes requiere inicio de sesión con usuarios de la tabla `usuarios` (rol `admin` o `usuario`).
6. **Información del servidor**: la página `servidor` muestra la hora del servidor (PHP y MySQL, que es la que se guarda en cada pulsación), datos del hosting y del stack.
