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
        } else {
            $negocios       = [];
            $plazas         = [];
            $regiones       = [];
            $usuariosFiltro = [];
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

        $activoModel = new Activo($this->db);
        $stockModel  = new Stock($this->db);
        $datos       = $this->datosDesdePost();
        $tipo        = Permisos::tipo();
        $plazaId     = max(0, (int) ($_POST['plaza_id'] ?? $_SESSION['usuario']['plaza_id'] ?? 0));

        $usuarioPlazas = $_SESSION['usuario']['plazas'] ?? [];
        if ($usuarioPlazas) {
            $plazaIds = array_column($usuarioPlazas, 'id');
            if ($plazaId <= 0 || !in_array($plazaId, $plazaIds, true)) {
                $plazaId = (int) $usuarioPlazas[0]['id'];
            }
        }

        // ── Default de asignado y bodega OXXO para la creación ──────────────
        $statusPost       = Activo::normalizarStatus($_POST['status'] ?? 'en_bodega');
        $stockDestino     = trim((string) ($_POST['stock_destino'] ?? ''));
        $stockDestinoDef  = trim((string) ($_POST['stock_destino_default'] ?? ''));

        if ($statusPost === 'asignado' && empty($_POST['asignado_usuario_id'])) {
            $_POST['asignado_usuario_id'] = Permisos::idUsuario();
        }

        if (empty($stockDestino) && !empty($stockDestinoDef)) {
            $stockDestino = $stockDestinoDef;
        }

        // Determinar negocio seleccionado desde el formulario (si aplica)
        $negocioIdPost = (int) ($_POST['negocio_id'] ?? 0);

        // --- VALIDACIÓN CRÍTICA: plaza_id y negocio_id deben ser consistentes ---
        // Si el formulario llegó desincronizado (ej. el <select> de negocio se
        // cambió visualmente pero el campo oculto plaza_id no se actualizó por
        // algún motivo del navegador), NO confiamos en plaza_id a ciegas.
        // Verificamos que la plaza recibida realmente pertenezca al negocio
        // recibido; si no, se corrige usando las plazas del usuario.
        if ($negocioIdPost > 0 && $plazaId > 0) {
            $plazaCheck = (new Plaza($this->db))->obtenerPorId($plazaId);
            if ($plazaCheck && (int) ($plazaCheck['negocio_id'] ?? 0) !== $negocioIdPost) {
                $plazaCorrecta = null;
                foreach ($usuarioPlazas as $p) {
                    $pFull = (new Plaza($this->db))->obtenerPorId((int) $p['id']);
                    if ($pFull && (int) ($pFull['negocio_id'] ?? 0) === $negocioIdPost) {
                        $plazaCorrecta = $pFull;
                        break;
                    }
                }
                if ($plazaCorrecta) {
                    $plazaId = (int) $plazaCorrecta['id'];
                } else {
                    $_SESSION['error'] = 'La plaza enviada no corresponde al negocio seleccionado. Verifica tu selección e inténtalo de nuevo.';
                    $this->redirigir('index.php?action=crear&negocio_id=' . $negocioIdPost);
                }
            }
        }

        $negocioSel = (new Negocio($this->db))->obtenerPorId($negocioIdPost);
        $bodegaNegocioPost = null;
        if ($negocioSel) {
            $bodegaNegocioPost = (new Bodega($this->db))->obtenerPorPlazaYNegocio($plazaId, $negocioSel['nombre']);
        }

        $bodegaOxxo = (new Bodega($this->db))->obtenerPorPlazaYNegocio($plazaId, 'oxxo');
        if (in_array($tipo, ['admin', 'coordinador']) && $statusPost === 'en_bodega' && empty($stockDestino)) {
            // Priorizar bodega por negocio+plaza, luego OXXO
            if (!empty($bodegaNegocioPost)) {
                $stockDestino = 'bodega_' . $bodegaNegocioPost['id'];
            } elseif (!empty($bodegaOxxo)) {
                $stockDestino = 'bodega_' . $bodegaOxxo['id'];
            }
        }

        // --- Determinar la bodega seleccionada/default de forma centralizada ---
        $bodegaSeleccionada = null;
        // Si el admin/coordinador especificó un destino explícito (stock_destino)
        if (!empty($stockDestino) && str_starts_with($stockDestino, 'bodega_')) {
            $id_dest_bodega = (int) explode('_', $stockDestino, 2)[1];
            $cand = (new Bodega($this->db))->obtenerPorId($id_dest_bodega);
            if ($cand) {
                // coordinador: validar acceso a plaza
                if ($tipo === 'coordinador') {
                    $plazasDeBodega = (new Bodega($this->db))->obtenerPlazasDeBodega($id_dest_bodega);
                    $plazaIdsAcceso = array_map('intval', array_column($plazasDeBodega, 'id'));
                    if (!in_array((int)$plazaId, $plazaIdsAcceso, true)) {
                        $_SESSION['error'] = 'La bodega seleccionada no pertenece a la plaza elegida.';
                        $this->redirigir('index.php?action=crear&negocio_id=' . $negocioIdPost . '&plaza_id=' . $plazaId);
                    }
                }
                $bodegaSeleccionada = $cand;
            }
        }

        // Si no hay bodega seleccionada por el usuario, tratar de obtener por negocio+plaza
        if ($bodegaSeleccionada === null) {
            $negocioIdPost = (int) ($_POST['negocio_id'] ?? 0);
            if ($negocioIdPost > 0) {
                $negocioSel = (new Negocio($this->db))->obtenerPorId($negocioIdPost);
                if ($negocioSel) {
                    $cand = (new Bodega($this->db))->obtenerPorPlazaYNegocio($plazaId, $negocioSel['nombre']);
                    if ($cand) $bodegaSeleccionada = $cand;
                }
            }
        }

        // Si aún no hay bodega, usar la bodega asignada a la plaza
        if ($bodegaSeleccionada === null) {
            $cand = (new Bodega($this->db))->obtenerPorPlaza($plazaId);
            if ($cand) $bodegaSeleccionada = $cand;
        }

        // Por último intentar OXXO
        if ($bodegaSeleccionada === null && !empty($bodegaOxxo)) {
            $bodegaSeleccionada = $bodegaOxxo;
        }

        if ($tipo === 'fs') {
            // fs: asignado/en_uso → stock personal (scopeado a la plaza/negocio elegido) | en_bodega/garantia/baja → bodega de la plaza seleccionada
            if ($statusPost === 'en_uso' || $statusPost === 'asignado') {
                $stock = $stockModel->obtenerPorUsuario(Permisos::idUsuario(), $plazaId);
            } else {
                $bodega = (new Bodega($this->db))->obtenerPorPlaza($plazaId);
                $stock  = $bodega ? $stockModel->obtenerPorBodega((int)$bodega['id']) : null;
                if ($stock && isset($stock['bodega_id']) && (int)$stock['bodega_id'] !== (int)($bodega['id'] ?? 0)) {
                    $stockModel->crearParaBodega((int)($bodega['id'] ?? 0));
                    $stock = $stockModel->obtenerPorBodega((int)($bodega['id'] ?? 0));
                }
            }
            $datos['stock_id'] = $stock ? $stock['id'] : null;

        } elseif ($tipo === 'ati') {
            // ati: 'asignado' SÍ puede dirigirse a otro usuario de su plaza (tiene
            // selector visible en el formulario, a diferencia de fs). 'en_uso'
            // se queda en el stock personal del propio ati (sigue siendo su equipo,
            // solo que desplegado en una tienda). 'en_bodega'/'garantia'/'baja'
            // van a la bodega de la plaza seleccionada.
            if ($statusPost === 'asignado') {
                $asignadoUsuarioId = (int) ($_POST['asignado_usuario_id'] ?? 0) ?: Permisos::idUsuario();

                if ($asignadoUsuarioId !== Permisos::idUsuario()) {
                    $usuarioAsignado = (new Usuario($this->db))->obtenerPorId($asignadoUsuarioId);
                    if (!$usuarioAsignado || !(new Usuario($this->db))->perteneceAPlaza($asignadoUsuarioId, $plazaId)) {
                        $_SESSION['error'] = 'Solo puedes asignar activos a usuarios de la plaza seleccionada.';
                        $this->redirigir('index.php?action=crear&negocio_id=' . $negocioIdPost . '&plaza_id=' . $plazaId);
                    }
                }

                $stock = $stockModel->obtenerPorUsuario($asignadoUsuarioId, $plazaId);
            } elseif ($statusPost === 'en_uso') {
                $stock = $stockModel->obtenerPorUsuario(Permisos::idUsuario(), $plazaId);
            } else {
                $bodega = (new Bodega($this->db))->obtenerPorPlaza($plazaId);
                $stock  = $bodega ? $stockModel->obtenerPorBodega((int)$bodega['id']) : null;
                if ($stock && isset($stock['bodega_id']) && (int)$stock['bodega_id'] !== (int)($bodega['id'] ?? 0)) {
                    $stockModel->crearParaBodega((int)($bodega['id'] ?? 0));
                    $stock = $stockModel->obtenerPorBodega((int)($bodega['id'] ?? 0));
                }
            }
            $datos['stock_id'] = $stock ? $stock['id'] : null;

        } elseif ($statusPost === 'asignado' && !empty($_POST['asignado_usuario_id'])) {
            $asignadoUsuarioId = (int) $_POST['asignado_usuario_id'];
            if ($tipo !== 'admin') {
                $usuarioAsignado = (new Usuario($this->db))->obtenerPorId($asignadoUsuarioId);
                if (!$usuarioAsignado || !(new Usuario($this->db))->perteneceAPlaza($asignadoUsuarioId, $plazaId)) {
                    $_SESSION['error'] = 'Solo puedes asignar activos a usuarios de la plaza seleccionada.';
                    $this->redirigir('index.php?action=crear&negocio_id=' . $negocioIdPost . '&plaza_id=' . $plazaId);
                }
            }
            // Asignado a usuario específico (stock scopeado a la plaza/negocio elegido)
            $stock = $stockModel->obtenerPorUsuario($asignadoUsuarioId, $plazaId);
            $datos['stock_id'] = $stock ? $stock['id'] : null;

        } elseif (!empty($stockDestino)) {
            // Admin/coordinador eligió destino manual o se aplicó OXXO por defecto
            [$tipo_dest, $id_dest] = explode('_', $stockDestino, 2);
            if ($tipo_dest === 'bodega') {
                if ($tipo === 'coordinador') {
                    // Verificar que la bodega tenga acceso a la plaza seleccionada
                    $bodegasModel = new Bodega($this->db);
                    $plazasDeBodega = $bodegasModel->obtenerPlazasDeBodega((int)$id_dest);
                    $plazaIdsAcceso = array_map('intval', array_column($plazasDeBodega, 'id'));
                    if (!in_array((int)$plazaId, $plazaIdsAcceso, true)) {
                        $_SESSION['error'] = 'La bodega seleccionada no pertenece a la plaza elegida.';
                        $this->redirigir('index.php?action=crear&negocio_id=' . $negocioIdPost . '&plaza_id=' . $plazaId);
                    }
                }
                $stock = $stockModel->obtenerPorBodega((int)$id_dest);
                // Asegurar que el stock devuelto pertenece a la bodega esperada
                if ($stock && isset($stock['bodega_id']) && (int)$stock['bodega_id'] !== (int)$id_dest) {
                    // Intentar crear/recuperar el stock correcto para esa bodega
                    $stockModel->crearParaBodega((int)$id_dest);
                    $stock = $stockModel->obtenerPorBodega((int)$id_dest);
                }
            } else {
                $stock = $stockModel->obtenerPorUsuario((int)$id_dest, $plazaId);
            }
            $datos['stock_id'] = $stock ? $stock['id'] : null;

        } else {
            // Por defecto: bodega de la plaza seleccionada
            $bodega = (new Bodega($this->db))->obtenerPorPlaza($plazaId);
            if ($bodega) {
                $stock = $stockModel->obtenerPorBodega((int)$bodega['id']);
                $datos['stock_id'] = $stock ? $stock['id'] : null;
            }
        }

        if (empty($datos['stock_id'])) {
            $_SESSION['error'] = 'No se pudo determinar el stock de destino. Verifique que su plaza tenga una bodega configurada.';
            $this->redirigir('index.php?action=crear&negocio_id=' . $negocioIdPost . '&plaza_id=' . $plazaId);
        }

        // ── Procesamiento de Imágenes ─────────────────────────────────────
        $fotos = \App\Helpers\ImageHelper::procesarYSubirImagenes(
            ROOT_PATH . '/public/uploads',
            null, // El ID se puede actualizar luego si es necesario, o dejarlo así
            []
        );
        $datos = array_merge($datos, $fotos);

        // IMPORTANTE: preservar negocio_id y plaza_id en la redirección.
        // Si no se preservan, crear() vuelve a cargar el negocio/plaza por
        // defecto (el primero de la lista, típicamente OXXO), y el usuario
        // sigue registrando activos "asignado" ahí sin darse cuenta, aunque
        // haya seleccionado otro negocio en el registro anterior.
        $redirectCrear = 'index.php?action=crear&negocio_id=' . $negocioIdPost . '&plaza_id=' . $plazaId;

        if ($activoModel->crear($datos)) {
            $_SESSION['success'] = 'Activo registrado con éxito.';
            $this->redirigir($redirectCrear);
        } else {
            $_SESSION['error'] = 'Error al guardar. Verifique que la serie no esté duplicada.';
            $this->redirigir($redirectCrear);
        }
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

        $id          = (int) ($_POST['id'] ?? 0);
        $activoModel = new Activo($this->db);
        $activo      = $activoModel->obtenerPorId($id);

        // Verificar que fs/ati solo editan sus propios activos
        if (Permisos::esFs() || Permisos::tipo() === 'ati') {
            if (!$activo || !Permisos::puedeEditarActivoConcreto($activo)) {
                $_SESSION['error'] = 'Solo puedes editar activos de tu propio stock.';
                $this->redirigir('index.php');
            }
            $datos = array_merge(['id' => $id], $this->datosDesdePost());

            if (Permisos::tipo() === 'ati'
                && ($datos['status'] ?? '') === 'asignado'
                && !empty($_POST['asignado_usuario_id'])
            ) {
                // ati SÍ puede reasignar el activo a otro usuario de su plaza al editar
                // (igual que al crearlo), validando que pertenezca a esa misma plaza.
                $asignadoUsuarioId = (int) $_POST['asignado_usuario_id'];
                $plazaIdActivo     = (int) ($activo['plaza_id'] ?? Permisos::plazaId());

                if ($asignadoUsuarioId !== Permisos::idUsuario()) {
                    $usuarioAsignado = (new Usuario($this->db))->obtenerPorId($asignadoUsuarioId);
                    if (!$usuarioAsignado || !(new Usuario($this->db))->perteneceAPlaza($asignadoUsuarioId, $plazaIdActivo)) {
                        $_SESSION['error'] = 'Solo puedes asignar activos a usuarios de la plaza seleccionada.';
                        $this->redirigir("index.php?action=editar&id={$id}");
                    }
                }

                $stockNuevo = (new Stock($this->db))->obtenerPorUsuario($asignadoUsuarioId, $plazaIdActivo);
                $datos['stock_id'] = $stockNuevo ? $stockNuevo['id'] : $activo['stock_id'];
            } else {
                // fs siempre, y ati para estatus distinto de 'asignado': no cambian el stock_id
                $datos['stock_id'] = $activo['stock_id'];
            }
        } else {
            $datos = array_merge(['id' => $id], $this->datosDesdePost());
        }

        // ── Blindaje: si el nuevo status es 'en_bodega', el stock JAMÁS debe
        //    seguir apuntando al stock personal de un técnico. El campo oculto
        //    stock_id del formulario de edición no se actualiza en JS para este
        //    caso, así que lo resolvemos aquí de forma autoritativa. ─────────
        if (($datos['status'] ?? '') === 'en_bodega') {
            $plazaIdActivo = (int) ($activo['plaza_id'] ?? Permisos::plazaId());
            $stockModel    = new Stock($this->db);
            $bodegaModel   = new Bodega($this->db);
            $bodegaDestino = null;

            // Respetar un destino explícito si el admin/coordinador lo envió
            $stockDestinoPost = trim((string) ($_POST['stock_destino'] ?? ''));
            if ($stockDestinoPost !== '' && str_starts_with($stockDestinoPost, 'bodega_')) {
                $idBodegaPost  = (int) explode('_', $stockDestinoPost, 2)[1];
                $bodegaDestino = $bodegaModel->obtenerPorId($idBodegaPost);
            }

            // Si no hay destino explícito, usar la bodega de la plaza del activo
            if (!$bodegaDestino) {
                $bodegaDestino = $bodegaModel->obtenerPorPlaza($plazaIdActivo);
            }

            if ($bodegaDestino) {
                $stockBodega = $stockModel->obtenerPorBodega((int) $bodegaDestino['id']);
                if ($stockBodega) {
                    $datos['stock_id'] = $stockBodega['id'];
                }
            }
        }

        // ── Procesamiento de Imágenes ─────────────────────────────────────
        $fotos = \App\Helpers\ImageHelper::procesarYSubirImagenes(
            ROOT_PATH . '/public/uploads',
            $id,
            $activo ?: []
        );
        
        // Solo actualizar fotos que realmente se subieron
        foreach ($fotos as $key => $val) {
            if ($val !== null) $datos[$key] = $val;
        }

        if ($activoModel->actualizar($datos)) {
            $_SESSION['success'] = 'Activo actualizado con éxito.';
            $this->redirigir('index.php');
        } else {
            $_SESSION['error'] = 'Error al actualizar. Verifique que la serie no esté duplicada.';
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
        return [
            'serie'                 => trim($_POST['serie'] ?? ''),
            'placa'                 => trim($_POST['placa'] ?? '') ?: null,
            'modelo_id'             => !empty($_POST['modelo_id'])             ? (int) $_POST['modelo_id']             : null,
            'status'                => Activo::normalizarStatus($_POST['status'] ?? 'en_bodega'),
            'procedencia_tienda_id' => !empty($_POST['procedencia_tienda_id']) ? (int) $_POST['procedencia_tienda_id'] : null,
            'tienda_uso_id'         => !empty($_POST['tienda_uso_id'])         ? (int) $_POST['tienda_uso_id'] : null,
            'stock_id'              => !empty($_POST['stock_id'])              ? (int) $_POST['stock_id']              : null,
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

        fputcsv($out, ['ID', 'Serie', 'Placa', 'Dispositivo', 'Modelo', 'Área', 'Status',
                       'Plaza', 'Negocio', 'Stock (tipo)', 'Técnico/Bodega',
                       'Tienda en uso', 'Procedencia', 'Fecha alta']);

        foreach ($activos as $a) {
            fputcsv($out, [
                $a['id'],
                $a['serie'],
                $a['placa'] ?? '',
                $a['dispositivo_nombre'] ?? '',
                $a['modelo_nombre'] ?? '',
                $a['area_nombre'] ?? '',
                $a['status'],
                $a['plaza_nombre'] ?? '',
                $a['negocio_nombre'] ?? '',
                $a['stock_tipo'] ?? '',
                $a['stock_tipo'] === 'usuario' ? ($a['usuario_nombre'] ?? '') : ($a['bodega_nombre'] ?? ''),
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