<?php

namespace App\Models;

use PDO;

/**
 * InventarioTienda — checklist de inventario POR TIENDA (migración 036).
 * Mismo mecanismo que InventarioBodega (abrir→snapshot→escanear→cerrar) pero
 * NO es lo mismo: aquí no hay período mensual (es por-visita) y el objetivo
 * es "los activos instalados en ESTA tienda ahora mismo", no una bodega ni
 * un stock personal. Al cerrar, además, cachea el resumen en
 * `tienda.ultimo_inv_*` (ver Tienda::guardarUltimoInventario) para que la
 * lista de Tiendas y el Dashboard no tengan que calcularlo con un JOIN.
 */
class InventarioTienda
{
    private PDO $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function obtenerAbierto(int $tiendaId): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM inventario_tienda WHERE tienda_id = :tienda_id AND estado = 'abierto' LIMIT 1"
        );
        $stmt->execute([':tienda_id' => $tiendaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerCabecera(int $id): array|false
    {
        $stmt = $this->conn->prepare("SELECT * FROM inventario_tienda WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Abre el checklist: snapshot de los activos HOY instalados en la
     * tienda (stock_tipo='tienda'), todos no-encontrados de inicio.
     */
    public function crear(int $tiendaId, int $usuarioId): int
    {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO inventario_tienda (tienda_id, usuario_id, total_esperado)
                 VALUES (:tienda_id, :usuario_id, 0)"
            );
            $stmt->execute([':tienda_id' => $tiendaId, ':usuario_id' => $usuarioId]);
            $id = (int) $this->conn->lastInsertId();

            $this->conn->prepare(
                "INSERT INTO inventario_tienda_detalle (inventario_id, activo_id)
                 SELECT :inv, a.id FROM activo a
                 JOIN stock s ON s.id = a.stock_id
                 WHERE s.tipo = 'tienda' AND s.tienda_id = :tienda_id"
            )->execute([':inv' => $id, ':tienda_id' => $tiendaId]);

            $this->conn->prepare(
                "UPDATE inventario_tienda it
                 SET total_esperado = (SELECT COUNT(*) FROM inventario_tienda_detalle WHERE inventario_id = it.id)
                 WHERE it.id = :id"
            )->execute([':id' => $id]);

            $this->conn->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    /** Auditorías cerradas, más reciente primero — módulo "Auditoría". $plazaIds null = todas (admin). */
    public function listarCerradas(?array $plazaIds = null): array
    {
        $sql = "SELECT it.id, it.tienda_id, t.nombre AS tienda_nombre, t.cr_tienda,
                       it.usuario_id, u.nombre AS usuario_nombre,
                       it.total_esperado, it.total_encontrado, it.creado_en, it.cerrado_en
                FROM inventario_tienda it
                JOIN tienda t ON t.id = it.tienda_id
                JOIN usuario u ON u.id = it.usuario_id
                WHERE it.estado = 'cerrado'";
        $params = [];
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
        $sql .= " ORDER BY it.cerrado_en DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cabecera + detalle con datos del activo (clave "detalle"). */
    public function obtenerDetalle(int $inventarioId): array
    {
        $cab = $this->obtenerCabecera($inventarioId);
        if (!$cab) return [];

        $stmt = $this->conn->prepare(
            "SELECT itd.id AS detalle_id, itd.activo_id, itd.encontrado, itd.escaneado_en, itd.nota,
                    a.serie, a.codigo_barras, a.num_activo,
                    m.nombre AS modelo_nombre, mar.nombre AS marca_nombre, d.nombre AS dispositivo_nombre
             FROM inventario_tienda_detalle itd
             JOIN activo a ON a.id = itd.activo_id
             LEFT JOIN modelo m ON m.id = a.modelo_id
             LEFT JOIN marca mar ON mar.id = m.marca_id
             LEFT JOIN dispositivo d ON d.id = m.dispositivo_id
             WHERE itd.inventario_id = :id
             ORDER BY d.nombre, a.serie"
        );
        $stmt->execute([':id' => $inventarioId]);

        $cab['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $cab;
    }

    public function obtenerDetalleRow(int $detalleId): array|false
    {
        $stmt = $this->conn->prepare("SELECT * FROM inventario_tienda_detalle WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $detalleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Marca encontrado el detalle cuyo activo coincide con serie, código de
     * barras o N° de activo. Devuelve el activo_id, o null si el código no
     * pertenece a este inventario (activo de otra tienda, por ejemplo).
     */
    public function marcarEscaneado(int $inventarioId, string $codigo, int $usuarioId): ?int
    {
        $stmt = $this->conn->prepare(
            "SELECT itd.id, itd.activo_id
             FROM inventario_tienda_detalle itd
             JOIN activo a ON a.id = itd.activo_id
             WHERE itd.inventario_id = :inv
               AND (a.serie = :codigo OR a.codigo_barras = :codigo2 OR a.num_activo = :codigo3)
             LIMIT 1"
        );
        $stmt->execute([':inv' => $inventarioId, ':codigo' => $codigo, ':codigo2' => $codigo, ':codigo3' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) return null;

        $this->marcar((int) $fila['id'], true, $usuarioId);
        $this->recalcularTotales($inventarioId);
        return (int) $fila['activo_id'];
    }

    /** Toque manual en pantalla (✓/✗), no por escaneo — el nuevo diseño lo permite tocando el icono. */
    public function marcar(int $detalleId, bool $encontrado, int $usuarioId): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE inventario_tienda_detalle
             SET encontrado = :enc, escaneado_en = NOW(), escaneado_por = :usuario_id
             WHERE id = :id"
        );
        $stmt->execute([':enc' => $encontrado ? 1 : 0, ':usuario_id' => $usuarioId, ':id' => $detalleId]);
    }

    public function guardarNota(int $detalleId, string $nota): bool
    {
        $stmt = $this->conn->prepare("UPDATE inventario_tienda_detalle SET nota = :nota WHERE id = :id");
        return $stmt->execute([':nota' => $nota !== '' ? $nota : null, ':id' => $detalleId]);
    }

    /**
     * Activo encontrado en la tienda que NO estaba en el snapshot original
     * (FAB "Agregar activo" del modo inventario, sin pestañas ni select de
     * stock). Entra ya marcado encontrado=1 — la app lo etiqueta "NUEVO".
     */
    public function agregarNuevo(int $inventarioId, int $activoId, int $usuarioId): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO inventario_tienda_detalle (inventario_id, activo_id, encontrado, escaneado_en, escaneado_por)
             VALUES (:inv, :activo, 1, NOW(), :usuario_id)"
        );
        $stmt->execute([':inv' => $inventarioId, ':activo' => $activoId, ':usuario_id' => $usuarioId]);
        $id = (int) $this->conn->lastInsertId();

        $this->conn->prepare(
            "UPDATE inventario_tienda it
             SET total_esperado = (SELECT COUNT(*) FROM inventario_tienda_detalle WHERE inventario_id = it.id)
             WHERE it.id = :id"
        )->execute([':id' => $inventarioId]);
        $this->recalcularTotales($inventarioId);
        return $id;
    }

    /** Cierra el checklist y cachea el resumen en `tienda` (ver Tienda::guardarUltimoInventario). */
    public function cerrar(int $id): array
    {
        $this->recalcularTotales($id);
        $cab = $this->obtenerCabecera($id);
        $noEncontrados = (int) $cab['total_esperado'] - (int) $cab['total_encontrado'];

        $this->conn->prepare(
            "UPDATE inventario_tienda SET estado = 'cerrado', cerrado_en = NOW() WHERE id = :id"
        )->execute([':id' => $id]);

        (new Tienda($this->conn))->guardarUltimoInventario(
            (int) $cab['tienda_id'], date('Y-m-d'), (int) $cab['total_encontrado'], $noEncontrados
        );

        return ['encontrados' => (int) $cab['total_encontrado'], 'no_encontrados' => $noEncontrados];
    }

    /** Cancela sin guardar nada en la tienda (el "X" del nuevo diseño descarta). */
    public function cancelar(int $id): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM inventario_tienda WHERE id = :id AND estado = 'abierto'");
        return $stmt->execute([':id' => $id]);
    }

    private function recalcularTotales(int $inventarioId): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE inventario_tienda it
             SET total_encontrado = (
                 SELECT COUNT(*) FROM inventario_tienda_detalle WHERE inventario_id = it.id AND encontrado = 1
             )
             WHERE it.id = :id"
        );
        $stmt->execute([':id' => $inventarioId]);
    }
}
