<?php

namespace App\Models;

use PDO;

class InventarioBodega
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /** Histórico de inventarios de una bodega, más reciente primero. */
    public function listar(int $bodegaId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT ib.id, ib.bodega_id, ib.periodo, ib.estado,
                    ib.total_esperado, ib.total_encontrado,
                    ib.creado_en, ib.cerrado_en,
                    u.nombre AS usuario_nombre
             FROM inventario_bodega ib
             LEFT JOIN usuario u ON u.id = ib.usuario_id
             WHERE ib.bodega_id = :bodega_id
             ORDER BY ib.periodo DESC"
        );
        $stmt->bindParam(':bodega_id', $bodegaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAbierto(int $bodegaId): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM inventario_bodega WHERE bodega_id = :bodega_id AND estado = 'abierto' LIMIT 1"
        );
        $stmt->bindParam(':bodega_id', $bodegaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerCabecera(int $id): array|false
    {
        $stmt = $this->conn->prepare("SELECT * FROM inventario_bodega WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Abre un inventario nuevo con el snapshot de activos indicados
     * (todos en_bodega al momento de iniciar), estado inicial no-encontrado.
     */
    public function crear(int $bodegaId, string $periodo, int $usuarioId, array $activoIds): int
    {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO inventario_bodega (bodega_id, periodo, usuario_id, total_esperado)
                 VALUES (:bodega_id, :periodo, :usuario_id, :total_esperado)"
            );
            $stmt->execute([
                ':bodega_id'      => $bodegaId,
                ':periodo'        => $periodo,
                ':usuario_id'     => $usuarioId,
                ':total_esperado' => count($activoIds),
            ]);
            $id = (int) $this->conn->lastInsertId();

            if ($activoIds) {
                $valores = [];
                $params  = [];
                foreach ($activoIds as $i => $activoId) {
                    $valores[] = "(:inv{$i}, :act{$i})";
                    $params[":inv{$i}"] = $id;
                    $params[":act{$i}"] = $activoId;
                }
                $sql = "INSERT INTO inventario_bodega_detalle (inventario_id, activo_id) VALUES " . implode(',', $valores);
                $this->conn->prepare($sql)->execute($params);
            }

            $this->conn->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /** Cabecera + lista de detalle con datos del activo (clave "detalle"). */
    public function obtenerDetalle(int $inventarioId): array
    {
        $cab = $this->obtenerCabecera($inventarioId);
        if (!$cab) return [];

        $stmt = $this->conn->prepare(
            "SELECT ibd.id AS detalle_id, ibd.activo_id, ibd.encontrado, ibd.escaneado_en, ibd.nota,
                    a.serie, a.codigo_barras, a.num_activo,
                    m.nombre AS modelo_nombre, mar.nombre AS marca_nombre, d.nombre AS dispositivo_nombre
             FROM inventario_bodega_detalle ibd
             JOIN activo a ON a.id = ibd.activo_id
             LEFT JOIN modelo m ON m.id = a.modelo_id
             LEFT JOIN marca mar ON mar.id = m.marca_id
             LEFT JOIN dispositivo d ON d.id = m.dispositivo_id
             WHERE ibd.inventario_id = :id
             ORDER BY d.nombre, a.serie"
        );
        $stmt->bindParam(':id', $inventarioId, PDO::PARAM_INT);
        $stmt->execute();

        $cab['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $cab;
    }

    public function obtenerDetalleRow(int $detalleId): array|false
    {
        $stmt = $this->conn->prepare("SELECT * FROM inventario_bodega_detalle WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $detalleId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Marca como encontrado el detalle cuyo activo coincide con serie,
     * código de barras o núm. de activo. Devuelve el activo_id, o null
     * si el código no pertenece a la lista de este inventario.
     */
    public function marcarEscaneado(int $inventarioId, string $codigo, int $usuarioId): ?int
    {
        $stmt = $this->conn->prepare(
            "SELECT ibd.id, ibd.activo_id
             FROM inventario_bodega_detalle ibd
             JOIN activo a ON a.id = ibd.activo_id
             WHERE ibd.inventario_id = :inv
               AND (a.serie = :codigo OR a.codigo_barras = :codigo2 OR a.num_activo = :codigo3)
             LIMIT 1"
        );
        $stmt->execute([':inv' => $inventarioId, ':codigo' => $codigo, ':codigo2' => $codigo, ':codigo3' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) return null;

        $upd = $this->conn->prepare(
            "UPDATE inventario_bodega_detalle
             SET encontrado = 1, escaneado_en = NOW(), escaneado_por = :usuario_id
             WHERE id = :id"
        );
        $upd->execute([':usuario_id' => $usuarioId, ':id' => $fila['id']]);

        $this->recalcularTotales($inventarioId);
        return (int) $fila['activo_id'];
    }

    public function guardarNota(int $detalleId, string $nota): bool
    {
        $stmt = $this->conn->prepare("UPDATE inventario_bodega_detalle SET nota = :nota WHERE id = :id");
        return $stmt->execute([':nota' => $nota !== '' ? $nota : null, ':id' => $detalleId]);
    }

    public function cerrar(int $id): bool
    {
        $this->recalcularTotales($id);
        $stmt = $this->conn->prepare(
            "UPDATE inventario_bodega SET estado = 'cerrado', cerrado_en = NOW() WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    private function recalcularTotales(int $inventarioId): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE inventario_bodega ib
             SET total_encontrado = (
                 SELECT COUNT(*) FROM inventario_bodega_detalle WHERE inventario_id = ib.id AND encontrado = 1
             )
             WHERE ib.id = :id"
        );
        $stmt->execute([':id' => $inventarioId]);
    }
}
