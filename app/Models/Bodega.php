<?php

namespace App\Models;

use PDO;
use PDOException;

class Bodega
{
    private $conn;
    private string $table = 'bodega';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Todas las bodegas, con las plazas que tienen acceso a cada una
     * concatenadas en plazas_nombres (ej. "Ciudad Valles, León").
     */
    public function obtenerTodas(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT b.id, b.nombre, b.usuario_id,
                    u.nombre AS encargado_nombre,
                    GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR ', ') AS plazas_nombres,
                    GROUP_CONCAT(p.id    ORDER BY p.nombre SEPARATOR ',')  AS plazas_ids
             FROM {$this->table} b
             LEFT JOIN usuario u ON b.usuario_id = u.id
             LEFT JOIN bodega_acceso_plaza bap ON bap.bodega_id = b.id
             LEFT JOIN plaza p ON p.id = bap.plaza_id
             GROUP BY b.id, b.nombre, b.usuario_id, u.nombre
             ORDER BY b.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT b.id, b.nombre, b.usuario_id,
                    u.nombre AS encargado_nombre,
                    GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR ', ') AS plazas_nombres,
                    GROUP_CONCAT(p.id    ORDER BY p.nombre SEPARATOR ',')  AS plazas_ids
             FROM {$this->table} b
             LEFT JOIN usuario u ON b.usuario_id = u.id
             LEFT JOIN bodega_acceso_plaza bap ON bap.bodega_id = b.id
             LEFT JOIN plaza p ON p.id = bap.plaza_id
             WHERE b.id = :id
             GROUP BY b.id, b.nombre, b.usuario_id, u.nombre
             LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Bodega que da servicio a una plaza dada (vía bodega_acceso_plaza).
     * Si varias plazas comparten bodega (ej. Valles y León), cada una
     * de esas plazas devuelve la misma bodega física.
     */
    public function obtenerPorPlaza(int $plazaId): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT b.id, b.nombre, b.usuario_id,
                    u.nombre AS encargado_nombre
             FROM bodega_acceso_plaza bap
             JOIN {$this->table} b ON b.id = bap.bodega_id
             LEFT JOIN usuario u ON b.usuario_id = u.id
             WHERE bap.plaza_id = :plaza_id LIMIT 1"
        );
        $stmt->bindParam(':plaza_id', $plazaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerPorPlazaYNegocio(int $plazaId, string $negocioNombre): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT b.id, b.nombre, b.usuario_id,
                    u.nombre AS encargado_nombre
             FROM bodega_acceso_plaza bap
             JOIN {$this->table} b ON b.id = bap.bodega_id
             LEFT JOIN usuario u ON b.usuario_id = u.id
             JOIN plaza p ON p.id = bap.plaza_id
             JOIN region r ON r.id = p.region_id
             JOIN negocio n ON n.id = r.negocio_id
             WHERE bap.plaza_id = :plaza_id
               AND LOWER(n.nombre) = LOWER(:negocio_nombre)
             LIMIT 1"
        );
        $stmt->bindParam(':plaza_id', $plazaId, PDO::PARAM_INT);
        $stmt->bindParam(':negocio_nombre', $negocioNombre, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Todas las plazas que tienen acceso a una bodega dada.
     */
    public function obtenerPlazasDeBodega(int $bodegaId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.id, p.cr_plaza, p.nombre
             FROM bodega_acceso_plaza bap
             JOIN plaza p ON p.id = bap.plaza_id
             WHERE bap.bodega_id = :bodega_id
             ORDER BY p.nombre"
        );
        $stmt->bindParam(':bodega_id', $bodegaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea una bodega nueva y la asocia a una plaza (caso normal:
     * una bodega nace ligada a una sola plaza). Para compartirla con
     * otra plaza después, usar agregarAccesoPlaza().
     */
    public function crear(array $datos): bool
    {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table} (nombre, usuario_id)
                 VALUES (:nombre, :usuario_id)"
            );
            $stmt->execute([
                ':nombre'     => $datos['nombre'],
                ':usuario_id' => $datos['usuario_id'],
            ]);

            $bodegaId = (int) $this->conn->lastInsertId();

            if (!empty($datos['plaza_id'])) {
                $this->agregarAccesoPlaza($bodegaId, (int) $datos['plaza_id']);
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function actualizar(array $datos): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET nombre = :nombre, usuario_id = :usuario_id
             WHERE id = :id"
        );
        return $stmt->execute([
            ':id'         => $datos['id'],
            ':nombre'     => $datos['nombre'],
            ':usuario_id' => $datos['usuario_id'],
        ]);
    }

    /**
     * Da acceso a una plaza adicional sobre una bodega ya existente
     * (ej. dar de alta Plaza León usando la Bodega Valles).
     * Es idempotente: si ya existe el acceso, no lo duplica.
     */
    public function agregarAccesoPlaza(int $bodegaId, int $plazaId): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO bodega_acceso_plaza (bodega_id, plaza_id)
             SELECT :bodega_id, :plaza_id
             WHERE NOT EXISTS (
                 SELECT 1 FROM bodega_acceso_plaza
                 WHERE bodega_id = :bodega_id2 AND plaza_id = :plaza_id2
             )"
        );
        return $stmt->execute([
            ':bodega_id'  => $bodegaId,
            ':plaza_id'   => $plazaId,
            ':bodega_id2' => $bodegaId,
            ':plaza_id2'  => $plazaId,
        ]);
    }

    /**
     * Quita el acceso de una plaza a una bodega (no borra la bodega
     * ni afecta el acceso de otras plazas a esa misma bodega).
     */
    public function quitarAccesoPlaza(int $bodegaId, int $plazaId): bool
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM bodega_acceso_plaza
             WHERE bodega_id = :bodega_id AND plaza_id = :plaza_id"
        );
        return $stmt->execute([
            ':bodega_id' => $bodegaId,
            ':plaza_id'  => $plazaId,
        ]);
    }
}