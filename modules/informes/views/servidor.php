<?php
declare(strict_types=1);

include __DIR__ . '/layout/header.php';

$servicio = new InformesService();
$info = $servicio->datosServidor();

$prueba_ok = '';
$prueba_error = '';
$prueba_dev_info = '';
$prueba_dev_contenido = '';
$prueba_enlace = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';

    if (!Session::csrfValido($_POST['csrf_token'] ?? null)) {
        $prueba_error = 'Sesión caducada o petición inválida. Vuelve a intentarlo.';
    } elseif ($tipo === 'correo') {
        $destino = trim($_POST['destino'] ?? '');
        if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            $prueba_error = 'Ingresa un correo de destino válido.';
        } else {
            $asunto = trim($_POST['asunto'] ?? '');
            if ($asunto === '') {
                $asunto = 'Prueba de correo — Control de Asistencia';
            }
            $res = Notificaciones::enviarCorreo(
                $destino,
                $asunto,
                '<p>Este es un <b>correo de prueba</b> enviado desde la página Servidor.</p><p>Si lo recibes, el envío de correo funciona correctamente.</p>',
                "Este es un correo de prueba enviado desde la página Servidor. Si lo recibes, el envío de correo funciona correctamente."
            );
            if ($res['ok']) {
                $prueba_ok = 'Correo enviado correctamente a ' . $destino . '.';
            } else {
                $prueba_error = 'No se pudo enviar: ' . $res['error'];
            }
            $prueba_dev_info = $res['devInfo'];
        }
    } elseif ($tipo === 'whatsapp') {
        $telefono = trim($_POST['telefono'] ?? '');
        if ($telefono === '' || !preg_match('/^\+?[0-9 \-]{7,20}$/', $telefono)) {
            $prueba_error = 'Ingresa un teléfono válido (ej. +51000000000).';
        } else {
            $mensaje = trim($_POST['mensaje'] ?? '');
            if ($mensaje === '') {
                $mensaje = 'Prueba de WhatsApp — Control de Asistencia';
            }
            $res = Notificaciones::enviarWhatsApp($telefono, $mensaje);
            if ($res['ok']) {
                $prueba_ok = 'Enlace de WhatsApp generado para ' . $telefono . ':';
                $prueba_enlace = $res['enlace'];
            } else {
                $prueba_error = 'No se pudo generar: ' . $res['error'];
            }
            $prueba_dev_info = $res['devInfo'];
        }
    }

    if ($prueba_dev_info !== '' && is_file($prueba_dev_info)) {
        $prueba_dev_contenido = (string) file_get_contents($prueba_dev_info);
    }
}

function filaServidor(string $clave, string $valor): void
{
    echo "<tr><td class='clave'>" . htmlspecialchars($clave) . "</td><td>" . htmlspecialchars($valor) . "</td></tr>";
}

function gigabytesServidor(float $bytes): string
{
    return number_format($bytes / (1024 ** 3), 2, '.', ',') . ' GB';
}

function formatoUptimeServidor(string $segundos): string
{
    $s = (int) $segundos;
    $dias = intdiv($s, 86400);
    $horas = intdiv($s % 86400, 3600);
    $min = intdiv($s % 3600, 60);
    $seg = $s % 60;
    return "$dias d $horas h $min m $seg s";
}
?>
<style>
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .info-card {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        padding: 20px;
        overflow: hidden;
    }

    .info-card h3 {
        margin-top: 0;
        border-bottom: 2px solid #4a148c;
        padding-bottom: 8px;
        color: #2c3e50;
        font-size: 1.05rem;
    }

    .info-card table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    .info-card td {
        padding: 7px 4px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.88rem;
        word-break: break-word;
    }

    .info-card td.clave {
        width: 45%;
        color: #7f8c8d;
        font-weight: 600;
    }

    .hora-card {
        background: linear-gradient(135deg, #4a148c, #880e4f);
        color: #fff;
    }

    .hora-card h3 {
        color: #fff;
        border-bottom-color: rgba(255,255,255,0.4);
    }

    .hora-card td.clave {
        color: rgba(255,255,255,0.75);
    }

    .hora-card td {
        border-bottom-color: rgba(255,255,255,0.15);
    }

    .hora-grande {
        font-size: 1.6rem;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .ext-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .ext-chip {
        background: #f0edf6;
        color: #4a148c;
        border-radius: 12px;
        padding: 4px 10px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .test-card {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        padding: 20px;
        margin-bottom: 20px;
    }

    .test-card h3 {
        margin-top: 0;
        border-bottom: 2px solid #4a148c;
        padding-bottom: 8px;
        color: #2c3e50;
        font-size: 1.05rem;
    }

    .test-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .test-grid form {
        border: 1px solid #eee;
        border-radius: 10px;
        padding: 15px;
    }

    .test-grid label {
        display: block;
        font-size: 0.82rem;
        font-weight: 600;
        color: #555;
        margin: 10px 0 4px;
    }

    .test-grid input[type="email"],
    .test-grid input[type="tel"],
    .test-grid input[type="text"] {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 0.9rem;
        box-sizing: border-box;
    }

    .test-grid button {
        margin-top: 12px;
        padding: 9px 18px;
        border: none;
        border-radius: 8px;
        background: #4a148c;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
    }

    .test-grid button:hover {
        background: #6a1b9a;
    }

    .test-alert {
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 14px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .test-alert.ok {
        background: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #a5d6a7;
    }

    .test-alert.error {
        background: #fce4ec;
        color: #c62828;
        border: 1px solid #f48fb1;
    }

    .test-dev {
        background: #fff8e1;
        border: 1px solid #ffe082;
        border-radius: 10px;
        padding: 12px 16px;
        margin-top: 14px;
        font-size: 0.82rem;
        color: #795548;
        word-break: break-word;
    }

    .test-dev pre {
        background: #fff;
        border-radius: 6px;
        padding: 10px;
        font-size: 0.78rem;
        white-space: pre-wrap;
        word-break: break-all;
        margin: 8px 0 0;
    }
</style>

<div class="test-card">
    <h3><i class="fas fa-paper-plane"></i> Herramientas de envío</h3>

    <?php if ($prueba_ok !== ''): ?>
        <div class="test-alert ok"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($prueba_ok); ?></div>
    <?php endif; ?>
    <?php if ($prueba_enlace !== ''): ?>
        <div class="test-dev">
            <strong>Enlace generado:</strong><br>
            <a href="<?php echo htmlspecialchars($prueba_enlace); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($prueba_enlace); ?></a><br>
            <span style="font-size:0.78rem;">Al abrirlo se inicia WhatsApp con el mensaje listo para enviar (pulsa enviar en WhatsApp).</span>
        </div>
    <?php endif; ?>
    <?php if ($prueba_error !== ''): ?>
        <div class="test-alert error"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($prueba_error); ?></div>
    <?php endif; ?>

    <?php if ($prueba_dev_info !== ''): ?>
        <div class="test-dev">
            <strong>Modo local / modo dev:</strong> no se envió realmente; se guardó en
            <code><?php echo htmlspecialchars($prueba_dev_info); ?></code>.
            <?php if ($prueba_dev_contenido !== ''): ?>
                <pre><?php echo htmlspecialchars($prueba_dev_contenido); ?></pre>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="test-grid">
        <form method="POST" action="index.php?ruta=servidor">
            <?php echo Session::campoCsrf(); ?>
            <input type="hidden" name="tipo" value="correo">
            <h4><i class="fas fa-envelope"></i> Probar correo</h4>
            <label for="destino">Correo de destino</label>
            <input type="email" id="destino" name="destino" required placeholder="destinatario@ejemplo.com">
            <label for="asunto">Asunto (opcional)</label>
            <input type="text" id="asunto" name="asunto" placeholder="Prueba de correo">
            <button type="submit"><i class="fas fa-paper-plane me-2"></i>Enviar correo de prueba</button>
        </form>

        <form method="POST" action="index.php?ruta=servidor">
            <?php echo Session::campoCsrf(); ?>
            <input type="hidden" name="tipo" value="whatsapp">
            <h4><i class="fab fa-whatsapp"></i> Probar WhatsApp</h4>
            <label for="telefono">Número de teléfono</label>
            <input type="tel" id="telefono" name="telefono" required placeholder="+51000000000">
            <label for="mensaje">Mensaje (opcional)</label>
            <input type="text" id="mensaje" name="mensaje" placeholder="Prueba de WhatsApp">
            <button type="submit"><i class="fab fa-whatsapp me-2"></i>Generar enlace de prueba</button>
        </form>
    </div>
</div>

<div class="info-grid">
    <div class="info-card hora-card">
        <h3><i class="fas fa-clock"></i> Hora del Servidor</h3>
        <table>
            <tr><td class="clave">Hora actual (PHP)</td><td class="hora-grande"><?php echo htmlspecialchars($info['hora']['php_hora']); ?></td></tr>
            <tr><td class="clave">Hora MySQL (NOW)</td><td><?php echo htmlspecialchars($info['hora']['mysql_now']); ?></td></tr>
            <tr><td class="clave">Zona horaria PHP</td><td><?php echo htmlspecialchars($info['hora']['php_zona']); ?> (UTC<?php echo htmlspecialchars($info['hora']['php_desplazamiento']); ?>)</td></tr>
            <tr><td class="clave">Zona horaria MySQL</td><td><?php echo htmlspecialchars($info['hora']['mysql_zona']); ?></td></tr>
            <tr><td class="clave">Nota</td><td><?php echo htmlspecialchars($info['hora']['nota']); ?></td></tr>
        </table>
    </div>

    <div class="info-card">
        <h3><i class="fas fa-database"></i> Base de Datos</h3>
        <table>
            <?php filaServidor('Base de datos', $info['base_datos']['nombre']); ?>
            <?php filaServidor('Servidor MySQL', $info['base_datos']['servidor']); ?>
            <?php filaServidor('Conexión', $info['base_datos']['conexion']); ?>
            <?php filaServidor('Hostname MySQL', $info['base_datos']['hostname']); ?>
            <?php filaServidor('Charset conexión', $info['base_datos']['charset']); ?>
            <?php filaServidor('Uptime MySQL', formatoUptimeServidor($info['base_datos']['uptime'])); ?>
            <?php filaServidor('Conexiones activas', $info['base_datos']['conexiones_activas']); ?>
            <?php filaServidor('Máx. conexiones usadas', $info['base_datos']['max_conexiones']); ?>
        </table>
    </div>

    <div class="info-card">
        <h3><i class="fas fa-server"></i> Servidor Web</h3>
        <table>
            <?php filaServidor('Software', $info['servidor_web']['software']); ?>
            <?php filaServidor('SAPI PHP', $info['servidor_web']['sapi']); ?>
            <?php filaServidor('Host', $info['servidor_web']['host']); ?>
            <?php filaServidor('Nombre del servidor', $info['servidor_web']['nombre']); ?>
            <?php filaServidor('IP del servidor', $info['servidor_web']['ip_servidor']); ?>
            <?php filaServidor('IP del cliente', $info['servidor_web']['ip_cliente']); ?>
            <?php filaServidor('Document root', $info['servidor_web']['document_root']); ?>
            <?php filaServidor('Archivo ejecutado', $info['servidor_web']['archivo']); ?>
        </table>
    </div>

    <div class="info-card">
        <h3><i class="fas fa-cogs"></i> Sistema y PHP</h3>
        <table>
            <?php filaServidor('Sistema operativo', $info['sistema_php']['os']); ?>
            <?php filaServidor('Versión de PHP', $info['sistema_php']['version']); ?>
            <?php filaServidor('Binario PHP', $info['sistema_php']['binario']); ?>
            <?php filaServidor('Memoria límite', $info['sistema_php']['memoria']); ?>
            <?php filaServidor('Tiempo máx. ejecución', $info['sistema_php']['max_ejecucion']); ?>
            <?php filaServidor('Subida máx.', $info['sistema_php']['subida']); ?>
            <?php filaServidor('POST máx.', $info['sistema_php']['post']); ?>
            <?php filaServidor('Ruta error_log', $info['sistema_php']['error_log']); ?>
            <?php filaServidor('display_errors', $info['sistema_php']['display_errors']); ?>
            <?php filaServidor('date.timezone', $info['sistema_php']['timezone']); ?>
        </table>
    </div>

    <div class="info-card">
        <h3><i class="fas fa-hdd"></i> Almacenamiento</h3>
        <table>
            <?php filaServidor('Ruta de la app', $info['almacenamiento']['ruta']); ?>
            <?php filaServidor('Espacio total', gigabytesServidor($info['almacenamiento']['total'])); ?>
            <?php filaServidor('Espacio libre', gigabytesServidor($info['almacenamiento']['libre'])); ?>
            <?php
            $total = $info['almacenamiento']['total'];
            $libre = $info['almacenamiento']['libre'];
            $uso = $total > 0 ? round((($total - $libre) / $total) * 100, 1) : 0;
            filaServidor('Uso del disco', $uso . '%');
            ?>
        </table>
    </div>

    <div class="info-card">
        <h3><i class="fas fa-puzzle-piece"></i> Extensiones PHP (<?php echo count($info['extensiones']); ?>)</h3>
        <div class="ext-list">
            <?php foreach ($info['extensiones'] as $ext): ?>
                <span class="ext-chip"><?php echo htmlspecialchars($ext); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
