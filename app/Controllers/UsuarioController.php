<?php

namespace App\Controllers;

use App\Models\Usuario;
use App\Models\Plaza;
use App\Helpers\ImageHelper;

/**
 * UsuarioController — femsa_assets
 *
 * CRUD de usuarios. Tipos: admin, fs, coordinador, ati
 * Solo admin puede crear, editar y eliminar usuarios.
 */
class UsuarioController
{
    private $db;
    private AuthController $auth;

    public function __construct($db)
    {
        $this->db   = $db;
        $this->auth = new AuthController($db);
        $this->auth->requerirAutenticacion();
    }

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->soloAdmin();
        $usuarios = (new Usuario($this->db))->obtenerTodos();
        require_once ROOT_PATH . '/app/views/usuarios/index.php';
    }

    // ── Perfil ────────────────────────────────────────────────────────────────

    public function perfil(): void
    {
        $idSesion = $this->idSesion();
        $id       = (int) ($_GET['id'] ?? $idSesion);

        if ($id !== $idSesion && !$this->auth->tieneTipo('admin')) {
            $this->error('No tienes permisos para ver este perfil.', 'index.php');
        }

        $usuario = (new Usuario($this->db))->obtenerPorId($id);

        if (!$usuario) {
            $this->error('Usuario no encontrado.', 'index.php');
        }

        require_once ROOT_PATH . '/app/views/usuarios/perfil.php';
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function crear(): void
    {
        $this->soloAdmin();
        $plazas = (new Plaza($this->db))->obtenerTodas();
        require_once ROOT_PATH . '/app/views/usuarios/crear.php';
    }

    public function guardar(): void
    {
        $this->soloAdmin();
        $this->requerirPost();

        $usuarioModel = new Usuario($this->db);
        $email        = trim($_POST['email'] ?? '');

        if ($usuarioModel->existeEmail($email)) {
            $this->error(
                "El email '{$email}' ya está registrado.",
                'index.php?controller=usuario&action=crear'
            );
        }

        $foto = $this->subirFoto();

        $plazaIds = $_POST['plaza_id'] ?? [];
        if (!is_array($plazaIds)) {
            $plazaIds = [$plazaIds];
        }
        $plazaIds = array_values(array_filter(array_map('intval', $plazaIds), fn($id) => $id > 0));
        $mainPlaza = $plazaIds[0] ?? null;

        $datos = [
            'nombre'   => trim($_POST['nombre']),
            'email'    => $email,
            'password' => $_POST['password'],
            'tipo'     => $_POST['tipo'] ?? 'fs',
            'plaza_id' => $mainPlaza,
            'foto'     => $foto,
        ];

        if ($mainPlaza === null) {
            $this->error('Debe seleccionar al menos una plaza.', 'index.php?controller=usuario&action=crear');
        }

        if ($usuarioModel->crear($datos)) {
            $nuevoId = (int) $this->db->lastInsertId();
            $usuarioModel->guardarPlazas($nuevoId, $plazaIds);
            $_SESSION['success'] = "Usuario '{$datos['nombre']}' creado exitosamente.";
            $this->redirigir('index.php?controller=usuario&action=index');
        } else {
            if ($foto) {
                ImageHelper::borrarArchivo($this->rutaFotos(), $foto);
            }
            $this->error('Error al crear el usuario.', 'index.php?controller=usuario&action=crear');
        }
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function editar(): void
    {
        $this->soloAdmin();

        $id      = $this->idGet();
        $usuario = (new Usuario($this->db))->obtenerPorId($id);

        if (!$usuario) {
            $this->error('Usuario no encontrado.', 'index.php?controller=usuario&action=index');
        }

        $plazas       = (new Plaza($this->db))->obtenerTodas();
        $usuarioPlazas = (new Usuario($this->db))->obtenerPlazas($id);
        require_once ROOT_PATH . '/app/views/usuarios/editar.php';
    }

    public function actualizar(): void
    {
        $this->soloAdmin();
        $this->requerirPost();

        $id           = (int) ($_POST['id'] ?? 0);
        $email        = trim($_POST['email'] ?? '');
        $usuarioModel = new Usuario($this->db);

        if ($usuarioModel->existeEmail($email, $id)) {
            $this->error(
                "El email '{$email}' ya está en uso.",
                "index.php?controller=usuario&action=editar&id={$id}"
            );
        }

        $usuarioActual = $usuarioModel->obtenerPorId($id);
        $fotoVieja     = $usuarioActual['foto'] ?? null;
        $foto          = $this->subirFoto($id, $fotoVieja);

        $plazaIds = $_POST['plaza_id'] ?? [];
        if (!is_array($plazaIds)) {
            $plazaIds = [$plazaIds];
        }
        $plazaIds = array_values(array_filter(array_map('intval', $plazaIds), fn($id) => $id > 0));
        $mainPlaza = $plazaIds[0] ?? null;

        $datos = [
            'id'       => $id,
            'nombre'   => trim($_POST['nombre']),
            'email'    => $email,
            'tipo'     => $_POST['tipo'] ?? 'fs',
            'plaza_id' => $mainPlaza,
        ];

        if (!empty($_POST['password'])) {
            $datos['password'] = $_POST['password'];
        }
        if ($foto) {
            $datos['foto'] = $foto;
        }

        if ($mainPlaza === null) {
            $this->error('Debe seleccionar al menos una plaza.', "index.php?controller=usuario&action=editar&id={$id}");
        }

        if ($usuarioModel->actualizar($datos)) {
            $usuarioModel->guardarPlazas($id, $plazaIds);
            $_SESSION['success'] = "Usuario '{$datos['nombre']}' actualizado exitosamente.";
            $this->redirigir('index.php?controller=usuario&action=index');
        } else {
            if ($foto) {
                ImageHelper::borrarArchivo($this->rutaFotos(), $foto);
            }
            $this->error(
                'Error al actualizar el usuario.',
                "index.php?controller=usuario&action=editar&id={$id}"
            );
        }
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function eliminar(): void
    {
        $this->soloAdmin();

        $id       = (int) ($_POST['id'] ?? 0);
        $idSesion = $this->idSesion();

        if ($id <= 0 || $id === $idSesion) {
            $this->error('No se puede eliminar este usuario.', 'index.php?controller=usuario&action=index');
        }

        $usuarioModel = new Usuario($this->db);
        $usuario      = $usuarioModel->obtenerPorId($id);

        if ($usuario && $usuarioModel->eliminar($id)) {
            if (!empty($usuario['foto'])) {
                ImageHelper::borrarArchivo($this->rutaFotos(), $usuario['foto']);
            }
            $_SESSION['success'] = "Usuario '{$usuario['nombre']}' eliminado.";
        } else {
            $_SESSION['error'] = 'Error al eliminar el usuario.';
        }

        $this->redirigir('index.php?controller=usuario&action=index');
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function subirFoto(?int $usuarioId = null, ?string $fotoVieja = null): ?string
    {
        if (empty($_FILES['foto']['name'])) return null;
        return ImageHelper::subirFotoUsuario($this->rutaFotos(), $usuarioId, $fotoVieja);
    }

    private function rutaFotos(): string
    {
        $ruta = ROOT_PATH . '/public/uploads/usuarios';
        if (!is_dir($ruta)) mkdir($ruta, 0775, true);
        return $ruta;
    }

    private function soloAdmin(): void
    {
        if (!$this->auth->tieneTipo('admin')) {
            $_SESSION['error'] = 'No tienes permisos para esta acción.';
            $this->redirigir('index.php');
        }
    }

    private function requerirPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir('index.php?controller=usuario&action=index');
        }
    }

    private function idSesion(): int
    {
        return (int) ($_SESSION['usuario']['id'] ?? $_SESSION['usuario_id'] ?? 0);
    }

    private function idGet(): int
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) $this->redirigir('index.php?controller=usuario&action=index');
        return $id;
    }

    private function error(string $msg, string $url): never
    {
        $_SESSION['error'] = $msg;
        $this->redirigir($url);
    }

    private function redirigir(string $url): never
    {
        header("Location: {$url}");
        exit;
    }
}