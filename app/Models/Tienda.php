<?php

namespace App\Models;

use PDO;

class Tienda
{
    private PDO $conn;
    private string $table = 'tienda';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function obtenerTodas(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.cr_tienda, t.nombre, t.coordenadas, t.plaza_id,
                    p.nombre AS plaza_nombre,
                    r.nombre AS region_nombre,
                    n.nombre AS negocio_nombre
             FROM {$this->table} t
             LEFT JOIN plaza   p ON t.plaza_id   = p.id
             LEFT JOIN region  r ON p.region_id  = r.id
             LEFT JOIN negocio n ON r.negocio_id = n.id
             ORDER BY t.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorPlaza(int $plazaId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, cr_tienda, nombre, coordenadas, plaza_id
             FROM {$this->table}
             WHERE plaza_id = :plaza_id
             ORDER BY nombre"
        );
        $stmt->bindParam(':plaza_id', $plazaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.cr_tienda, t.nombre, t.coordenadas, t.plaza_id,
                    p.nombre AS plaza_nombre,
                    r.nombre AS region_nombre,
                    n.nombre AS negocio_nombre
             FROM {$this->table} t
             LEFT JOIN plaza   p ON t.plaza_id   = p.id
             LEFT JOIN region  r ON p.region_id  = r.id
             LEFT JOIN negocio n ON r.negocio_id = n.id
             WHERE t.id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}