<?php
declare(strict_types=1);

final class Notificaciones
{
    private static function escribirLog(string $nombre, string $contenido): string
    {
        $dir = STORAGE_DIR . '/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $archivo = $dir . '/' . $nombre;
        file_put_contents($archivo, $contenido, FILE_APPEND);
        return $archivo;
    }

    private static function cabeceraCodificada(string $texto): string
    {
        return '=?UTF-8?B?' . base64_encode($texto) . '?=';
    }

    private static function smtpEnviar(string $host, int $puerto, string $seguridad, string $usuario, string $pass, string $from, string $fromName, string $para, string $asunto, string $html, string $texto): bool
    {
        $prefijo = $seguridad === 'ssl' ? 'tls://' : '';
        $sock = @stream_socket_client("$prefijo$host:$puerto", $errno, $errstr, 15);
        if (!$sock) {
            return false;
        }

        $leer = function () use ($sock) {
            $buf = '';
            while (($linea = fgets($sock, 515)) !== false) {
                $buf .= $linea;
                if (isset($linea[3]) && $linea[3] === ' ') {
                    break;
                }
            }
            return $buf;
        };
        $escribir = function (string $linea) use ($sock): void {
            fwrite($sock, $linea . "\r\n");
        };
        $respuesta = function (string $buf, string $esperado): bool {
            return str_starts_with($buf, $esperado);
        };

        if (!$respuesta($leer(), '220')) {
            fclose($sock);
            return false;
        }

        $escribir('EHLO ' . (gethostname() ?: 'localhost'));
        $leer();

        if ($seguridad === 'starttls') {
            $escribir('STARTTLS');
            if (!$respuesta($leer(), '220')) {
                fclose($sock);
                return false;
            }
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                return false;
            }
            $escribir('EHLO ' . (gethostname() ?: 'localhost'));
            $leer();
        }

        if ($usuario !== '' && $pass !== '') {
            $escribir('AUTH LOGIN');
            if (!$respuesta($leer(), '334')) {
                fclose($sock);
                return false;
            }
            $escribir(base64_encode($usuario));
            if (!$respuesta($leer(), '334')) {
                fclose($sock);
                return false;
            }
            $escribir(base64_encode($pass));
            if (!$respuesta($leer(), '235')) {
                fclose($sock);
                return false;
            }
        }

        $escribir('MAIL FROM:<' . $from . '>');
        if (!$respuesta($leer(), '250')) {
            fclose($sock);
            return false;
        }
        $escribir('RCPT TO:<' . $para . '>');
        if (!$respuesta($leer(), '250')) {
            fclose($sock);
            return false;
        }

        $escribir('DATA');
        if (!$respuesta($leer(), '354')) {
            fclose($sock);
            return false;
        }

        $limite = '=_controlasistencia_' . bin2hex(random_bytes(6));
        $cuerpo = "--$limite\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . base64_encode($texto) . "\r\n"
            . "--$limite\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . base64_encode($html) . "\r\n"
            . "--$limite--\r\n";

        $cabeceras = "From: " . self::cabeceraCodificada($fromName) . " <$from>\r\n"
            . "To: <$para>\r\n"
            . "Subject: " . self::cabeceraCodificada($asunto) . "\r\n"
            . "Date: " . date('r') . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/alternative; boundary=\"$limite\"\r\n\r\n";

        fwrite($sock, $cabeceras . $cuerpo . "\r\n.\r\n");
        $ok = $respuesta($leer(), '250');

        $escribir('QUIT');
        fclose($sock);
        return $ok;
    }

    /**
     * @return array{ok: bool, dev: bool, devInfo: string, error: string}
     */
    public static function enviarCorreo(string $para, string $asunto, string $html, string $texto): array
    {
        $host = config('SMTP_HOST');
        $puerto = (int) config('SMTP_PORT', '587');
        $seguridad = config('SMTP_SECURE', 'starttls');
        $usuario = config('SMTP_USER');
        $pass = config('SMTP_PASS');
        $from = config('SMTP_FROM', 'no-reply@localhost');
        $fromName = config('SMTP_FROM_NAME', 'Control de Asistencia');

        if ($host !== '') {
            $ok = self::smtpEnviar($host, $puerto, $seguridad, $usuario, $pass, $from, $fromName, $para, $asunto, $html, $texto);
            if ($ok) {
                return ['ok' => true, 'dev' => false, 'devInfo' => '', 'error' => ''];
            }
            return ['ok' => false, 'dev' => false, 'devInfo' => '', 'error' => 'SMTP no disponible'];
        }

        $cabeceras = "From: " . self::cabeceraCodificada($fromName) . " <$from>\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n";
        $ok = @mail($para, self::cabeceraCodificada($asunto), $html, $cabeceras);
        if ($ok) {
            return ['ok' => true, 'dev' => false, 'devInfo' => '', 'error' => ''];
        }

        $log = self::escribirLog(
            'correo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.html',
            "<h2>$asunto</h2><p><strong>Para:</strong> $para</p>$html"
        );
        return ['ok' => false, 'dev' => true, 'devInfo' => $log, 'error' => 'Correo no enviado (modo local)'];
    }

    /**
     * @return array{ok: bool, dev: bool, devInfo: string, error: string}
     */
    public static function enviarSMS(string $telefono, string $mensaje): array
    {
        $metodo = config('SMS_METODO', 'dev');

        if ($metodo === 'dev') {
            $log = self::escribirLog('sms_' . date('Ymd_His') . '.txt', "[$telefono] $mensaje\n");
            return ['ok' => true, 'dev' => true, 'devInfo' => $log, 'error' => ''];
        }

        if ($metodo === 'email-gateway') {
            $dominio = config('SMS_EMAIL_GATEWAY', 'txt.att.net');
            if ($dominio === '' || !filter_var('x@' . $dominio, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'dev' => false, 'devInfo' => '', 'error' => 'SMS_EMAIL_GATEWAY inválido'];
            }
            $destino = $telefono . '@' . $dominio;
            return self::enviarCorreo($destino, 'Código de recuperación', '<p>' . htmlspecialchars($mensaje) . '</p>', $mensaje);
        }

        if ($metodo === 'textbelt') {
            $clave = config('SMS_TEXTBELT_APIKEY', 'textbelt');
            $ch = curl_init('https://textbelt.com/text');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['phone' => $telefono, 'message' => $mensaje, 'key' => $clave]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($resp === false) {
                return ['ok' => false, 'dev' => false, 'devInfo' => '', 'error' => 'TextBelt: ' . $err];
            }
            $datos = json_decode((string) $resp, true);
            $ok = !empty($datos['success']);
            return ['ok' => $ok, 'dev' => false, 'devInfo' => '', 'error' => $ok ? '' : ($datos['error'] ?? 'TextBelt rechazó el envío')];
        }

        if ($metodo === 'twilio') {
            $sid = config('TWILIO_SID');
            $token = config('TWILIO_TOKEN');
            $from = config('TWILIO_FROM');
            if ($sid === '' || $token === '' || $from === '') {
                return ['ok' => false, 'dev' => false, 'devInfo' => '', 'error' => 'Twilio no configurado'];
            }
            $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['From' => $from, 'To' => $telefono, 'Body' => $mensaje]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            $resp = curl_exec($ch);
            curl_close($ch);
            $datos = json_decode((string) $resp, true);
            $ok = isset($datos['sid']);
            return ['ok' => $ok, 'dev' => false, 'devInfo' => '', 'error' => $ok ? '' : ($datos['message'] ?? 'Twilio rechazó el envío')];
        }

        return ['ok' => false, 'dev' => false, 'devInfo' => '', 'error' => 'SMS_METODO desconocido'];
    }

    /**
     * Genera un enlace de WhatsApp (wa.me) con el mensaje listo para enviar.
     * No envía automáticamente: al abrir el enlace se abre la conversación con el mensaje escrito.
     * Si WHATSAPP_OPERADOR está configurado, el enlace apunta a ese número (el operador reenvía el código);
     * si está vacío, el enlace apunta al número del receptor.
     *
     * @return array{ok: bool, dev: bool, devInfo: string, error: string, enlace: string}
     */
    public static function enviarWhatsApp(string $telefono, string $mensaje): array
    {
        $destino = preg_replace('/[^0-9]/', '', $telefono);
        if ($destino === '') {
            return ['ok' => false, 'dev' => false, 'devInfo' => '', 'error' => 'Número de teléfono inválido', 'enlace' => ''];
        }

        $operador = preg_replace('/[^0-9]/', '', (string) config('WHATSAPP_OPERADOR', ''));
        $objetivo = $operador !== '' ? $operador : $destino;
        $enlace = 'https://wa.me/' . $objetivo . '?text=' . rawurlencode($mensaje);

        $log = self::escribirLog('whatsapp_' . date('Ymd_His') . '.txt', "[$telefono] $mensaje\nEnlace: $enlace\n");
        return ['ok' => true, 'dev' => true, 'devInfo' => $log, 'error' => '', 'enlace' => $enlace];
    }
}
