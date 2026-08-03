<?php
declare(strict_types=1);

$error = '';
$aviso = '';
$devInfo = '';
$medio = $_GET['medio'] ?? ($_POST['medio'] ?? 'email');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::csrfValido($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión caducada o petición inválida. Vuelve a intentarlo.';
    } else {
        $medio = $_POST['medio'] ?? 'email';
        $valor = trim($_POST['email'] ?? $_POST['telefono'] ?? '');

        if ($valor === '') {
            $error = 'Indica tu correo o tu teléfono.';
        } else {
            $auth = new AuthService();
            $resultado = $auth->solicitarRecuperacion($medio, $valor);

            if (!$resultado['ok']) {
                $error = $resultado['error'];
            } else {
                $aviso = $resultado['aviso'];
                $devInfo = $resultado['devInfo'];
            }
        }
    }
}

if (!filter_var(config('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN)) {
    $devInfo = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña — Informe de Asistencia</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-card">
        <div class="icon-circle"><i class="fas fa-key"></i></div>
        <h1>Recuperar contraseña</h1>
        <p>Elige cómo recibir el enlace o código</p>

        <?php if ($error !== ''): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($aviso !== ''): ?>
            <div class="alert-ok"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($aviso); ?></div>
        <?php endif; ?>

        <?php if ($devInfo !== ''): ?>
            <div class="alert-dev"><?php echo htmlspecialchars($devInfo); ?></div>
        <?php endif; ?>

        <?php if ($aviso === ''): ?>
        <form method="POST" action="index.php?ruta=recuperar">
            <?php echo Session::campoCsrf(); ?>
            <select name="medio" id="medio" onchange="actualizarCampo()">
                <option value="email" <?php echo $medio === 'email' ? 'selected' : ''; ?>>Por correo (enlace)</option>
                <option value="whatsapp" <?php echo $medio === 'whatsapp' ? 'selected' : ''; ?>>Por WhatsApp (vía enlace)</option>
            </select>
            <input type="text" id="campoValor" name="email" placeholder="Correo electrónico" required autocomplete="email">
            <button type="submit"><i class="fas fa-paper-plane me-2"></i>Enviar recuperación</button>
        </form>

        <div class="login-links centro">
            <a href="index.php?ruta=login">Volver a iniciar sesión</a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function actualizarCampo() {
            var medio = document.getElementById('medio').value;
            var campo = document.getElementById('campoValor');
            if (medio === 'whatsapp') {
                campo.name = 'telefono';
                campo.placeholder = 'Teléfono con WhatsApp (ej. +51000000000)';
                campo.type = 'tel';
            } else {
                campo.name = 'email';
                campo.placeholder = 'Correo electrónico';
                campo.type = 'text';
            }
        }
    </script>
</body>
</html>
