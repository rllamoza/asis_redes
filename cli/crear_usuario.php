<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos.');
}

require_once __DIR__ . '/../core/bootstrap.php';

$conn = Database::conexion();

function uso(): void
{
    echo "Uso:\n";
    echo "  php cli/crear_usuario.php <email> <password> [nombre] [rol] [telefono]\n";
    echo "  php cli/crear_usuario.php --lista\n";
    echo "\nEjemplos:\n";
    echo "  php cli/crear_usuario.php admin@ejemplo.com MiClave123 \"Administrador\" admin\n";
    echo "  php cli/crear_usuario.php admin@ejemplo.com MiClave123 \"Administrador\" admin \"+51000000000\"\n";
    echo "  php cli/crear_usuario.php --lista\n";
}

$argv = $_SERVER['argv'] ?? [];

if (count($argv) === 2 && $argv[1] === '--lista') {
    $result = $conn->query('SELECT id, email, nombre, rol, telefono, fecha_creacion FROM usuarios ORDER BY id');
    echo "ID | email | nombre | rol | telefono | fecha_creacion\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['id'] . ' | ' . $row['email'] . ' | ' . $row['nombre'] . ' | ' . $row['rol'] . ' | ' . ($row['telefono'] ?? '') . ' | ' . $row['fecha_creacion'] . "\n";
    }
    Database::cerrar();
    exit(0);
}

if (count($argv) < 3) {
    uso();
    Database::cerrar();
    exit(1);
}

$email = trim($argv[1]);
$password = $argv[2];
$nombre = trim($argv[3] ?? '');
$rol = trim($argv[4] ?? 'admin');
$telefono = trim($argv[5] ?? '');

if ($email === '' || $password === '') {
    echo "Error: email y password son obligatorios.\n";
    Database::cerrar();
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: email no válido.\n";
    Database::cerrar();
    exit(1);
}

if (strlen($password) < 6) {
    echo "Error: la contraseña debe tener al menos 6 caracteres.\n";
    Database::cerrar();
    exit(1);
}

if (!in_array($rol, ['admin', 'usuario'], true)) {
    echo "Error: el rol debe ser 'admin' o 'usuario'.\n";
    Database::cerrar();
    exit(1);
}

$conn->query("CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` int NOT NULL AUTO_INCREMENT,
    `nombre` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `PASSWORD` varchar(255) NOT NULL,
    `rol` enum('admin','usuario') NOT NULL DEFAULT 'usuario',
    `telefono` varchar(20) DEFAULT NULL,
    `token_recuperacion` varchar(255) DEFAULT NULL,
    `codigo_recuperacion` varchar(10) DEFAULT NULL,
    `token_expiracion` datetime DEFAULT NULL,
    `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1");

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare('SELECT id FROM `usuarios` WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$existe = $stmt->get_result()->num_rows > 0;

if ($existe) {
    $stmt = $conn->prepare('UPDATE `usuarios` SET `PASSWORD` = ?, nombre = ?, rol = ?, telefono = ? WHERE email = ?');
    $stmt->bind_param('sssss', $hash, $nombre, $rol, $telefono, $email);
    $stmt->execute();
    echo "Usuario actualizado: $email\n";
} else {
    $stmt = $conn->prepare('INSERT INTO `usuarios` (email, `PASSWORD`, nombre, rol, telefono) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $email, $hash, $nombre, $rol, $telefono);
    $stmt->execute();
    echo "Usuario creado: $email\n";
}

Database::cerrar();
