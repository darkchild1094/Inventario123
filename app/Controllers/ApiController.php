<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\Usuario;
use App\Models\Dispositivo;
use App\Models\Modelo;
use App\Models\Tienda;
use App\Models\Plaza;
use App\Models\Region;
use App\Models\Negocio;
use App\Models\Bodega;
use App\Models\Stock;
use App\Models\Area;
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
            $filtros['usuario_id'] = $_GET['usuario_id'] ?? null;
        } elseif ($tipo === 'coordinador') {
            $misPlazas = Permisos::plazasIds() ?: [$plazaId];
            $plazaGet  = (int) ($_GET['plaza_id'] ?? 0);
            $filtros['plaza_id']   = ($plazaGet > 0 && in_array($plazaGet, $misPlazas, true))
                ? $plazaGet
                : $misPlazas;
            $filtros['negocio_id'] = $_GET['negocio_id'] ?? null;
            $filtros['region_id']  = $_GET['region_id']  ?? null;
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

        $tipo        = Permisos::tipo();
        $stockModel  = new Stock($this->db);
        $activoModel = new Activo($this->db);
        $datos       = $this->datosActivoPost();
        $statusPost  = $datos['status'];

        $negocioIdPost = (int) ($_POST['negocio_id'] ?? 0);
        $plazaId       = $this->resolverPlazaId($negocioIdPost);
        if ($plazaId <= 0) {
            $this->json(['success' => false, 'message' => 'Debes indicar una plaza válida.'], 400);
        }

        $stock = null;

        if ($tipo === 'fs') {
            if ($statusPost === 'en_uso' || $statusPost === 'asignado') {
                $stock = $stockModel->obtenerPorUsuario(Permisos::idUsuario(), $plazaId);
            } else {
                $stock = $this->stockDeBodega($plazaId);
            }
        } elseif ($tipo === 'ati') {
            if ($statusPost === 'asignado') {
                $asignadoUsuarioId = (int) ($_POST['asignado_usuario_id'] ?? 0) ?: Permisos::idUsuario();
                if ($asignadoUsuarioId !== Permisos::idUsuario()) {
                    $err = $this->validarAsignacion($asignadoUsuarioId, $plazaId);
                    if ($err) $this->json(['success' => false, 'message' => $err], 400);
                }
                $stock = $stockModel->obtenerPorUsuario($asignadoUsuarioId, $plazaId);
            } elseif ($statusPost === 'en_uso') {
                $stock = $stockModel->obtenerPorUsuario(Permisos::idUsuario(), $plazaId);
            } else {
                $stock = $this->stockDeBodega($plazaId);
            }
        } elseif ($statusPost === 'asignado' && !empty($_POST['asignado_usuario_id'])) {
            // admin / coordinador asignando a un usuario específico
            $asignadoUsuarioId = (int) $_POST['asignado_usuario_id'];
            if ($tipo !== 'admin') {
                $err = $this->validarAsignacion($asignadoUsuarioId, $plazaId);
                if ($err) $this->json(['success' => false, 'message' => $err], 400);
            }
            $stock = $stockModel->obtenerPorUsuario($asignadoUsuarioId, $plazaId);
        } elseif (!empty($_POST['stock_destino'])) {
            // admin/coordinador con destino explícito: 'bodega_X' o 'usuario_X'
            [$tipoDestino, $idDest] = array_pad(explode('_', $_POST['stock_destino'], 2), 2, null);
            if ($tipoDestino === 'bodega') {
                $stock = $stockModel->obtenerPorBodega((int) $idDest);
            } else {
                $stock = $stockModel->obtenerPorUsuario((int) $idDest, $plazaId);
            }
        } elseif ($statusPost === 'en_bodega') {
            $stock = $this->stockDeBodega($plazaId);
        }

        $datos['stock_id'] = $stock ? $stock['id'] : null;

        if (!$activoModel->crear($datos)) {
            $this->json(['success' => false, 'message' => 'Error al crear. Verifica que la serie no esté duplicada.'], 400);
        }

        $nuevoId = $activoModel->ultimoId();
        $this->json(['success' => true, 'message' => 'Activo registrado correctamente.', 'id' => $nuevoId]);
    }

    public function actualizarActivo(): void
    {
        $this->requerirPost();
        $id          = (int) ($_POST['id'] ?? 0);
        $activoModel = new Activo($this->db);
        $activo      = $activoModel->obtenerPorId($id);

        if (!$activo) $this->json(['success' => false, 'message' => 'Activo no encontrado.'], 404);
        if (!Permisos::puedeEditarActivoConcreto($activo)) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para editar este activo.'], 403);
        }

        $tipo  = Permisos::tipo();
        $datos = array_merge(['id' => $id], $this->datosActivoPost());

        if (Permisos::esFs() || $tipo === 'ati') {
            if ($tipo === 'ati'
                && $datos['status'] === 'asignado'
                && !empty($_POST['asignado_usuario_id'])
            ) {
                $asignadoUsuarioId = (int) $_POST['asignado_usuario_id'];
                $plazaIdActivo     = (int) ($activo['plaza_id'] ?? Permisos::plazaId());
                if ($asignadoUsuarioId !== Permisos::idUsuario()) {
                    $err = $this->validarAsignacion($asignadoUsuarioId, $plazaIdActivo);
                    if ($err) $this->json(['success' => false, 'message' => $err], 400);
                }
                $stockNuevo = (new Stock($this->db))->obtenerPorUsuario($asignadoUsuarioId, $plazaIdActivo);
                $datos['stock_id'] = $stockNuevo ? $stockNuevo['id'] : $activo['stock_id'];
            } else {
                $datos['stock_id'] = $activo['stock_id'];
            }
        }

        if ($activoModel->actualizar($datos)) {
            $this->json(['success' => true, 'message' => 'Activo actualizado correctamente.']);
        } else {
            $this->json(['success' => false, 'message' => 'No se pudo actualizar el activo.'], 400);
        }
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

    private function stockDeBodega(int $plazaId): ?array
    {
        $bodega = (new Bodega($this->db))->obtenerPorPlaza($plazaId);
        if (!$bodega) return null;
        $stockModel = new Stock($this->db);
        $stock = $stockModel->obtenerPorBodega((int) $bodega['id']);
        if ($stock && isset($stock['bodega_id']) && (int) $stock['bodega_id'] !== (int) $bodega['id']) {
            $stockModel->crearParaBodega((int) $bodega['id']);
            $stock = $stockModel->obtenerPorBodega((int) $bodega['id']);
        }
        return $stock;
    }

    /** Valida que un usuario destino pertenezca a la plaza indicada. Devuelve mensaje de error o null si OK. */
    private function validarAsignacion(int $usuarioId, int $plazaId): ?string
    {
        $usuarioModel    = new Usuario($this->db);
        $usuarioAsignado = $usuarioModel->obtenerPorId($usuarioId);
        if (!$usuarioAsignado || !$usuarioModel->perteneceAPlaza($usuarioId, $plazaId)) {
            return 'Solo puedes asignar activos a usuarios de la plaza seleccionada.';
        }
        return null;
    }

    private function datosActivoPost(): array
    {
        return [
            'serie'                 => trim($_POST['serie']  ?? ''),
            'placa'                 => trim($_POST['placa'] ?? '') ?: null,
            'modelo_id'             => !empty($_POST['modelo_id'])             ? (int) $_POST['modelo_id']             : null,
            'status'                => Activo::normalizarStatus($_POST['status'] ?? 'en_bodega'),
            'procedencia_tienda_id' => !empty($_POST['procedencia_tienda_id']) ? (int) $_POST['procedencia_tienda_id'] : null,
            'tienda_uso_id'         => !empty($_POST['tienda_uso_id'])         ? (int) $_POST['tienda_uso_id']         : null,
            'stock_id'              => !empty($_POST['stock_id'])              ? (int) $_POST['stock_id']              : null,
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