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
            "SELECT t.id, t.cr_tienda, t.nombre, t.coordenadas, t.plaza_id, t.ati_usuario_id,
                    p.nombre AS plaza_nombre,
                    r.nombre AS region_nombre,
                    n.nombre AS negocio_nombre,
                    ua.nombre AS ati_nombre
             FROM {$this->table} t
             LEFT JOIN plaza   p ON t.plaza_id   = p.id
             LEFT JOIN region  r ON p.region_id  = r.id
             LEFT JOIN negocio n ON r.negocio_id = n.id
             LEFT JOIN usuario ua ON ua.id = t.ati_usuario_id
             ORDER BY t.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorPlaza(int $plazaId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.cr_tienda, t.nombre, t.coordenadas, t.plaza_id, t.ati_usuario_id,
                    ua.nombre AS ati_nombre
             FROM {$this->table} t
             LEFT JOIN usuario ua ON ua.id = t.ati_usuario_id
             WHERE t.plaza_id = :plaza_id
             ORDER BY t.nombre"
        );
        $stmt->bindParam(':plaza_id', $plazaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.cr_tienda, t.nombre, t.coordenadas, t.plaza_id, t.ati_usuario_id,
                    p.nombre AS plaza_nombre,
                    r.nombre AS region_nombre,
                    n.nombre AS negocio_nombre,
                    ua.nombre AS ati_nombre
             FROM {$this->table} t
             LEFT JOIN plaza   p ON t.plaza_id   = p.id
             LEFT JOIN region  r ON p.region_id  = r.id
             LEFT JOIN negocio n ON r.negocio_id = n.id
             LEFT JOIN usuario ua ON ua.id = t.ati_usuario_id
             WHERE t.id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Asigna (o quita, con null) el ATI responsable de garantía/baja de la tienda.
     */
    public function asignarAti(int $tiendaId, ?int $usuarioId): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET ati_usuario_id = :ati WHERE id = :id"
        );
        return $stmt->execute([
            ':ati' => $usuarioId ?: null,
            ':id'  => $tiendaId,
        ]);
    }

    /** Primer usuario tipo 'ati' de una plaza (fallback cuando la tienda no tiene ATI). */
    public function primerAtiDePlaza(int $plazaId): int
    {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT u.id
             FROM usuario u
             LEFT JOIN usuario_plaza up ON up.usuario_id = u.id
             WHERE u.tipo = 'ati' AND (u.plaza_id = :p1 OR up.plaza_id = :p2)
             ORDER BY u.id LIMIT 1"
        );
        $stmt->execute([':p1' => $plazaId, ':p2' => $plazaId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** Todos los usuarios tipo 'ati' de una plaza (para selects de responsable). */
    public function atisDePlaza(int $plazaId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT u.id, u.nombre, u.email
             FROM usuario u
             LEFT JOIN usuario_plaza up ON up.usuario_id = u.id
             WHERE u.tipo = 'ati' AND (u.plaza_id = :p1 OR up.plaza_id = :p2)
             ORDER BY u.nombre"
        );
        $stmt->execute([':p1' => $plazaId, ':p2' => $plazaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}