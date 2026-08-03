<?php
declare(strict_types=1);

$error = '';
$email_enviado = '';
$aviso = '';

if (isset($_GET['restablecida']) && $_GET['restablecida'] === '1') {
    $aviso = 'Contraseña restablecida. Ya puedes iniciar sesión.';
}
if (isset($_GET['registrado']) && $_GET['registrado'] === '1') {
    $aviso = 'Cuenta creada. Ya puedes iniciar sesión.';
}
if (isset($_GET['caducada']) && $_GET['caducada'] === '1') {
    $aviso = 'Tu sesión ha caducado por inactividad. Inicia sesión de nuevo.';
}

if (Session::usuarioActual() !== null) {
    header('Location: index.php?ruta=informes');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::csrfValido($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión caducada o petición inválida. Vuelve a intentarlo.';
    } elseif (empty($_POST['email']) || empty($_POST['password'])) {
        $email_enviado = trim($_POST['email'] ?? '');
        $error = 'Ingresa tu correo y contraseña.';
    } else {
        $email_enviado = trim($_POST['email'] ?? '');
        $auth = new AuthService();
        $resultado = $auth->login($email_enviado, $_POST['password'] ?? '');

        if ($resultado['ok']) {
            header('Location: index.php?ruta=informes');
            exit;
        }

        $error = $resultado['message'];
        $intentos = (int) ($_SESSION['intentos_fallidos'] ?? 0) + 1;
        $_SESSION['intentos_fallidos'] = $intentos;
        if ($intentos >= 3) {
            sleep(1);
        }
    }
}

$registro_abierto = filter_var(config('APP_REGISTRO_ABIERTO', 'true'), FILTER_VALIDATE_BOOLEAN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Informe de Asistencia</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-card">
        <div class="icon-circle"><i class="fas fa-fingerprint"></i></div>
        <h1>Informe de Asistencia</h1>
        <p>Inicia sesión para acceder</p>

        <?php if ($error !== ''): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($aviso !== ''): ?>
            <div class="alert-ok"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($aviso); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php?ruta=login">
            <?php echo Session::campoCsrf(); ?>
            <input type="email" name="email" placeholder="Correo electrónico" required
                   value="<?php echo htmlspecialchars($email_enviado); ?>" autocomplete="username">
            <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
            <button type="submit"><i class="fas fa-sign-in-alt me-2"></i>Ingresar</button>
        </form>

        <div class="login-links">
            <a href="index.php?ruta=recuperar">¿Olvidaste tu contraseña?</a>
            <?php if ($registro_abierto): ?>
                <a href="index.php?ruta=registro">Registrarse</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
