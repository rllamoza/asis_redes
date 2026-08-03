<?php
declare(strict_types=1);

final class KioscoService
{
    private const EXCLUIDAS = ['VIDALON'];

    public function personas(): array
    {
        $conn = Database::conexion();
        $result = $conn->query('SELECT nombre, contador FROM contadores ORDER BY nombre');
        $personas = [];
        while ($row = $result->fetch_assoc()) {
            if (in_array($row['nombre'], self::EXCLUIDAS, true)) {
                continue;
            }
            $personas[] = [
                'nombre' => $row['nombre'],
                'contador' => (int) $row['contador'],
            ];
        }
        return $personas;
    }

    public function pulsar(string $nombre): array
    {
        $conn = Database::conexion();
        $nombre = trim($nombre);

        if ($nombre === '') {
            return ['ok' => false, 'codigo' => 400, 'message' => 'Nombre no proporcionado'];
        }
        if (mb_strlen($nombre) > 50) {
            return ['ok' => false, 'codigo' => 400, 'message' => 'Nombre demasiado largo'];
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = substr(trim($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'), 0, 255);

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare('UPDATE contadores SET contador = contador + 1 WHERE nombre = ?');
            $stmt->bind_param('s', $nombre);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                $conn->rollback();
                return ['ok' => false, 'codigo' => 404, 'message' => 'La persona no existe'];
            }

            $stmt = $conn->prepare('INSERT INTO pulsaciones (nombre_persona, ip_usuario, user_agent) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $nombre, $ip, $user_agent);
            $stmt->execute();

            $stmt = $conn->prepare('SELECT contador FROM contadores WHERE nombre = ?');
            $stmt->bind_param('s', $nombre);
            $stmt->execute();
            $contador = (int) $stmt->get_result()->fetch_assoc()['contador'];

            $conn->commit();

            return ['ok' => true, 'contador' => $contador];
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            return ['ok' => false, 'codigo' => 500, 'message' => 'Error al registrar la pulsación'];
        }
    }
}
