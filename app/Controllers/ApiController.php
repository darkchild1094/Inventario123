<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\Usuario;
use App\Models\Dispositivo;
use App\Models\Modelo;
use App\Models\Marca;
use App\Models\Tienda;
use App\Models\Plaza;
use App\Models\Region;
use App\Models\Negocio;
use App\Models\Bodega;
use App\Models\Stock;
use App\Models\Area;
use App\Models\Movimiento;
use App\Services\ActivoGuardado;
use App\Services\MovimientoService;
use App\Helpers\Permisos;

class ApiController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    // ── Perfil / capacidades del usuario logueado (para que la app sepa qué mostrar) ──

    public function obtenerPerfil(): void
    {
        $tipo = Permisos::tipo();
        $this->json([
            'usuario' => $_SESSION['usuario'] ?? null,
            'permisos' => [
                'tipo'                  => $tipo,
                'puedeVerTodasPlazas'   => Permisos::puedeVerTodasPlazas(),
                'puedeFiltrarPorPlaza'  => Permisos::puedeFiltrarPorPlaza(),
                'puedeCrearActivo'      => Permisos::puedeCrearActivo(),
                'puedeEditarActivo'     => Permisos::puedeEditarActivo(),
                'puedeGestionarUsuarios'=> Permisos::puedeGestionarUsuarios(),
                'puedeExportar'         => Permisos::puedeExportar(),
                'puedeVerBodega'        => Permisos::puedeVerBodega(),
                'puedeVerHistorial'     => Permisos::puedeVerHistorial(),
                'puedeGestionarTiendas' => Permisos::puedeGestionarTiendas(),
                'puedeGestionarModelos' => Permisos::puedeGestionarModelos(),
                'plazaId'               => Permisos::plazaId(),
                'plazasIds'             => Permisos::plazasIds(),
            ],
            // Pestañas visibles en la navbar, según rol (mismo criterio que components/navbar.php)
            'vistasDisponibles' => $this->vistasDisponiblesParaTipo($tipo),
        ]);
    }

    // ── Activos ───────────────────────────────────────────────────────────────

    public function listarActivos(): void
    {
        $tipo    = Permisos::tipo();
        $plazaId = Permisos::plazaId();
        $vista   = $_GET['vista'] ?? $this->vistaDefaultParaTipo($tipo);
        $vista   = $this->vistaPermitida($vista, $tipo);

        $scope   = Permisos::filtrosScope();
        $statusFiltro = $_GET['status'] ?? null;
        if ($statusFiltro !== null && $statusFiltro !== '') {
            $statusFiltro = Activo::normalizarStatus((string) $statusFiltro);
        } else {
            $statusFiltro = null;
        }

        $filtros = array_merge($scope, [
            'dispositivo_id' => $_GET['dispositivo_id'] ?? null,
            'status'         => $statusFiltro,
            'busqueda'       => $_GET['busqueda']       ?? null,
            'solo_bodega'    => false,
        ]);

        if (Permisos::puedeVerTodasPlazas()) {
            $filtros['negocio_id'] = $_GET['negocio_id'] ?? null;
            $filtros['region_id']  = $_GET['region_id']  ?? null;
            $filtros['plaza_id']   = $_GET['plaza_id']   ?? null;
            $filtros['tienda_id']  = $_GET['tienda_id']  ?? null;
            $filtros['usuario_id'] = $_GET['usuario_id'] ?? null;
        } elseif ($tipo === 'coordinador') {
            $misPlazas = Permisos::plazasIds() ?: [$plazaId];
            $plazaGet  = (int) ($_GET['plaza_id'] ?? 0);
            $filtros['plaza_id']   = ($plazaGet > 0 && in_array($plazaGet, $misPlazas, true))
                ? $plazaGet
                : $misPlazas;
            $filtros['negocio_id'] = $_GET['negocio_id'] ?? null;
            $filtros['region_id']  = $_GET['region_id']  ?? null;
            $filtros['tienda_id']  = $_GET['tienda_id']  ?? null;
            $filtros['usuario_id'] = $_GET['usuario_id'] ?? null;
        } elseif ($tipo === 'ati') {
            $filtros['plaza_id'] = $plazaId;
        }

        if ($vista === 'bodega') {
            if (!Permisos::puedeVerBodega()) {
                $this->json(['success' => false, 'message' => 'No tienes acceso a esta vista.'], 403);
            }
            $filtros['solo_bodega'] = true;
        } elseif ($vista === 'mi_stock') {
            $filtros['stock_usuario_id'] = Permisos::idUsuario();
            unset($filtros['plaza_id']);
        }
        // vista === 'todos': sin filtro adicional, ya viene acotado por $scope

        $pagina    = max(1, (int) ($_GET['pagina']     ?? 1));
        $porPagina = max(1, (int) ($_GET['por_pagina'] ?? 20));

        $resultado = (new Activo($this->db))->obtenerTodosFiltrado($filtros, $pagina, $porPagina);

        // Agregar flags de permiso por cada activo, para que la app sepa qué botones mostrar
        $resultado['activos'] = array_map(function ($a) {
            $a['puedeEditar']   = Permisos::puedeEditarActivoConcreto($a);
            $a['puedeEliminar'] = Permisos::puedeEliminarActivo($a);
            return $a;
        }, $resultado['activos'] ?? []);

        $resultado['vista'] = $vista;

        $this->json($resultado);
    }

    public function obtenerActivo(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(['success' => false, 'message' => 'ID inválido.'], 400);

        $activo = (new Activo($this->db))->obtenerPorId($id);
        if (!$activo) $this->json(['success' => false, 'message' => 'Activo no encontrado.'], 404);

        if (!Permisos::puedeVerActivoConcreto($activo)) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para ver este activo.'], 403);
        }

        $activo['puedeEditar']   = Permisos::puedeEditarActivoConcreto($activo);
        $activo['puedeEliminar'] = Permisos::puedeEliminarActivo($activo);
        $this->json($activo);
    }

    public function guardarActivo(): void
    {
        $this->requerirPost();
        if (!Permisos::puedeCrearActivo()) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para registrar activos.'], 403);
        }

        $datos   = $this->datosActivoPost();
        $plazaId = $this->resolverPlazaId((int) ($_POST['negocio_id'] ?? 0));
        if ($plazaId <= 0) {
            $this->json(['success' => false, 'message' => 'Debes indicar una plaza válida.'], 400);
        }

        $fotos = \App\Helpers\ImageHelper::procesarYSubirImagenes(ROOT_PATH . '/public/uploads', null, []);
        $datos = array_merge($datos, $fotos);

        $post = array_merge($_POST, ['plaza_id' => $plazaId]);
        $res  = (new ActivoGuardado($this->db))->crear($datos, $post, $this->actorSesion());

        if ($res['ok']) {
            $this->json(['success' => true, 'message' => 'Activo registrado correctamente.', 'id' => $res['id']]);
        } else {
            $this->json(['success' => false, 'message' => $res['error'] ?? 'No se pudo registrar el activo.'], 400);
        }
    }

    public function actualizarActivo(): void
    {
        $this->requerirPost();
        $id     = (int) ($_POST['id'] ?? 0);
        $antes  = (new Activo($this->db))->obtenerPorId($id);

        if (!$antes) $this->json(['success' => false, 'message' => 'Activo no encontrado.'], 404);
        if (!Permisos::puedeEditarActivoConcreto($antes)) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para editar este activo.'], 403);
        }

        $datos = $this->datosActivoPost();

        $fotos = \App\Helpers\ImageHelper::procesarYSubirImagenes(ROOT_PATH . '/public/uploads', $id, $antes ?: []);
        foreach ($fotos as $key => $val) {
            if ($val !== null) $datos[$key] = $val;
        }

        $post  = array_merge($_POST, ['plaza_id' => (int) ($antes['plaza_id'] ?? Permisos::plazaId())]);
        $res   = (new ActivoGuardado($this->db))->actualizar($id, $datos, $antes, $post, $this->actorSesion());

        if ($res['ok']) {
            $this->json(['success' => true, 'message' => 'Activo actualizado correctamente.']);
        } else {
            $this->json(['success' => false, 'message' => $res['error'] ?? 'No se pudo actualizar el activo.'], 400);
        }
    }

    private function actorSesion(): array
    {
        return [
            'id'       => Permisos::idUsuario(),
            'tipo'     => Permisos::tipo(),
            'plazas'   => array_map('intval', $_SESSION['usuario']['plaza_ids'] ?? []),
            'plaza_id' => Permisos::plazaId(),
        ];
    }

    public function eliminarActivo(): void
    {
        $this->requerirPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->json(['success' => false, 'message' => 'ID inválido.'], 400);

        $activoModel = new Activo($this->db);
        $activo      = $activoModel->obtenerPorId($id);

        if (!$activo || !Permisos::puedeEliminarActivo($activo)) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para eliminar este activo.'], 403);
        }

        (new MovimientoService($this->db))->registrarEliminacion($activo, Permisos::idUsuario());

        if ($activoModel->eliminar($id)) {
            $this->json(['success' => true, 'message' => 'Activo eliminado correctamente.']);
        } else {
            $this->json(['success' => false, 'message' => 'No se pudo eliminar el activo.'], 500);
        }
    }

    // ── Usuarios ──────────────────────────────────────────────────────────────

    public function listarUsuarios(): void
    {
        if (!Permisos::puedeGestionarUsuarios()) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para ver usuarios.'], 403);
        }
        $tipo = Permisos::tipo();
        if ($tipo === 'admin') {
            $this->json((new Usuario($this->db))->obtenerTodos());
        } else {
            // coordinador: solo usuarios de sus plazas asignadas
            $misPlazas = Permisos::plazasIds() ?: [Permisos::plazaId()];
            $usuarioModel = new Usuario($this->db);
            $vistos = [];
            foreach ($misPlazas as $pid) {
                foreach ($usuarioModel->obtenerPorPlaza((int) $pid) as $u) {
                    $vistos[(int) $u['id']] = $u;
                }
            }
            $this->json(array_values($vistos));
        }
    }

    public function obtenerUsuario(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(['success' => false, 'message' => 'ID inválido.'], 400);
        $usuario = (new Usuario($this->db))->obtenerPorId($id);
        if ($usuario) {
            unset($usuario['password']);
            $this->json($usuario);
        } else {
            $this->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }
    }

    public function guardarUsuario(): void
    {
        if (!Permisos::puedeGestionarUsuarios()) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para crear usuarios.'], 403);
        }
        $this->requerirPost();

        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $usuarioModel = new Usuario($this->db);

        if ($usuarioModel->existeEmail($body['email'] ?? '')) {
            $this->json(['success' => false, 'message' => 'El email ya está registrado.'], 400);
        }

        $plazaIds = array_map('intval', (array) ($body['plaza_id'] ?? []));
        if (empty($plazaIds)) {
            $this->json(['success' => false, 'message' => 'Debes seleccionar al menos una plaza.'], 400);
        }
        $body['plaza_id'] = $plazaIds[0];

        if ($usuarioModel->crear($body)) {
            $nuevoId = (int) $this->db->lastInsertId();
            $usuarioModel->guardarPlazas($nuevoId, $plazaIds);
            $this->json(['success' => true, 'message' => 'Usuario creado correctamente.', 'id' => $nuevoId]);
        } else {
            $this->json(['success' => false, 'message' => 'Error al crear usuario.'], 500);
        }
    }

    public function actualizarUsuario(): void
    {
        if (!Permisos::puedeGestionarUsuarios()) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para editar usuarios.'], 403);
        }
        $this->requerirPost();

        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) $this->json(['success' => false, 'message' => 'ID inválido.'], 400);

        $usuarioModel = new Usuario($this->db);

        if (isset($body['plaza_id'])) {
            $plazaIds = array_map('intval', (array) $body['plaza_id']);
            if (empty($plazaIds)) {
                $this->json(['success' => false, 'message' => 'Debes seleccionar al menos una plaza.'], 400);
            }
            $body['plaza_id'] = $plazaIds[0];
            $usuarioModel->guardarPlazas($id, $plazaIds);
        }

        if ($usuarioModel->actualizar($body)) {
            $this->json(['success' => true, 'message' => 'Usuario actualizado correctamente.']);
        } else {
            $this->json(['success' => false, 'message' => 'Error al actualizar usuario.'], 500);
        }
    }

    public function eliminarUsuario(): void
    {
        $this->requerirAdmin();
        $this->requerirPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->json(['success' => false, 'message' => 'ID inválido.'], 400);

        if ((new Usuario($this->db))->eliminar($id)) {
            $this->json(['success' => true, 'message' => 'Usuario eliminado correctamente.']);
        } else {
            $this->json(['success' => false, 'message' => 'No se pudo eliminar el usuario.'], 500);
        }
    }

    // ── Catálogos (acotados por rol, igual que la web) ──────────────────────────

    public function obtenerCatalogos(): void
    {
        $tipo    = Permisos::tipo();
        $plazaId = Permisos::plazaId();

        $dispositivos = (new Dispositivo($this->db))->leerTodos();
        $modelos      = (new Modelo($this->db))->obtenerTodos();
        $tiendasTodas = (new Tienda($this->db))->obtenerTodas();
        $bodegasTodas = (new Bodega($this->db))->obtenerTodas();

        if (Permisos::puedeVerTodasPlazas()) {
            $negocios = (new Negocio($this->db))->obtenerTodos();
            $plazas   = (new Plaza($this->db))->obtenerTodas();
            $regiones = (new Region($this->db))->obtenerTodas();
            $usuarios = (new Usuario($this->db))->obtenerTodos();
        } elseif ($tipo === 'coordinador') {
            $misPlazasIds = Permisos::plazasIds() ?: [$plazaId];
            $plazas = array_values(array_filter(
                (new Plaza($this->db))->obtenerTodas(),
                fn($p) => in_array((int) $p['id'], $misPlazasIds, true)
            ));
            $negociosVistos = [];
            $regionesVistas = [];
            foreach ($plazas as $p) {
                if (!empty($p['negocio_id'])) $negociosVistos[(int) $p['negocio_id']] = $p['negocio_nombre'];
                if (!empty($p['region_id'])) {
                    $regionesVistas[(int) $p['region_id']] = [
                        'nombre' => $p['region_nombre'] ?? '', 'negocio_id' => (int) ($p['negocio_id'] ?? 0),
                    ];
                }
            }
            $negocios = array_map(fn($id, $n) => ['id' => $id, 'nombre' => $n], array_keys($negociosVistos), array_values($negociosVistos));
            $regiones = array_map(fn($id, $r) => ['id' => $id, 'nombre' => $r['nombre'], 'negocio_id' => $r['negocio_id']], array_keys($regionesVistas), array_values($regionesVistas));

            $usuarioModel = new Usuario($this->db);
            $usuariosVistos = [];
            foreach ($misPlazasIds as $pid) {
                foreach ($usuarioModel->obtenerPorPlaza((int) $pid) as $u) $usuariosVistos[(int) $u['id']] = $u;
            }
            $usuarios = array_values($usuariosVistos);
        } else {
            // fs / ati: su propia plaza solamente
            $todasPlazas = (new Plaza($this->db))->obtenerTodas();
            $plazas = array_values(array_filter($todasPlazas, fn($p) => (int) $p['id'] === $plazaId));
            $negocios = [];
            $regiones = [];
            foreach ($plazas as $p) {
                if (!empty($p['negocio_id'])) $negocios[] = ['id' => $p['negocio_id'], 'nombre' => $p['negocio_nombre']];
                if (!empty($p['region_id']))  $regiones[] = ['id' => $p['region_id'], 'nombre' => $p['region_nombre'], 'negocio_id' => $p['negocio_id']];
            }
            $usuarios = $tipo === 'ati' ? (new Usuario($this->db))->obtenerPorPlaza($plazaId) : [];
        }

        // Agregar admins a la lista de usuarios asignables (no están atados a ninguna plaza)
        if ($tipo !== 'admin') {
            $idsYa = array_column($usuarios, 'id');
            foreach ((new Usuario($this->db))->obtenerTodos() as $u) {
                if ($u['tipo'] === 'admin' && !in_array($u['id'], $idsYa, true)) $usuarios[] = $u;
            }
        }

        $plazaIdsFiltro = array_column($plazas, 'id');
        $tiendas = Permisos::puedeVerTodasPlazas()
            ? $tiendasTodas
            : array_values(array_filter($tiendasTodas, fn($t) => in_array((int) $t['plaza_id'], $plazaIdsFiltro, true)));

        $this->json([
            'dispositivos' => $dispositivos,
            'modelos'      => $modelos,
            'tiendas'      => $tiendas,
            'plazas'       => $plazas,
            'regiones'     => $regiones,
            'negocios'     => $negocios,
            'usuarios'     => $usuarios,
            'bodegas'      => $bodegasTodas,
            'areas'        => (new Area($this->db))->obtenerTodas(),
            'status_opts'  => $this->opcionesStatus(),
        ]);
    }

    // GET ?action=obtenerModelosPorDispositivo&dispositivo_id=X
    public function obtenerModelosPorDispositivo(): void
    {
        if (empty($_GET['dispositivo_id'])) { $this->json([]); return; }
        $this->json((new Modelo($this->db))->porDispositivo((int) $_GET['dispositivo_id']));
    }

    // GET ?action=obtenerPlazasPorNegocio&negocio_id=X
    public function obtenerPlazasPorNegocio(): void
    {
        if (empty($_GET['negocio_id'])) { $this->json([]); return; }
        $this->json((new Plaza($this->db))->obtenerPorNegocio((int) $_GET['negocio_id']));
    }

    // GET ?action=obtenerPlazasPorRegion&region_id=X
    public function obtenerPlazasPorRegion(): void
    {
        if (empty($_GET['region_id'])) { $this->json([]); return; }
        $this->json((new Plaza($this->db))->obtenerPorRegion((int) $_GET['region_id']));
    }

    // GET ?action=obtenerRegionesPorNegocio&negocio_id=X
    public function obtenerRegionesPorNegocio(): void
    {
        if (empty($_GET['negocio_id'])) { $this->json([]); return; }
        $this->json((new Region($this->db))->obtenerPorNegocio((int) $_GET['negocio_id']));
    }

    // GET ?action=obtenerTiendasPorPlaza&plaza_id=X
    public function obtenerTiendasPorPlaza(): void
    {
        if (empty($_GET['plaza_id'])) { $this->json([]); return; }
        $this->json((new Tienda($this->db))->obtenerPorPlaza((int) $_GET['plaza_id']));
    }

    // GET ?action=obtenerUsuariosPorPlaza&plaza_id=X
    public function obtenerUsuariosPorPlaza(): void
    {
        if (empty($_GET['plaza_id'])) { $this->json([]); return; }
        $this->json((new Usuario($this->db))->obtenerPorPlaza((int) $_GET['plaza_id']));
    }

    // GET ?action=obtenerStockPorUsuario&usuario_id=X&plaza_id=Y
    public function obtenerStockPorUsuario(): void
    {
        if (empty($_GET['usuario_id'])) { $this->json(null); return; }
        $plazaId = (int) ($_GET['plaza_id'] ?? 0);
        $this->json((new Stock($this->db))->obtenerPorUsuario((int) $_GET['usuario_id'], $plazaId));
    }

    // GET ?action=obtenerStockPorBodega&bodega_id=X
    public function obtenerStockPorBodega(): void
    {
        if (empty($_GET['bodega_id'])) { $this->json(null); return; }
        $this->json((new Stock($this->db))->obtenerPorBodega((int) $_GET['bodega_id']));
    }

    // GET ?action=obtenerBodegaPorPlaza&plaza_id=X
    public function obtenerBodegaPorPlaza(): void
    {
        if (empty($_GET['plaza_id'])) { $this->json(null); return; }
        $this->json((new Bodega($this->db))->obtenerPorPlaza((int) $_GET['plaza_id']));
    }

    // GET ?action=obtenerActivosEnTiendaPorDispositivo&tienda_id=X[&dispositivo_id=Y]&excepto_id=Z
    // Alimenta el selector "¿Reemplaza a?". Sin dispositivo_id → todas las categorías.
    public function obtenerActivosEnTiendaPorDispositivo(): void
    {
        $tiendaId      = (int) ($_GET['tienda_id'] ?? 0);
        $dispositivoId = (int) ($_GET['dispositivo_id'] ?? 0) ?: null;
        $exceptoId     = (int) ($_GET['excepto_id'] ?? 0) ?: null;
        if ($tiendaId <= 0) { $this->json([]); return; }
        $this->json((new Activo($this->db))->enTiendaPorDispositivo($tiendaId, $dispositivoId, $exceptoId));
    }

    // ── Catálogo de modelos (solo admin) ──────────────────────────────────

    public function listarModelos(): void
    {
        if (!Permisos::puedeGestionarModelos()) { $this->json(['error' => 'forbidden'], 403); return; }
        $this->json((new Modelo($this->db))->obtenerTodosDetallado());
    }

    public function obtenerModelo(): void
    {
        if (!Permisos::puedeGestionarModelos()) { $this->json(['error' => 'forbidden'], 403); return; }
        $m = (new Modelo($this->db))->obtenerPorId((int) ($_GET['id'] ?? 0));
        $this->json($m ?: ['error' => 'not_found'], $m ? 200 : 404);
    }

    public function guardarModelo(): void
    {
        $this->guardarOModelo(null);
    }

    public function actualizarModelo(): void
    {
        $b  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($b['id'] ?? 0);
        $this->guardarOModelo($id ?: null);
    }

    private function guardarOModelo(?int $id): void
    {
        if (!Permisos::puedeGestionarModelos()) { $this->json(['success' => false, 'message' => 'Sin permiso.'], 403); return; }
        $this->requerirPost();
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $nombre     = trim((string) ($b['nombre'] ?? ''));
        $dispId     = (int) ($b['dispositivo_id'] ?? 0);
        $marcaNueva = trim((string) ($b['marca_nueva'] ?? ''));
        $marcaId    = (int) ($b['marca_id'] ?? 0) ?: null;

        if ($nombre === '' || $dispId <= 0) {
            $this->json(['success' => false, 'message' => 'Nombre y categoría de dispositivo son obligatorios.'], 422);
            return;
        }
        if ($marcaNueva !== '') {
            $marcaId = (new Marca($this->db))->obtenerOCrear($marcaNueva);
        }

        $modelo = new Modelo($this->db);
        if ($modelo->existe($nombre, $dispId, $marcaId, $id)) {
            $this->json(['success' => false, 'message' => 'Ya existe un modelo con esa marca y nombre en esa categoría.'], 409);
            return;
        }

        if ($id) {
            $modelo->actualizar(['id' => $id, 'nombre' => $nombre, 'dispositivo_id' => $dispId, 'marca_id' => $marcaId]);
            $this->json(['success' => true, 'message' => 'Modelo actualizado.', 'id' => $id]);
        } else {
            $nuevo = $modelo->crear(['nombre' => $nombre, 'dispositivo_id' => $dispId, 'marca_id' => $marcaId]);
            $this->json(['success' => true, 'message' => 'Modelo creado.', 'id' => $nuevo]);
        }
    }

    public function eliminarModelo(): void
    {
        if (!Permisos::puedeGestionarModelos()) { $this->json(['success' => false, 'message' => 'Sin permiso.'], 403); return; }
        $this->requerirPost();
        $b          = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id         = (int) ($b['id'] ?? 0);
        $reasignarA = (int) ($b['reasignar_a'] ?? 0) ?: null;
        if ($id <= 0) { $this->json(['success' => false, 'message' => 'Modelo inválido.'], 422); return; }

        $modelo = new Modelo($this->db);
        if (!$modelo->obtenerPorId($id)) { $this->json(['success' => false, 'message' => 'Modelo no encontrado.'], 404); return; }

        $enUso = $modelo->contarActivos($id);
        if ($enUso > 0 && !$reasignarA) {
            $this->json(['success' => false, 'message' => "Sin destino: {$enUso} activos usan este modelo.", 'activos' => $enUso], 409);
            return;
        }
        if ($reasignarA === $id) { $this->json(['success' => false, 'message' => 'El modelo destino debe ser distinto.'], 422); return; }

        try {
            $this->db->beginTransaction();
            $movidos = 0;
            if ($enUso > 0) {
                if (!$modelo->obtenerPorId($reasignarA)) throw new \RuntimeException('El modelo destino no existe.');
                $movidos = $modelo->reasignarActivos($id, $reasignarA);
            }
            $modelo->eliminar($id);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->json(['success' => false, 'message' => 'No se pudo eliminar: ' . $e->getMessage()], 500);
            return;
        }
        $this->json(['success' => true, 'message' => "Modelo eliminado." . ($movidos ? " {$movidos} activos reasignados." : '')]);
    }

    // GET ?action=obtenerAtisPorPlaza&plaza_id=X
    public function obtenerAtisPorPlaza(): void
    {
        if (empty($_GET['plaza_id'])) { $this->json([]); return; }
        $this->json((new Tienda($this->db))->atisDePlaza((int) $_GET['plaza_id']));
    }

    // GET ?action=listarHistorial  (mismos filtros que la pestaña Historial)
    public function listarHistorial(): void
    {
        if (!Permisos::puedeVerHistorial()) {
            $this->json(['success' => false, 'message' => 'No tienes acceso al historial.'], 403);
        }
        $filtros = array_merge(Permisos::filtrosHistorial(), array_filter([
            'activo_id'  => $_GET['activo_id']  ?? null,
            'serie'      => $_GET['serie']      ?? null,
            'evento'     => $_GET['evento']     ?? null,
            'tienda_id'  => $_GET['tienda_id']  ?? null,
            'usuario_id' => $_GET['usuario_id'] ?? null,
            'desde'      => $_GET['desde']      ?? null,
            'hasta'      => $_GET['hasta']      ?? null,
        ], fn($v) => $v !== null && $v !== ''));

        $pagina    = max(1, (int) ($_GET['pagina']     ?? 1));
        $porPagina = max(1, (int) ($_GET['por_pagina'] ?? 30));
        $this->json((new Movimiento($this->db))->listar($filtros, $pagina, $porPagina));
    }

    // POST ?action=asignarAtiTienda  (tienda_id, ati_usuario_id|'' )  — solo admin
    public function asignarAtiTienda(): void
    {
        $this->requerirPost();
        if (!Permisos::puedeGestionarTiendas()) {
            $this->json(['success' => false, 'message' => 'Acceso restringido.'], 403);
        }
        $tiendaId = (int) ($_POST['tienda_id'] ?? 0);
        $atiId    = (int) ($_POST['ati_usuario_id'] ?? 0) ?: null;
        if ($tiendaId <= 0) $this->json(['success' => false, 'message' => 'Tienda inválida.'], 400);

        if ((new Tienda($this->db))->asignarAti($tiendaId, $atiId)) {
            $this->json(['success' => true, 'message' => 'ATI responsable actualizado.']);
        } else {
            $this->json(['success' => false, 'message' => 'No se pudo actualizar.'], 500);
        }
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function login(): void
    {
        $this->requerirPost();
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $email    = trim($body['email']    ?? '');
        $password = $body['password'] ?? '';
        if (empty($email) || empty($password)) {
            $this->json(['success' => false, 'message' => 'Email y contraseña son requeridos.'], 400);
        }
        $usuarioModel = new Usuario($this->db);
        $usuario      = $usuarioModel->buscarPorEmail($email);

        if ($usuario && password_verify($password, $usuario['password'])) {
            $usuarioPlazas = $usuarioModel->obtenerPlazas($usuario['id']);
            $plazaId       = (int) ($usuario['plaza_id'] ?? 0);
            if ($plazaId === 0 && !empty($usuarioPlazas[0]['id'])) {
                $plazaId = (int) $usuarioPlazas[0]['id'];
            }

            $plazaNombre = '';
            foreach ($usuarioPlazas as $plaza) {
                if ((int) $plaza['id'] === $plazaId) {
                    $plazaNombre = $plaza['nombre'];
                    break;
                }
            }

            session_regenerate_id(true);
            $tipo = strtolower(trim($usuario['tipo'] ?? 'fs'));

            $_SESSION['usuario'] = [
                'id'           => $usuario['id'],
                'nombre'       => $usuario['nombre'],
                'tipo'         => $tipo,
                'email'        => $usuario['email'],
                'foto'         => $usuario['foto'] ?? null,
                'plaza_id'     => $plazaId,
                'plaza_nombre' => $plazaNombre,
                'plazas'       => $usuarioPlazas,
                'plaza_ids'    => array_map(fn($p) => (int) $p['id'], $usuarioPlazas),
            ];

            $_SESSION['usuario_id']     = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_tipo']   = $tipo;
            $_SESSION['last_activity']  = time();

            unset($usuario['password']);
            $this->json([
                'success'    => true,
                'message'    => 'Login exitoso.',
                'usuario'    => $usuario,
                'session_id' => session_id(),
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Credenciales incorrectas.'], 401);
        }
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->json(['success' => true, 'message' => 'Sesión cerrada.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function vistaDefaultParaTipo(string $tipo): string
    {
        return match ($tipo) {
            'admin' => 'todos',
            'fs'    => 'mi_stock',
            'ati'   => 'mi_stock',
            default => 'bodega',
        };
    }

    private function vistaPermitida(string $vista, string $tipo): string
    {
        if ($tipo === 'fs' && $vista !== 'mi_stock') return 'mi_stock';
        return $vista;
    }

    private function vistasDisponiblesParaTipo(string $tipo): array
    {
        return match ($tipo) {
            'admin', 'coordinador' => ['bodega', 'todos'],
            'ati'                  => ['bodega', 'mi_stock', 'todos'],
            'fs'                   => ['mi_stock'],
            default                => [],
        };
    }

    /** Resuelve la plaza a usar: la posteada si es válida, o la primera disponible del negocio/usuario */
    private function resolverPlazaId(int $negocioIdPost): int
    {
        $plazaPost = (int) ($_POST['plaza_id'] ?? 0);
        $tipo      = Permisos::tipo();

        if ($tipo === 'admin') {
            return $plazaPost > 0 ? $plazaPost : Permisos::plazaId();
        }

        $misPlazas = Permisos::plazasIds() ?: [Permisos::plazaId()];
        if ($plazaPost > 0 && in_array($plazaPost, $misPlazas, true)) {
            return $plazaPost;
        }
        return $misPlazas[0] ?? Permisos::plazaId();
    }

    private function datosActivoPost(): array
    {
        // stock_id ya no se acepta del cliente: lo resuelve StockResolver por estatus.
        return [
            'serie'                 => trim($_POST['serie']  ?? ''),
            'codigo_barras'         => trim($_POST['codigo_barras'] ?? '') ?: null,
            'num_activo'            => trim($_POST['num_activo'] ?? '') ?: null,
            'modelo_id'             => !empty($_POST['modelo_id'])             ? (int) $_POST['modelo_id']             : null,
            'status'                => Activo::normalizarStatus($_POST['status'] ?? 'en_bodega'),
            'procedencia_tienda_id' => !empty($_POST['procedencia_tienda_id']) ? (int) $_POST['procedencia_tienda_id'] : null,
            'tienda_uso_id'         => !empty($_POST['tienda_uso_id'])         ? (int) $_POST['tienda_uso_id']         : null,
        ];
    }

    private function opcionesStatus(): array
    {
        return [
            ['value' => 'en_bodega', 'label' => 'En Bodega'],
            ['value' => 'en_uso',    'label' => 'En Uso'],
            ['value' => 'baja',      'label' => 'Baja'],
            ['value' => 'garantia',  'label' => 'Garantía'],
            ['value' => 'asignado',  'label' => 'Asignado'],
        ];
    }

    private function json(mixed $datos, int $codigo = 200): never
    {
        if (ob_get_length()) ob_end_clean();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function requerirPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            $this->json(['success' => false, 'message' => 'Método no permitido.'], 405);
    }

    private function requerirAdmin(): void
    {
        if (!Permisos::esAdmin()) {
            $this->json(['success' => false, 'message' => 'Acceso restringido a administradores.'], 403);
        }
    }
}