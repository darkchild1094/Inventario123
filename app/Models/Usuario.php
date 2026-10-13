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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $row['plaza_ids'] = $this->plazaIdsDe($id);
        return $row;
    }

    /**
     * TODAS las plazas asignadas al usuario (usuario_plaza), no sólo la
     * principal de `usuario.plaza_id`.
     *
     * Hace falta porque guardarPlazas() borra y reinserta: un formulario que
     * premarque sólo la plaza principal y guarde, le quita al usuario el resto
     * de sus plazas sin avisar. El coordinador de Valles trabaja OXXO y BARA;
     * editarlo con la lista incompleta lo dejaba sin una de las dos.
     *
     * @return int[]
     */
    public function plazaIdsDe(int $usuarioId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT plaza_id FROM usuario_plaza WHERE usuario_id = :id ORDER BY plaza_id"
        );
        $stmt->execute([':id' => $usuarioId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function obtenerTodos(): array
    {
        // plaza_ids viene con TODAS las plazas asignadas: el formulario de
        // edición las necesita para premarcarlas, porque guardarPlazas() borra
        // y reinserta y guardar con la lista incompleta se las quita.
        $stmt = $this->conn->prepare(
            "SELECT u.id, u.nombre, u.email, u.foto, u.plaza_id, u.tipo,
                    p.nombre AS plaza_nombre,
                    (SELECT GROUP_CONCAT(up.plaza_id ORDER BY up.plaza_id)
                       FROM usuario_plaza up WHERE up.usuario_id = u.id) AS plazas_csv
             FROM {$this->tabla} u
             LEFT JOIN plaza p ON u.plaza_id = p.id
             ORDER BY u.nombre"
        );
        $stmt->execute();
        return array_map(function (array $u): array {
            $csv = (string) ($u['plazas_csv'] ?? '');
            unset($u['plazas_csv']);
            $u['plaza_ids'] = $csv === '' ? [] : array_map('intval', explode(',', $csv));
            return $u;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Sólo los admins. Son asignables desde cualquier plaza porque no están
     * atados a una, y obtenerCatalogos() los añadía recorriendo obtenerTodos()
     * completo — una segunda lectura de toda la tabla en la misma petición.
     */
    public function obtenerAdmins(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id, u.nombre, u.email, u.foto, u.plaza_id, u.tipo,
                    p.nombre AS plaza_nombre
             FROM {$this->tabla} u
             LEFT JOIN plaza p ON u.plaza_id = p.id
             WHERE u.tipo = 'admin'
             ORDER BY u.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Coordinadores + admins de una plaza — a quién avisar cuando alguien
     * "Envía" activos a la bodega de esa plaza (una bodega no es una
     * persona; se notifica a quien la gestiona). Los admins van siempre
     * (ven todas las plazas), los coordinadores solo si la plaza es suya.
     */
    public function coordinadoresYAdminsDePlaza(int $plazaId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT u.id, u.nombre
             FROM {$this->tabla} u
             LEFT JOIN usuario_plaza up ON up.usuario_id = u.id
             WHERE u.tipo = 'admin'
                OR (u.tipo = 'coordinador' AND (u.plaza_id = :p1 OR up.plaza_id = :p2))
             ORDER BY u.nombre"
        );
        $stmt->execute([':p1' => $plazaId, ':p2' => $plazaId]);
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

    /**
     * "Stock usuarios": TODOS los usuarios de la plaza (admin, coordinador,
     * pfs, ati) con los activos de su stock personal. Solo lectura.
     *
     * @return array<int,array> usuario + `activos` (tipo, modelo, serie, AF) + `activos_count`
     */
    public function stockDePlaza(int $plazaId): array
    {
        $usuarios = $this->obtenerPorPlaza($plazaId);
        if (!$usuarios) return [];

        $ids = array_map(fn($u) => (int) $u['id'], $usuarios);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $this->conn->prepare(
            "SELECT s.usuario_id, a.id, a.serie, a.num_activo,
                    d.nombre AS dispositivo_nombre, mo.nombre AS modelo_nombre
             FROM stock s
             JOIN activo a ON a.stock_id = s.id
             LEFT JOIN modelo mo ON a.modelo_id = mo.id
             LEFT JOIN dispositivo d ON mo.dispositivo_id = d.id
             WHERE s.tipo = 'usuario' AND s.usuario_id IN ({$ph})
             ORDER BY d.nombre, mo.nombre, a.id"
        );
        $st->execute($ids);
        $porUsuario = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $porUsuario[(int) $r['usuario_id']][] = [
                'id'         => (int) $r['id'],
                'tipo'       => $r['dispositivo_nombre'],
                'modelo'     => $r['modelo_nombre'],
                'serie'      => $r['serie'],
                'num_activo' => $r['num_activo'],
            ];
        }

        $orden = ['admin' => 0, 'coordinador' => 1, 'pfs' => 2, 'ati' => 3];
        foreach ($usuarios as &$u) {
            $u['activos']       = $porUsuario[(int) $u['id']] ?? [];
            $u['activos_count'] = count($u['activos']);
            $u['foto']          = null;
        }
        unset($u);
        usort($usuarios, fn($x, $y) =>
            ($orden[$x['tipo']] ?? 9) <=> ($orden[$y['tipo']] ?? 9) ?: strcmp($x['nombre'], $y['nombre']));
        return $usuarios;
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
                ':tipo'     => $datos['tipo']      ?? 'pfs',
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