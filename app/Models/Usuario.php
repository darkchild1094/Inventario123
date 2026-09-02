<?php

namespace App\Models;

use PDO;
use PDOException;

class Usuario
{
    private $conn;
    private string $tabla = 'usuario';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ── Consultas ─────────────────────────────────────────────────────────────

    public function buscarPorEmail(string $email): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->tabla}
             WHERE email = :email
             LIMIT 1"
        );
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id, u.nombre, u.email, u.foto, u.plaza_id, u.tipo,
                    p.nombre AS plaza_nombre
             FROM {$this->tabla} u
             LEFT JOIN plaza p ON u.plaza_id = p.id
             WHERE u.id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerTodos(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id, u.nombre, u.email, u.foto, u.plaza_id, u.tipo,
                    p.nombre AS plaza_nombre
             FROM {$this->tabla} u
             LEFT JOIN plaza p ON u.plaza_id = p.id
             ORDER BY u.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorPlaza(int $plazaId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT u.id, u.nombre, u.email, u.foto, u.plaza_id, u.tipo,
                    p.nombre AS plaza_nombre
             FROM {$this->tabla} u
             LEFT JOIN plaza p ON u.plaza_id = p.id
             LEFT JOIN usuario_plaza up ON up.usuario_id = u.id
             WHERE u.plaza_id = :plaza_id1
                OR up.plaza_id = :plaza_id2
             ORDER BY u.nombre"
        );
        $stmt->bindParam(':plaza_id1', $plazaId, PDO::PARAM_INT);
        $stmt->bindParam(':plaza_id2', $plazaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPlazas(int $usuarioId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.id, p.cr_plaza, p.nombre, p.region_id
             FROM plaza p
             JOIN usuario_plaza up ON up.plaza_id = p.id
             WHERE up.usuario_id = :usuario_id
             ORDER BY p.nombre"
        );
        $stmt->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarPlazas(int $usuarioId, array $plazaIds): bool
    {
        $this->conn->beginTransaction();
        try {
            $stmtDelete = $this->conn->prepare(
                "DELETE FROM usuario_plaza WHERE usuario_id = :usuario_id"
            );
            $stmtDelete->execute([':usuario_id' => $usuarioId]);

            $stmtInsert = $this->conn->prepare(
                "INSERT INTO usuario_plaza (usuario_id, plaza_id)
                 VALUES (:usuario_id, :plaza_id)"
            );

            foreach (array_unique($plazaIds) as $plazaId) {
                $plazaId = (int) $plazaId;
                if ($plazaId <= 0) {
                    continue;
                }
                $stmtInsert->execute([
                    ':usuario_id' => $usuarioId,
                    ':plaza_id'   => $plazaId,
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function perteneceAPlaza(int $usuarioId, int $plazaId): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT 1
             FROM {$this->tabla} u
             LEFT JOIN usuario_plaza up ON up.usuario_id = u.id
             WHERE u.id = :usuario_id
               AND (u.plaza_id = :plaza_id1 OR up.plaza_id = :plaza_id2)
             LIMIT 1"
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':plaza_id1'  => $plazaId,
            ':plaza_id2'  => $plazaId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function existeEmail(string $email, ?int $exceptoId = null): bool
    {
        $sql    = "SELECT id FROM {$this->tabla} WHERE email = :email";
        $params = [':email' => $email];

        if ($exceptoId) {
            $sql .= ' AND id != :id';
            $params[':id'] = $exceptoId;
        }

        $stmt = $this->conn->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function contarPorTipo(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT tipo, COUNT(*) AS total
             FROM {$this->tabla}
             GROUP BY tipo"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    public function crear(array $datos): bool
    {
        $sql = "INSERT INTO {$this->tabla}
                    (nombre, email, password, foto, plaza_id, tipo)
                VALUES
                    (:nombre, :email, :password, :foto, :plaza_id, :tipo)";

        try {
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':nombre'   => $datos['nombre'],
                ':email'    => $datos['email'],
                ':password' => password_hash($datos['password'], PASSWORD_BCRYPT),
                ':foto'     => $datos['foto']     ?? null,
                ':plaza_id' => $datos['plaza_id'] ?? null,
                ':tipo'     => $datos['tipo']      ?? 'fs',
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') return false;
            throw $e;
        }
    }

    public function actualizar(array $datos): bool
    {
        $campos = [
            'nombre   = :nombre',
            'email    = :email',
            'plaza_id = :plaza_id',
            'tipo     = :tipo',
        ];

        $params = [
            ':id'       => $datos['id'],
            ':nombre'   => $datos['nombre'],
            ':email'    => $datos['email'],
            ':plaza_id' => $datos['plaza_id'] ?? null,
            ':tipo'     => $datos['tipo'],
        ];

        if (!empty($datos['password'])) {
            $campos[]            = 'password = :password';
            $params[':password'] = password_hash($datos['password'], PASSWORD_BCRYPT);
        }
        if (!empty($datos['foto'])) {
            $campos[]        = 'foto = :foto';
            $params[':foto'] = $datos['foto'];
        }

        $sql = "UPDATE {$this->tabla}
                SET " . implode(', ', $campos) . "
                WHERE id = :id";

        try {
            return $this->conn->prepare($sql)->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') return false;
            throw $e;
        }
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM {$this->tabla} WHERE id = :id"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}