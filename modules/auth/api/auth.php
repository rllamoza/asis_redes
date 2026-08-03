<?php
declare(strict_types=1);

Respuesta::requiereMetodo('POST');

if (!Session::csrfValido($_POST['csrf_token'] ?? null)) {
    Respuesta::error('Sesión caducada o petición inválida.', 403);
}

$auth = new AuthService();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'login':
        $r = $auth->login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
        if ($r['ok']) {
            Respuesta::ok(['usuario' => Session::usuarioActual()]);
        }
        Respuesta::error($r['message'], 401);

    case 'registro':
        $r = $auth->registrar($_POST);
        if ($r['ok']) {
            Respuesta::ok(null, 201);
        }
        Respuesta::error($r['message']);

    case 'recuperar':
        $r = $auth->solicitarRecuperacion($_POST['medio'] ?? 'email', trim($_POST['email'] ?? $_POST['telefono'] ?? ''));
        if ($r['ok']) {
            Respuesta::ok(['aviso' => $r['aviso'], 'devInfo' => $r['devInfo']]);
        }
        Respuesta::error($r['error'], 500);

    case 'restablecer':
        $r = $auth->restablecer($_POST);
        if ($r['ok']) {
            Respuesta::ok(null, 200);
        }
        Respuesta::error($r['message']);

    case 'logout':
        Session::destruir();
        Respuesta::ok(['logout' => true]);

    default:
        Respuesta::error('Acción no válida', 404);
}
