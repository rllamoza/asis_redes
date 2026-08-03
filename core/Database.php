<?php
declare(strict_types=1);

final class Database
{
    private static ?mysqli $conn = null;

    public static function conexion(): mysqli
    {
        if (self::$conn === null) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
                $conn = new mysqli(
                    config('MYSQL_HOST', 'localhost'),
                    config('MYSQL_USER', 'root'),
                    config('MYSQL_PASSWORD', ''),
                    config('MYSQL_DATABASE', 'jowfbilo_contador'),
                    (int) config('MYSQL_PORT', '3306')
                );
                $conn->set_charset('utf8mb4');
                self::$conn = $conn;
            } catch (mysqli_sql_exception $e) {
                http_response_code(500);
                exit('Error de conexión a la base de datos.');
            }
        }
        return self::$conn;
    }

    public static function cerrar(): void
    {
        if (self::$conn !== null) {
            self::$conn->close();
            self::$conn = null;
        }
    }
}
