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

    /**
     * Tiendas de un conjunto de plazas, filtrado en SQL. $plazaIds vacío = todas.
     * obtenerCatalogos() usaba obtenerTodas() + array_filter en PHP, trayendo las
     * 1,011 tiendas en cada llamada para quedarse con las de una plaza.
     */
    public function obtenerPorPlazas(array $plazaIds): array
    {
        $plazaIds = array_values(array_filter(array_map('intval', $plazaIds)));
        if (!$plazaIds) return $this->obtenerTodas();

        $ph = implode(',', array_fill(0, count($plazaIds), '?'));
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
             WHERE t.plaza_id IN ({$ph})
             ORDER BY t.nombre"
        );
        $stmt->execute($plazaIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista de tiendas para el módulo "Tiendas": nombre, CR, ATI responsable y
     * el número de activos que hoy viven en el stock de la tienda.
     * $plazaIds vacío = todas (admin). $busqueda filtra por nombre o CR.
     */
    public function listarConConteo(array $plazaIds = [], ?string $busqueda = null): array
    {
        $where  = '1=1';
        $params = [];

        $plazaIds = array_values(array_filter(array_map('intval', $plazaIds)));
        if ($plazaIds) {
            $ph = [];
            foreach ($plazaIds as $i => $pid) { $ph[] = ":p{$i}"; $params[":p{$i}"] = $pid; }
            $where .= ' AND t.plaza_id IN (' . implode(',', $ph) . ')';
        }
        if ($busqueda !== null && trim($busqueda) !== '') {
            $where .= ' AND (t.nombre LIKE :b1 OR t.cr_tienda LIKE :b2)';
            $params[':b1'] = '%' . trim($busqueda) . '%';
            $params[':b2'] = '%' . trim($busqueda) . '%';
        }

        $sql = "SELECT t.id, t.cr_tienda, t.nombre, t.plaza_id, t.ati_usuario_id,
                       t.ultimo_inv_fecha, t.ultimo_inv_encontrados, t.ultimo_inv_no_encontrados,
                       p.nombre AS plaza_nombre,
                       r.nombre AS region_nombre,
                       n.nombre AS negocio_nombre,
                       ua.nombre AS ati_nombre,
                       (SELECT COUNT(*) FROM activo a
                          JOIN stock s ON s.id = a.stock_id
                         WHERE s.tipo = 'tienda' AND s.tienda_id = t.id) AS activos_count
                FROM {$this->table} t
                LEFT JOIN plaza   p ON t.plaza_id   = p.id
                LEFT JOIN region  r ON p.region_id  = r.id
                LEFT JOIN negocio n ON r.negocio_id = n.id
                LEFT JOIN usuario ua ON ua.id = t.ati_usuario_id
                WHERE {$where}
                ORDER BY t.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.cr_tienda, t.nombre, t.coordenadas, t.plaza_id, t.ati_usuario_id,
                    t.ultimo_inv_fecha, t.ultimo_inv_encontrados, t.ultimo_inv_no_encontrados,
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

    // ── CRUD (nuevo diseño: antes solo había lectura + asignar ATI) ───────

    /** ¿Ya existe otra tienda con ese CR? $exceptoId para validar en edición. */
    public function existeCr(string $cr, ?int $exceptoId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE cr_tienda = :cr";
        $params = [':cr' => $cr];
        if ($exceptoId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $exceptoId;
        }
        $stmt = $this->conn->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function crear(array $d): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (cr_tienda, nombre, plaza_id, ati_usuario_id, coordenadas)
             VALUES (:cr, :nombre, :plaza_id, :ati, :coords)"
        );
        $stmt->execute([
            ':cr'     => $d['cr_tienda'],
            ':nombre' => $d['nombre'],
            ':plaza_id' => $d['plaza_id'],
            ':ati'    => $d['ati_usuario_id'] ?: null,
            ':coords' => $d['coordenadas'] ?? null,
        ]);
        $id = (int) $this->conn->lastInsertId();
        // El trigger de la migración 001 crea el stock de la tienda al
        // insertar en `tienda`; nada más que hacer aquí.
        return $id;
    }

    public function actualizar(int $id, array $d): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
                SET cr_tienda = :cr, nombre = :nombre, plaza_id = :plaza_id,
                    ati_usuario_id = :ati, coordenadas = :coords
              WHERE id = :id"
        );
        return $stmt->execute([
            ':cr'     => $d['cr_tienda'],
            ':nombre' => $d['nombre'],
            ':plaza_id' => $d['plaza_id'],
            ':ati'    => $d['ati_usuario_id'] ?: null,
            ':coords' => $d['coordenadas'] ?? null,
            ':id'     => $id,
        ]);
    }

    /** Activos hoy instalados en la tienda (stock_tipo='tienda'). */
    public function contarActivosInstalados(int $id): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM activo a
             JOIN stock s ON s.id = a.stock_id
             WHERE s.tipo = 'tienda' AND s.tienda_id = :id"
        );
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Borra la tienda. El llamador debe haber verificado ya
     * contarActivosInstalados()==0 — aquí solo se ejecuta el DELETE
     * (las FKs de `stock`/`inventario_tienda` referencian esta tienda,
     * así que si quedara algo colgado la base lo rechazaría igual).
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /** Guarda el resumen del último inventario (checklist) cerrado. */
    public function guardarUltimoInventario(int $id, string $fecha, int $encontrados, int $noEncontrados): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
                SET ultimo_inv_fecha = :fecha, ultimo_inv_encontrados = :ok,
                    ultimo_inv_no_encontrados = :falta
              WHERE id = :id"
        );
        $stmt->execute([':fecha' => $fecha, ':ok' => $encontrados, ':falta' => $noEncontrados, ':id' => $id]);
    }
}