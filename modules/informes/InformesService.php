<?php
declare(strict_types=1);

final class InformesService
{
    public function rangoFechas(?string $desde, ?string $hasta): array
    {
        $desde = $desde !== null && $desde !== '' ? trim($desde) : date('Y-m-d', strtotime('-7 days'));
        $hasta = $hasta !== null && $hasta !== '' ? trim($hasta) : date('Y-m-d');

        if (!esFechaValida($desde) || !esFechaValida($hasta)) {
            $desde = date('Y-m-d', strtotime('-7 days'));
            $hasta = date('Y-m-d');
        }

        return [$desde, $hasta];
    }

    public function contadores(string $desde, string $hasta): array
    {
        $conn = Database::conexion();
        $stmt = $conn->prepare('SELECT id, nombre, contador, ultima_pulsacion FROM contadores WHERE DATE(ultima_pulsacion) BETWEEN ? AND ?');
        $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function pulsaciones(string $desde, string $hasta): array
    {
        $conn = Database::conexion();
        $stmt = $conn->prepare('SELECT id, nombre_persona, fecha_hora, ip_usuario, user_agent FROM pulsaciones WHERE DATE(fecha_hora) BETWEEN ? AND ?');
        $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function dashboard(string $desde, string $hasta): array
    {
        $conn = Database::conexion();

        $stmt = $conn->prepare(
            'SELECT
                nombre_persona,
                SUM(CASE WHEN TIME(fecha_hora) BETWEEN \'07:00:00\' AND \'11:00:00\' THEN 1 ELSE 0 END) as primer_servicio,
                SUM(CASE WHEN TIME(fecha_hora) BETWEEN \'11:01:00\' AND \'14:00:00\' THEN 1 ELSE 0 END) as segundo_servicio
            FROM pulsaciones
            WHERE DATE(fecha_hora) BETWEEN ? AND ?
            GROUP BY nombre_persona
            ORDER BY (primer_servicio + segundo_servicio) DESC
            LIMIT 5'
        );
        $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        $top = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare('SELECT DATE(fecha_hora) as dia, COUNT(*) as cantidad FROM pulsaciones WHERE DATE(fecha_hora) BETWEEN ? AND ? GROUP BY dia ORDER BY dia');
        $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        $por_dia = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare('SELECT CASE WHEN TIME(fecha_hora) BETWEEN \'07:00:00\' AND \'11:00:00\' THEN \'Primer Servicio\' ELSE \'Segundo Servicio\' END as servicio, COUNT(*) as cantidad FROM pulsaciones WHERE DATE(fecha_hora) BETWEEN ? AND ? AND TIME(fecha_hora) BETWEEN \'07:00:00\' AND \'14:00:00\' GROUP BY servicio');
        $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        $servicios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare('SELECT COUNT(*) as total FROM pulsaciones WHERE DATE(fecha_hora) BETWEEN ? AND ?');
        $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];

        return [
            'top_personas' => $top,
            'asistencias_por_dia' => $por_dia,
            'servicios' => $servicios,
            'total' => $total,
        ];
    }

    public function datosServidor(): array
    {
        $conn = Database::conexion();

        $valorEstado = static function (string $variable) use ($conn): string {
            $row = $conn->query("SHOW GLOBAL STATUS LIKE '" . $conn->real_escape_string($variable) . "'")->fetch_assoc();
            return $row['Value'] ?? '';
        };

        $disco_ruta = BASE_DIR;
        $disco_libre = disk_free_space($disco_ruta);
        $disco_total = disk_total_space($disco_ruta);

        $extensiones = get_loaded_extensions();
        sort($extensiones);

        return [
            'hora' => [
                'php_hora' => date('Y-m-d H:i:s'),
                'php_zona' => date_default_timezone_get(),
                'php_desplazamiento' => date('P'),
                'mysql_now' => $conn->query('SELECT NOW() AS ahora')->fetch_assoc()['ahora'],
                'mysql_zona' => $conn->query('SELECT @@session.time_zone AS tz')->fetch_assoc()['tz'],
                'nota' => 'La hora guardada en cada pulsación (pulsaciones.fecha_hora) es la hora MySQL (NOW) al momento del registro.',
            ],
            'base_datos' => [
                'nombre' => config('MYSQL_DATABASE', 'jowfbilo_contador'),
                'servidor' => $conn->server_info,
                'conexion' => $conn->host_info,
                'hostname' => $conn->query('SELECT @@hostname AS h')->fetch_assoc()['h'],
                'charset' => $conn->character_set_name(),
                'uptime' => $valorEstado('Uptime'),
                'conexiones_activas' => $valorEstado('Threads_connected'),
                'max_conexiones' => $valorEstado('Max_used_connections'),
            ],
            'servidor_web' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/D',
                'sapi' => php_sapi_name(),
                'host' => $_SERVER['HTTP_HOST'] ?? 'N/D',
                'nombre' => $_SERVER['SERVER_NAME'] ?? 'N/D',
                'ip_servidor' => $_SERVER['SERVER_ADDR'] ?? 'N/D',
                'ip_cliente' => $_SERVER['REMOTE_ADDR'] ?? 'N/D',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/D',
                'archivo' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/D',
            ],
            'sistema_php' => [
                'os' => php_uname(),
                'version' => PHP_VERSION,
                'binario' => PHP_BINARY,
                'memoria' => ini_get('memory_limit'),
                'max_ejecucion' => ini_get('max_execution_time') . ' s',
                'subida' => ini_get('upload_max_filesize'),
                'post' => ini_get('post_max_size'),
                'error_log' => ini_get('error_log') ?: 'N/D',
                'display_errors' => ini_get('display_errors'),
                'timezone' => ini_get('date.timezone') ?: 'N/D',
            ],
            'almacenamiento' => [
                'ruta' => $disco_ruta,
                'total' => $disco_total !== false ? (float) $disco_total : 0.0,
                'libre' => $disco_libre !== false ? (float) $disco_libre : 0.0,
            ],
            'extensiones' => $extensiones,
        ];
    }
}
