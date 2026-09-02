<?php

namespace App\Models;

use PDO;

class Modelo
{
    private $conn;
    private string $table = 'modelo';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function obtenerTodos(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT m.id, m.nombre, m.dispositivo_id, d.nombre AS dispositivo_nombre
             FROM {$this->table} m
             LEFT JOIN dispositivo d ON m.dispositivo_id = d.id
             ORDER BY m.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function porDispositivo(int $dispositivoId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, nombre, dispositivo_id
             FROM {$this->table}
             WHERE dispositivo_id = :dispositivo_id
             ORDER BY nombre"
        );
        $stmt->bindParam(':dispositivo_id', $dispositivoId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function porArea(int $areaId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT m.id, m.nombre, m.dispositivo_id, d.nombre AS dispositivo_nombre
             FROM {$this->table} m
             JOIN area_modelo am ON am.modelo_id = m.id
             JOIN dispositivo  d  ON d.id = m.dispositivo_id
             WHERE am.area_id = :area_id
             ORDER BY d.nombre, m.nombre"
        );
        $stmt->bindParam(':area_id', $areaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT m.id, m.nombre, m.dispositivo_id, d.nombre AS dispositivo_nombre
             FROM {$this->table} m
             LEFT JOIN dispositivo d ON m.dispositivo_id = d.id
             WHERE m.id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}