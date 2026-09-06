<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * SolicitudTraslado — solicitud de MOVIMIENTO de activos con firma.
 *
 * Todo cambio de dueño/estatus de un activo (salvo el alta y la instalación en
 * tienda) pasa por aquí. El destino y quién aprueba dependen del tipo:
 *
 *   destino    origen            aprueban / firman
 *   --------   ---------------   ------------------------------------
 *   asignado   ingeniero A       el ingeniero B que recibe
 *   en_bodega  ingeniero/tienda  un coordinador de la plaza
 *   baja       ingeniero/tienda  un ATI de la plaza
 *   garantia   ingeniero/tienda  un ATI  Y  un coordinador (doble firma)
 *
 * Nada se ejecuta hasta reunir todas las firmas: ver App\Services\TrasladoService.
 */
class SolicitudTraslado
{
    private PDO $conn;
    private string $table = 'solicitud_traslado';

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'aprobada'  => 'Aprobada',
        'rechazada' => 'Rechazada',
        'cancelada' => 'Cancelada',
    ];

    public const DESTINOS = [
        'asignado'  => 'Traspaso a otro ingeniero',
        'en_bodega' => 'Devolución a bodega',
        'baja'      => 'Baja',
        'garantia'  => 'Garantía',
    ];

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /** ¿El destino requiere doble firma de aprobación (ATI + coordinador)? */
    public static function requiereDobleAprobacion(string $destino): bool
    {
        return $destino === 'garantia';
    }

    /**
     * @param array $d  destino, plaza_id, solicitante_id, firma_solicitante,
     *                  nota, grupo_id, activos (int[]),
     *                  origen_usuario_id|null, origen_tienda_id|null,
     *                  destino_bodega_id|null, destino_usuario_id|null
     * @return int  id (0 si falla)
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
                    (estado, destino, plaza_id, origen_usuario_id, origen_tienda_id,
                     destino_bodega_id, destino_usuario_id, solicitante_id,
                     firma_solicitante, firmado_solicitante_en, nota, grupo_id)
                 VALUES
                    ('pendiente', :destino, :plaza_id, :origen_usuario_id, :origen_tienda_id,
                     :destino_bodega_id, :destino_usuario_id, :solicitante_id,
                     :firma_solicitante, CURRENT_TIMESTAMP, :nota, :grupo_id)"
            );
            $stmt->execute([
                ':destino'            => $d['destino'],
                ':plaza_id'           => (int) $d['plaza_id'],
                ':origen_usuario_id'  => !empty($d['origen_usuario_id']) ? (int) $d['origen_usuario_id'] : null,
                ':origen_tienda_id'   => !empty($d['origen_tienda_id']) ? (int) $d['origen_tienda_id'] : null,
                ':destino_bodega_id'  => !empty($d['destino_bodega_id']) ? (int) $d['destino_bodega_id'] : null,
                ':destino_usuario_id' => !empty($d['destino_usuario_id']) ? (int) $d['destino_usuario_id'] : null,
                ':solicitante_id'     => (int) $d['solicitante_id'],
                ':firma_solicitante'  => $d['firma_solicitante'] ?? null,
                ':nota'               => ($d['nota'] ?? '') !== '' ? mb_substr((string) $d['nota'], 0, 255) : null,
                ':grupo_id'           => $d['grupo_id'] ?? null,
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

    private const SELECT_CABECERA = "
        s.*,
        p.nombre  AS plaza_nombre,
        b.nombre  AS bodega_nombre,
        uo.nombre AS origen_nombre,
        ot.nombre AS origen_tienda_nombre,
        ud.nombre AS destino_usuario_nombre,
        us.nombre AS solicitante_nombre,
        ua.nombre AS aprobador_nombre,
        ua2.nombre AS aprobador2_nombre";

    private const JOINS_CABECERA = "
        LEFT JOIN plaza   p   ON p.id  = s.plaza_id
        LEFT JOIN bodega  b   ON b.id  = s.destino_bodega_id
        LEFT JOIN usuario uo  ON uo.id = s.origen_usuario_id
        LEFT JOIN tienda  ot  ON ot.id = s.origen_tienda_id
        LEFT JOIN usuario ud  ON ud.id = s.destino_usuario_id
        LEFT JOIN usuario us  ON us.id = s.solicitante_id
        LEFT JOIN usuario ua  ON ua.id = s.aprobador_id
        LEFT JOIN usuario ua2 ON ua2.id = s.aprobador2_id";

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT " . self::SELECT_CABECERA . " FROM {$this->table} s " . self::JOINS_CABECERA . " WHERE s.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function activosDe(int $solicitudId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT a.id, a.serie, a.codigo_barras, a.num_activo, a.status,
                    mo.nombre AS modelo_nombre, ma.nombre AS marca_nombre, d.nombre AS dispositivo_nombre
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

    private function listar(string $where, array $params): array
    {
        $stmt = $this->conn->prepare(
            "SELECT " . self::SELECT_CABECERA . ",
                    (SELECT COUNT(*) FROM solicitud_traslado_activo sta WHERE sta.solicitud_id = s.id) AS activos_count
             FROM {$this->table} s " . self::JOINS_CABECERA . "
             {$where}
             ORDER BY FIELD(s.estado,'pendiente')=1 DESC, s.creado_en DESC, s.id DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Solicitudes en las que el usuario es parte (solicitante, origen o destino). */
    public function listarDeUsuario(int $usuarioId): array
    {
        return $this->listar(
            'WHERE s.solicitante_id = :u OR s.origen_usuario_id = :u2 OR s.destino_usuario_id = :u3',
            [':u' => $usuarioId, ':u2' => $usuarioId, ':u3' => $usuarioId]
        );
    }

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

    /**
     * Solicitudes 'pendiente' que este usuario puede resolver según su rol.
     * @param bool $esCoord  el usuario es coordinador (o admin actuando como)
     * @param bool $esAti    el usuario es ATI (o admin actuando como)
     * @param bool $esAdmin  admin: puede resolver en cualquier plaza
     */
    public function pendientesParaResolver(int $usuarioId, array $plazaIds, bool $esCoord, bool $esAti, bool $esAdmin): array
    {
        $cond    = [];
        $params  = [':u' => $usuarioId];

        // asignado -> el ingeniero que recibe
        $cond[] = "(s.destino = 'asignado' AND s.destino_usuario_id = :u)";

        $filtroPlaza = '';
        if (!$esAdmin) {
            $plazaIds = array_values(array_filter(array_map('intval', $plazaIds)));
            if (!$plazaIds) {
                $plazaIds = [-1];
            }
            $ph = [];
            foreach ($plazaIds as $i => $pid) {
                $ph[] = ":pl{$i}";
                $params[":pl{$i}"] = $pid;
            }
            $filtroPlaza = ' AND s.plaza_id IN (' . implode(',', $ph) . ')';
        }

        if ($esCoord || $esAdmin) {
            $cond[] = "(s.destino = 'en_bodega'{$filtroPlaza})";
            // garantía: el slot de coordinador aún sin firmar
            $cond[] = "(s.destino = 'garantia' AND s.aprobador2_id IS NULL{$filtroPlaza})";
        }
        if ($esAti || $esAdmin) {
            $cond[] = "(s.destino = 'baja'{$filtroPlaza})";
            // garantía: el slot de ATI aún sin firmar
            $cond[] = "(s.destino = 'garantia' AND s.aprobador_id IS NULL{$filtroPlaza})";
        }

        return $this->listar("WHERE s.estado = 'pendiente' AND (" . implode(' OR ', $cond) . ")", $params);
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

    /**
     * Registra una firma de aprobación. Devuelve:
     *   'lista'      → ya tiene todas las firmas necesarias (hay que ejecutar)
     *   'parcial'    → firma registrada, falta la otra (garantía)
     *   'duplicada'  → este rol ya había firmado
     *   'no_aplica'  → el usuario no puede firmar esta solicitud
     *   false        → la solicitud ya no está pendiente
     *
     * @param string $rol  'coordinador' | 'ati' | 'ingeniero' (según el destino)
     */
    public function firmarAprobacion(int $id, int $usuarioId, ?string $firma, string $rol): string|false
    {
        $sol = $this->obtenerPorId($id);
        if (!$sol || $sol['estado'] !== 'pendiente') {
            return false;
        }
        $destino = $sol['destino'];

        // ¿En qué "slot" firma este rol?
        if ($destino === 'garantia') {
            $slot = $rol === 'ati' ? 1 : ($rol === 'coordinador' ? 2 : 0);
        } else {
            $slot = 1; // asignado / en_bodega / baja: un solo firmante
        }
        if ($slot === 0) {
            return 'no_aplica';
        }

        if ($slot === 1) {
            if (!empty($sol['aprobador_id'])) return 'duplicada';
            $this->conn->prepare(
                "UPDATE {$this->table} SET aprobador_id = :u, firma_aprobador = :f, firmado_aprobador_en = CURRENT_TIMESTAMP
                 WHERE id = :id AND estado = 'pendiente'"
            )->execute([':u' => $usuarioId, ':f' => $firma, ':id' => $id]);
        } else {
            if (!empty($sol['aprobador2_id'])) return 'duplicada';
            $this->conn->prepare(
                "UPDATE {$this->table} SET aprobador2_id = :u, firma_aprobador2 = :f, firmado_aprobador2_en = CURRENT_TIMESTAMP
                 WHERE id = :id AND estado = 'pendiente'"
            )->execute([':u' => $usuarioId, ':f' => $firma, ':id' => $id]);
        }

        $sol = $this->obtenerPorId($id);
        $completa = self::requiereDobleAprobacion($destino)
            ? (!empty($sol['aprobador_id']) && !empty($sol['aprobador2_id']))
            : !empty($sol['aprobador_id']);

        return $completa ? 'lista' : 'parcial';
    }

    /** Marca la solicitud como aprobada (tras ejecutar el movimiento). */
    public function marcarAprobada(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET estado = 'aprobada', resuelto_en = CURRENT_TIMESTAMP
             WHERE id = :id AND estado = 'pendiente'"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function marcarRechazada(int $id, int $aprobadorId, string $motivo): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET estado = 'rechazada', aprobador_id = COALESCE(aprobador_id, :ap),
                 motivo_rechazo = :motivo, resuelto_en = CURRENT_TIMESTAMP
             WHERE id = :id AND estado = 'pendiente'"
        );
        $stmt->execute([':ap' => $aprobadorId, ':motivo' => mb_substr($motivo, 0, 255), ':id' => $id]);
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
