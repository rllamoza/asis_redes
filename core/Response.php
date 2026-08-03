<?php
declare(strict_types=1);

final class Respuesta
{
    public static function json(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos);
        exit;
    }

    public static function ok(mixed $datos = null, int $codigo = 200): void
    {
        self::json(['success' => true, 'data' => $datos], $codigo);
    }

    public static function error(string $mensaje, int $codigo = 400): void
    {
        self::json(['success' => false, 'message' => $mensaje], $codigo);
    }

    public static function requiereMetodo(string $metodo): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $metodo) {
            self::error('Método no permitido', 405);
        }
    }
}
