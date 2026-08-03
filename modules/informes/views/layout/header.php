<?php
declare(strict_types=1);

Session::requiereLogin();
$usuario_actual = Session::usuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Asistencia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/menu.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <a href="index.php?ruta=informes" class="sidebar-icon" title="Informes"><i class="fas fa-home"></i></a>
            <a href="index.php?ruta=dashboard" class="sidebar-icon" title="Gráficos"><i class="fas fa-chart-bar"></i></a>
            <a href="index.php?ruta=servidor" class="sidebar-icon" title="Servidor"><i class="fas fa-server"></i></a>
            <div class="sidebar-icon"><i class="fas fa-globe"></i></div>
            <div class="sidebar-icon"><i class="fas fa-file-alt"></i></div>
            <div class="sidebar-icon"><i class="fas fa-wrench"></i></div>
        </div>
        <div class="content-wrapper">
            <div class="dashboard-header">
                <div class="dashboard-tabs">
                    <a href="index.php?ruta=informes">Informes</a>
                    <a href="index.php?ruta=dashboard">Gráficos</a>
                    <a href="index.php?ruta=servidor" class="btn-server"><i class="fas fa-server"></i> Servidor</a>
                </div>
                <div class="dashboard-actions">
                    <i class="fas fa-user-circle icon"></i>
                    <span class="user-label"><?php echo htmlspecialchars($usuario_actual['email']); ?></span>
                    <a href="index.php?ruta=logout" class="logout-link"><i class="fas fa-sign-out-alt"></i> Salir</a>
                </div>
            </div>
            <div class="main-content">
