<?php

namespace App\Models;

use PDO;

/**
 * TransferenciaPendiente — "alta administrativa pendiente" (migración 038).
 * Se crea cuando un activo CON num_activo (placa oficial FEMSA) se mueve de
 * inmediato a una tienda distinta de donde estaba (p. ej. encontrado de más
 * durante una auditoría): el activo ya se movió en el sistema, pero falta
 * avisarle a FEMSA fuera de aquí. Esta tabla rastrea ese pendiente, no el
 * movimiento en sí (eso ya quedó en `movimiento`).
 */
class TransferenciaPendiente
{
    private PDO $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function crear(int $activoId, int $tiendaDestinoId, ?string $ubicacionAnterior): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO transferencia_pendiente (activo_id, tienda_destino_id, ubicacion_anterior)
             VALUES (:activo_id, :tienda_id, :ubicacion)"
        );
        $stmt->execute([
            ':activo_id' => $activoId,
            ':tienda_id' => $tiendaDestinoId,
            ':ubicacion' => $ubicacionAnterior,
        ]);
        return (int) $this->conn->lastInsertId();
    }

    private function selectBase(): string
    {
        return "SELECT tp.id, tp.activo_id, tp.tienda_destino_id, tp.ubicacion_anterior,
                        tp.completado, tp.completado_en, tp.completado_por_usuario_id,
                        tp.creado_en,
                        a.serie, a.codigo_barras, a.num_activo,
                        mo.nombre AS modelo_nombre, mar.nombre AS marca_nombre, d.nombre AS dispositivo_nombre,
                        t.nombre AS tienda_nombre, t.cr_tienda,
                        uc.nombre AS completado_por_nombre
                 FROM transferencia_pendiente tp
                 JOIN activo a ON a.id = tp.activo_id
                 LEFT JOIN modelo mo ON mo.id = a.modelo_id
                 LEFT JOIN marca mar ON mar.id = mo.marca_id
                 LEFT JOIN dispositivo d ON d.id = mo.dispositivo_id
                 JOIN tienda t ON t.id = tp.tienda_destino_id
                 LEFT JOIN usuario uc ON uc.id = tp.completado_por_usuario_id";
    }

    /** Alcance opcional por plaza (ATI/admin no-admin sólo ve las suyas). */
    public function listar(bool $completado, ?array $plazaIds = null): array
    {
        $sql = $this->selectBase() . " WHERE tp.completado = :completado";
        $params = [':completado' => $completado ? 1 : 0];
        if ($plazaIds !== null) {
            if (!$plazaIds) return [];
            $ph = [];
            foreach (array_values($plazaIds) as $i => $pid) {
                $key = ":p{$i}";
                $ph[] = $key;
                $params[$key] = $pid;
            }
            $sql .= " AND t.plaza_id IN (" . implode(',', $ph) . ")";
        }
        $sql .= " ORDER BY tp.creado_en DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): array|false
    {
        $stmt = $this->conn->prepare($this->selectBase() . " WHERE tp.id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function completar(int $id, int $usuarioId): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE transferencia_pendiente
             SET completado = 1, completado_en = NOW(), completado_por_usuario_id = :uid
             WHERE id = :id AND completado = 0"
        );
        $stmt->execute([':uid' => $usuarioId, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
