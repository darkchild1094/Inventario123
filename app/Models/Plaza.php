<?php

namespace App\Models;

use PDO;

class Plaza
{
    private $conn;
    private string $table = 'plaza';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function obtenerTodas(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.id, p.cr_plaza, p.nombre, p.region_id,
                    r.nombre AS region_nombre,
                    n.id     AS negocio_id,
                    n.nombre AS negocio_nombre
             FROM {$this->table} p
             LEFT JOIN region  r ON p.region_id  = r.id
             LEFT JOIN negocio n ON r.negocio_id = n.id
             ORDER BY p.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorRegion(int $regionId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, cr_plaza, nombre, region_id
             FROM {$this->table}
             WHERE region_id = :region_id
             ORDER BY nombre"
        );
        $stmt->bindParam(':region_id', $regionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorNegocio(int $negocioId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.id, p.cr_plaza, p.nombre, p.region_id,
                    r.nombre AS region_nombre
             FROM {$this->table} p
             LEFT JOIN region r ON p.region_id = r.id
             WHERE r.negocio_id = :negocio_id
             ORDER BY p.nombre"
        );
        $stmt->bindParam(':negocio_id', $negocioId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT p.id, p.cr_plaza, p.nombre, p.region_id,
                    r.nombre AS region_nombre,
                    n.id     AS negocio_id,
                    n.nombre AS negocio_nombre
             FROM {$this->table} p
             LEFT JOIN region  r ON p.region_id  = r.id
             LEFT JOIN negocio n ON r.negocio_id = n.id
             WHERE p.id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}