<?php

namespace App\Models;

use PDO;

/**
 * Notificacion — aviso de un "Enviar" (Bodega↔Mi stock↔usuario, ver
 * migración 035). El envío en sí es inmediato (sin aceptación); esto es
 * solo el registro para que el destinatario se entere de qué le llegó o
 * qué envió, aunque su teléfono haya estado offline cuando pasó.
 */
class Notificacion
{
    private PDO $conn;
    private string $table = 'notificacion';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Crea una notificación con sus activos. $activosIds va a
     * notificacion_activo para mostrarlos sin tener que reconstruir el
     * grupo desde `movimiento`.
     */
    public function crear(array $d, array $activosIds): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table}
                (usuario_destino_id, de_usuario_id, direccion, lugar, grupo_id)
             VALUES (:destino, :de, :direccion, :lugar, :grupo)"
        );
        $stmt->execute([
            ':destino'   => $d['usuario_destino_id'],
            ':de'        => $d['de_usuario_id'],
            ':direccion' => $d['direccion'],
            ':lugar'     => $d['lugar'],
            ':grupo'     => $d['grupo_id'],
        ]);
        $id = (int) $this->conn->lastInsertId();

        if ($activosIds) {
            $valores = [];
            $params  = [];
            foreach ($activosIds as $i => $activoId) {
                $valores[] = "(:nid, :aid{$i})";
                $params[":aid{$i}"] = $activoId;
            }
            $params[':nid'] = $id;
            $sql = "INSERT INTO notificacion_activo (notificacion_id, activo_id) VALUES "
                 . implode(',', $valores);
            $ins = $this->conn->prepare($sql);
            $ins->execute($params);
        }
        return $id;
    }

    /** Lista de un usuario, más reciente primero, con sus activos agrupados. */
    public function listarDeUsuario(int $usuarioId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT n.id, n.usuario_destino_id, n.de_usuario_id, n.direccion, n.lugar,
                    n.grupo_id, n.leida, n.creado_en, u.nombre AS de_nombre
             FROM {$this->table} n
             JOIN usuario u ON u.id = n.de_usuario_id
             WHERE n.usuario_destino_id = :uid
             ORDER BY n.id DESC"
        );
        $stmt->execute([':uid' => $usuarioId]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$notifs) return [];

        $ids = array_column($notifs, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $act = $this->conn->prepare(
            "SELECT na.notificacion_id, a.id, a.serie, a.codigo_barras, a.num_activo,
                    m.nombre AS modelo_nombre, mc.nombre AS marca_nombre, d.nombre AS dispositivo_nombre
             FROM notificacion_activo na
             JOIN activo a ON a.id = na.activo_id
             JOIN modelo m ON m.id = a.modelo_id
             LEFT JOIN marca mc ON mc.id = m.marca_id
             JOIN dispositivo d ON d.id = m.dispositivo_id
             WHERE na.notificacion_id IN ({$ph})"
        );
        $act->execute($ids);
        $porNotif = [];
        foreach ($act->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $porNotif[$row['notificacion_id']][] = $row;
        }
        foreach ($notifs as &$n) {
            $n['activos'] = $porNotif[$n['id']] ?? [];
        }
        return $notifs;
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE usuario_destino_id = :uid AND leida = 0"
        );
        $stmt->execute([':uid' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    /** $id null = todas las del usuario. */
    public function marcarLeida(int $usuarioId, ?int $id = null): void
    {
        if ($id !== null) {
            $stmt = $this->conn->prepare(
                "UPDATE {$this->table} SET leida = 1 WHERE id = :id AND usuario_destino_id = :uid"
            );
            $stmt->execute([':id' => $id, ':uid' => $usuarioId]);
        } else {
            $stmt = $this->conn->prepare(
                "UPDATE {$this->table} SET leida = 1 WHERE usuario_destino_id = :uid AND leida = 0"
            );
            $stmt->execute([':uid' => $usuarioId]);
        }
    }
}
