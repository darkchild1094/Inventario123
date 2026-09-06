<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * SolicitudTraslado — cabecera del flujo "ingeniero manda su stock a bodega
 * con doble firma". Una solicitud agrupa N activos (status 'asignado' del
 * ingeniero) y no ejecuta ningún cambio de stock hasta que el coordinador
 * la aprueba y firma. Ver App\Services\TrasladoService::ejecutar().
 */
class SolicitudTraslado
{
    private PDO $conn;
    private string $table = 'solicitud_traslado';

    public const ESTADOS = [
        'pendiente'  => 'Pendiente',
        'aprobada'   => 'Aprobada',
        'rechazada'  => 'Rechazada',
        'cancelada'  => 'Cancelada',
    ];

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Crea una solicitud 'pendiente' con la firma del solicitante ya cargada.
     *
     * @param array $d  plaza_id, origen_usuario_id, destino_bodega_id,
     *                   solicitante_id, firma_solicitante (archivo), nota,
     *                   grupo_id, activos (int[])
     * @return int  id de la solicitud creada (0 si falla)
     */
    public function crear(array $d): int
    {
        $activos = array_values(array_unique(array_map('intval', $d['activos'] ?? [])));
        if (!$activos) {
            return 0;
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table}
                    (estado, plaza_id, origen_usuario_id, destino_bodega_id, solicitante_id,
                     firma_solicitante, firmado_solicitante_en, nota, grupo_id)
                 VALUES
                    ('pendiente', :plaza_id, :origen_usuario_id, :destino_bodega_id, :solicitante_id,
                     :firma_solicitante, CURRENT_TIMESTAMP, :nota, :grupo_id)"
            );
            $stmt->execute([
                ':plaza_id'          => (int) $d['plaza_id'],
                ':origen_usuario_id' => (int) $d['origen_usuario_id'],
                ':destino_bodega_id' => (int) $d['destino_bodega_id'],
                ':solicitante_id'    => (int) $d['solicitante_id'],
                ':firma_solicitante' => $d['firma_solicitante'] ?? null,
                ':nota'              => ($d['nota'] ?? '') !== '' ? mb_substr((string) $d['nota'], 0, 255) : null,
                ':grupo_id'          => $d['grupo_id'] ?? null,
            ]);
            $id = (int) $this->conn->lastInsertId();

            $ins = $this->conn->prepare(
                "INSERT INTO solicitud_traslado_activo (solicitud_id, activo_id) VALUES (:s, :a)"
            );
            foreach ($activos as $activoId) {
                $ins->execute([':s' => $id, ':a' => $activoId]);
            }

            $this->conn->commit();
            return $id;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('SolicitudTraslado::crear ' . $e->getMessage());
            return 0;
        }
    }

    /** Cabecera + nombres legibles. */
    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT s.*,
                    p.nombre  AS plaza_nombre,
                    b.nombre  AS bodega_nombre,
                    uo.nombre AS origen_nombre,
                    us.nombre AS solicitante_nombre,
                    ua.nombre AS aprobador_nombre
             FROM {$this->table} s
             LEFT JOIN plaza   p  ON p.id  = s.plaza_id
             LEFT JOIN bodega  b  ON b.id  = s.destino_bodega_id
             LEFT JOIN usuario uo ON uo.id = s.origen_usuario_id
             LEFT JOIN usuario us ON us.id = s.solicitante_id
             LEFT JOIN usuario ua ON ua.id = s.aprobador_id
             WHERE s.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Activos incluidos en una solicitud, con marca/modelo/serie/código. */
    public function activosDe(int $solicitudId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT a.id, a.serie, a.codigo_barras, a.num_activo, a.status,
                    mo.nombre AS modelo_nombre,
                    ma.nombre AS marca_nombre,
                    d.nombre  AS dispositivo_nombre
             FROM solicitud_traslado_activo sta
             JOIN activo a       ON a.id  = sta.activo_id
             LEFT JOIN modelo mo ON mo.id = a.modelo_id
             LEFT JOIN marca  ma ON ma.id = mo.marca_id
             LEFT JOIN dispositivo d ON d.id = mo.dispositivo_id
             WHERE sta.solicitud_id = :id
             ORDER BY d.nombre, mo.nombre, a.serie"
        );
        $stmt->bindValue(':id', $solicitudId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lista (con conteo de activos) para el índice. */
    private function listar(string $where, array $params): array
    {
        $stmt = $this->conn->prepare(
            "SELECT s.*,
                    p.nombre  AS plaza_nombre,
                    b.nombre  AS bodega_nombre,
                    uo.nombre AS origen_nombre,
                    ua.nombre AS aprobador_nombre,
                    (SELECT COUNT(*) FROM solicitud_traslado_activo sta WHERE sta.solicitud_id = s.id) AS activos_count
             FROM {$this->table} s
             LEFT JOIN plaza   p  ON p.id  = s.plaza_id
             LEFT JOIN bodega  b  ON b.id  = s.destino_bodega_id
             LEFT JOIN usuario uo ON uo.id = s.origen_usuario_id
             LEFT JOIN usuario ua ON ua.id = s.aprobador_id
             {$where}
             ORDER BY FIELD(s.estado,'pendiente')=1 DESC, s.creado_en DESC, s.id DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Solicitudes creadas por / para un ingeniero. */
    public function listarDeUsuario(int $usuarioId): array
    {
        return $this->listar(
            'WHERE s.origen_usuario_id = :u OR s.solicitante_id = :u2',
            [':u' => $usuarioId, ':u2' => $usuarioId]
        );
    }

    /** Solicitudes de un conjunto de plazas (coordinador/admin). */
    public function listarPorPlazas(array $plazaIds, ?string $estado = null): array
    {
        $plazaIds = array_values(array_filter(array_map('intval', $plazaIds)));
        if (!$plazaIds) {
            return [];
        }
        $in = [];
        $params = [];
        foreach ($plazaIds as $i => $pid) {
            $in[] = ":p{$i}";
            $params[":p{$i}"] = $pid;
        }
        $where = 'WHERE s.plaza_id IN (' . implode(',', $in) . ')';
        if ($estado !== null && isset(self::ESTADOS[$estado])) {
            $where .= ' AND s.estado = :estado';
            $params[':estado'] = $estado;
        }
        return $this->listar($where, $params);
    }

    /** Todas (admin sin filtro de plaza). */
    public function listarTodas(?string $estado = null): array
    {
        $where  = 'WHERE 1=1';
        $params = [];
        if ($estado !== null && isset(self::ESTADOS[$estado])) {
            $where .= ' AND s.estado = :estado';
            $params[':estado'] = $estado;
        }
        return $this->listar($where, $params);
    }

    public function contarPendientesPorPlazas(array $plazaIds): int
    {
        $plazaIds = array_values(array_filter(array_map('intval', $plazaIds)));
        if (!$plazaIds) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($plazaIds), '?'));
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE estado = 'pendiente' AND plaza_id IN ({$in})"
        );
        $stmt->execute($plazaIds);
        return (int) $stmt->fetchColumn();
    }

    public function contarPendientesTodas(): int
    {
        return (int) $this->conn->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE estado = 'pendiente'"
        )->fetchColumn();
    }

    /** ¿Alguno de estos activos ya está en una solicitud pendiente? Devuelve ids. */
    public function activosEnSolicitudPendiente(array $activoIds, ?int $exceptoSolicitud = null): array
    {
        $activoIds = array_values(array_filter(array_map('intval', $activoIds)));
        if (!$activoIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($activoIds), '?'));
        $sql = "SELECT DISTINCT sta.activo_id
                FROM solicitud_traslado_activo sta
                JOIN {$this->table} s ON s.id = sta.solicitud_id
                WHERE s.estado = 'pendiente' AND sta.activo_id IN ({$in})";
        $params = $activoIds;
        if ($exceptoSolicitud !== null) {
            $sql .= ' AND s.id <> ?';
            $params[] = $exceptoSolicitud;
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function marcarAprobada(int $id, int $aprobadorId, ?string $firmaArchivo): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET estado = 'aprobada', aprobador_id = :ap, firma_aprobador = :firma,
                 firmado_aprobador_en = CURRENT_TIMESTAMP, resuelto_en = CURRENT_TIMESTAMP
             WHERE id = :id AND estado = 'pendiente'"
        );
        $stmt->execute([':ap' => $aprobadorId, ':firma' => $firmaArchivo, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function marcarRechazada(int $id, int $aprobadorId, string $motivo): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET estado = 'rechazada', aprobador_id = :ap,
                 motivo_rechazo = :motivo, resuelto_en = CURRENT_TIMESTAMP
             WHERE id = :id AND estado = 'pendiente'"
        );
        $stmt->execute([
            ':ap'     => $aprobadorId,
            ':motivo' => mb_substr($motivo, 0, 255),
            ':id'     => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function marcarCancelada(int $id, int $usuarioId): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET estado = 'cancelada', resuelto_en = CURRENT_TIMESTAMP
             WHERE id = :id AND estado = 'pendiente'
               AND (solicitante_id = :u OR origen_usuario_id = :u2)"
        );
        $stmt->execute([':id' => $id, ':u' => $usuarioId, ':u2' => $usuarioId]);
        return $stmt->rowCount() > 0;
    }
}
