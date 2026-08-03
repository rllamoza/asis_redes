<?php
declare(strict_types=1);

$error = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$codigo = trim($_GET['codigo'] ?? $_POST['codigo'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::csrfValido($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión caducada o petición inválida. Vuelve a intentarlo.';
    } else {
        $auth = new AuthService();
        $resultado = $auth->restablecer([
            'token' => $_POST['token'] ?? '',
            'codigo' => $_POST['codigo'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'password2' => $_POST['password2'] ?? '',
        ]);

        if ($resultado['ok']) {
            header('Location: index.php?ruta=login&restablecida=1');
            exit;
        }

        $error = $resultado['message'];
    }
} elseif ($token === '' && ($codigo === '' || $email === '')) {
    $error = 'Faltan datos de recuperación.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña — Informe de Asistencia</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-card">
        <div class="icon-circle"><i class="fas fa-lock"></i></div>
        <h1>Restablecer contraseña</h1>
        <p>Escribe tu nueva contraseña</p>

        <?php if ($error !== ''): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php?ruta=restablecer">
            <?php echo Session::campoCsrf(); ?>
            <?php if ($token !== ''): ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <?php else: ?>
                <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($codigo); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <?php endif; ?>
            <input type="password" name="password" placeholder="Nueva contraseña (mín. 6)" required minlength="6" autocomplete="new-password">
            <input type="password" name="password2" placeholder="Repite la contraseña" required minlength="6" autocomplete="new-password">
            <button type="submit"><i class="fas fa-check me-2"></i>Guardar contraseña</button>
        </form>

        <div class="login-links centro">
            <a href="index.php?ruta=login">Volver a iniciar sesión</a>
        </div>
    </div>
</body>
</html>
