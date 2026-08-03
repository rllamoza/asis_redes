<?php
declare(strict_types=1);

if (!filter_var(config('APP_REGISTRO_ABIERTO', 'true'), FILTER_VALIDATE_BOOLEAN)) {
    header('Location: index.php?ruta=login');
    exit;
}

if (Session::usuarioActual() !== null) {
    header('Location: index.php?ruta=informes');
    exit;
}

$error = '';
$nombre = '';
$email = '';
$telefono = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::csrfValido($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión caducada o petición inválida. Vuelve a intentarlo.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        $auth = new AuthService();
        $resultado = $auth->registrar([
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'password' => $_POST['password'] ?? '',
            'password2' => $_POST['password2'] ?? '',
        ]);

        if ($resultado['ok']) {
            header('Location: index.php?ruta=login&registrado=1');
            exit;
        }

        $error = $resultado['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse — Informe de Asistencia</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-card">
        <div class="icon-circle"><i class="fas fa-user-plus"></i></div>
        <h1>Crear cuenta</h1>
        <p>Regístrate para acceder a los informes</p>

        <?php if ($error !== ''): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php?ruta=registro">
            <?php echo Session::campoCsrf(); ?>
            <input type="text" name="nombre" placeholder="Nombre completo" required autocomplete="name"
                   value="<?php echo htmlspecialchars($nombre); ?>">
            <input type="email" name="email" placeholder="Correo electrónico" required autocomplete="username"
                   value="<?php echo htmlspecialchars($email); ?>">
            <input type="tel" name="telefono" placeholder="Teléfono (opcional, para SMS)" autocomplete="tel"
                   value="<?php echo htmlspecialchars($telefono); ?>">
            <input type="password" name="password" placeholder="Contraseña (mín. 6)" required minlength="6" autocomplete="new-password">
            <input type="password" name="password2" placeholder="Repite la contraseña" required minlength="6" autocomplete="new-password">
            <button type="submit"><i class="fas fa-user-check me-2"></i>Crear cuenta</button>
        </form>

        <div class="login-links centro">
            <a href="index.php?ruta=login">¿Ya tienes cuenta? Inicia sesión</a>
        </div>
    </div>
</body>
</html>
