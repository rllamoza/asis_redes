<?php
declare(strict_types=1);

final class Session
{
    private const INACTIVIDAD = 1800;
    private const CSRF_VIDA = 1800;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function usuarioActual(): ?array
    {
        if (empty($_SESSION['usuario_id'])) {
            return null;
        }

        if (isset($_SESSION['ultimo_acceso']) && time() - (int) $_SESSION['ultimo_acceso'] > self::INACTIVIDAD) {
            self::destruir();
            return null;
        }

        $_SESSION['ultimo_acceso'] = time();

        return [
            'id' => (int) $_SESSION['usuario_id'],
            'email' => $_SESSION['usuario_email'],
            'nombre' => $_SESSION['usuario_nombre'] ?? '',
            'rol' => $_SESSION['usuario_rol'] ?? 'usuario',
        ];
    }

    public static function iniciar(array $usuario): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'] ?? '';
        $_SESSION['usuario_rol'] = $usuario['rol'] ?? 'usuario';
        $_SESSION['ultimo_acceso'] = time();
    }

    public static function destruir(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function requiereLogin(): void
    {
        if (self::usuarioActual() === null) {
            header('Location: index.php?ruta=login');
            exit;
        }
    }

    public static function requiereLoginApi(): void
    {
        if (self::usuarioActual() === null) {
            Respuesta::error('No autenticado', 401);
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token']) || (!empty($_SESSION['csrf_expira']) && time() > (int) $_SESSION['csrf_expira'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_expira'] = time() + self::CSRF_VIDA;
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfValido(?string $token): bool
    {
        if ($token === null || empty($_SESSION['csrf_token'])) {
            return false;
        }
        if (!empty($_SESSION['csrf_expira']) && time() > (int) $_SESSION['csrf_expira']) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function campoCsrf(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrfToken()) . '">';
    }
}
