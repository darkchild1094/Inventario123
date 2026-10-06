<?php

namespace App\Models;

use PDO;

/**
 * AppBuild — historial de APKs subidas desde el panel (ver migración 033).
 */
class AppBuild
{
    private PDO $conn;
    private string $table = 'app_build';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function crear(array $d): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table}
                (version_code, version_name, archivo, tamano_bytes, notas, subido_por)
             VALUES (:vc, :vn, :archivo, :tam, :notas, :subido_por)"
        );
        $stmt->execute([
            ':vc'          => $d['version_code'],
            ':vn'          => $d['version_name'],
            ':archivo'     => $d['archivo'],
            ':tam'         => $d['tamano_bytes'],
            ':notas'       => $d['notas'] ?: null,
            ':subido_por'  => $d['subido_por'],
        ]);
        return (int) $this->conn->lastInsertId();
    }

    /** Más reciente primero. */
    public function listar(): array
    {
        $stmt = $this->conn->query(
            "SELECT ab.*, u.nombre AS subido_por_nombre
             FROM {$this->table} ab
             JOIN usuario u ON u.id = ab.subido_por
             ORDER BY ab.id DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** La última subida (la que apunta el enlace de descarga estable). */
    public function ultima(): array|false
    {
        $stmt = $this->conn->query(
            "SELECT ab.*, u.nombre AS subido_por_nombre
             FROM {$this->table} ab
             JOIN usuario u ON u.id = ab.subido_por
             ORDER BY ab.id DESC LIMIT 1"
        );
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function eliminar(int $id): void
    {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
