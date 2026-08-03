<?php
declare(strict_types=1);

const BASE_DIR = __DIR__ . '/..';
const CORE_DIR = __DIR__;
const MODULES_DIR = BASE_DIR . '/modules';
const STORAGE_DIR = BASE_DIR . '/storage';

$envFile = BASE_DIR . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[-1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv("$name=$value");
    }
}

spl_autoload_register(static function (string $clase): void {
    static $mapa = null;
    if ($mapa === null) {
        $mapa = [
            'Database' => CORE_DIR . '/Database.php',
            'Session' => CORE_DIR . '/Session.php',
            'Respuesta' => CORE_DIR . '/Response.php',
            'Notificaciones' => CORE_DIR . '/Notificaciones.php',
            'KioscoService' => MODULES_DIR . '/kiosco/KioscoService.php',
            'AuthService' => MODULES_DIR . '/auth/AuthService.php',
            'InformesService' => MODULES_DIR . '/informes/InformesService.php',
        ];
    }
    if (isset($mapa[$clase])) {
        require_once $mapa[$clase];
    }
});

require_once CORE_DIR . '/helpers.php';

Session::start();
