<?php

namespace App\Models;

use PDO;

/**
 * ProyectoRentec — agrupa bajo un folio los activos de un proyecto de
 * Renovación Tecnológica. No reinventa el movimiento de inventario: reutiliza
 * el alta normal (status=en_bodega) y el modo Reemplazo de Tiendas (que ya
 * enlaza instalado↔retirado vía movimiento.grupo_id/activo_relacionado_id) —
 * solo les añade `proyecto_rentec_id` para poder listarlos y exportarlos.
 */
class ProyectoRentec
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Condición SQL: movimientos de instalación reales de un proyecto.
     * Un alta directa que ADEMÁS reemplaza a otro activo genera DOS filas de
     * movimiento para el mismo activo_id ('alta' y 'reemplazo_entra', ambas
     * con status_nuevo='en_uso') — sin el NOT EXISTS de abajo, ambas
     * calificarían y el activo saldría duplicado en el listado (el COUNT
     * DISTINCT del conteo lo disimulaba, pero el detalle no). Cuando existe
     * el 'reemplazo_entra' (más informativo: trae el relacionado) se prefiere
     * ese y se descarta el 'alta' gemelo del mismo activo+proyecto.
     */
    private const COND_INSTALADO =
        "m.status_nuevo = 'en_uso' AND (
            m.evento = 'reemplazo_entra'
            OR (m.evento = 'alta' AND NOT EXISTS (
                SELECT 1 FROM movimiento m3
                WHERE m3.activo_id = m.activo_id
                  AND m3.proyecto_rentec_id = m.proyecto_rentec_id
                  AND m3.evento = 'reemplazo_entra'
            ))
        )";

    /**
     * Crea el folio. La plaza se graba al momento (migración 032) para que el
     * proyecto sea visible para todo su equipo desde el primer instante, sin
     * esperar a que tenga movimientos.
     */
    public function crear(string $nombre, int $usuarioId, int $plazaId): array
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO proyecto_rentec (nombre, usuario_id, plaza_id)
             VALUES (:nombre, :usuario_id, :plaza_id)"
        );
        $stmt->execute([
            ':nombre'     => $nombre,
            ':usuario_id' => $usuarioId,
            ':plaza_id'   => $plazaId > 0 ? $plazaId : null,
        ]);
        $id = (int) $this->conn->lastInsertId();

        $folio = 'RENTEC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $this->conn->prepare("UPDATE proyecto_rentec SET folio = :folio WHERE id = :id")
            ->execute([':folio' => $folio, ':id' => $id]);

        return ['id' => $id, 'folio' => $folio];
    }

    /**
     * Lista de proyectos, más reciente primero. $plazaIds = null → sin acotar
     * (admin). Un proyecto siempre es visible para quien lo creó (aunque
     * todavía no tenga ningún movimiento, recién creado) o para cualquiera
     * con acceso a una plaza que ya haya tocado.
     */
    public function listar(?array $plazaIds, int $usuarioId): array
    {
        $sql = "SELECT pr.id, pr.folio, pr.nombre, pr.estado, pr.usuario_id, u.nombre AS usuario_nombre,
                       pr.creado_en, pr.cerrado_en,
                       (SELECT COUNT(*) FROM activo a WHERE a.proyecto_rentec_id = pr.id AND a.status = 'en_bodega') AS recibidos,
                       (SELECT COUNT(DISTINCT m.activo_id) FROM movimiento m WHERE m.proyecto_rentec_id = pr.id AND " . self::COND_INSTALADO . ") AS instalados
                FROM proyecto_rentec pr
                LEFT JOIN usuario u ON u.id = pr.usuario_id";

        $params = [];
        if ($plazaIds !== null) {
            // Dos juegos de placeholders con nombres distintos: los prepares
            // nativos (EMULATE_PREPARES=false) no permiten reutilizar el mismo
            // :nombre en dos sitios de la consulta.
            $phPlaza = [];
            $phMov   = [];
            foreach (array_values($plazaIds) as $i => $pid) {
                $phPlaza[] = ":pp{$i}";
                $phMov[]   = ":pm{$i}";
                $params[":pp{$i}"] = (int) $pid;
                $params[":pm{$i}"] = (int) $pid;
            }
            // La plaza del proyecto manda (migración 032). Se conserva la
            // condición por movimientos para las filas viejas que quedaron sin
            // plaza, y la del creador como red de seguridad.
            $condPlazas = $phPlaza
                ? "pr.plaza_id IN (" . implode(',', $phPlaza) . ")
                   OR EXISTS (SELECT 1 FROM movimiento m2
                              WHERE m2.proyecto_rentec_id = pr.id
                                AND m2.plaza_id IN (" . implode(',', $phMov) . "))"
                : '0';
            $sql .= " WHERE (pr.usuario_id = :usuario_id OR {$condPlazas})";
            $params[':usuario_id'] = $usuarioId;
        }
        $sql .= " ORDER BY pr.creado_en DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCabecera(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT pr.id, pr.folio, pr.nombre, pr.estado, pr.usuario_id, u.nombre AS usuario_nombre,
                    pr.creado_en, pr.cerrado_en,
                    (SELECT COUNT(*) FROM activo a WHERE a.proyecto_rentec_id = pr.id AND a.status = 'en_bodega') AS recibidos,
                    (SELECT COUNT(DISTINCT m.activo_id) FROM movimiento m WHERE m.proyecto_rentec_id = pr.id AND " . self::COND_INSTALADO . ") AS instalados
             FROM proyecto_rentec pr
             LEFT JOIN usuario u ON u.id = pr.usuario_id
             WHERE pr.id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Cabecera + activos recibidos en bodega (pendientes de instalar) + ya instalados. */
    public function obtenerDetalle(int $id): array
    {
        $cab = $this->obtenerCabecera($id);
        if (!$cab) return [];

        $recibidos = $this->conn->prepare(
            "SELECT a.id AS activo_id, a.serie, a.codigo_barras, a.num_activo,
                    mo.nombre AS modelo_nombre, mar.nombre AS marca_nombre, di.nombre AS dispositivo_nombre
             FROM activo a
             LEFT JOIN modelo mo ON mo.id = a.modelo_id
             LEFT JOIN marca mar ON mar.id = mo.marca_id
             LEFT JOIN dispositivo di ON di.id = mo.dispositivo_id
             WHERE a.proyecto_rentec_id = :id AND a.status = 'en_bodega'
             ORDER BY di.nombre, a.serie"
        );
        $recibidos->bindParam(':id', $id, PDO::PARAM_INT);
        $recibidos->execute();
        // OJO: 'recibidos'/'instalados' en la cabecera son CONTEOS (int); no
        // sobreescribirlos aquí con los arreglos o se rompe el contrato JSON
        // (la app espera número en el listado, arreglo en el detalle).
        $cab['detalle_recibidos'] = $recibidos->fetchAll(PDO::FETCH_ASSOC);

        $instalados = $this->conn->prepare(
            "SELECT m.id AS movimiento_id, m.evento, m.creado_en, m.tienda_id, t.nombre AS tienda_nombre, t.cr_tienda AS cr_tienda,
                    m.activo_id, m.activo_relacionado_id, m.usuario_id, u.nombre AS usuario_nombre,
                    a.serie AS serie_entra, a.codigo_barras AS codigo_entra,
                    mo.nombre AS modelo_nombre, mar.nombre AS marca_nombre, di.nombre AS dispositivo_nombre,
                    sale.serie AS serie_sale, sale.codigo_barras AS codigo_sale, sale.num_activo AS num_activo_sale
             FROM movimiento m
             JOIN activo a ON a.id = m.activo_id
             LEFT JOIN modelo mo ON mo.id = a.modelo_id
             LEFT JOIN marca mar ON mar.id = mo.marca_id
             LEFT JOIN dispositivo di ON di.id = mo.dispositivo_id
             LEFT JOIN tienda t ON t.id = m.tienda_id
             LEFT JOIN usuario u ON u.id = m.usuario_id
             LEFT JOIN activo sale ON sale.id = m.activo_relacionado_id
             WHERE m.proyecto_rentec_id = :id AND " . self::COND_INSTALADO . "
             ORDER BY m.creado_en DESC"
        );
        $instalados->bindParam(':id', $id, PDO::PARAM_INT);
        $instalados->execute();
        $cab['detalle_instalados'] = $instalados->fetchAll(PDO::FETCH_ASSOC);

        return $cab;
    }

    /** ¿Este proyecto toca alguna de las plazas dadas? (ya se movió algo ahí). */
    public function tocaPlazas(int $id, array $plazaIds): bool
    {
        if (!$plazaIds) return false;
        $ph = [];
        $params = [':id' => $id];
        foreach (array_values($plazaIds) as $i => $pid) {
            $key = ":p{$i}";
            $ph[] = $key;
            $params[$key] = (int) $pid;
        }
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM movimiento WHERE proyecto_rentec_id = :id AND plaza_id IN (" . implode(',', $ph) . ") LIMIT 1"
        );
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function cerrar(int $id): bool
    {
        $stmt = $this->conn->prepare("UPDATE proyecto_rentec SET estado = 'cerrado', cerrado_en = NOW() WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Cuánta huella dejó el proyecto. Si hay algo, no se puede borrar: las dos
     * claves foráneas que lo apuntan (activo.proyecto_rentec_id y
     * movimiento.proyecto_rentec_id) son ON DELETE RESTRICT, y con razón —
     * borrar el folio dejaría equipo y bitácora sin el proyecto que los explica.
     *
     * @return array{activos:int, movimientos:int}
     */
    public function actividad(int $id): array
    {
        $st = $this->conn->prepare(
            "SELECT (SELECT COUNT(*) FROM activo     WHERE proyecto_rentec_id = :a) AS activos,
                    (SELECT COUNT(*) FROM movimiento WHERE proyecto_rentec_id = :b) AS movimientos"
        );
        $st->execute([':a' => $id, ':b' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'activos'     => (int) ($r['activos'] ?? 0),
            'movimientos' => (int) ($r['movimientos'] ?? 0),
        ];
    }

    /** Borra el folio. Sólo tiene sentido si actividad() salió en cero. */
    public function eliminar(int $id): bool
    {
        return $this->conn->prepare("DELETE FROM proyecto_rentec WHERE id = :id")->execute([':id' => $id]);
    }
}
