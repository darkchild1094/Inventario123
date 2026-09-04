<?php

namespace App\Models;

use PDO;
use PDOException;

class Stock
{
    private $conn;
    private string $table = 'stock';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT s.*,
                    u.nombre AS usuario_nombre,
                    b.nombre AS bodega_nombre,
                    t.nombre AS tienda_nombre
             FROM {$this->table} s
             LEFT JOIN usuario u ON s.usuario_id = u.id
             LEFT JOIN bodega  b ON s.bodega_id  = b.id
             LEFT JOIN tienda  t ON s.tienda_id  = t.id
             WHERE s.id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el stock PERSONAL de un usuario, SCOPEADO a una plaza específica.
     * 
     * IMPORTANTE: Un usuario puede tener MÚLTIPLES stocks personales,
     * uno por cada plaza/negocio en que trabaja:
     * - Usuario 1 con Plaza Valles (OXXO) → stock_id = X, plaza_id = 1
     * - Usuario 1 con Plaza Valles (BARA) → stock_id = Y, plaza_id = 5
     * 
     * Esto permite que activos asignados a un usuario se guarden en su
     * stock personal de CADA negocio/plaza, no centralizados en OXXO.
     *
     * Si $plazaId se omite (0), usa usuario.plaza_id (comportamiento legado
     * para no romper llamadas antiguas). En código nuevo SIEMPRE pasar
     * la plaza/negocio seleccionado en el formulario.
     * 
     * Si no existe stock para esa plaza, lo crea automáticamente.
     */
    public function obtenerPorUsuario(int $usuarioId, int $plazaId = 0): array|false
    {
        if ($plazaId <= 0) {
            $stmtUsr = $this->conn->prepare('SELECT plaza_id FROM usuario WHERE id = :id LIMIT 1');
            $stmtUsr->bindValue(':id', $usuarioId, PDO::PARAM_INT);
            $stmtUsr->execute();
            $plazaId = (int) ($stmtUsr->fetchColumn() ?: 0);
        }

        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE tipo = 'usuario' AND usuario_id = :uid AND plaza_id = :pid LIMIT 1"
        );
        $stmt->bindValue(':uid', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':pid', $plazaId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->crearParaUsuario($usuarioId, $plazaId);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $row;
    }

    /**
     * Obtiene el stock de una bodega. Si no existe, lo crea automáticamente.
     */
    public function obtenerPorBodega(int $bodegaId): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE tipo = 'bodega' AND bodega_id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $bodegaId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->crearParaBodega($bodegaId);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $row;
    }

    /**
     * Obtiene el stock de una tienda. Si no existe, lo crea automáticamente.
     *
     * Normalmente NO hará falta crearlo: cada tienda nace con su stock por
     * el trigger `trg_crear_stock_tienda`, y las tiendas previas quedaron
     * pobladas por la migración 001. Esto es solo una red de seguridad.
     */
    public function obtenerPorTienda(int $tiendaId): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE tipo = 'tienda' AND tienda_id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $tiendaId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->crearParaTienda($tiendaId);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $row;
    }

    public function crearParaUsuario(int $usuarioId, int $plazaId = 0): bool
    {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table} (tipo, usuario_id, bodega_id, plaza_id)
                 VALUES ('usuario', :usuario_id, NULL, :plaza_id)"
            );
            return $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':plaza_id'   => $plazaId > 0 ? $plazaId : null,
            ]);
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function crearParaBodega(int $bodegaId): bool
    {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table} (tipo, usuario_id, bodega_id)
                 VALUES ('bodega', NULL, :bodega_id)"
            );
            return $stmt->execute([':bodega_id' => $bodegaId]);
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function crearParaTienda(int $tiendaId): bool
    {
        try {
            // plaza_id se copia de la tienda para mantener la misma
            // convención que el trigger y la migración de backfill.
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table} (tipo, usuario_id, bodega_id, tienda_id, plaza_id)
                 SELECT 'tienda', NULL, NULL, t.id, t.plaza_id
                 FROM tienda t WHERE t.id = :tienda_id"
            );
            return $stmt->execute([':tienda_id' => $tiendaId]);
        } catch (PDOException $e) {
            throw $e;
        }
    }
}