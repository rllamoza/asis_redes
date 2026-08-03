<?php
declare(strict_types=1);

$action = $_GET['action'] ?? ($_POST['action'] ?? 'pulsar');

if ($action === 'pulsar') {
    Respuesta::requiereMetodo('POST');

    $servicio = new KioscoService();
    $resultado = $servicio->pulsar($_POST['nombre'] ?? '');

    if ($resultado['ok']) {
        Respuesta::json(['success' => true, 'contador' => $resultado['contador']]);
    }

    Respuesta::error($resultado['message'], $resultado['codigo']);
}

Respuesta::error('Acción no válida', 404);
