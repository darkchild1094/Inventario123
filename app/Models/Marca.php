<?php

namespace App\Models;

use PDO;

class Marca
{
    private PDO $conn;
    private string $table = 'marca';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function obtenerTodos(): array
    {
        $stmt = $this->conn->query("SELECT id, nombre FROM {$this->table} ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare("SELECT id, nombre FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve el id de la marca con ese nombre; si no existe la crea.
     * `marca.nombre` es UNIQUE, así que el INSERT ... WHERE NOT EXISTS es seguro.
     */
    public function obtenerOCrear(string $nombre): int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre de la marca no puede estar vacío.');
        }

        $stmt = $this->conn->prepare("SELECT id FROM {$this->table} WHERE nombre = :n LIMIT 1");
        $stmt->execute([':n' => $nombre]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $ins = $this->conn->prepare("INSERT INTO {$this->table} (nombre) VALUES (:n)");
        $ins->execute([':n' => $nombre]);
        return (int) $this->conn->lastInsertId();
    }
}
