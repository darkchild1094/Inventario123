<?php

namespace App\Models;

use PDO;

class Dispositivo
{
    private $conn;
    private string $table = 'dispositivo';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function leerTodos(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT d.id, d.nombre
             FROM {$this->table} d
             ORDER BY d.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT d.id, d.nombre
             FROM {$this->table} d
             WHERE d.id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}