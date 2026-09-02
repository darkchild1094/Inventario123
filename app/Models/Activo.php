<?php

namespace App\Models;

use PDO;
use PDOException;

class Activo
{
    private PDO    $conn;
    private string $table = 'activo';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public static function normalizarStatus(string $status): string
    {
        $status = trim(strtolower($status));

        return match ($status) {
            'en bodega', 'en_bodega' => 'en_bodega',
            'en uso', 'en_uso', 'uso' => 'en_uso',
            'baja' => 'baja',
            'garantia' => 'garantia',
            'asignado' => 'asignado',
            default => 'en_bodega',
        };
    }

    private static function limpiarPlaca(?string $placa): ?string
    {
        $placa = trim((string) $placa);
        return $placa === '' ? null : $placa;
    }

    public function obtenerTodosFiltrado(array $filtros = [], int $pagina = 1, int $porPagina = 20): array
    {
        $negocio_id       = $filtros['negocio_id']       ?? null;
        $region_id        = $filtros['region_id']        ?? null;
        $plaza_id         = $filtros['plaza_id']         ?? null;
        $usuario_id       = $filtros['usuario_id']       ?? null;
        $dispositivo_id   = $filtros['dispositivo_id']   ?? null;
        $stock_id         = $filtros['stock_id']         ?? null;
        $stock_usuario_id = $filtros['stock_usuario_id'] ?? null;
        $status           = $filtros['status']           ?? null;
        $busqueda         = $filtros['busqueda']         ?? null;
        $solo_bodega      = $filtros['solo_bodega']      ?? false;

        $sqlBase = "FROM {$this->table} a
                    LEFT JOIN modelo      mo  ON a.modelo_id             = mo.id
                    LEFT JOIN dispositivo d   ON mo.dispositivo_id       = d.id
                    LEFT JOIN area_modelo am  ON am.modelo_id            = mo.id
                    LEFT JOIN area        ar  ON am.area_id              = ar.id
                    LEFT JOIN stock       s   ON a.stock_id              = s.id
                    LEFT JOIN usuario     u   ON s.usuario_id            = u.id
                    LEFT JOIN bodega      b   ON s.bodega_id             = b.id
                    LEFT JOIN bodega_acceso_plaza bap ON bap.bodega_id   = b.id
                    LEFT JOIN plaza       p   ON COALESCE(s.plaza_id, bap.plaza_id, u.plaza_id) = p.id
                    LEFT JOIN region      r   ON p.region_id             = r.id
                    LEFT JOIN negocio     n   ON r.negocio_id            = n.id
                    LEFT JOIN tienda      tu  ON a.tienda_uso_id         = tu.id
                    LEFT JOIN tienda      tp  ON a.procedencia_tienda_id = tp.id
                    WHERE 1=1";

        $params = [];

        if ($negocio_id) {
            $sqlBase .= ' AND n.id = :negocio_id';
            $params[':negocio_id'] = $negocio_id;
        }
        if ($region_id) {
            $sqlBase .= ' AND r.id = :region_id';
            $params[':region_id'] = $region_id;
        }
        if ($plaza_id) {
            if (is_array($plaza_id)) {
                $plaza_id = array_values(array_filter(array_map('intval', $plaza_id)));
                if ($plaza_id) {
                    $placeholders = [];
                    foreach ($plaza_id as $i => $pid) {
                        $ph = ":plaza_id_{$i}";
                        $placeholders[]   = $ph;
                        $params[$ph]      = $pid;
                    }
                    $sqlBase .= ' AND p.id IN (' . implode(',', $placeholders) . ')';
                }
            } else {
                $sqlBase .= ' AND p.id = :plaza_id';
                $params[':plaza_id'] = $plaza_id;
            }
        }
        if ($dispositivo_id) {
            $sqlBase .= ' AND d.id = :dispositivo_id';
            $params[':dispositivo_id'] = $dispositivo_id;
        }
        if ($usuario_id) {
            $sqlBase .= ' AND u.id = :usuario_id';
            $params[':usuario_id'] = $usuario_id;
        }
        if ($stock_id) {
            $sqlBase .= ' AND a.stock_id = :stock_id';
            $params[':stock_id'] = $stock_id;
        }
        if ($stock_usuario_id) {
            $sqlBase .= " AND s.tipo = 'usuario' AND s.usuario_id = :stock_usuario_id";
            $params[':stock_usuario_id'] = $stock_usuario_id;
        }
        if ($status) {
            $sqlBase .= ' AND a.status = :status';
            $params[':status'] = $status;
        }
        if ($solo_bodega) {
            $sqlBase .= " AND s.tipo = 'bodega'";
        }
        if ($busqueda) {
            $sqlBase .= ' AND (a.serie LIKE :busqueda OR a.placa LIKE :busqueda
                               OR mo.nombre LIKE :busqueda OR d.nombre LIKE :busqueda
                               OR n.nombre LIKE :busqueda OR r.nombre LIKE :busqueda
                               OR p.nombre LIKE :busqueda OR u.nombre LIKE :busqueda
                               OR b.nombre LIKE :busqueda)';
            $params[':busqueda'] = "%{$busqueda}%";
        }

        // Total
        $stmtCount = $this->conn->prepare("SELECT COUNT(a.id) {$sqlBase}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $porPagina    = max(1, $porPagina);
        $totalPaginas = (int) ceil($total / $porPagina);
        $pagina       = max(1, min($pagina, max(1, $totalPaginas)));
        $offset       = ($pagina - 1) * $porPagina;

        $sql = "SELECT
                    a.id, a.serie, a.placa, a.modelo_id, a.status,
                    a.procedencia_tienda_id, a.tienda_uso_id, a.stock_id,
                    a.fecha_alta, a.fecha_modificacion,
                    mo.nombre  AS modelo_nombre,
                    d.id       AS dispositivo_id,
                    d.nombre   AS dispositivo_nombre,
                    ar.nombre  AS area_nombre,
                    s.tipo     AS stock_tipo,
                    u.id       AS usuario_stock_id,
                    u.nombre   AS usuario_nombre,
                    b.id       AS bodega_stock_id,
                    b.nombre   AS bodega_nombre,
                    p.id       AS plaza_id,
                    p.nombre   AS plaza_nombre,
                    r.nombre   AS region_nombre,
                    n.nombre   AS negocio_nombre,
                    tu.nombre  AS tienda_uso_nombre,
                    tp.nombre  AS procedencia_nombre
                {$sqlBase}
                ORDER BY a.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        unset($val);

        $stmt->bindParam(':limit',  $porPagina, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        return [
            'activos'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'paginacion' => [
                'pagina_actual'    => $pagina,
                'total_paginas'    => $totalPaginas,
                'total_resultados' => $total,
                'por_pagina'       => $porPagina,
            ],
        ];
    }

    public function obtenerPorId(int $id): array|false
    {
        $sql = "SELECT
                    a.*,
                    mo.nombre  AS modelo_nombre,
                    d.id       AS dispositivo_id,
                    d.nombre   AS dispositivo_nombre,
                    ar.nombre  AS area_nombre,
                    s.tipo     AS stock_tipo,
                    u.id       AS usuario_stock_id,
                    u.nombre   AS usuario_nombre,
                    b.id       AS bodega_stock_id,
                    b.nombre   AS bodega_nombre,
                    p.id       AS plaza_id,
                    p.nombre   AS plaza_nombre,
                    r.nombre   AS region_nombre,
                    n.nombre   AS negocio_nombre,
                    tu.nombre  AS tienda_uso_nombre,
                    tp.nombre  AS procedencia_nombre
                FROM {$this->table} a
                LEFT JOIN modelo      mo  ON a.modelo_id             = mo.id
                LEFT JOIN dispositivo d   ON mo.dispositivo_id       = d.id
                LEFT JOIN area_modelo am  ON am.modelo_id            = mo.id
                LEFT JOIN area        ar  ON am.area_id              = ar.id
                LEFT JOIN stock       s   ON a.stock_id              = s.id
                LEFT JOIN usuario     u   ON s.usuario_id            = u.id
                LEFT JOIN bodega      b   ON s.bodega_id             = b.id
                LEFT JOIN bodega_acceso_plaza bap ON bap.bodega_id   = b.id
                LEFT JOIN plaza       p   ON COALESCE(s.plaza_id, bap.plaza_id, u.plaza_id) = p.id
                LEFT JOIN region      r   ON p.region_id             = r.id
                LEFT JOIN negocio     n   ON r.negocio_id            = n.id
                LEFT JOIN tienda      tu  ON a.tienda_uso_id         = tu.id
                LEFT JOIN tienda      tp  ON a.procedencia_tienda_id = tp.id
                WHERE a.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function ultimoId(): int
    {
        return (int) $this->conn->lastInsertId();
    }

        public function crear(array $datos): bool
    {
        // Campos base obligatorios/por defecto
        $campos = [
            'serie', 'placa', 'modelo_id', 'status',
            'procedencia_tienda_id', 'tienda_uso_id', 'stock_id'
        ];

        $placeholders = [
            ':serie', ':placa', ':modelo_id', ':status',
            ':procedencia_tienda_id', ':tienda_uso_id', ':stock_id'
        ];

        $params = [
            ':serie'                 => trim($datos['serie'] ?? ''),
            ':placa'                 => self::limpiarPlaca($datos['placa'] ?? null),
            ':modelo_id'             => !empty($datos['modelo_id'])             ? (int) $datos['modelo_id']             : null,
            ':status'                => self::normalizarStatus($datos['status'] ?? 'en_bodega'),
            ':procedencia_tienda_id' => !empty($datos['procedencia_tienda_id']) ? (int) $datos['procedencia_tienda_id'] : null,
            ':tienda_uso_id'         => !empty($datos['tienda_uso_id'])         ? (int) $datos['tienda_uso_id']         : null,
            ':stock_id'              => !empty($datos['stock_id'])              ? (int) $datos['stock_id']              : null,
        ];

        // Agregar campos de foto solo si vienen en $datos
        foreach (['foto_equipo', 'foto_serie', 'foto_activo'] as $foto) {
            if (isset($datos[$foto])) {
                $campos[] = $foto;
                $placeholders[] = ':' . $foto;
                $params[':' . $foto] = $datos[$foto];
            }
        }

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $placeholders) . ")";

        try {
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') return false;
            throw $e;
        }
    }

        public function actualizar(array $datos): bool
    {
        $campos = [
            'serie                 = :serie',
            'placa                 = :placa',
            'modelo_id             = :modelo_id',
            'status                = :status',
            'procedencia_tienda_id = :procedencia_tienda_id',
            'tienda_uso_id         = :tienda_uso_id',
            'stock_id              = :stock_id'
        ];

        $params = [
            ':id'                    => (int) $datos['id'],
            ':serie'                 => trim($datos['serie'] ?? ''),
            ':placa'                 => self::limpiarPlaca($datos['placa'] ?? null),
            ':modelo_id'             => !empty($datos['modelo_id'])             ? (int) $datos['modelo_id']             : null,
            ':status'                => self::normalizarStatus($datos['status'] ?? 'en_bodega'),
            ':procedencia_tienda_id' => !empty($datos['procedencia_tienda_id']) ? (int) $datos['procedencia_tienda_id'] : null,
            ':tienda_uso_id'         => !empty($datos['tienda_uso_id'])         ? (int) $datos['tienda_uso_id']         : null,
            ':stock_id'              => !empty($datos['stock_id'])              ? (int) $datos['stock_id']              : null,
        ];

        foreach (['foto_equipo', 'foto_serie', 'foto_activo'] as $foto) {
            if (isset($datos[$foto]) && $datos[$foto] !== null) {
                $campos[] = "{$foto} = :{$foto}";
                $params[":{$foto}"] = $datos[$foto];
            }
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $campos) . " WHERE id = :id";

        try {
            return $this->conn->prepare($sql)->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') return false;
            throw $e;
        }
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}