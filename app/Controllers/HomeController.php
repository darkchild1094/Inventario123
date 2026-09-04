<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\Dispositivo;
use App\Models\Modelo;
use App\Models\Tienda;
use App\Models\Plaza;
use App\Models\Region;
use App\Models\Negocio;
use App\Models\Usuario;
use App\Models\Bodega;
use App\Models\Stock;
use App\Models\Area;
use App\Models\Movimiento;
use App\Services\ActivoGuardado;
use App\Services\MovimientoService;
use App\Helpers\Permisos;

class HomeController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $tipo    = Permisos::tipo();
        $plazaId = Permisos::plazaId();
        $vista   = $_GET['vista'] ?? ($this->vistaDefaultParaTipo($tipo));

        // Validar que el rol puede ver la vista solicitada
        $vista = $this->vistaPermitida($vista, $tipo);

        // ── Filtros de scope según rol (no modificables por URL) ──────────────
        $scope = Permisos::filtrosScope();

        $statusFiltro = $_GET['status'] ?? null;
        if ($statusFiltro !== null && $statusFiltro !== '') {
            $statusFiltro = Activo::normalizarStatus((string) $statusFiltro);
        } else {
            $statusFiltro = null;
        }

        // ── Filtros opcionales de la URL (solo los permitidos por rol) ────────
        $filtros = array_merge($scope, [
            'dispositivo_id' => $_GET['dispositivo_id'] ?? null,
            'status'         => $statusFiltro,
            'busqueda'       => $_GET['busqueda']       ?? null,
            'solo_bodega'    => false,
        ]);

        // Negocio y plaza solo si el rol puede filtrar por ellos
        if (Permisos::puedeVerTodasPlazas()) {
            $filtros['negocio_id'] = $_GET['negocio_id'] ?? null;
            $filtros['plaza_id']   = $_GET['plaza_id']   ?? null;
            $filtros['region_id']  = $_GET['region_id']  ?? null;
            $filtros['tienda_id']  = $_GET['tienda_id']  ?? null;
            $filtros['usuario_id'] = $_GET['usuario_id'] ?? null;
        } elseif ($tipo === 'coordinador') {
            // Coordinador puede tener VARIAS plazas asignadas (incluso de negocios
            // distintos, ej. Valles-OXXO y León-BARA). Si pide una plaza específica
            // por URL y es suya, se filtra a esa; si no, se ven TODAS las suyas.
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
            // ati: siempre su única plaza, ignorar ?plaza_id de URL
            $filtros['plaza_id'] = $plazaId;
        }

        // ── Vista específica ──────────────────────────────────────────────────
        if ($vista === 'bodega') {
            // fs no puede ver bodega general
            if (Permisos::soloSuStock()) {
                $this->redirigir('index.php?vista=mi_stock');
            }
            $filtros['solo_bodega'] = true;
            unset($filtros['stock_usuario_id']); // bodega no filtra por usuario
        } elseif ($vista === 'mi_stock') {
            // Para fs y ati: su stock personal
            $filtros['stock_usuario_id'] = Permisos::idUsuario();
            $filtros['solo_bodega']      = false;
            unset($filtros['plaza_id']); // el stock personal no depende de plaza
        } elseif ($vista === 'todos') {
            // admin y coordinador: todo (dentro de su scope)
            Permisos::requerir(['admin', 'coordinador']);
        }

        $pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
        $resultado = (new Activo($this->db))->obtenerTodosFiltrado($filtros, $pagina, 20);

        $activos    = $resultado['activos'];
        $paginacion = $resultado['paginacion'];

        // Variables para filtros en la vista
        $negocio_id     = $filtros['negocio_id']     ?? null;
        $plaza_id       = $filtros['plaza_id']       ?? null;
        $region_id      = $filtros['region_id']      ?? null;
        $tienda_id      = $filtros['tienda_id']      ?? null;
        $usuario_id     = $filtros['usuario_id']     ?? null;
        $dispositivo_id = $filtros['dispositivo_id'] ?? null;
        $status         = $filtros['status']         ?? null;
        $busqueda       = $filtros['busqueda']       ?? null;

        // Catálogos para filtros (limitados por rol)
        if (Permisos::puedeVerTodasPlazas()) {
            $negocios       = (new Negocio($this->db))->obtenerTodos();
            $plazas         = (new Plaza($this->db))->obtenerTodas();
            $regiones       = (new Region($this->db))->obtenerTodas();
            $usuariosFiltro = (new Usuario($this->db))->obtenerTodos();
            $tiendasFiltro  = (new Tienda($this->db))->obtenerTodas();
        } elseif ($tipo === 'coordinador') {
            // Coordinador: solo SUS plazas asignadas, que pueden pertenecer
            // a negocios distintos (ej. Valles-OXXO y León-BARA).
            $misPlazasIds = Permisos::plazasIds() ?: [$plazaId];
            $plazas = array_values(array_filter(
                (new Plaza($this->db))->obtenerTodas(),
                fn($p) => in_array((int) $p['id'], $misPlazasIds, true)
            ));

            // Los negocios y regiones disponibles en el filtro son los que
            // corresponden a las plazas que el coordinador realmente tiene asignadas.
            $negociosVistos = [];
            $regionesVistas = [];
            foreach ($plazas as $p) {
                if (!empty($p['negocio_id'])) {
                    $negociosVistos[(int) $p['negocio_id']] = $p['negocio_nombre'];
                }
                if (!empty($p['region_id'])) {
                    $regionesVistas[(int) $p['region_id']] = [
                        'nombre'     => $p['region_nombre'] ?? '',
                        'negocio_id' => (int) ($p['negocio_id'] ?? 0),
                    ];
                }
            }
            $negocios = array_map(
                fn($id, $nombre) => ['id' => $id, 'nombre' => $nombre],
                array_keys($negociosVistos),
                array_values($negociosVistos)
            );
            $regiones = array_map(
                fn($id, $r) => ['id' => $id, 'nombre' => $r['nombre'], 'negocio_id' => $r['negocio_id']],
                array_keys($regionesVistas),
                array_values($regionesVistas)
            );

            // Usuarios: todos los que pertenecen a alguna de sus plazas asignadas
            $usuarioModel   = new Usuario($this->db);
            $usuariosFiltro = [];
            foreach ($misPlazasIds as $pid) {
                foreach ($usuarioModel->obtenerPorPlaza((int) $pid) as $u) {
                    $usuariosFiltro[(int) $u['id']] = $u;
                }
            }
            $usuariosFiltro = array_values($usuariosFiltro);

            // Tiendas de sus plazas asignadas
            $tiendasFiltro = array_values(array_filter(
                (new Tienda($this->db))->obtenerTodas(),
                fn($t) => in_array((int) $t['plaza_id'], $misPlazasIds, true)
            ));
        } else {
            $negocios       = [];
            $plazas         = [];
            $regiones       = [];
            $usuariosFiltro = [];
            $tiendasFiltro  = [];
        }

        // ── Respuesta AJAX: solo el fragmento de resultados, sin recargar ──────
        if ($this->esPeticionAjax()) {
            require ROOT_PATH . '/app/views/home/_resultados.php';
            return;
        }

        require ROOT_PATH . '/app/views/home/index.php';
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function crear(): void
    {
        Permisos::requerir(['admin', 'coordinador', 'fs', 'ati']);
        [
            $dispositivos, $tiendas, $plazas,
            $regiones, $negocios, $usuarios, $bodegas,
        ] = $this->cargarCatalogos();

        $tipo           = Permisos::tipo();
        $usuarioPlazas  = $_SESSION['usuario']['plazas'] ?? [];
        $esAdmin        = Permisos::esAdmin();

        // Si el usuario tiene plazas asignadas en usuario_plaza, restringimos
        // las opciones a esas plazas. Solo si no tiene plazas asignadas y
        // es admin mostramos todas las plazas.
        $usuarioPlazaIds = array_map('intval', array_column($usuarioPlazas, 'id'));
        if (!empty($usuarioPlazaIds)) {
            $plazasDisponibles = array_values(array_filter($plazas, fn($p) => in_array((int)$p['id'], $usuarioPlazaIds, true)));
        } elseif ($esAdmin) {
            $plazasDisponibles = $plazas;
        } else {
            // Fallback: mostrar todas si no hay asignaciones (por compatibilidad)
            $plazasDisponibles = $plazas;
        }

        $negocioIds = array_unique(array_map(fn($p) => (int)($p['negocio_id'] ?? 0), $plazasDisponibles));
        $negociosDisponibles = array_values(array_filter($negocios, fn($n) => in_array((int)$n['id'], $negocioIds, true)));

        $negocioId = max(0, (int) ($_GET['negocio_id'] ?? 0));
        if ($negocioId <= 0 || !in_array($negocioId, $negocioIds, true)) {
            $negocioId = $plazasDisponibles[0]['negocio_id'] ?? $negociosDisponibles[0]['id'] ?? 0;
        }

        $plazasPorNegocio = array_values(array_filter($plazasDisponibles, fn($p) => (int)$p['negocio_id'] === $negocioId));
        if (empty($plazasPorNegocio)) {
            $plazasPorNegocio = $plazasDisponibles;
        }

        $plazaId = max(0, (int) ($_GET['plaza_id'] ?? 0));
        if ($plazaId <= 0 || !in_array($plazaId, array_column($plazasPorNegocio, 'id'), true)) {
            $plazaId = $plazasPorNegocio[0]['id'] ?? 0;
        }

        $tiendasPlaza = array_values(array_filter($tiendas, fn($t) => (int)$t['plaza_id'] === $plazaId));
        $bodegasPlaza = array_values(array_filter($bodegas, fn($b) => in_array($plazaId, array_map('intval', array_filter(explode(',', $b['plazas_ids'] ?? ''))), true)));
        $usuariosPorPlaza = (new Usuario($this->db))->obtenerPorPlaza($plazaId);
        $bodegaPorPlaza   = (new Bodega($this->db))->obtenerPorPlaza($plazaId);
        $bodegaOxxo       = (new Bodega($this->db))->obtenerPorPlazaYNegocio($plazaId, 'oxxo');
        // Preferir una bodega específica para el negocio seleccionado
        $negocioSeleccionado = (new Negocio($this->db))->obtenerPorId($negocioId);
        $bodegaPorNegocio = null;
        if ($negocioSeleccionado) {
            $bodegaPorNegocio = (new Bodega($this->db))->obtenerPorPlazaYNegocio($plazaId, $negocioSeleccionado['nombre']);
        }

        require ROOT_PATH . '/app/views/home/crear.php';
    }

    public function guardar(): void
    {
        Permisos::requerir(['admin', 'coordinador', 'fs', 'ati']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirigir('index.php');

        $datos         = $this->datosDesdePost();
        $negocioIdPost = (int) ($_POST['negocio_id'] ?? 0);
        $plazaId       = $this->resolverPlazaFormulario($negocioIdPost);
        $redirectCrear = 'index.php?action=crear&negocio_id=' . $negocioIdPost . '&plaza_id=' . $plazaId;

        // Imágenes
        $fotos = \App\Helpers\ImageHelper::procesarYSubirImagenes(ROOT_PATH . '/public/uploads', null, []);
        $datos = array_merge($datos, $fotos);

        // Toda la resolución de stock + bitácora + reemplazo vive en el servicio.
        $post = array_merge($_POST, ['plaza_id' => $plazaId]);
        $res  = (new ActivoGuardado($this->db))->crear($datos, $post, $this->actorSesion());

        $_SESSION[$res['ok'] ? 'success' : 'error'] = $res['ok']
            ? 'Activo registrado con éxito.'
            : ($res['error'] ?? 'No se pudo registrar el activo.');
        $this->redirigir($redirectCrear);
    }

    /** Datos del usuario en sesión que necesitan los servicios de guardado. */
    private function actorSesion(): array
    {
        return [
            'id'       => Permisos::idUsuario(),
            'tipo'     => Permisos::tipo(),
            'plazas'   => array_map('intval', $_SESSION['usuario']['plaza_ids'] ?? []),
            'plaza_id' => Permisos::plazaId(),
        ];
    }

    /**
     * Resuelve la plaza de trabajo del formulario multi-negocio: si la plaza
     * posteada no pertenece al negocio elegido (form desincronizado), busca una
     * plaza del usuario que sí corresponda a ese negocio.
     */
    private function resolverPlazaFormulario(int $negocioIdPost): int
    {
        $plazaId       = max(0, (int) ($_POST['plaza_id'] ?? $_SESSION['usuario']['plaza_id'] ?? 0));
        $usuarioPlazas = $_SESSION['usuario']['plazas'] ?? [];

        if ($usuarioPlazas) {
            $plazaIds = array_map('intval', array_column($usuarioPlazas, 'id'));
            if ($plazaId <= 0 || !in_array($plazaId, $plazaIds, true)) {
                $plazaId = (int) $usuarioPlazas[0]['id'];
            }
        }

        if ($negocioIdPost > 0 && $plazaId > 0) {
            $plazaCheck = (new Plaza($this->db))->obtenerPorId($plazaId);
            if ($plazaCheck && (int) ($plazaCheck['negocio_id'] ?? 0) !== $negocioIdPost) {
                foreach ($usuarioPlazas as $p) {
                    $pFull = (new Plaza($this->db))->obtenerPorId((int) $p['id']);
                    if ($pFull && (int) ($pFull['negocio_id'] ?? 0) === $negocioIdPost) {
                        return (int) $pFull['id'];
                    }
                }
            }
        }
        return $plazaId;
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function editar(): void
    {
        Permisos::requerir(['admin', 'coordinador', 'fs', 'ati']);
        $id     = (int) ($_GET['id'] ?? 0);
        $activo = (new Activo($this->db))->obtenerPorId($id);

        if (!$activo || $id <= 0) $this->redirigir('index.php?error=no_encontrado');

        $activo['status'] = Activo::normalizarStatus($activo['status'] ?? 'en_bodega');

        // fs/ati: solo pueden editar activos de su propio stock
        if (!Permisos::puedeEditarActivoConcreto($activo)) {
            $_SESSION['error'] = 'Solo puedes editar activos de tu propio stock.';
            $this->redirigir('index.php');
        }

        $modelos = (new Modelo($this->db))->porDispositivo($activo['dispositivo_id'] ?? 0);
        [
            $dispositivos, $tiendas, $plazas,
            $regiones, $negocios, $usuarios, $bodegas,
        ] = $this->cargarCatalogos();

        $plazaId = (int) ($activo['plaza_id'] ?? Permisos::plazaId());
        $tiendasPlaza = array_values(array_filter($tiendas, fn($t) => (int)$t['plaza_id'] === $plazaId));
        $bodegasPlaza = array_values(array_filter($bodegas, fn($b) => in_array($plazaId, array_map('intval', array_filter(explode(',', $b['plazas_ids'] ?? ''))), true)));
        $usuariosPorPlaza = (new Usuario($this->db))->obtenerPorPlaza($plazaId);

        require ROOT_PATH . '/app/views/home/editar.php';
    }

    public function actualizar(): void
    {
        Permisos::requerir(['admin', 'coordinador', 'fs', 'ati']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirigir('index.php');

        $id     = (int) ($_POST['id'] ?? 0);
        $antes  = (new Activo($this->db))->obtenerPorId($id);

        if (!$antes || !Permisos::puedeEditarActivoConcreto($antes)) {
            $_SESSION['error'] = 'Solo puedes editar activos de tu propio stock.';
            $this->redirigir('index.php');
        }

        $datos = $this->datosDesdePost();

        // Imágenes: solo se sobrescriben las que realmente se subieron.
        $fotos = \App\Helpers\ImageHelper::procesarYSubirImagenes(ROOT_PATH . '/public/uploads', $id, $antes ?: []);
        foreach ($fotos as $key => $val) {
            if ($val !== null) $datos[$key] = $val;
        }

        $post = array_merge($_POST, ['plaza_id' => (int) ($antes['plaza_id'] ?? Permisos::plazaId())]);
        $res  = (new ActivoGuardado($this->db))->actualizar($id, $datos, $antes, $post, $this->actorSesion());

        if ($res['ok']) {
            $_SESSION['success'] = 'Activo actualizado con éxito.';
            $this->redirigir('index.php');
        } else {
            $_SESSION['error'] = $res['error'] ?? 'No se pudo actualizar el activo.';
            $this->redirigir("index.php?action=editar&id={$id}");
        }
    }

    // ── Detalle (modal, sin recargar la página) ─────────────────────────────────

    public function detalle(): void
    {
        $id     = (int) ($_GET['id'] ?? 0);
        $activo = $id > 0 ? (new Activo($this->db))->obtenerPorId($id) : null;

        if (!$activo) {
            $this->jsonSalida(['success' => false, 'message' => 'Activo no encontrado.'], 404);
        }

        if (!Permisos::puedeVerActivoConcreto($activo)) {
            $this->jsonSalida(['success' => false, 'message' => 'No tienes permiso para ver este activo.'], 403);
        }

        $activo['status'] = Activo::normalizarStatus($activo['status'] ?? 'en_bodega');
        $movimientos      = (new Movimiento($this->db))->porActivo((int) $activo['id']);

        ob_start();
        require ROOT_PATH . '/app/views/home/_detalle_activo.php';
        $html = ob_get_clean();

        $this->jsonSalida([
            'success'       => true,
            'html'          => $html,
            'puedeEditar'   => Permisos::puedeEditarActivoConcreto($activo),
            'puedeEliminar' => Permisos::puedeEliminarActivo($activo),
            'id'            => $activo['id'],
        ]);
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function eliminar(): void
    {
        Permisos::requerir(['admin', 'ati']);
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $activo = (new Activo($this->db))->obtenerPorId($id);

            if (!$activo || !Permisos::puedeEliminarActivo($activo)) {
                $_SESSION['error'] = 'No tienes permiso para eliminar este activo.';
                $this->redirigir('index.php');
            }

            // Bitácora ANTES del borrado físico (datos_json conserva el rastro).
            (new MovimientoService($this->db))->registrarEliminacion($activo, Permisos::idUsuario());

            $ok = (new Activo($this->db))->eliminar($id);
            $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Activo eliminado.' : 'No se pudo eliminar.';
        }
        $this->redirigir('index.php');
    }

    // ── Exportar Excel ────────────────────────────────────────────────────────

    public function exportar(): void
    {
        Permisos::requerir(['admin', 'coordinador', 'fs', 'ati']);

        // Usar filtros de exportación según matriz de roles:
        // admin → todo | coordinador/ati → su plaza | fs → su stock
        $filtros   = Permisos::filtrosExportar();
        $resultado = (new Activo($this->db))->obtenerTodosFiltrado($filtros, 1, 99999);
        $activos   = $resultado['activos'];

        $this->generarExcel($activos);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function vistaDefaultParaTipo(string $tipo): string
    {
        return match($tipo) {
            'admin' => 'todos',
            'fs'    => 'mi_stock',
            'ati'   => 'mi_stock',
            default => 'bodega',
        };
    }

    private function vistaPermitida(string $vista, string $tipo): string
    {
        // fs solo puede ver mi_stock
        if ($tipo === 'fs' && $vista !== 'mi_stock') return 'mi_stock';
        // ati puede ver mi_stock o bodega (bodega = su plaza)
        if ($tipo === 'ati' && $vista === 'todos') return 'bodega';
        return $vista;
    }

    private function datosDesdePost(): array
    {
        // El stock_id ya NO se acepta del formulario: lo resuelve StockResolver
        // a partir del estatus (en_uso→tienda, asignado→usuario, en_bodega→bodega,
        // garantia/baja→ATI de la tienda).
        return [
            'serie'                 => trim($_POST['serie'] ?? ''),
            'codigo_barras'         => trim($_POST['codigo_barras'] ?? '') ?: null,
            'num_activo'            => trim($_POST['num_activo'] ?? '') ?: null,
            'modelo_id'             => !empty($_POST['modelo_id'])             ? (int) $_POST['modelo_id']             : null,
            'status'                => Activo::normalizarStatus($_POST['status'] ?? 'en_bodega'),
            'procedencia_tienda_id' => !empty($_POST['procedencia_tienda_id']) ? (int) $_POST['procedencia_tienda_id'] : null,
            'tienda_uso_id'         => !empty($_POST['tienda_uso_id'])         ? (int) $_POST['tienda_uso_id'] : null,
        ];
    }

    private function cargarCatalogos(): array
    {
        return [
            (new Dispositivo($this->db))->leerTodos(),
            (new Tienda($this->db))->obtenerTodas(),
            (new Plaza($this->db))->obtenerTodas(),
            (new Region($this->db))->obtenerTodas(),
            (new Negocio($this->db))->obtenerTodos(),
            (new Usuario($this->db))->obtenerTodos(),
            (new Bodega($this->db))->obtenerTodas(),
        ];
    }

    private function generarExcel(array $activos): never
    {
        $filename = 'inventario_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

        fputcsv($out, ['ID', 'Serie', 'Código de barras', 'N° de activo', 'Dispositivo', 'Modelo', 'Área', 'Status',
                       'Plaza', 'Negocio', 'Stock (tipo)', 'Técnico/Bodega',
                       'Tienda en uso', 'Procedencia', 'Fecha alta']);

        foreach ($activos as $a) {
            fputcsv($out, [
                $a['id'],
                $a['serie'],
                $a['codigo_barras'] ?? '',
                $a['num_activo'] ?? '',
                $a['dispositivo_nombre'] ?? '',
                $a['modelo_nombre'] ?? '',
                $a['area_nombre'] ?? '',
                $a['status'],
                $a['plaza_nombre'] ?? '',
                $a['negocio_nombre'] ?? '',
                $a['stock_tipo'] ?? '',
                match ($a['stock_tipo'] ?? '') {
                    'usuario' => $a['usuario_nombre'] ?? '',
                    'tienda'  => $a['tienda_stock_nombre'] ?? '',
                    default   => $a['bodega_nombre'] ?? '',
                },
                $a['tienda_uso_nombre'] ?? '',
                $a['procedencia_nombre'] ?? '',
                $a['fecha_alta'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    private function responderOk(string $msg, string $redirect, bool $esAjax): never
    {
        if ($esAjax) $this->jsonSalida(['success' => true, 'message' => $msg]);
        $_SESSION['success'] = $msg;
        $this->redirigir($redirect);
    }

    private function responderError(string $msg, string $redirect, bool $esAjax): never
    {
        if ($esAjax) $this->jsonSalida(['success' => false, 'message' => $msg], 400);
        $_SESSION['error'] = $msg;
        $this->redirigir($redirect);
    }

    private function jsonSalida(array $datos, int $codigo = 200): never
    {
        if (ob_get_length()) ob_end_clean();
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos);
        exit;
    }

    private function redirigir(string $url): never
    {
        if ($this->esPeticionAjax()) {
            $exito   = empty($_SESSION['error']);
            $mensaje = $_SESSION['success'] ?? $_SESSION['error'] ?? '';
            unset($_SESSION['success'], $_SESSION['error']);
            $this->jsonSalida([
                'success'  => $exito,
                'message'  => $mensaje,
                'redirect' => $url,
            ], $exito ? 200 : 400);
        }

        header("Location: {$url}");
        exit;
    }

    /** Detecta si la petición viene de fetch()/AJAX (para responder JSON en vez de redirigir) */
    private function esPeticionAjax(): bool
    {
        $conAjaxHeader = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $aceptaJson = isset($_SERVER['HTTP_ACCEPT'])
            && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

        return $conAjaxHeader || $aceptaJson;
    }
}