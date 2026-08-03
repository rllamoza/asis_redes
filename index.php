<?php
declare(strict_types=1);

require __DIR__ . '/core/bootstrap.php';

$ruta = $_GET['ruta'] ?? 'kiosco';

$mapa = [
    'kiosco'        => ['tipo' => 'vista', 'modulo' => 'kiosco', 'archivo' => 'kiosco'],
    'kiosco/api'    => ['tipo' => 'api', 'modulo' => 'kiosco', 'archivo' => 'kiosco'],

    'login'         => ['tipo' => 'vista', 'modulo' => 'auth', 'archivo' => 'login'],
    'logout'        => ['tipo' => 'vista', 'modulo' => 'auth', 'archivo' => 'logout'],
    'registro'      => ['tipo' => 'vista', 'modulo' => 'auth', 'archivo' => 'registro'],
    'recuperar'     => ['tipo' => 'vista', 'modulo' => 'auth', 'archivo' => 'recuperar'],
    'restablecer'   => ['tipo' => 'vista', 'modulo' => 'auth', 'archivo' => 'restablecer'],
    'auth/api'      => ['tipo' => 'api', 'modulo' => 'auth', 'archivo' => 'auth'],

    'informes'      => ['tipo' => 'vista', 'modulo' => 'informes', 'archivo' => 'informes'],
    'dashboard'     => ['tipo' => 'vista', 'modulo' => 'informes', 'archivo' => 'dashboard'],
    'servidor'      => ['tipo' => 'vista', 'modulo' => 'informes', 'archivo' => 'servidor'],
    'informes/api'  => ['tipo' => 'api', 'modulo' => 'informes', 'archivo' => 'informes'],
];

if (!isset($mapa[$ruta])) {
    http_response_code(404);
    exit('Ruta no encontrada.');
}

$destino = $mapa[$ruta];
$subcarpeta = $destino['tipo'] === 'api' ? 'api' : 'views';
$archivo = MODULES_DIR . '/' . $destino['modulo'] . '/' . $subcarpeta . '/' . $destino['archivo'] . '.php';

if (!is_file($archivo)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

require $archivo;
