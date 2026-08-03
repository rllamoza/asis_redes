<?php
declare(strict_types=1);

Session::requiereLoginApi();

$servicio = new InformesService();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'contadores':
        [$desde, $hasta] = $servicio->rangoFechas($_GET['fecha_inicio'] ?? null, $_GET['fecha_fin'] ?? null);
        Respuesta::ok($servicio->contadores($desde, $hasta));

    case 'pulsaciones':
        [$desde, $hasta] = $servicio->rangoFechas($_GET['fecha_inicio'] ?? null, $_GET['fecha_fin'] ?? null);
        Respuesta::ok($servicio->pulsaciones($desde, $hasta));

    case 'dashboard':
        [$desde, $hasta] = $servicio->rangoFechas($_GET['fecha_inicio'] ?? null, $_GET['fecha_fin'] ?? null);
        Respuesta::ok($servicio->dashboard($desde, $hasta));

    case 'servidor':
        Respuesta::ok($servicio->datosServidor());

    default:
        Respuesta::error('Acción no válida', 404);
}
