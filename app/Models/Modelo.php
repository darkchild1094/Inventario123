<?php

namespace App\Models;

use PDO;

class Modelo
{
    private PDO $conn;
    private string $table = 'modelo';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /** Lista plana para selects (dispositivo + marca). */
    public function obtenerTodos(): array
    {
        $stmt = $this->conn->query(
            "SELECT m.id, m.nombre, m.dispositivo_id, m.marca_id,
                    d.nombre AS dispositivo_nombre, ma.nombre AS marca_nombre
             FROM {$this->table} m
             LEFT JOIN dispositivo d ON d.id = m.dispositivo_id
             LEFT JOIN marca       ma ON ma.id = m.marca_id
             ORDER BY d.nombre, ma.nombre, m.nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Igual que obtenerTodos pero con el conteo de activos por modelo (para el CRUD). */
    public function obtenerTodosDetallado(): array
    {
        $stmt = $this->conn->query(
            "SELECT m.id, m.nombre, m.dispositivo_id, m.marca_id,
                    d.nombre AS dispositivo_nombre, ma.nombre AS marca_nombre,
                    (SELECT COUNT(*) FROM activo a WHERE a.modelo_id = m.id) AS activos_count
             FROM {$this->table} m
             LEFT JOIN dispositivo d ON d.id = m.dispositivo_id
             LEFT JOIN marca       ma ON ma.id = m.marca_id
             ORDER BY d.nombre, ma.nombre, m.nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function porDispositivo(int $dispositivoId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT m.id, m.nombre, m.dispositivo_id, m.marca_id, ma.nombre AS marca_nombre
             FROM {$this->table} m
             LEFT JOIN marca ma ON ma.id = m.marca_id
             WHERE m.dispositivo_id = :d
             ORDER BY ma.nombre, m.nombre"
        );
        $stmt->execute([':d' => $dispositivoId]);
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
        $stmt->execute([':area_id' => $areaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT m.id, m.nombre, m.dispositivo_id, m.marca_id,
                    d.nombre AS dispositivo_nombre, ma.nombre AS marca_nombre
             FROM {$this->table} m
             LEFT JOIN dispositivo d ON d.id = m.dispositivo_id
             LEFT JOIN marca       ma ON ma.id = m.marca_id
             WHERE m.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── CRUD ─────────────────────────────────────────────────────────────

    /** ¿Ya existe un modelo con el mismo dispositivo + marca + nombre? (evita duplicados) */
    public function existe(string $nombre, int $dispositivoId, ?int $marcaId, ?int $exceptoId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE nombre = :n AND dispositivo_id = :d
                  AND " . ($marcaId === null ? "marca_id IS NULL" : "marca_id = :m");
        $params = [':n' => trim($nombre), ':d' => $dispositivoId];
        if ($marcaId !== null) $params[':m'] = $marcaId;
        if ($exceptoId)       { $sql .= " AND id <> :x"; $params[':x'] = $exceptoId; }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $d): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (nombre, dispositivo_id, marca_id)
             VALUES (:n, :d, :m)"
        );
        $stmt->execute([
            ':n' => trim($d['nombre']),
            ':d' => (int) $d['dispositivo_id'],
            ':m' => !empty($d['marca_id']) ? (int) $d['marca_id'] : null,
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function actualizar(array $d): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET nombre = :n, dispositivo_id = :d, marca_id = :m
             WHERE id = :id"
        );
        return $stmt->execute([
            ':n'  => trim($d['nombre']),
            ':d'  => (int) $d['dispositivo_id'],
            ':m'  => !empty($d['marca_id']) ? (int) $d['marca_id'] : null,
            ':id' => (int) $d['id'],
        ]);
    }

    public function eliminar(int $id): bool
    {
        // area_modelo referencia modelo sin ON DELETE CASCADE: se limpia primero.
        $this->conn->prepare("DELETE FROM area_modelo WHERE modelo_id = :id")->execute([':id' => $id]);
        return $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id")->execute([':id' => $id]);
    }

    public function contarActivos(int $modeloId): int
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM activo WHERE modelo_id = :id");
        $stmt->execute([':id' => $modeloId]);
        return (int) $stmt->fetchColumn();
    }

    /** Mueve todos los activos de un modelo a otro. Devuelve cuántos movió. */
    public function reasignarActivos(int $de, int $a): int
    {
        $stmt = $this->conn->prepare("UPDATE activo SET modelo_id = :a WHERE modelo_id = :de");
        $stmt->execute([':a' => $a, ':de' => $de]);
        return $stmt->rowCount();
    }
}
