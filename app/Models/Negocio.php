<?php

namespace App\Models;

use PDO;

class Negocio
{
    private $conn;
    private string $table = 'negocio';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function obtenerTodos(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, nombre FROM {$this->table} ORDER BY nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT id, nombre FROM {$this->table} WHERE id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}