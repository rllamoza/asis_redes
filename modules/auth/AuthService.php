<?php
declare(strict_types=1);

final class AuthService
{
    public function login(string $email, string $password): array
    {
        $conn = Database::conexion();
        $stmt = $conn->prepare('SELECT id, email, `PASSWORD`, nombre, rol FROM `usuarios` WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();

        if ($usuario && password_verify($password, $usuario['PASSWORD'])) {
            Session::iniciar($usuario);
            return ['ok' => true];
        }

        return ['ok' => false, 'message' => 'Credenciales incorrectas.'];
    }

    public function registrar(array $datos): array
    {
        $conn = Database::conexion();
        $nombre = trim($datos['nombre'] ?? '');
        $email = trim($datos['email'] ?? '');
        $telefono = trim($datos['telefono'] ?? '');
        $password = $datos['password'] ?? '';
        $password2 = $datos['password2'] ?? '';

        if ($nombre === '' || $email === '' || $password === '') {
            return ['ok' => false, 'message' => 'Completa nombre, correo y contraseña.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'El correo no es válido.'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.'];
        }
        if ($password !== $password2) {
            return ['ok' => false, 'message' => 'Las contraseñas no coinciden.'];
        }
        if ($telefono !== '' && !preg_match('/^\+?[0-9 \-]{7,20}$/', $telefono)) {
            return ['ok' => false, 'message' => 'El teléfono no es válido (ej. +51000000000).'];
        }

        $stmt = $conn->prepare('SELECT id FROM `usuarios` WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['ok' => false, 'message' => 'Ya existe una cuenta con ese correo.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $rol = 'usuario';
        $stmt = $conn->prepare('INSERT INTO `usuarios` (email, `PASSWORD`, nombre, rol, telefono) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sssss', $email, $hash, $nombre, $rol, $telefono);
        $stmt->execute();

        return ['ok' => true];
    }

    public function solicitarRecuperacion(string $medio, string $valor): array
    {
        $conn = Database::conexion();
        $aviso = 'Si tu correo o teléfono está registrado, recibirás las instrucciones para restablecer tu contraseña.';

        $stmt = $conn->prepare('SELECT id, email, nombre, telefono FROM `usuarios` WHERE email = ? OR telefono = ? LIMIT 1');
        $stmt->bind_param('ss', $valor, $valor);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();

        if (!$usuario) {
            return ['ok' => true, 'aviso' => $aviso, 'devInfo' => '', 'error' => ''];
        }

        $token = bin2hex(random_bytes(21));
        $codigo = (string) random_int(100000, 999999);
        $expiracion = date('Y-m-d H:i:s', time() + 1800);

        $stmt = $conn->prepare('UPDATE `usuarios` SET token_recuperacion = ?, codigo_recuperacion = ?, token_expiracion = ? WHERE id = ?');
        $stmt->bind_param('sssi', $token, $codigo, $expiracion, $usuario['id']);
        $stmt->execute();

        $devInfo = '';
        if ($medio === 'whatsapp' && !empty($usuario['telefono'])) {
            $mensaje = "Tu codigo de recuperacion de Informe de Asistencia es: $codigo (valido 30 min).";
            $resultado = Notificaciones::enviarWhatsApp($usuario['telefono'], $mensaje);
            if (!empty($resultado['enlace'])) {
                $devInfo = 'Enlace de WhatsApp: ' . $resultado['enlace'] . ' — Código: ' . $codigo;
            }
        } else {
            $urlApp = rtrim(config('APP_URL', 'http://localhost/conteo'), '/');
            $enlace = $urlApp . '/index.php?ruta=restablecer&token=' . $token;
            $html = '<p>Hola ' . htmlspecialchars($usuario['nombre']) . ',</p>'
                . '<p>Recibimos una solicitud para restablecer tu contraseña.</p>'
                . '<p><a href="' . htmlspecialchars($enlace) . '">Restablecer contraseña</a></p>'
                . '<p>Este enlace es válido por 30 minutos. Si no lo solicitaste, ignora este correo.</p>';
            $texto = "Restablece tu contraseña en: $enlace (valido 30 min)";
            $resultado = Notificaciones::enviarCorreo($usuario['email'], 'Restablece tu contraseña', $html, $texto);
            if ($resultado['dev']) {
                $devInfo = 'Enlace (modo local, no se envió correo): ' . $enlace;
            }
        }

        if (!empty($resultado['error']) && !$resultado['dev']) {
            return ['ok' => false, 'aviso' => '', 'devInfo' => '', 'error' => 'No se pudo enviar la recuperación. Inténtalo más tarde.'];
        }

        return ['ok' => true, 'aviso' => $aviso, 'devInfo' => $devInfo, 'error' => ''];
    }

    public function restablecer(array $datos): array
    {
        $conn = Database::conexion();
        $token = trim($datos['token'] ?? '');
        $codigo = trim($datos['codigo'] ?? '');
        $email = trim($datos['email'] ?? '');
        $password = $datos['password'] ?? '';
        $password2 = $datos['password2'] ?? '';

        $usuario = null;
        if ($token !== '') {
            $stmt = $conn->prepare('SELECT id, token_expiracion FROM `usuarios` WHERE token_recuperacion = ?');
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $usuario = $stmt->get_result()->fetch_assoc();
            if (!$usuario) {
                return ['ok' => false, 'message' => 'Enlace de recuperación inválido o ya utilizado.'];
            }
        } elseif ($codigo !== '' && $email !== '') {
            $stmt = $conn->prepare('SELECT id, token_expiracion FROM `usuarios` WHERE email = ? AND codigo_recuperacion = ?');
            $stmt->bind_param('ss', $email, $codigo);
            $stmt->execute();
            $usuario = $stmt->get_result()->fetch_assoc();
            if (!$usuario) {
                return ['ok' => false, 'message' => 'Código inválido o ya utilizado.'];
            }
        } else {
            return ['ok' => false, 'message' => 'Faltan datos de recuperación.'];
        }

        if (!$usuario['token_expiracion'] || strtotime($usuario['token_expiracion']) < time()) {
            return ['ok' => false, 'message' => 'El enlace/código ha caducado. Solicita uno nuevo.'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.'];
        }
        if ($password !== $password2) {
            return ['ok' => false, 'message' => 'Las contraseñas no coinciden.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE `usuarios` SET `PASSWORD` = ?, token_recuperacion = NULL, codigo_recuperacion = NULL, token_expiracion = NULL WHERE id = ?');
        $stmt->bind_param('si', $hash, $usuario['id']);
        $stmt->execute();

        return ['ok' => true];
    }
}
