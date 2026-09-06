<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Movimiento — bitácora de cambios de un activo (altas, cambios de estatus/stock,
 * reemplazos, bajas y eliminaciones). Alimenta la pestaña "Historial" y la línea
 * de tiempo del detalle del activo.
 */
class Movimiento
{
    private PDO $conn;
    private string $table = 'movimiento';

    /** Etiquetas legibles por tipo de evento. */
    public const EVENTOS = [
        'alta'            => 'Alta',
        'cambio_status'   => 'Cambio de estatus',
        'cambio_stock'    => 'Cambio de stock',
        'reemplazo_entra' => 'Entra por reemplazo',
        'reemplazo_sale'  => 'Sale por reemplazo',
        'edicion'         => 'Edición',
        'baja'            => 'Baja',
        'eliminacion'     => 'Eliminación',
    ];

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Inserta una fila de bitácora. Devuelve el id insertado (0 si falla).
     * Claves aceptadas: activo_id, evento (obligatorio), status_anterior,
     * status_nuevo, stock_anterior_id, stock_nuevo_id, tienda_id, plaza_id,
     * activo_relacionado_id, grupo_id, usuario_id, nota, datos_json.
     */
    public function registrar(array $m): int
    {
        $campos = [
            'activo_id', 'evento', 'status_anterior', 'status_nuevo',
            'stock_anterior_id', 'stock_nuevo_id', 'tienda_id', 'plaza_id',
            'activo_relacionado_id', 'grupo_id', 'solicitud_traslado_id',
            'usuario_id', 'nota', 'datos_json',
        ];

        $cols = [];
        $ph   = [];
        $par  = [];
        foreach ($campos as $c) {
            if (!array_key_exists($c, $m)) {
                continue;
            }
            $cols[]      = "`{$c}`";
            $ph[]        = ":{$c}";
            $val         = $m[$c];
            if (is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
            $par[":{$c}"] = ($val === '' ? null : $val);
        }

        if (!in_array('`evento`', $cols, true)) {
            return 0;
        }

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $cols) . ")
                VALUES (" . implode(', ', $ph) . ")";
        try {
            $this->conn->prepare($sql)->execute($par);
            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log('Movimiento::registrar ' . $e->getMessage());
            return 0;
        }
    }

    /** Genera un identificador de grupo para unir los dos lados de un reemplazo. */
    public static function nuevoGrupoId(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** SELECT + JOINs comunes para porActivo() y listar(). */
    private function selectBase(): string
    {
        return "
        SELECT m.*,
               a.serie              AS activo_serie,
               a.codigo_barras      AS activo_codigo_barras,
               a.num_activo         AS activo_num_activo,
               mo.nombre            AS modelo_nombre,
               ma.nombre            AS marca_nombre,
               d.nombre             AS dispositivo_nombre,
               t.nombre             AS tienda_nombre,
               uact.nombre          AS actor_nombre,
               arel.serie           AS relacionado_serie,
               arel.codigo_barras   AS relacionado_codigo_barras,
               arel.num_activo      AS relacionado_num_activo,
               morel.nombre         AS relacionado_modelo,
               marel.nombre         AS relacionado_marca,
               drel.nombre          AS relacionado_dispositivo,
               so.tipo              AS stock_ant_tipo,
               COALESCE(uso.nombre, bo.nombre, tso.nombre) AS stock_ant_nombre,
               sn.tipo              AS stock_new_tipo,
               COALESCE(usn.nombre, bn.nombre, tsn.nombre) AS stock_new_nombre
        FROM {$this->table} m
        LEFT JOIN activo      a    ON a.id  = m.activo_id
        LEFT JOIN modelo      mo   ON mo.id = a.modelo_id
        LEFT JOIN marca       ma   ON ma.id = mo.marca_id
        LEFT JOIN dispositivo d    ON d.id  = mo.dispositivo_id
        LEFT JOIN tienda      t    ON t.id  = m.tienda_id
        LEFT JOIN usuario     uact ON uact.id = m.usuario_id
        LEFT JOIN activo      arel  ON arel.id  = m.activo_relacionado_id
        LEFT JOIN modelo      morel ON morel.id = arel.modelo_id
        LEFT JOIN marca       marel ON marel.id = morel.marca_id
        LEFT JOIN dispositivo drel  ON drel.id  = morel.dispositivo_id
        LEFT JOIN stock       so   ON so.id = m.stock_anterior_id
        LEFT JOIN usuario     uso  ON uso.id = so.usuario_id
        LEFT JOIN bodega      bo   ON bo.id  = so.bodega_id
        LEFT JOIN tienda      tso  ON tso.id = so.tienda_id
        LEFT JOIN stock       sn   ON sn.id = m.stock_nuevo_id
        LEFT JOIN usuario     usn  ON usn.id = sn.usuario_id
        LEFT JOIN bodega      bn   ON bn.id  = sn.bodega_id
        LEFT JOIN tienda      tsn  ON tsn.id = sn.tienda_id
    ";
    }

    /** Timeline completa de un activo (más reciente primero). */
    public function porActivo(int $activoId): array
    {
        $sql = $this->selectBase() . " WHERE m.activo_id = :id ORDER BY m.creado_en DESC, m.id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $activoId, PDO::PARAM_INT);
        $stmt->execute();
        return $this->enriquecer($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Añade a cada movimiento los campos ya resueltos del equipo y del equipo
     * relacionado (reemplazo), preferindo el snapshot congelado en datos_json y
     * cayendo a las columnas vivas del JOIN cuando el snapshot es viejo/parcial.
     *
     *   eq_dispositivo, eq_marca, eq_modelo, eq_serie, eq_codigo_barras, eq_num_activo, eq_status
     *   rel_dispositivo, rel_marca, rel_modelo, rel_serie, rel_codigo_barras, rel_num_activo  (o null)
     *   datos           snapshot decodificado (array) para quien lo quiera crudo
     */
    private function enriquecer(array $rows): array
    {
        foreach ($rows as &$m) {
            $s = [];
            if (!empty($m['datos_json'])) {
                $dec = json_decode($m['datos_json'], true);
                if (is_array($dec)) $s = $dec;
            }
            $m['datos'] = $s;

            $m['eq_dispositivo']   = $s['dispositivo']   ?? $m['dispositivo_nombre']      ?? null;
            $m['eq_marca']         = $s['marca']         ?? $m['marca_nombre']            ?? null;
            $m['eq_modelo']        = $s['modelo']        ?? $m['modelo_nombre']           ?? null;
            $m['eq_serie']         = $s['serie']         ?? $m['activo_serie']            ?? null;
            $m['eq_codigo_barras'] = $s['codigo_barras'] ?? $m['activo_codigo_barras']   ?? null;
            $m['eq_num_activo']    = $s['num_activo']    ?? $m['activo_num_activo']       ?? null;
            $m['eq_status']        = $s['status']        ?? $m['status_nuevo']            ?? null;

            $r = (isset($s['relacionado']) && is_array($s['relacionado'])) ? $s['relacionado'] : null;
            $tieneRel = $r !== null
                || !empty($m['relacionado_serie']) || !empty($m['relacionado_codigo_barras'])
                || !empty($m['relacionado_num_activo']) || !empty($m['activo_relacionado_id']);

            if ($tieneRel) {
                $m['rel_dispositivo']   = $r['dispositivo']   ?? $m['relacionado_dispositivo']    ?? null;
                $m['rel_marca']         = $r['marca']         ?? $m['relacionado_marca']          ?? null;
                $m['rel_modelo']        = $r['modelo']        ?? $m['relacionado_modelo']         ?? null;
                $m['rel_serie']         = $r['serie']         ?? $m['relacionado_serie']          ?? null;
                $m['rel_codigo_barras'] = $r['codigo_barras'] ?? $m['relacionado_codigo_barras']  ?? null;
                $m['rel_num_activo']    = $r['num_activo']    ?? $m['relacionado_num_activo']     ?? null;
            } else {
                $m['rel_dispositivo'] = $m['rel_marca'] = $m['rel_modelo'] = null;
                $m['rel_serie'] = $m['rel_codigo_barras'] = $m['rel_num_activo'] = null;
            }
        }
        unset($m);
        return $rows;
    }

    /**
     * Listado paginado con filtros y scope de rol.
     * Filtros: activo_id, serie, evento, tienda_id, usuario_id (actor),
     * plaza_id (int|int[]), desde, hasta (YYYY-MM-DD), fs_scope (usuario_id).
     */
    public function listar(array $filtros = [], int $pagina = 1, int $porPagina = 30): array
    {
        $where  = ' WHERE 1=1';
        $params = [];

        if (!empty($filtros['activo_id'])) {
            $where .= ' AND m.activo_id = :activo_id';
            $params[':activo_id'] = (int) $filtros['activo_id'];
        }
        if (!empty($filtros['serie'])) {
            // Un placeholder por columna: con prepares nativos (EMULATE_PREPARES=false)
            // no se puede reutilizar el mismo :nombre varias veces.
            $like = '%' . $filtros['serie'] . '%';
            $where .= ' AND (a.serie LIKE :serie_a OR a.codigo_barras LIKE :serie_b OR a.num_activo LIKE :serie_c'
                    . ' OR arel.serie LIKE :serie_d OR arel.codigo_barras LIKE :serie_e OR arel.num_activo LIKE :serie_f)';
            $params[':serie_a'] = $like;
            $params[':serie_b'] = $like;
            $params[':serie_c'] = $like;
            $params[':serie_d'] = $like;
            $params[':serie_e'] = $like;
            $params[':serie_f'] = $like;
        }
        if (!empty($filtros['evento']) && isset(self::EVENTOS[$filtros['evento']])) {
            $where .= ' AND m.evento = :evento';
            $params[':evento'] = $filtros['evento'];
        }
        if (!empty($filtros['tienda_id'])) {
            $where .= ' AND m.tienda_id = :tienda_id';
            $params[':tienda_id'] = (int) $filtros['tienda_id'];
        }
        if (!empty($filtros['usuario_id'])) {
            $where .= ' AND m.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int) $filtros['usuario_id'];
        }
        if (!empty($filtros['desde'])) {
            $where .= ' AND m.creado_en >= :desde';
            $params[':desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where .= ' AND m.creado_en <= :hasta';
            $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
        }

        // Scope por plaza (coordinador / ati)
        if (isset($filtros['plaza_id']) && $filtros['plaza_id'] !== null && $filtros['plaza_id'] !== '') {
            $plazas = array_values(array_filter(array_map('intval', (array) $filtros['plaza_id'])));
            if ($plazas) {
                $in = [];
                foreach ($plazas as $i => $pid) {
                    $in[] = ":pl{$i}";
                    $params[":pl{$i}"] = $pid;
                }
                $where .= ' AND m.plaza_id IN (' . implode(',', $in) . ')';
            } else {
                $where .= ' AND 1=0';
            }
        }

        // Scope FS: su propio stock de usuario + todo lo de tiendas
        if (!empty($filtros['fs_scope'])) {
            $where .= ' AND (m.tienda_id IS NOT NULL
                             OR sn.usuario_id = :fs_scope OR so.usuario_id = :fs_scope)';
            $params[':fs_scope'] = (int) $filtros['fs_scope'];
        }

        $stmtC = $this->conn->prepare(
            "SELECT COUNT(*) FROM {$this->table} m LEFT JOIN activo a ON a.id = m.activo_id"
            . " LEFT JOIN activo arel ON arel.id = m.activo_relacionado_id"
            . " LEFT JOIN stock so ON so.id = m.stock_anterior_id"
            . " LEFT JOIN stock sn ON sn.id = m.stock_nuevo_id" . $where
        );
        $stmtC->execute($params);
        $total = (int) $stmtC->fetchColumn();

        $porPagina    = max(1, $porPagina);
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina       = max(1, min($pagina, $totalPaginas));
        $offset       = ($pagina - 1) * $porPagina;

        $sql = $this->selectBase() . $where . ' ORDER BY m.creado_en DESC, m.id DESC LIMIT :lim OFFSET :off';
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'movimientos' => $this->enriquecer($stmt->fetchAll(PDO::FETCH_ASSOC)),
            'paginacion'  => [
                'pagina_actual'    => $pagina,
                'total_paginas'    => $totalPaginas,
                'total_resultados' => $total,
                'por_pagina'       => $porPagina,
            ],
        ];
    }
}
