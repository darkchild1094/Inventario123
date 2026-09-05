<?php

namespace App\Controllers;

use App\Models\Modelo;
use App\Models\Marca;
use App\Models\Dispositivo;
use App\Helpers\Permisos;

/**
 * ModeloController — CRUD del catálogo de modelos. Solo admin.
 * Un modelo = nombre + marca (opcional, se puede crear al vuelo) + categoría de
 * dispositivo. Al borrar un modelo con activos hay que reasignarlos a otro.
 */
class ModeloController
{
    private $db;
    private AuthController $auth;

    public function __construct($db)
    {
        $this->db   = $db;
        $this->auth = new AuthController($db);
        $this->auth->requerirAutenticacion();
    }

    public function index(): void
    {
        $this->soloAdmin();

        $modelos = (new Modelo($this->db))->obtenerTodosDetallado();

        $fDisp  = (int) ($_GET['dispositivo_id'] ?? 0);
        $fMarca = (int) ($_GET['marca_id'] ?? 0);
        $busq   = mb_strtolower(trim((string) ($_GET['busqueda'] ?? '')));
        if ($fDisp > 0)  $modelos = array_values(array_filter($modelos, fn($m) => (int) $m['dispositivo_id'] === $fDisp));
        if ($fMarca > 0) $modelos = array_values(array_filter($modelos, fn($m) => (int) $m['marca_id'] === $fMarca));
        if ($busq !== '') {
            $modelos = array_values(array_filter($modelos, fn($m) =>
                str_contains(mb_strtolower(($m['nombre'] ?? '') . ' ' . ($m['marca_nombre'] ?? '') . ' ' . ($m['dispositivo_nombre'] ?? '')), $busq)));
        }

        $dispositivos = (new Dispositivo($this->db))->leerTodos();
        $marcas       = (new Marca($this->db))->obtenerTodos();
        $todosModelos = (new Modelo($this->db))->obtenerTodos(); // para el select "reasignar a"

        require ROOT_PATH . '/app/views/modelos/index.php';
    }

    public function crear(): void
    {
        $this->soloAdmin();
        $dispositivos = (new Dispositivo($this->db))->leerTodos();
        $marcas       = (new Marca($this->db))->obtenerTodos();
        require ROOT_PATH . '/app/views/modelos/crear.php';
    }

    public function guardar(): void
    {
        $this->soloAdmin();
        $this->requerirPost();

        [$nombre, $dispId, $marcaId, $err] = $this->parsePost();
        if ($err) $this->error($err, 'index.php?controller=modelo&action=crear');

        $modeloModel = new Modelo($this->db);
        if ($modeloModel->existe($nombre, $dispId, $marcaId)) {
            $this->error('Ya existe un modelo con esa marca y nombre en esa categoría.', 'index.php?controller=modelo&action=crear');
        }

        $modeloModel->crear(['nombre' => $nombre, 'dispositivo_id' => $dispId, 'marca_id' => $marcaId]);
        $_SESSION['success'] = "Modelo '{$nombre}' creado.";
        $this->redirigir('index.php?controller=modelo&action=index');
    }

    public function editar(): void
    {
        $this->soloAdmin();
        $id     = $this->idGet();
        $modelo = (new Modelo($this->db))->obtenerPorId($id);
        if (!$modelo) $this->error('Modelo no encontrado.', 'index.php?controller=modelo&action=index');

        $dispositivos = (new Dispositivo($this->db))->leerTodos();
        $marcas       = (new Marca($this->db))->obtenerTodos();
        require ROOT_PATH . '/app/views/modelos/editar.php';
    }

    public function actualizar(): void
    {
        $this->soloAdmin();
        $this->requerirPost();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->error('Modelo inválido.', 'index.php?controller=modelo&action=index');

        [$nombre, $dispId, $marcaId, $err] = $this->parsePost();
        if ($err) $this->error($err, "index.php?controller=modelo&action=editar&id={$id}");

        $modeloModel = new Modelo($this->db);
        if ($modeloModel->existe($nombre, $dispId, $marcaId, $id)) {
            $this->error('Ya existe otro modelo con esa marca y nombre en esa categoría.', "index.php?controller=modelo&action=editar&id={$id}");
        }

        $modeloModel->actualizar(['id' => $id, 'nombre' => $nombre, 'dispositivo_id' => $dispId, 'marca_id' => $marcaId]);
        $_SESSION['success'] = "Modelo '{$nombre}' actualizado.";
        $this->redirigir('index.php?controller=modelo&action=index');
    }

    public function eliminar(): void
    {
        $this->soloAdmin();
        $this->requerirPost();

        $id         = (int) ($_POST['id'] ?? 0);
        $reasignarA = (int) ($_POST['reasignar_a'] ?? 0) ?: null;
        if ($id <= 0) $this->error('Modelo inválido.', 'index.php?controller=modelo&action=index');

        $modeloModel = new Modelo($this->db);
        $modelo      = $modeloModel->obtenerPorId($id);
        if (!$modelo) $this->error('Modelo no encontrado.', 'index.php?controller=modelo&action=index');

        $enUso = $modeloModel->contarActivos($id);
        if ($enUso > 0 && !$reasignarA) {
            $this->error("No se puede eliminar: {$enUso} activos usan este modelo. Elige un modelo destino para reasignarlos.", 'index.php?controller=modelo&action=index');
        }
        if ($reasignarA === $id) {
            $this->error('El modelo destino debe ser distinto.', 'index.php?controller=modelo&action=index');
        }

        try {
            $this->db->beginTransaction();
            if ($enUso > 0) {
                if (!$modeloModel->obtenerPorId($reasignarA)) {
                    throw new \RuntimeException('El modelo destino no existe.');
                }
                $movidos = $modeloModel->reasignarActivos($id, $reasignarA);
            }
            $modeloModel->eliminar($id);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->error('No se pudo eliminar el modelo: ' . $e->getMessage(), 'index.php?controller=modelo&action=index');
        }

        $_SESSION['success'] = $enUso > 0
            ? "Modelo '{$modelo['nombre']}' eliminado; {$movidos} activos reasignados."
            : "Modelo '{$modelo['nombre']}' eliminado.";
        $this->redirigir('index.php?controller=modelo&action=index');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** @return array{0:string,1:int,2:?int,3:?string} nombre, dispositivo_id, marca_id, error */
    private function parsePost(): array
    {
        $nombre = trim($_POST['nombre'] ?? '');
        $dispId = (int) ($_POST['dispositivo_id'] ?? 0);
        $marcaNueva = trim($_POST['marca_nueva'] ?? '');
        $marcaId    = (int) ($_POST['marca_id'] ?? 0) ?: null;

        if ($nombre === '')  return ['', 0, null, 'El nombre del modelo es obligatorio.'];
        if ($dispId <= 0)    return ['', 0, null, 'Selecciona una categoría de dispositivo.'];

        if ($marcaNueva !== '') {
            $marcaId = (new Marca($this->db))->obtenerOCrear($marcaNueva);
        }
        return [$nombre, $dispId, $marcaId, null];
    }

    private function soloAdmin(): void
    {
        if (!Permisos::puedeGestionarModelos()) {
            $_SESSION['error'] = 'Acceso restringido a administradores.';
            $this->redirigir('index.php');
        }
    }

    private function requerirPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir('index.php?controller=modelo&action=index');
        }
    }

    private function idGet(): int
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) $this->redirigir('index.php?controller=modelo&action=index');
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
