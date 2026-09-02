<?php

namespace App\Models;

use PDO;

class Region
{
    private $conn;
    private string $table = 'region';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function obtenerTodas(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT r.id, r.nombre, r.negocio_id, n.nombre AS negocio_nombre
             FROM {$this->table} r
             LEFT JOIN negocio n ON r.negocio_id = n.id
             ORDER BY r.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorNegocio(int $negocioId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, nombre, negocio_id
             FROM {$this->table}
             WHERE negocio_id = :negocio_id
             ORDER BY nombre"
        );
        $stmt->bindParam(':negocio_id', $negocioId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT r.id, r.nombre, r.negocio_id, n.nombre AS negocio_nombre
             FROM {$this->table} r
             LEFT JOIN negocio n ON r.negocio_id = n.id
             WHERE r.id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}