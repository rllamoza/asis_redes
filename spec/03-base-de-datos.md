# 03 — Base de Datos

**Motor:** MySQL/MariaDB · **BD:** `jowfbilo_contador` · **Charset de tablas:** latin1 (heredado) · **Conexión:** `utf8mb4`

## Tabla `contadores`

Contador acumulado por persona.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT (PK) | Identificador |
| `nombre` | VARCHAR(50) NOT NULL | Nombre de la persona (único por fila) |
| `contador` | INT NOT NULL DEFAULT 0 | Total de pulsaciones acumuladas |
| `ultima_pulsacion` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Fecha/hora de la última pulsación (se actualiza automáticamente al modificar la fila) |

```sql
CREATE TABLE `contadores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `contador` int NOT NULL DEFAULT '0',
  `ultima_pulsacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

## Tabla `pulsaciones`

Histórico individual de cada pulsación registrada.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT (PK) | Identificador |
| `nombre_persona` | VARCHAR(50) NOT NULL | Nombre de la persona que pulsó |
| `fecha_hora` | TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP | Momento de la pulsación |
| `ip_usuario` | VARCHAR(45) | Dirección IP del cliente |
| `user_agent` | VARCHAR(255) | Agente de usuario del navegador |

```sql
CREATE TABLE `pulsaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_persona` varchar(50) NOT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_usuario` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

## Relación

No hay claves foráneas. La relación lógica es **1 `contadores` → N `pulsaciones`** por el campo `nombre`.

## Tabla `usuarios`

Usuarios del login del módulo de informes. **Esquema preexistente** en la BD (reutilizado).

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT (PK) | Identificador |
| `nombre` | VARCHAR(100) NOT NULL | Nombre mostrado |
| `email` | VARCHAR(100) NOT NULL UNIQUE | Identificador de login |
| `PASSWORD` | VARCHAR(255) NOT NULL | Hash bcrypt (`password_hash` / `password_verify`) |
| `rol` | ENUM('admin','usuario') DEFAULT 'usuario' | Rol del usuario |
| `telefono` | VARCHAR(20) NULL | Teléfono móvil (opcional, para recuperación por SMS) |
| `token_recuperacion` | VARCHAR(255) NULL | Token aleatorio del enlace de recuperación por correo |
| `codigo_recuperacion` | VARCHAR(10) NULL | Código numérico de 6 dígitos para recuperación por SMS |
| `token_expiracion` | DATETIME NULL | Caducidad del token/código (30 minutos) |
| `fecha_creacion` | TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP | Fecha de alta |

```sql
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `PASSWORD` varchar(255) NOT NULL,
  `rol` enum('admin','usuario') NOT NULL DEFAULT 'usuario',
  `telefono` varchar(20) DEFAULT NULL,
  `token_recuperacion` varchar(255) DEFAULT NULL,
  `codigo_recuperacion` varchar(10) DEFAULT NULL,
  `token_expiracion` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

**Cambios aplicados a la tabla existente** (no afectan a `contadores`/`pulsaciones`):

```sql
ALTER TABLE `usuarios` ADD COLUMN telefono VARCHAR(20) NULL AFTER `rol`;
ALTER TABLE `usuarios` ADD COLUMN codigo_recuperacion VARCHAR(10) NULL AFTER `token_recuperacion`;
```

### Flujo de recuperación de contraseña

1. `recuperar.php` recibe el email **o** el teléfono, localiza al usuario y guarda:
   - `token_recuperacion` = 42 hex (42 bytes) para el enlace por correo.
   - `codigo_recuperacion` = 6 dígitos para el código por SMS.
   - `token_expiracion` = ahora + 30 minutos.
2. `restablecer.php` valida el enlace (`token_recuperacion`) o el par email + `codigo_recuperacion`, comprueba `token_expiracion` y, al guardar la nueva contraseña, limpia las tres columnas (`NULL`). Los tokens no se reutilizan.

## Consultas principales

| Propósito | Consulta |
|---|---|
| Contadores (kiosco) | `SELECT nombre, contador FROM contadores ORDER BY nombre` |
| Incrementar contador | `UPDATE contadores SET contador = contador + 1 WHERE nombre = ?` |
| Insertar pulsación | `INSERT INTO pulsaciones (nombre_persona, ip_usuario, user_agent) VALUES (?, ?, ?)` |
| Contadores por rango | `SELECT id, nombre, contador, ultima_pulsacion FROM contadores WHERE DATE(ultima_pulsacion) BETWEEN ? AND ?` |
| Pulsaciones por rango | `SELECT id, nombre_persona, fecha_hora, ip_usuario, user_agent FROM pulsaciones WHERE DATE(fecha_hora) BETWEEN ? AND ?` |
| Top 5 por servicio | Ver `informe_asis/dashboard.php` (primer servicio 07:00–11:00, segundo 11:01–14:00) |
| Asistencias por día | `SELECT DATE(fecha_hora) AS dia, COUNT(*) AS cantidad FROM pulsaciones WHERE DATE(fecha_hora) BETWEEN ? AND ? GROUP BY dia ORDER BY dia` |
| Total en rango | `SELECT COUNT(*) AS total FROM pulsaciones WHERE DATE(fecha_hora) BETWEEN ? AND ?` |

## Consideraciones

- `DATE(col)` en `WHERE` impide usar índices eficientemente; para tablas muy grandes conviene indexar `fecha_hora` y consultar con rangos de `BETWEEN` sobre el valor completo. El volumen actual supera los 200k registros.
- El contador de `contadores` y el historial de `pulsaciones` podrían desincronizarse si se borran filas manualmente; la aplicación los mantiene consistentes en una transacción.
- La columna `ultima_pulsacion` se actualiza con `ON UPDATE CURRENT_TIMESTAMP` automáticamente cuando cambia `contador`.
