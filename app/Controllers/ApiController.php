<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\AppBuild;
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
use App\Models\Movimiento;
use App\Models\SolicitudTraslado;
use App\Models\InventarioBodega;
use App\Models\ProyectoRentec;
use App\Services\ActivoGuardado;
use App\Services\MovimientoService;
use App\Services\TrasladoService;
use App\Helpers\ImageHelper;
use App\Helpers\Permisos;

class ApiController
{
    /**
     * Fotos del equipo que SALE en un reemplazo: nombre de la parte multipart
     * que manda la app → clave con la que ActivoGuardado la pasa al servicio.
     * Son las mismas tres que lleva cualquier activo (equipo, serie y código de
     * barras): el que sale merece el mismo respaldo fotográfico que el que entra.
     */
    private const FOTOS_SALIDA = [
        'foto_equipo_salida' => 'salida_foto_equipo',
        'foto_serie_salida'  => 'salida_foto_serie',
        'foto_activo_salida' => 'salida_foto_activo',
    ];

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
                'puedeRecibirRentec'    => Permisos::puedeRecibirRentec(),
                'puedeTransferir'       => Permisos::puedeTransferir(),
                'puedeCrearSolicitudTraslado' => Permisos::puedeCrearSolicitudTraslado(),
                'puedeAprobarTraslados' => Permisos::puedeAprobarTraslados(),
                'puedeVerTraslados'     => Permisos::puedeVerTraslados(),
                'plazaId'               => Permisos::plazaId(),
                'plazasIds'             => Permisos::plazasIds(),
            ],
            // Navegación por módulos (fuente única: Permisos::modulos()).
            // modulosApp() excluye los que son solo del panel web (ej. 'apk').
            'modulos' => Permisos::modulosApp(),
            // Compat con los APK ya instalados (navegación por "vista"). NO se
            // puede borrar hasta que todos los equipos estén en la versión que
            // navega sólo por 'modulo': un teléfono sin actualizar se queda sin
            // listado. Borrar junto con la rama `vista` de listarActivos().
            'vistasDisponibles' => $this->vistasDisponiblesParaTipo($tipo),
        ]);
    }

    // GET ?action=resumenDashboard  — KPIs para la pantalla principal de la app.
    public function resumenDashboard(): void
    {
        $resumen = (new Activo($this->db))->resumen(Permisos::filtrosScope());
        $movs    = (new Movimiento($this->db))->listar(Permisos::filtrosHistorial(), 1, 8)['movimientos'] ?? [];

        $pendTraslados = 0;
        if (Permisos::puedeAprobarTraslados()) {
            $pendTraslados = count((new SolicitudTraslado($this->db))->pendientesParaResolver(
                Permisos::idUsuario(), Permisos::misPlazas(),
                in_array(Permisos::tipo(), ['coordinador', 'admin'], true),
                in_array(Permisos::tipo(), ['ati', 'admin'], true),
                Permisos::esAdmin()));
        }

        // Conteo por módulo visible (para las tarjetas del dashboard).
        $activoModel = new Activo($this->db);
        $porModulo = [];
        foreach (Permisos::modulosApp() as $m) {
            $clave = $m['clave'];
            if (in_array($clave, ['dashboard', 'consulta', 'usuarios'], true)) continue;
            $porModulo[$clave] = $activoModel->resumen(Permisos::filtrosModulo($clave))['total'];
        }

        $salida = [
            'total'            => $resumen['total'],
            'por_status'       => $resumen['por_status'],
            'por_dispositivo'  => $resumen['por_dispositivo'],
            'por_plaza'        => $resumen['por_plaza'],
            'por_modulo'       => $porModulo,
            'traslados_pendientes' => $pendTraslados,
            'movimientos'      => array_map(fn($m) => [
                'evento'      => $m['evento'],
                'creado_en'   => $m['creado_en'],
                'equipo'      => trim(($m['eq_dispositivo'] ?? '') . ' ' . trim(($m['eq_marca'] ?? '') . ' ' . ($m['eq_modelo'] ?? ''))),
                'serie'       => $m['eq_serie'] ?? $m['eq_codigo_barras'] ?? $m['eq_num_activo'] ?? null,
            ], $movs),
        ];

        // Bloque técnico para admin (salud del sistema y catálogo).
        if (Permisos::esAdmin()) {
            $solPorEstado = array_fill_keys(array_keys(SolicitudTraslado::ESTADOS), 0);
            foreach ($this->db->query("SELECT estado, COUNT(*) n FROM solicitud_traslado GROUP BY estado") as $r) {
                $solPorEstado[$r['estado']] = (int) $r['n'];
            }
            // activo.modelo_id es NOT NULL, así que el viejo "activos sin modelo"
            // siempre daba 0. Lo que sí puede pasar es que apunte a un modelo
            // borrado o sin marca: eso es lo que vale la pena vigilar.
            $sinModelo = (int) $this->db->query(
                "SELECT COUNT(*) FROM activo a
                 LEFT JOIN modelo m ON m.id = a.modelo_id
                 WHERE m.id IS NULL OR m.marca_id IS NULL"
            )->fetchColumn();
            $salida['tecnico'] = [
                'usuarios'             => count((new Usuario($this->db))->obtenerTodos()),
                'tiendas'              => count((new Tienda($this->db))->obtenerTodas()),
                'modelos'             => count((new Modelo($this->db))->obtenerTodos()),
                'bodegas'             => count((new Bodega($this->db))->obtenerTodas()),
                'solicitudes_por_estado' => $solPorEstado,
                'activos_sin_modelo'  => $sinModelo,
            ];
        }

        $this->json($salida);
    }

    // GET ?action=obtenerUltimaVersionApp
    // Para la sección "Actualizar app" del dashboard — visible a TODOS los
    // roles, no solo admin (quien sube el APK sigue siendo solo admin, desde
    // el panel web; esto es de solo lectura). Compara contra lo que ya trae
    // instalado la app (versionCode) para decidir si hay algo más nuevo.
    public function obtenerUltimaVersionApp(): void
    {
        $ultima = (new AppBuild($this->db))->ultima();
        if (!$ultima) { $this->json(['hay_version' => false]); return; }

        $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
            . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

        $this->json([
            'hay_version'  => true,
            'version_code' => (int) $ultima['version_code'],
            'version_name' => $ultima['version_name'],
            'notas'        => $ultima['notas'],
            'creado_en'    => $ultima['creado_en'],
            'url_descarga' => "{$base}/index.php?controller=apk&action=descargar",
        ]);
    }

    // ── Activos ───────────────────────────────────────────────────────────────

    public function listarActivos(): void
    {
        $tipo    = Permisos::tipo();
        $plazaId = Permisos::plazaId();

        $statusFiltro = $_GET['status'] ?? null;
        $statusFiltro = ($statusFiltro !== null && $statusFiltro !== '')
            ? Activo::normalizarStatus((string) $statusFiltro)
            : null;

        $comun = [
            'dispositivo_id' => $_GET['dispositivo_id'] ?? null,
            'status'         => $statusFiltro,
            'busqueda'       => $_GET['busqueda']       ?? null,
        ];

        $modulo = trim((string) ($_GET['modulo'] ?? ''));

        if ($modulo !== '') {
            // ── Navegación nueva por módulos ──────────────────────────────────
            if (!Permisos::moduloPermitido($modulo)) {
                $this->json(['success' => false, 'message' => 'No tienes acceso a este módulo.'], 403);
            }
            $filtros = array_merge(Permisos::filtrosModulo($modulo), $comun);
            $filtros = $this->aplicarAcotadores($filtros, $modulo);
            $vista   = $modulo;
        } else {
            // ── Compat: navegación por "vista" ───────────────────────────────
            $vista = $_GET['vista'] ?? $this->vistaDefaultParaTipo($tipo);
            $vista = $this->vistaPermitida($vista, $tipo);

            $filtros = array_merge(Permisos::filtrosScope(), $comun, ['solo_bodega' => false]);

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
        }

        $pagina    = max(1, (int) ($_GET['pagina']     ?? 1));
        $porPagina = max(1, (int) ($_GET['por_pagina'] ?? 20));

        $resultado = (new Activo($this->db))->obtenerTodosFiltrado($filtros, $pagina, $porPagina);

        // Agregar flags de permiso por cada activo, para que la app sepa qué botones mostrar
        $resultado['activos'] = array_map(function ($a) {
            $a['puedeEditar']   = Permisos::puedeEditarActivoConcreto($a);
            $a['puedeEliminar'] = Permisos::puedeEliminarActivo($a);
            return $a;
        }, $resultado['activos'] ?? []);

        $resultado['vista']  = $vista;
        $resultado['modulo'] = $modulo ?: null;
        $resultado['moduloEditable'] = $modulo !== '' && Permisos::moduloEditable($modulo);

        $this->json($resultado);
    }

    /**
     * Acotadores opcionales del listado por módulo (tienda_id, plaza_id,
     * region_id, dispositivo_id), validados contra el scope del rol. Admin
     * puede acotar a cualquier plaza; el resto sólo a las suyas.
     */
    private function aplicarAcotadores(array $filtros, string $modulo): array
    {
        $misPlazas = Permisos::misPlazas();
        $esAdmin   = Permisos::esAdmin();

        $tiendaId = (int) ($_GET['tienda_id'] ?? 0);
        if ($tiendaId > 0) {
            $tienda = (new Tienda($this->db))->obtenerPorId($tiendaId);
            if ($tienda && ($esAdmin || in_array((int) $tienda['plaza_id'], $misPlazas, true))) {
                $filtros['tienda_id'] = $tiendaId;
            }
        }

        $plazaGet = (int) ($_GET['plaza_id'] ?? 0);
        if ($plazaGet > 0 && ($esAdmin || in_array($plazaGet, $misPlazas, true))) {
            $filtros['plaza_id'] = $plazaGet;
        }

        $regionGet = (int) ($_GET['region_id'] ?? 0);
        if ($regionGet > 0 && ($esAdmin || $this->regionEnAlcance($regionGet, $misPlazas))) {
            $filtros['region_id'] = $regionGet;
        }

        // Acota al stock personal de un ingeniero concreto (lo manda la app al
        // elegir a alguien en el módulo "Stock PFS"). Sólo en los módulos que
        // listan stock de OTRAS personas: en 'mi_stock' el dueño es siempre el
        // propio usuario y aceptarlo aquí dejaría a un pfs ver stock ajeno.
        $usuarioGet = (int) ($_GET['usuario_id'] ?? 0);
        if ($usuarioGet > 0 && in_array($modulo, ['stock_pfs', 'ati'], true)) {
            if (!$esAdmin && !$this->usuarioEnAlcance($usuarioGet, $misPlazas)) {
                $this->json(['success' => false, 'message' => 'Ese usuario no pertenece a tu plaza.'], 403);
            }
            $filtros['stock_usuario_id'] = $usuarioGet;
        }

        return $filtros;
    }

    /** ¿La región contiene al menos una de las plazas del actor? */
    private function regionEnAlcance(int $regionId, array $misPlazas): bool
    {
        if (!$misPlazas) return false;
        $ph = implode(',', array_fill(0, count($misPlazas), '?'));
        $st = $this->db->prepare("SELECT COUNT(*) FROM plaza WHERE region_id = ? AND id IN ({$ph})");
        $st->execute(array_merge([$regionId], $misPlazas));
        return (int) $st->fetchColumn() > 0;
    }

    /** ¿El usuario objetivo pertenece a alguna de las plazas del actor? */
    private function usuarioEnAlcance(int $usuarioId, array $misPlazas): bool
    {
        $um = new Usuario($this->db);
        foreach ($misPlazas as $pid) {
            if ($um->perteneceAPlaza($usuarioId, (int) $pid)) return true;
        }
        return false;
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

    // GET ?action=resolverSerie&serie=<q>[&tienda_id=X]
    // Ayuda al formulario "Movimiento en tienda": dada una serie/código exacto,
    // dice si el activo ya está en el stock personal del usuario (instalación =
    // mover ese) o instalado en la tienda indicada (retiro). Alcance global.
    public function resolverSerie(): void
    {
        $q = trim((string) ($_GET['serie'] ?? $_GET['q'] ?? ''));
        if ($q === '') {
            $this->json(['success' => false, 'message' => 'Falta la serie o código.'], 400);
        }
        $tiendaId = (int) ($_GET['tienda_id'] ?? 0);

        $filas = (new Activo($this->db))
            ->obtenerTodosFiltrado(['identificador_exacto' => $q], 1, 10)['activos'] ?? [];

        if (!$filas) {
            $this->json(['encontrado' => false, 'activo' => null, 'en_mi_stock' => false, 'en_esta_tienda' => false]);
        }

        if (count($filas) > 1) {
            $this->json([
                'encontrado'    => true,
                'activo'        => null,
                'en_mi_stock'   => false,
                'en_esta_tienda'=> false,
                'coincidencias' => array_map(fn($a) => [
                    'id' => (int) $a['id'], 'serie' => $a['serie'] ?? null,
                    'codigo_barras' => $a['codigo_barras'] ?? null,
                    'modelo_nombre' => $a['modelo_nombre'] ?? null,
                    'status' => $a['status'] ?? null,
                    'ubicacion_corta' => $this->ubicacionCorta($a),
                ], $filas),
            ]);
        }

        $a  = $filas[0];
        $yo = Permisos::idUsuario();
        $enMiStock = ($a['stock_tipo'] ?? '') === 'usuario'
            && (int) ($a['usuario_stock_id'] ?? 0) === $yo;
        $enEstaTienda = $tiendaId > 0
            && ($a['stock_tipo'] ?? '') === 'tienda'
            && (int) ($a['tienda_stock_id'] ?? 0) === $tiendaId
            && ($a['status'] ?? '') === 'en_uso';

        $this->json([
            'encontrado'     => true,
            'activo'         => $a,
            'en_mi_stock'    => $enMiStock,
            'en_esta_tienda' => $enEstaTienda,
            // Para RENTEC: si ya fue dado de alta en bodega, la fase de
            // instalación debe MOVERLO (actualizar) en vez de darlo de alta
            // otra vez (evita duplicar el activo).
            'en_bodega'          => ($a['status'] ?? '') === 'en_bodega',
            'proyecto_rentec_id' => isset($a['proyecto_rentec_id']) ? (int) $a['proyecto_rentec_id'] : null,
            'ubicacion_corta'=> $this->ubicacionCorta($a),
        ]);
    }

    // GET ?action=consultar&q=<serie|codigo_barras|num_activo>
    // Módulo "Consulta": identifica un equipo y devuelve dónde está y su
    // historial. Alcance GLOBAL (cualquier usuario autenticado, sólo lectura):
    // sirve para saber a qué tienda pertenece un activo que te encontraste.
    public function consultar(): void
    {
        $q = trim((string) ($_GET['q'] ?? $_GET['busqueda'] ?? ''));
        if ($q === '') {
            $this->json(['success' => false, 'message' => 'Escribe o escanea una serie o código.'], 400);
        }

        $activoModel = new Activo($this->db);

        // 1) Coincidencia exacta por serie / código de barras / N° de activo.
        $exactos = $activoModel->obtenerTodosFiltrado(['identificador_exacto' => $q], 1, 25)['activos'] ?? [];

        if (count($exactos) === 1) {
            $id     = (int) $exactos[0]['id'];
            $activo = $activoModel->obtenerPorId($id) ?: $exactos[0];
            $this->json([
                'encontrado' => true,
                'activo'     => $activo,
                'ubicacion'  => $this->ubicacionDeActivo($activo),
                'historial'  => (new Movimiento($this->db))->porActivo($id),
            ]);
        }

        // 2) Varias exactas (serie duplicada) o ninguna → lista de coincidencias.
        $filas = $exactos;
        if (!$filas) {
            $filas = $activoModel->obtenerTodosFiltrado(['busqueda' => $q], 1, 10)['activos'] ?? [];
        }
        if (!$filas) {
            $this->json(['encontrado' => false, 'message' => 'Sin coincidencias para «' . $q . '».'], 404);
        }

        $this->json([
            'encontrado'   => true,
            'coincidencias' => array_map(fn($a) => [
                'id'                 => (int) $a['id'],
                'serie'              => $a['serie'] ?? null,
                'codigo_barras'      => $a['codigo_barras'] ?? null,
                'num_activo'         => $a['num_activo'] ?? null,
                'dispositivo_nombre' => $a['dispositivo_nombre'] ?? null,
                'modelo_nombre'      => $a['modelo_nombre'] ?? null,
                'marca_nombre'       => $a['marca_nombre'] ?? null,
                'status'             => $a['status'] ?? null,
                'ubicacion_corta'    => $this->ubicacionCorta($a),
            ], $filas),
        ]);
    }

    /** Ubicación estructurada de un activo, a partir de su fila enriquecida. */
    private function ubicacionDeActivo(array $a): array
    {
        return [
            'stock_tipo'         => $a['stock_tipo'] ?? null,
            'tienda_stock'       => $a['tienda_stock_nombre'] ?? null,
            'bodega'             => $a['bodega_nombre'] ?? null,
            'usuario'            => $a['usuario_nombre'] ?? null,
            'tienda_uso'         => $a['tienda_uso_nombre'] ?? null,
            'procedencia'        => $a['procedencia_nombre'] ?? null,
            'plaza_nombre'       => $a['plaza_nombre'] ?? null,
            'region_nombre'      => $a['region_nombre'] ?? null,
            'negocio_nombre'     => $a['negocio_nombre'] ?? null,
            'status'             => $a['status'] ?? null,
            'resumen'            => $this->ubicacionCorta($a),
        ];
    }

    /** Texto de una línea con la ubicación actual del activo. */
    private function ubicacionCorta(array $a): string
    {
        $tipo = $a['stock_tipo'] ?? '';
        $base = match ($tipo) {
            'tienda'  => 'Tienda ' . ($a['tienda_stock_nombre'] ?? $a['tienda_uso_nombre'] ?? '—'),
            'bodega'  => 'Bodega ' . ($a['bodega_nombre'] ?? '—'),
            'usuario' => 'Con ' . ($a['usuario_nombre'] ?? '—'),
            default   => 'Sin ubicación',
        };
        $plaza = $a['plaza_nombre'] ?? '';
        return $plaza !== '' ? "{$base} · {$plaza}" : $base;
    }

    public function guardarActivo(): void
    {
        $this->requerirPost();
        if (!Permisos::puedeCrearActivo()) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para registrar activos.'], 403);
        }

        // Idempotencia: la app manda una clave única por alta. Un reintento
        // (respuesta perdida tras un alta exitosa) debe devolver el activo ya
        // creado, nunca insertar un duplicado. La clave viaja en el INSERT y el
        // índice UNIQUE la refuerza.
        $idemKey = substr(trim((string) ($_POST['idempotency_key'] ?? '')), 0, 64) ?: null;
        $buscarPorClave = function () use ($idemKey): ?int {
            if ($idemKey === null) return null;
            $st = $this->db->prepare('SELECT id FROM activo WHERE idempotency_key = :k LIMIT 1');
            $st->execute([':k' => $idemKey]);
            $id = $st->fetchColumn();
            return $id ? (int) $id : null;
        };
        if ($ya = $buscarPorClave()) {
            $this->json(['success' => true, 'message' => 'Activo ya registrado.', 'id' => $ya, 'duplicado' => true]);
        }

        $datos   = $this->datosActivoPost();
        if ($idemKey !== null) $datos['idempotency_key'] = $idemKey;
        $plazaId = $this->resolverPlazaId((int) ($_POST['negocio_id'] ?? 0));
        if ($plazaId <= 0) {
            $this->json(['success' => false, 'message' => 'Debes indicar una plaza válida.'], 400);
        }

        // Recibir equipo nuevo en bodega bajo un folio RENTEC es la entrada del
        // material al sistema: sólo coordinador y admin. Instalar lo ya recibido
        // sí lo puede hacer cualquier rol del módulo. Se valida aquí y no sólo
        // en la app para que no dependa de la versión instalada.
        if (!empty($_POST['proyecto_rentec_id'])
            && ($datos['status'] ?? '') === 'en_bodega'
            && !Permisos::puedeRecibirRentec()) {
            $this->json([
                'success' => false,
                'message' => 'Recibir equipo de un proyecto RENTEC es cosa de un coordinador. '
                    . 'Tú sí puedes instalar lo que ya esté recibido.',
            ], 403);
        }

        // ── El equipo ya existe → se MUEVE, no se duplica ────────────────────
        // Un activo que se recoge de una tienda y se escanea en bodega tiene que
        // cambiar de ubicación, no generar un segundo registro. Eso es lo que
        // dejó 61 pares del mismo equipo apareciendo a la vez en tienda y en
        // bodega. Si el identificador ya está en el sistema no se inserta: se
        // responde 409 con el activo encontrado para que la app pregunte, y si
        // el usuario confirma vuelve con mover_existente=1.
        $activoModel = new Activo($this->db);
        $existente = $activoModel->buscarExistente(
            $datos['serie'] ?? null,
            $datos['codigo_barras'] ?? null,
            $datos['num_activo'] ?? null
        );

        if ($existente) {
            if (!filter_var($_POST['mover_existente'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->json([
                    'success'      => false,
                    'ya_existe'    => true,
                    'coincidio_por' => $existente['coincidio_por'],
                    'activo'       => $existente,
                    'ubicacion'    => $this->ubicacionCorta($existente),
                    'message'      => sprintf(
                        'Este equipo ya está registrado (coincide el %s) y hoy está en %s. '
                        . '¿Lo mueves a la nueva ubicación en vez de darlo de alta otra vez?',
                        ['num_activo' => 'N° de activo', 'codigo_barras' => 'código de barras', 'serie' => 'número de serie'][$existente['coincidio_por']],
                        $this->ubicacionCorta($existente)
                    ),
                ], 409);
            }

            // Confirmado: se reusa actualizarActivo, que ya valida permisos
            // sobre el activo concreto y aplica la regla de la firma.
            $this->moverExistente($existente, $datos, $plazaId);
        }

        $fotos = ImageHelper::procesarYSubirImagenes(
            ROOT_PATH . '/public/uploads', null, [], array_keys(self::FOTOS_SALIDA)
        );
        $post = array_merge($_POST, ['plaza_id' => $plazaId], $this->extraerFotosSalida($fotos));
        $datos = array_merge($datos, $fotos);

        $res  = (new ActivoGuardado($this->db))->crear($datos, $post, $this->actorSesion());

        if ($res['ok']) {
            $this->json(['success' => true, 'message' => 'Activo registrado correctamente.', 'id' => $res['id']]);
        }
        // Falló el INSERT: si ya hay un activo con esta clave, fue una carrera
        // entre dos reintentos idénticos → devolvemos el que ganó.
        if ($ganador = $buscarPorClave()) {
            $this->json(['success' => true, 'message' => 'Activo ya registrado.', 'id' => $ganador, 'duplicado' => true]);
        }
        // Las imágenes se subieron antes del INSERT; si el alta no cuajó hay que
        // retirarlas (las del que entra y las del que sale) o quedan huérfanas.
        $this->descartarImagenes(array_merge(
            $fotos,
            array_intersect_key($post, array_flip(self::FOTOS_SALIDA))
        ));
        $this->json(['success' => false, 'message' => $res['error'] ?? 'No se pudo registrar el activo.'], 400);
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

        $fotos = ImageHelper::procesarYSubirImagenes(
            ROOT_PATH . '/public/uploads', $id, $antes ?: [], array_keys(self::FOTOS_SALIDA)
        );
        $post = array_merge(
            $_POST,
            ['plaza_id' => (int) ($antes['plaza_id'] ?? Permisos::plazaId())],
            $this->extraerFotosSalida($fotos)
        );
        foreach ($fotos as $key => $val) {
            if ($val !== null) $datos[$key] = $val;
        }

        $res   = (new ActivoGuardado($this->db))->actualizar($id, $datos, $antes, $post, $this->actorSesion());

        if ($res['ok']) {
            $this->json(['success' => true, 'message' => 'Activo actualizado correctamente.']);
        } else {
            $this->json(['success' => false, 'message' => $res['error'] ?? 'No se pudo actualizar el activo.'], 400);
        }
    }

    /**
     * Mueve un activo que YA existe a la ubicación que traía el alta, en vez de
     * insertar un duplicado. Pasa por ActivoGuardado::actualizar, así que
     * respeta la regla de la firma (devolver a bodega o traspasar a otro
     * ingeniero sigue exigiendo Solicitud) y deja el movimiento en la bitácora.
     *
     * No toca num_activo ni la serie del registro existente: la identidad del
     * equipo es la que ya estaba, lo único que cambia es dónde está.
     */
    private function moverExistente(array $existente, array $datos, int $plazaId): never
    {
        $id = (int) $existente['id'];

        if (!Permisos::puedeEditarActivoConcreto($existente)) {
            $this->json([
                'success'   => false,
                'ya_existe' => true,
                'activo'    => $existente,
                'message'   => 'Este equipo ya existe pero está fuera de tu alcance ('
                    . $this->ubicacionCorta($existente) . '), así que no lo puedes mover.',
            ], 403);
        }

        // Se conserva la identidad del registro existente; del alta sólo se
        // toma el destino (estatus, tienda de uso, procedencia).
        $mover = [
            'serie'                 => $existente['serie'],
            'codigo_barras'         => $existente['codigo_barras'],
            'num_activo'            => $existente['num_activo'],
            'modelo_id'             => $datos['modelo_id'] ?: $existente['modelo_id'],
            'status'                => $datos['status'],
            'tienda_uso_id'         => $datos['tienda_uso_id'] ?? null,
            'procedencia_tienda_id' => $datos['procedencia_tienda_id']
                ?? ($existente['tienda_uso_id'] ?? $existente['procedencia_tienda_id'] ?? null),
        ];

        // Las fotos se procesan aquí y no antes a propósito: si el alta se queda
        // en el 409 sin confirmar, no se escribió ningún archivo que limpiar.
        foreach (ImageHelper::procesarYSubirImagenes(
            ROOT_PATH . '/public/uploads', $id, $existente, ['foto_equipo_salida']
        ) as $campo => $valor) {
            if ($campo !== 'foto_equipo_salida' && $valor !== null) $mover[$campo] = $valor;
        }

        $post = array_merge($_POST, ['plaza_id' => $plazaId]);
        $res  = (new ActivoGuardado($this->db))->actualizar($id, $mover, $existente, $post, $this->actorSesion());

        if ($res['ok']) {
            $this->json([
                'success' => true,
                'id'      => $id,
                'movido'  => true,
                'message' => 'El equipo ya estaba registrado: se movió a la nueva ubicación en vez de duplicarlo.',
            ]);
        }
        $this->json(['success' => false, 'ya_existe' => true, 'activo' => $existente,
                     'message' => $res['error'] ?? 'No se pudo mover el equipo existente.'], 400);
    }

    /**
     * Saca de $fotos las que pertenecen al equipo que sale (las quita de ahí por
     * referencia, para que no se apliquen al que entra) y las devuelve con la
     * clave que espera ActivoGuardado::procesarReemplazo().
     */
    private function extraerFotosSalida(array &$fotos): array
    {
        $salida = [];
        foreach (self::FOTOS_SALIDA as $parte => $clave) {
            if (!empty($fotos[$parte])) $salida[$clave] = $fotos[$parte];
            unset($fotos[$parte]);
        }
        return $salida;
    }

    /**
     * Retira imágenes recién subidas cuando la escritura que las acompañaba
     * falló. ImageHelper::borrarArchivo() se encarga también del thumbnail.
     * Las firmas no viven en uploads/ sino en uploads/firmas/, de ahí $rutaBase.
     */
    private function descartarImagenes(array $nombres, ?string $rutaBase = null): void
    {
        $rutaBase ??= ROOT_PATH . '/public/uploads';
        foreach ($nombres as $nombre) {
            if (is_string($nombre) && $nombre !== '') {
                ImageHelper::borrarArchivo($rutaBase, $nombre);
            }
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

        // Antes este endpoint no validaba nada: cualquier usuario autenticado
        // podía leer la ficha de cualquier otro por id. Alcance: uno mismo
        // siempre, admin siempre, y el resto sólo gente de sus plazas.
        $puede = Permisos::esAdmin()
            || Permisos::idUsuario() === $id
            || $this->usuarioEnAlcance($id, Permisos::misPlazas());
        if (!$puede) {
            $this->json(['success' => false, 'message' => 'No tienes permiso para ver este usuario.'], 403);
        }

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

        // Agregar admins a la lista de usuarios asignables (no están atados a
        // ninguna plaza). obtenerAdmins() en vez de recorrer toda la tabla.
        if ($tipo !== 'admin') {
            $idsYa = array_column($usuarios, 'id');
            foreach ((new Usuario($this->db))->obtenerAdmins() as $u) {
                if (!in_array($u['id'], $idsYa, true)) $usuarios[] = $u;
            }
        }

        // Tiendas acotadas en SQL. Antes se leían las 1,011 y se filtraban en PHP.
        $tiendas = (new Tienda($this->db))->obtenerPorPlazas(
            Permisos::puedeVerTodasPlazas() ? [] : array_column($plazas, 'id')
        );

        $this->json([
            'dispositivos' => $dispositivos,
            'modelos'      => $modelos,
            'tiendas'      => $tiendas,
            'plazas'       => $plazas,
            'regiones'     => $regiones,
            'negocios'     => $negocios,
            'usuarios'     => $usuarios,
            'bodegas'      => $bodegasTodas,
            'status_opts'  => $this->opcionesStatus(),
        ]);
    }

    // GET ?action=obtenerHintsEscaner
    // Pistas para el lector de series. Para código de barras, la regla
    // global: 8 dígitos numéricos. Se cachea en el cliente.
    //
    // ANTES este endpoint también calculaba, por dispositivo, "prefijos
    // frecuentes" a partir de las series ya registradas (el 3 caracteres
    // iniciales más repetido, si cubría suficiente proporción) y los mandaba
    // como filtro obligatorio del escáner. La idea era buena para UPS (SIEMPRE
    // trae 3S/SM de fábrica), pero para el resto de dispositivos el cálculo es
    // una coincidencia estadística de lo YA cargado, no una regla real de la
    // etiqueta: para HAND HELD salió "S22" porque así arrancaban muchas series
    // ya importadas, y eso volvió el escáner inservible para cualquier equipo
    // nuevo cuyo código de barras no empezara igual — nunca había match, nunca
    // se aceptaba nada. Ahora solo quedan los dos overrides que SÍ son reglas
    // de fabricación conocidas, no estadística: UPS y regulador.
    public function obtenerHintsEscaner(): void
    {
        $nombres = [];
        foreach ((new Dispositivo($this->db))->leerTodos() as $d) {
            $nombres[(int) $d['id']] = mb_strtoupper($d['nombre'] ?? '');
        }

        $hints = [];
        foreach ($nombres as $id => $nom) {
            // El UPS trae un código diminuto que empieza con 3S/SM y necesita
            // zoom; el regulador no trae código útil → OCR tras "SERIE:"/"S/N:".
            // Para todo lo demás: sin prefijo, sin restricciones.
            $prefijos = [];
            $zoomAlto = false;
            $modoOcr  = false;
            if (str_contains($nom, 'UPS')) {
                $prefijos = ['3S', 'SM'];
                $zoomAlto = true;
            }
            if (str_contains($nom, 'REGULADOR') && !str_contains($nom, 'UPS')) {
                $modoOcr = true;
            }

            $hints[(string) $id] = [
                'serie' => [
                    'prefijos'  => $prefijos,
                    'modo_ocr'  => $modoOcr,
                    'zoom_alto' => $zoomAlto,
                ],
                'codigo_barras' => ['longitud' => 8, 'solo_digitos' => true],
            ];
        }

        $this->json([
            'por_dispositivo'    => $hints,
            'codigo_barras_regla' => ['longitud' => 8, 'solo_digitos' => true],
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

    // GET ?action=listarTiendas[&plaza_id=X][&busqueda=...]
    // Lista del módulo "Tiendas": acotada al rol, con nº de activos por tienda.
    public function listarTiendas(): void
    {
        $busqueda = $_GET['busqueda'] ?? null;
        $plazaGet = (int) ($_GET['plaza_id'] ?? 0);

        if (Permisos::esAdmin()) {
            $plazaIds = $plazaGet > 0 ? [$plazaGet] : [];
        } else {
            $misPlazas = Permisos::misPlazas();
            $plazaIds  = ($plazaGet > 0 && in_array($plazaGet, $misPlazas, true))
                ? [$plazaGet]
                : $misPlazas;
            if (!$plazaIds) { $this->json([]); return; }
        }

        $this->json([
            'tiendas'      => (new Tienda($this->db))->listarConConteo($plazaIds, $busqueda),
            'puedeAsignarAti' => Permisos::puedeGestionarTiendas(),
        ]);
    }

    /** admin ve cualquier plaza; el resto sólo las suyas. */
    private function plazaEnAlcance(int $plazaId): bool
    {
        return Permisos::esAdmin()
            || ($plazaId > 0 && in_array($plazaId, Permisos::misPlazas(), true));
    }

    // GET ?action=obtenerUsuariosPorPlaza&plaza_id=X
    public function obtenerUsuariosPorPlaza(): void
    {
        $plazaId = (int) ($_GET['plaza_id'] ?? 0);
        if ($plazaId <= 0 || !$this->plazaEnAlcance($plazaId)) { $this->json([]); return; }
        $this->json((new Usuario($this->db))->obtenerPorPlaza($plazaId));
    }

    // GET ?action=obtenerStockPorUsuario&usuario_id=X&plaza_id=Y
    public function obtenerStockPorUsuario(): void
    {
        if (!in_array(Permisos::tipo(), ['admin', 'coordinador'], true)) { $this->json(null); return; }
        $usuarioId = (int) ($_GET['usuario_id'] ?? 0);
        if ($usuarioId <= 0) { $this->json(null); return; }
        // El usuario objetivo debe pertenecer a una plaza que el actor administre.
        if (!Permisos::esAdmin()) {
            $enAlcance = false;
            foreach ((new Usuario($this->db))->obtenerPlazas($usuarioId) as $p) {
                if (in_array((int) $p['id'], Permisos::misPlazas(), true)) { $enAlcance = true; break; }
            }
            if (!$enAlcance) { $this->json(null); return; }
        }
        $this->json((new Stock($this->db))->obtenerPorUsuario($usuarioId, (int) ($_GET['plaza_id'] ?? 0)));
    }

    // GET ?action=obtenerStockPorBodega&bodega_id=X
    public function obtenerStockPorBodega(): void
    {
        if (!in_array(Permisos::tipo(), ['admin', 'coordinador', 'ati'], true)) { $this->json(null); return; }
        $bodegaId = (int) ($_GET['bodega_id'] ?? 0);
        if ($bodegaId <= 0) { $this->json(null); return; }
        if (!Permisos::esAdmin()) {
            $plazasBodega = array_map('intval', array_column(
                (new Bodega($this->db))->obtenerPlazasDeBodega($bodegaId), 'id'));
            if (!array_intersect($plazasBodega, Permisos::misPlazas())) { $this->json(null); return; }
        }
        $this->json((new Stock($this->db))->obtenerPorBodega($bodegaId));
    }

    // GET ?action=obtenerBodegaPorPlaza&plaza_id=X
    public function obtenerBodegaPorPlaza(): void
    {
        $plazaId = (int) ($_GET['plaza_id'] ?? 0);
        if ($plazaId <= 0 || !$this->plazaEnAlcance($plazaId)) { $this->json(null); return; }
        $this->json((new Bodega($this->db))->obtenerPorPlaza($plazaId));
    }

    // GET ?action=obtenerActivosEnTiendaPorDispositivo&tienda_id=X[&dispositivo_id=Y]&excepto_id=Z
    // Alimenta el selector "¿Reemplaza a?". Sin dispositivo_id → todas las categorías.
    public function obtenerActivosEnTiendaPorDispositivo(): void
    {
        $tiendaId      = (int) ($_GET['tienda_id'] ?? 0);
        $dispositivoId = (int) ($_GET['dispositivo_id'] ?? 0) ?: null;
        $exceptoId     = (int) ($_GET['excepto_id'] ?? 0) ?: null;
        if ($tiendaId <= 0) { $this->json([]); return; }
        $tienda = (new Tienda($this->db))->obtenerPorId($tiendaId);
        if (!$tienda || !$this->plazaEnAlcance((int) $tienda['plaza_id'])) { $this->json([]); return; }
        $this->json((new Activo($this->db))->enTiendaPorDispositivo($tiendaId, $dispositivoId, $exceptoId));
    }

    // GET ?action=obtenerActivosEnBodega&bodega_id=X
    // Alimenta el selector de activos cuando el origen de una solicitud es una bodega.
    public function obtenerActivosEnBodega(): void
    {
        if (!in_array(Permisos::tipo(), ['coordinador', 'admin'], true)) { $this->json([]); return; }
        $bodegaId = (int) ($_GET['bodega_id'] ?? 0);
        if ($bodegaId <= 0) { $this->json([]); return; }
        if (!Permisos::esAdmin()) {
            $ok = array_map('intval', array_column($this->bodegasDePlazaApi(Permisos::plazaId()), 'id'));
            if (!in_array($bodegaId, $ok, true)) { $this->json([]); return; }
        }
        $activos = (new Activo($this->db))->obtenerTodosFiltrado(
            ['bodega_id' => $bodegaId, 'status' => 'en_bodega'], 1, 5000
        )['activos'] ?? [];
        $this->json(array_map(fn($a) => [
            'id'                 => (int) $a['id'],
            'serie'              => $a['serie'] ?? null,
            'codigo_barras'      => $a['codigo_barras'] ?? null,
            'num_activo'         => $a['num_activo'] ?? null,
            'marca_nombre'       => $a['marca_nombre'] ?? null,
            'modelo_nombre'      => $a['modelo_nombre'] ?? null,
            'dispositivo_nombre' => $a['dispositivo_nombre'] ?? null,
        ], $activos));
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

    // ── Transferencia de equipo entre personas ───────────────────────────────
    // Reemplaza las "solicitudes de traslado" con firma: el dueño manda equipo
    // de su stock a otra persona y quien recibe lo acepta con un toque. Queda
    // registrado quién aceptó y cuándo, que es el respaldo que de verdad
    // importa — si falta un equipo, eso dice en manos de quién estaba.
    //
    // Se apoya en la tabla solicitud_traslado (destino='asignado') porque ya
    // tiene las columnas exactas: origen, destino, estado, los activos y la
    // fecha de resolución. Las columnas de firma quedan sin usar.

    // POST ?action=transferirActivo
    //   activos[]=, destino_usuario_id=, nota=, origen=mi_stock|bodega, bodega_id=
    //
    // Se puede entregar equipo de dos sitios: del propio stock (cualquier rol) o
    // de una bodega (sólo quien la tiene editable, o sea coordinador y admin —
    // el ATI la ve en lectura y no puede sacar material de ahí).
    public function transferirActivo(): void
    {
        $this->requerirPost();
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $yo       = Permisos::idUsuario();
        $plazaId  = Permisos::plazaId();
        $destUid  = (int) ($b['destino_usuario_id'] ?? 0);
        $activos  = array_values(array_unique(array_map('intval', (array) ($b['activos'] ?? []))));
        $nota     = trim((string) ($b['nota'] ?? ''));
        $origen   = ($b['origen'] ?? 'mi_stock') === 'bodega' ? 'bodega' : 'mi_stock';
        $bodegaId = (int) ($b['bodega_id'] ?? 0);

        if (!$activos) {
            $this->json(['success' => false, 'message' => 'Elige al menos un equipo para transferir.'], 400);
        }
        if ($destUid <= 0 || $destUid === $yo) {
            $this->json(['success' => false, 'message' => 'Elige a quién le vas a entregar el equipo.'], 400);
        }

        // Quien recibe tiene que ser alguien de tu plaza.
        $um = new Usuario($this->db);
        if (!$um->obtenerPorId($destUid) || !$um->perteneceAPlaza($destUid, $plazaId)) {
            $this->json(['success' => false, 'message' => 'Esa persona no está en tu plaza.'], 400);
        }

        // ── De dónde sale el equipo: sólo se transfiere lo que de verdad está ahí
        $datos = [
            'destino'            => 'asignado',
            'plaza_id'           => $plazaId,
            'solicitante_id'     => $yo,
            'destino_usuario_id' => $destUid,
            'nota'               => $nota,
            'grupo_id'           => Movimiento::nuevoGrupoId(),
            'activos'            => $activos,
        ];

        if ($origen === 'bodega') {
            if (!Permisos::moduloEditable('bodega')) {
                $this->json([
                    'success' => false,
                    'message' => 'Sacar equipo de bodega es cosa de un coordinador.',
                ], 403);
            }
            if ($bodegaId <= 0 || !$this->puedeOperarBodega($bodegaId)) {
                $this->json(['success' => false, 'message' => 'Bodega inválida.'], 400);
            }
            $validos = array_map('intval', array_column(
                (new Activo($this->db))->obtenerTodosFiltrado(
                    ['bodega_id' => $bodegaId, 'status' => 'en_bodega'], 1, 5000
                )['activos'] ?? [], 'id'));
            $fuera = 'Hay equipo que ya no está en esa bodega; vuelve a abrir la lista.';
            $datos['origen_bodega_id'] = $bodegaId;
        } else {
            $validos = array_map('intval', array_column($this->activosAsignadosDe($yo), 'id'));
            $fuera = 'Hay equipo que ya no está en tu stock; vuelve a abrir la lista.';
            $datos['origen_usuario_id'] = $yo;
        }

        foreach ($activos as $aid) {
            if (!in_array($aid, $validos, true)) {
                $this->json(['success' => false, 'message' => $fuera], 409);
            }
        }

        $model = new SolicitudTraslado($this->db);
        if ($model->activosEnSolicitudPendiente($activos)) {
            $this->json(['success' => false, 'message' => 'Ese equipo ya está en otra transferencia pendiente.'], 409);
        }

        $id = $model->crear($datos);
        if ($id <= 0) {
            $this->json(['success' => false, 'message' => 'No se pudo crear la transferencia.'], 500);
        }
        $this->json([
            'success' => true,
            'id'      => $id,
            'message' => 'Transferencia enviada. El equipo cambia de manos cuando la otra persona la acepte.',
        ]);
    }

    // GET ?action=listarTransferencias
    public function listarTransferencias(): void
    {
        $yo    = Permisos::idUsuario();
        $model = new SolicitudTraslado($this->db);
        $porAceptar = $model->transferenciasPorAceptar($yo);
        $enviadas   = $model->transferenciasEnviadas($yo);

        $conActivos = function (array $filas) use ($model, $yo): array {
            return array_map(function ($s) use ($model, $yo) {
                $s['activos']       = $model->activosDe((int) $s['id']);
                $s['puedeAceptar']  = $s['estado'] === 'pendiente' && (int) $s['destino_usuario_id'] === $yo;
                $s['puedeCancelar'] = $s['estado'] === 'pendiente' && (int) $s['origen_usuario_id'] === $yo;
                return $s;
            }, $filas);
        };

        $this->json([
            'por_aceptar' => $conActivos($porAceptar),
            'enviadas'    => $conActivos($enviadas),
            'pendientes'  => count($porAceptar),
        ]);
    }

    // GET ?action=contarTransferenciasPendientes
    public function contarTransferenciasPendientes(): void
    {
        $n = count((new SolicitudTraslado($this->db))->transferenciasPorAceptar(Permisos::idUsuario()));
        $this->json(['pendientes' => $n]);
    }

    // POST ?action=aceptarTransferencia   id=
    public function aceptarTransferencia(): void
    {
        $this->requerirPost();
        $b  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($b['id'] ?? 0);
        $yo = Permisos::idUsuario();

        $model = new SolicitudTraslado($this->db);
        $t = $model->obtenerPorId($id);
        if (!$t || $t['estado'] !== 'pendiente' || $t['destino'] !== 'asignado') {
            $this->json(['success' => false, 'message' => 'Esa transferencia ya no está pendiente.'], 409);
        }
        if ((int) ($t['destino_usuario_id'] ?? 0) !== $yo) {
            $this->json(['success' => false, 'message' => 'Esta transferencia no es para ti.'], 403);
        }

        try {
            $this->db->beginTransaction();
            if (!$model->marcarAceptada($id, $yo)) {
                throw new \RuntimeException('La transferencia cambió de estado.');
            }
            // Mueve el equipo y deja el movimiento en la bitácora.
            (new TrasladoService($this->db))->ejecutar($id, $yo);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->json(['success' => false, 'message' => 'No se pudo aceptar: ' . $e->getMessage()], 500);
        }
        $this->json(['success' => true, 'message' => 'Equipo recibido. Ya aparece en tu stock.']);
    }

    // POST ?action=rechazarTransferencia   id=, motivo=
    public function rechazarTransferencia(): void
    {
        $this->requerirPost();
        $b      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id     = (int) ($b['id'] ?? 0);
        $motivo = trim((string) ($b['motivo'] ?? ''));
        $yo     = Permisos::idUsuario();

        $model = new SolicitudTraslado($this->db);
        $t = $model->obtenerPorId($id);
        if (!$t || $t['estado'] !== 'pendiente') {
            $this->json(['success' => false, 'message' => 'Esa transferencia ya no está pendiente.'], 409);
        }
        if ((int) ($t['destino_usuario_id'] ?? 0) !== $yo) {
            $this->json(['success' => false, 'message' => 'Esta transferencia no es para ti.'], 403);
        }
        if ($motivo === '') {
            $this->json(['success' => false, 'message' => 'Dile por qué no la aceptas.'], 400);
        }
        if (!$model->marcarRechazada($id, $yo, $motivo)) {
            $this->json(['success' => false, 'message' => 'No se pudo rechazar.'], 500);
        }
        $this->json(['success' => true, 'message' => 'Transferencia rechazada. El equipo sigue en el stock de quien la envió.']);
    }

    // POST ?action=cancelarTransferencia   id=
    public function cancelarTransferencia(): void
    {
        $this->requerirPost();
        $b  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($b['id'] ?? 0);
        if (!(new SolicitudTraslado($this->db))->marcarCancelada($id, Permisos::idUsuario())) {
            $this->json([
                'success' => false,
                'message' => 'No se pudo cancelar (sólo quien la envió, y sólo mientras siga pendiente).',
            ], 400);
        }
        $this->json(['success' => true, 'message' => 'Transferencia cancelada.']);
    }

    // ── Solicitudes de movimiento con firma ──────────────────────────────────
    // OBSOLETO: lo reemplaza la transferencia de arriba. No se puede borrar
    // todavía porque los APK ya instalados siguen llamando a estos endpoints;
    // se quitan cuando todos los equipos estén en la versión nueva.

    // GET ?action=listarSolicitudes[&estado=pendiente]
    public function listarSolicitudes(): void
    {
        if (!Permisos::puedeVerTraslados()) {
            $this->json(['success' => false, 'message' => 'Sin acceso a Traslados.'], 403);
        }
        $model  = new SolicitudTraslado($this->db);
        $estado = $_GET['estado'] ?? null;
        $estado = isset(SolicitudTraslado::ESTADOS[$estado]) ? $estado : null;
        $uid    = Permisos::idUsuario();

        $porFirmar = Permisos::puedeAprobarTraslados()
            ? $model->pendientesParaResolver($uid, Permisos::misPlazas(),
                in_array(Permisos::tipo(), ['coordinador', 'admin'], true),
                in_array(Permisos::tipo(), ['ati', 'admin'], true),
                Permisos::esAdmin())
            : [];
        $porFirmarIds = array_column($porFirmar, 'id');

        $vistos = [];
        $solicitudes = [];
        foreach (array_merge($porFirmar, $model->listarDeUsuario($uid)) as $s) {
            if (isset($vistos[$s['id']])) continue;
            $vistos[$s['id']] = true;
            if ($estado !== null && $s['estado'] !== $estado) continue;
            $s['porFirmar'] = in_array($s['id'], $porFirmarIds, true);
            $solicitudes[] = $s;
        }

        $this->json([
            'solicitudes'  => $solicitudes,
            'pendientes'   => count($porFirmar),
            'puedeAprobar' => Permisos::puedeAprobarTraslados(),
            'puedeCrear'   => Permisos::puedeCrearSolicitudTraslado(),
        ]);
    }

    // GET ?action=obtenerSolicitud&id=X
    public function obtenerSolicitud(): void
    {
        if (!Permisos::puedeVerTraslados()) {
            $this->json(['success' => false, 'message' => 'Sin acceso.'], 403);
        }
        $id  = (int) ($_GET['id'] ?? 0);
        $model = new SolicitudTraslado($this->db);
        $sol   = $model->obtenerPorId($id);
        if (!$sol) {
            $this->json(['success' => false, 'message' => 'Solicitud no encontrada.'], 404);
        }
        if (!$this->puedeVerSolicitud($sol)) {
            $this->json(['success' => false, 'message' => 'Sin acceso a esta solicitud.'], 403);
        }
        $sol['activos']       = $model->activosDe($id);
        $sol['puedeFirmar']   = Permisos::slotDeFirma($sol) !== null;
        $sol['puedeCancelar'] = $sol['estado'] === 'pendiente'
            && in_array(Permisos::idUsuario(), [(int) $sol['solicitante_id'], (int) ($sol['origen_usuario_id'] ?? 0)], true);
        $this->json($sol);
    }

    // POST (multipart) ?action=crearSolicitud
    //   destino=asignado|en_bodega|baja|garantia, origen_tipo=asignado|tienda|bodega,
    //   origen_tienda_id?, origen_bodega_id?, destino_bodega_id?, destino_usuario_id?, activos[]=, nota=, firma=<file>
    public function crearSolicitud(): void
    {
        $this->requerirPost();
        if (!Permisos::puedeCrearSolicitudTraslado()) {
            $this->json(['success' => false, 'message' => 'Sin permiso para crear solicitudes.'], 403);
        }

        $uid     = Permisos::idUsuario();
        $plazaId = Permisos::plazaId();
        $destino = (string) ($_POST['destino'] ?? '');
        $origen  = (string) ($_POST['origen_tipo'] ?? 'asignado');
        $activos = array_values(array_unique(array_map('intval', $_POST['activos'] ?? [])));
        $nota    = trim((string) ($_POST['nota'] ?? ''));

        if (!isset(SolicitudTraslado::DESTINOS[$destino])) {
            $this->json(['success' => false, 'message' => 'Destino inválido.'], 400);
        }
        if (!$activos) $this->json(['success' => false, 'message' => 'Selecciona al menos un activo.'], 400);

        $datos = [
            'destino' => $destino, 'plaza_id' => $plazaId, 'solicitante_id' => $uid,
            'nota' => $nota, 'grupo_id' => Movimiento::nuevoGrupoId(), 'activos' => $activos,
        ];

        if ($origen === 'tienda') {
            $tiendaId = (int) ($_POST['origen_tienda_id'] ?? 0);
            $tienda   = $tiendaId > 0 ? (new Tienda($this->db))->obtenerPorId($tiendaId) : null;
            if (!$tienda) {
                $this->json(['success' => false, 'message' => 'Tienda de origen inválida.'], 400);
            }
            if (!Permisos::esAdmin() && !in_array((int) $tienda['plaza_id'], Permisos::misPlazas(), true)) {
                $this->json(['success' => false, 'message' => 'La tienda de origen no pertenece a tu plaza.'], 403);
            }
            $validos = array_map('intval', array_column(
                (new Activo($this->db))->obtenerTodosFiltrado(['tienda_id' => $tiendaId, 'status' => 'en_uso'], 1, 2000)['activos'] ?? [], 'id'));
            $datos['origen_tienda_id'] = $tiendaId;
        } elseif ($origen === 'bodega') {
            if (!in_array(Permisos::tipo(), ['coordinador', 'admin'], true)) {
                $this->json(['success' => false, 'message' => 'Solo un coordinador o admin puede sacar equipo de bodega.'], 403);
            }
            if ($destino === 'en_bodega') {
                $this->json(['success' => false, 'message' => 'El equipo ya está en bodega; elige otro destino.'], 400);
            }
            $bodegaId  = (int) ($_POST['origen_bodega_id'] ?? 0);
            $bodegasOk = array_map('intval', array_column($this->bodegasDePlazaApi($plazaId), 'id'));
            if (!in_array($bodegaId, $bodegasOk, true)) {
                $this->json(['success' => false, 'message' => 'Bodega de origen inválida.'], 400);
            }
            $validos = array_map('intval', array_column(
                (new Activo($this->db))->obtenerTodosFiltrado(['bodega_id' => $bodegaId, 'status' => 'en_bodega'], 1, 5000)['activos'] ?? [], 'id'));
            $datos['origen_bodega_id'] = $bodegaId;
        } else {
            $validos = array_map('intval', array_column($this->activosAsignadosDe($uid), 'id'));
            $datos['origen_usuario_id'] = $uid;
        }
        foreach ($activos as $aid) {
            if (!in_array($aid, $validos, true)) {
                $this->json(['success' => false, 'message' => 'Un activo no pertenece al origen o ya cambió de estado.'], 400);
            }
        }

        if ($destino === 'en_bodega') {
            $bodegaId = (int) ($_POST['destino_bodega_id'] ?? 0);
            $bodegasOk = array_map('intval', array_column($this->bodegasDePlazaApi($plazaId), 'id'));
            if (!in_array($bodegaId, $bodegasOk, true)) {
                $this->json(['success' => false, 'message' => 'Bodega destino inválida.'], 400);
            }
            $datos['destino_bodega_id'] = $bodegaId;
        } elseif ($destino === 'asignado') {
            $destUid = (int) ($_POST['destino_usuario_id'] ?? 0);
            $um = new Usuario($this->db);
            if ($destUid <= 0 || $destUid === $uid || !$um->obtenerPorId($destUid) || !$um->perteneceAPlaza($destUid, $plazaId)) {
                $this->json(['success' => false, 'message' => 'Elige un ingeniero válido de tu plaza que reciba el equipo.'], 400);
            }
            $datos['destino_usuario_id'] = $destUid;
        }

        $model = new SolicitudTraslado($this->db);
        if ($model->activosEnSolicitudPendiente($activos)) {
            $this->json(['success' => false, 'message' => 'Un activo ya está en otra solicitud pendiente.'], 409);
        }

        $firma = ImageHelper::guardarFirma('firma', 'firma_sol');
        if (!$firma) {
            $this->json(['success' => false, 'message' => 'Falta tu firma o no se pudo procesar.'], 400);
        }
        $datos['firma_solicitante'] = $firma;

        $id = $model->crear($datos);
        if ($id <= 0) {
            // La firma se guardó antes de crear la solicitud; si no cuajó, fuera.
            $this->descartarImagenes([$firma], ROOT_PATH . '/public/uploads/firmas');
            $this->json(['success' => false, 'message' => 'No se pudo crear la solicitud.'], 500);
        }
        $this->json(['success' => true, 'message' => 'Solicitud enviada.', 'id' => $id]);
    }

    // POST (multipart) ?action=aprobarSolicitud   id=, firma=<file>
    public function aprobarSolicitud(): void
    {
        $this->requerirPost();
        $id    = (int) ($_POST['id'] ?? 0);
        $model = new SolicitudTraslado($this->db);
        $sol   = $model->obtenerPorId($id);
        if (!$sol || $sol['estado'] !== 'pendiente') {
            $this->json(['success' => false, 'message' => 'La solicitud no está pendiente.'], 409);
        }
        $slot = Permisos::slotDeFirma($sol);
        if ($slot === null) {
            $this->json(['success' => false, 'message' => 'No te corresponde firmar esta solicitud.'], 403);
        }
        $firma = ImageHelper::guardarFirma('firma', 'firma_apr');
        if (!$firma) {
            $this->json(['success' => false, 'message' => 'Falta tu firma o no se pudo procesar.'], 400);
        }
        $rol = Permisos::rolDeFirma((string) $sol['destino'], $slot);

        try {
            $this->db->beginTransaction();
            $r = $model->firmarAprobacion($id, Permisos::idUsuario(), $firma, $rol);
            if ($r === false || $r === 'duplicada' || $r === 'no_aplica') {
                throw new \RuntimeException('No se pudo firmar (' . $r . ').');
            }
            if ($r === 'lista') {
                (new TrasladoService($this->db))->ejecutar($id, Permisos::idUsuario());
                $model->marcarAprobada($id);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->json(['success' => false, 'message' => 'No se pudo firmar: ' . $e->getMessage()], 500);
        }
        $this->json([
            'success' => true,
            'message' => $r === 'lista' ? 'Solicitud aprobada; el movimiento se ejecutó.' : 'Firma registrada; falta la otra.',
            'ejecutada' => $r === 'lista',
        ]);
    }

    // POST ?action=rechazarSolicitud   id=, motivo=
    public function rechazarSolicitud(): void
    {
        $this->requerirPost();
        $b      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id     = (int) ($b['id'] ?? 0);
        $motivo = trim((string) ($b['motivo'] ?? ''));
        $model  = new SolicitudTraslado($this->db);
        $sol    = $model->obtenerPorId($id);
        if (!$sol || $sol['estado'] !== 'pendiente') {
            $this->json(['success' => false, 'message' => 'La solicitud no está pendiente.'], 409);
        }
        if (Permisos::slotDeFirma($sol) === null) {
            $this->json(['success' => false, 'message' => 'No te corresponde resolver esta solicitud.'], 403);
        }
        if ($motivo === '') {
            $this->json(['success' => false, 'message' => 'Indica el motivo del rechazo.'], 400);
        }
        if (!$model->marcarRechazada($id, Permisos::idUsuario(), $motivo)) {
            $this->json(['success' => false, 'message' => 'No se pudo rechazar.'], 500);
        }
        $this->json(['success' => true, 'message' => 'Solicitud rechazada.']);
    }

    // POST ?action=cancelarSolicitud   id=
    public function cancelarSolicitud(): void
    {
        $this->requerirPost();
        $b  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($b['id'] ?? 0);
        if (!(new SolicitudTraslado($this->db))->marcarCancelada($id, Permisos::idUsuario())) {
            $this->json(['success' => false, 'message' => 'No se pudo cancelar (solo el solicitante, y solo si está pendiente).'], 400);
        }
        $this->json(['success' => true, 'message' => 'Solicitud cancelada.']);
    }

    // GET ?action=contarSolicitudesPendientes
    public function contarSolicitudesPendientes(): void
    {
        $model = new SolicitudTraslado($this->db);
        $n = 0;
        if (Permisos::puedeAprobarTraslados()) {
            $n = count($model->pendientesParaResolver(Permisos::idUsuario(), Permisos::misPlazas(),
                in_array(Permisos::tipo(), ['coordinador', 'admin'], true),
                in_array(Permisos::tipo(), ['ati', 'admin'], true),
                Permisos::esAdmin()));
        }
        $this->json(['pendientes' => $n]);
    }

    private function activosAsignadosDe(int $usuarioId): array
    {
        $res = (new Activo($this->db))->obtenerTodosFiltrado([
            'stock_usuario_id' => $usuarioId,
            'status'           => 'asignado',
        ], 1, 1000);
        return $res['activos'] ?? [];
    }

    // ── Inventario físico de bodega (auditoría por escaneo) ────────────────────

    // GET ?action=inventarioBodegaBodegas — bodegas donde el usuario puede hacer inventario.
    public function inventarioBodegaBodegas(): void
    {
        if (!in_array(Permisos::tipo(), ['coordinador', 'admin'], true)) { $this->json([]); }
        if (Permisos::esAdmin()) {
            $this->json((new Bodega($this->db))->obtenerTodas());
        }
        $out = [];
        foreach (Permisos::misPlazas() as $pid) {
            foreach ($this->bodegasDePlazaApi((int) $pid) as $b) $out[(int) $b['id']] = $b;
        }
        $this->json(array_values($out));
    }

    // GET ?action=inventarioBodegaListar&bodega_id= — histórico de inventarios (por mes) de una bodega.
    public function inventarioBodegaListar(): void
    {
        if (!in_array(Permisos::tipo(), ['coordinador', 'admin'], true)) { $this->json([]); }
        $bodegaId = (int) ($_GET['bodega_id'] ?? 0);
        if ($bodegaId <= 0 || !$this->puedeOperarBodega($bodegaId)) { $this->json([]); }
        $this->json((new InventarioBodega($this->db))->listar($bodegaId));
    }

    // POST ?action=inventarioBodegaIniciar  bodega_id= — abre (o retoma) el inventario del mes en curso.
    public function inventarioBodegaIniciar(): void
    {
        $this->requerirPost();
        if (!in_array(Permisos::tipo(), ['coordinador', 'admin'], true)) {
            $this->json(['success' => false, 'message' => 'Acceso restringido.'], 403);
        }
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $bodegaId = (int) ($b['bodega_id'] ?? 0);
        if ($bodegaId <= 0 || !$this->puedeOperarBodega($bodegaId)) {
            $this->json(['success' => false, 'message' => 'Bodega inválida.'], 400);
        }

        $model = new InventarioBodega($this->db);
        $abierto = $model->obtenerAbierto($bodegaId);
        if ($abierto) {
            $this->json(['success' => true, 'inventario' => $model->obtenerDetalle((int) $abierto['id'])]);
        }

        // El snapshot se arma con INSERT…SELECT dentro del modelo: antes se
        // traían las filas a PHP con un tope de 5000 y, si la bodega lo
        // superaba, el inventario nacía incompleto sin avisar.
        $id = $model->crear($bodegaId, date('Y-m'), Permisos::idUsuario());
        $this->json(['success' => true, 'inventario' => $model->obtenerDetalle($id)]);
    }

    // GET ?action=inventarioBodegaDetalle&id= — sirve tanto inventario de
    // bodega como de stock personal (puedeOperarInventario() distingue).
    public function inventarioBodegaDetalle(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $model = new InventarioBodega($this->db);
        $inv = $model->obtenerCabecera($id);
        if (!$inv || !$this->puedeOperarInventario($inv)) { $this->json(['error' => 'not_found'], 404); }
        $this->json($model->obtenerDetalle($id));
    }

    // POST ?action=inventarioBodegaEscanear  inventario_id=  codigo=
    public function inventarioBodegaEscanear(): void
    {
        $this->requerirPost();
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $inventarioId = (int) ($b['inventario_id'] ?? 0);
        $codigo = trim((string) ($b['codigo'] ?? ''));
        if ($inventarioId <= 0 || $codigo === '') {
            $this->json(['success' => false, 'message' => 'Faltan datos.'], 400);
        }

        $model = new InventarioBodega($this->db);
        $inv = $model->obtenerCabecera($inventarioId);
        if (!$inv || !$this->puedeOperarInventario($inv)) {
            $this->json(['success' => false, 'message' => 'Inventario no encontrado.'], 404);
        }
        if ($inv['estado'] !== 'abierto') {
            $this->json(['success' => false, 'message' => 'Este inventario ya está cerrado.'], 409);
        }

        $activoId = $model->marcarEscaneado($inventarioId, $codigo, Permisos::idUsuario());
        if ($activoId === null) {
            $this->json(['success' => false, 'message' => 'Ese activo no pertenece a la lista de este inventario.'], 404);
        }
        $this->json(['success' => true, 'activo_id' => $activoId, 'inventario' => $model->obtenerDetalle($inventarioId)]);
    }

    // POST ?action=inventarioBodegaNota  detalle_id=  nota=
    public function inventarioBodegaNota(): void
    {
        $this->requerirPost();
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $detalleId = (int) ($b['detalle_id'] ?? 0);
        $nota = trim((string) ($b['nota'] ?? ''));

        $model = new InventarioBodega($this->db);
        $det = $model->obtenerDetalleRow($detalleId);
        if (!$det) { $this->json(['success' => false, 'message' => 'No encontrado.'], 404); }
        $inv = $model->obtenerCabecera((int) $det['inventario_id']);
        if (!$inv || !$this->puedeOperarInventario($inv)) {
            $this->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }
        $model->guardarNota($detalleId, $nota);
        $this->json(['success' => true]);
    }

    // POST ?action=inventarioBodegaCerrar  id=
    public function inventarioBodegaCerrar(): void
    {
        $this->requerirPost();
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($b['id'] ?? 0);

        $model = new InventarioBodega($this->db);
        $inv = $model->obtenerCabecera($id);
        if (!$inv || !$this->puedeOperarInventario($inv)) {
            $this->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }
        if ($inv['estado'] !== 'abierto') {
            $this->json(['success' => false, 'message' => 'Ya está cerrado.'], 409);
        }
        $model->cerrar($id);
        $this->json(['success' => true, 'inventario' => $model->obtenerDetalle($id)]);
    }

    // ── Inventario de stock personal (Mi Stock / Stock PFS) — mismo mecanismo
    // que el de bodega de arriba, objetivo = stock_usuario_id en vez de
    // bodega_id. Detalle/Escanear/Nota/Cerrar de arriba ya son genéricos
    // (puedeOperarInventario distingue el tipo de objetivo). ────────────────

    // GET ?action=inventarioStockUsuarios — a quién puede auditar el usuario en sesión:
    // siempre a sí mismo; admin/coordinador/ati además a los PFS de su alcance.
    public function inventarioStockUsuarios(): void
    {
        $usuarioModel = new Usuario($this->db);
        $out = [];
        if ($yo = $usuarioModel->obtenerPorId(Permisos::idUsuario())) {
            $out[(int) $yo['id']] = $yo;
        }
        if (Permisos::esAdmin()) {
            foreach ($usuarioModel->obtenerTodos() as $u) {
                if (($u['tipo'] ?? '') === 'pfs') $out[(int) $u['id']] = $u;
            }
        } elseif (in_array(Permisos::tipo(), ['coordinador', 'ati'], true)) {
            foreach (Permisos::misPlazas() as $pid) {
                foreach ($usuarioModel->obtenerPorPlaza((int) $pid) as $u) {
                    if (($u['tipo'] ?? '') === 'pfs') $out[(int) $u['id']] = $u;
                }
            }
        }
        $this->json(array_values($out));
    }

    // GET ?action=stockPfsUsuarios — landing del módulo "Stock PFS": lista de
    // ingenieros PFS que tienen 1 o más activos registrados a su nombre.
    public function stockPfsUsuarios(): void
    {
        if (!Permisos::moduloPermitido('stock_pfs')) { $this->json([]); return; }
        $plazas = Permisos::esAdmin() ? null : Permisos::misPlazas();
        $this->json((new Usuario($this->db))->obtenerStockPersonalModuloPfs($plazas, Permisos::idUsuario()));
    }

    // GET ?action=inventarioStockListar&stock_usuario_id= — histórico (por mes).
    public function inventarioStockListar(): void
    {
        $stockUsuarioId = (int) ($_GET['stock_usuario_id'] ?? 0);
        if ($stockUsuarioId <= 0 || !$this->puedeOperarStockUsuario($stockUsuarioId)) { $this->json([]); }
        $this->json((new InventarioBodega($this->db))->listarUsuario($stockUsuarioId));
    }

    // POST ?action=inventarioStockIniciar  stock_usuario_id= — abre (o retoma) el del mes en curso.
    public function inventarioStockIniciar(): void
    {
        $this->requerirPost();
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $stockUsuarioId = (int) ($b['stock_usuario_id'] ?? 0);
        if ($stockUsuarioId <= 0 || !$this->puedeOperarStockUsuario($stockUsuarioId)) {
            $this->json(['success' => false, 'message' => 'Usuario inválido.'], 400);
        }

        $model = new InventarioBodega($this->db);
        $abierto = $model->obtenerAbiertoUsuario($stockUsuarioId);
        if ($abierto) {
            $this->json(['success' => true, 'inventario' => $model->obtenerDetalle((int) $abierto['id'])]);
        }

        // Sólo lo que la persona debe poder mostrar físicamente: 'asignado'.
        // Sin este filtro entraban también baja y garantía, que StockResolver
        // deja en el stock personal del ATI, y el inventario exigía encontrar
        // equipo ya dado de baja.
        $id = $model->crearUsuario($stockUsuarioId, date('Y-m'), Permisos::idUsuario());
        $this->json(['success' => true, 'inventario' => $model->obtenerDetalle($id)]);
    }

    /** ¿El usuario en sesión puede operar (ver/escanear) esta bodega? */
    private function puedeOperarBodega(int $bodegaId): bool
    {
        if (Permisos::esAdmin()) return true;
        foreach (Permisos::misPlazas() as $pid) {
            $ids = array_map('intval', array_column($this->bodegasDePlazaApi((int) $pid), 'id'));
            if (in_array($bodegaId, $ids, true)) return true;
        }
        return false;
    }

    /** ¿Puede auditar el stock personal de este usuario? Uno mismo siempre; admin siempre; coordinador/ati si está en su alcance. */
    private function puedeOperarStockUsuario(int $stockUsuarioId): bool
    {
        if (Permisos::esAdmin() || Permisos::idUsuario() === $stockUsuarioId) return true;
        if (!in_array(Permisos::tipo(), ['coordinador', 'ati'], true)) return false;
        $usuarioModel = new Usuario($this->db);
        foreach (Permisos::misPlazas() as $pid) {
            if ($usuarioModel->perteneceAPlaza($stockUsuarioId, (int) $pid)) return true;
        }
        return false;
    }

    /** Despacha al chequeo correcto según el tipo de objetivo del inventario (bodega o stock personal). */
    private function puedeOperarInventario(array $inv): bool
    {
        if (!empty($inv['bodega_id'])) return $this->puedeOperarBodega((int) $inv['bodega_id']);
        if (!empty($inv['stock_usuario_id'])) return $this->puedeOperarStockUsuario((int) $inv['stock_usuario_id']);
        return false;
    }

    // ── RENTEC: proyectos de Renovación Tecnológica ─────────────────────────
    // Reutiliza el alta normal (status=en_bodega) y el modo Reemplazo de
    // Tiendas — ambos ya aceptan `proyecto_rentec_id` opcional (ver
    // datosActivoPost/ActivoGuardado/MovimientoService). Aquí solo viven el
    // CRUD del proyecto/folio y su listado/detalle.

    // GET ?action=rentecListar — visible para cualquier rol: lo que creó, o
    // cualquier proyecto que ya haya tocado una de sus plazas.
    public function rentecListar(): void
    {
        $plazas = Permisos::esAdmin() ? null : Permisos::misPlazas();
        $this->json((new ProyectoRentec($this->db))->listar($plazas, Permisos::idUsuario()));
    }

    // POST ?action=rentecCrear  nombre= — folio autogenerado (RENTEC-0001…).
    public function rentecCrear(): void
    {
        $this->requerirPost();
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $nombre = trim((string) ($b['nombre'] ?? ''));
        if ($nombre === '') {
            $this->json(['success' => false, 'message' => 'Dale un nombre al proyecto.'], 400);
        }
        // La plaza queda grabada en el proyecto para que todo su equipo lo vea
        // desde el primer momento, sin esperar a que tenga movimientos.
        $creado = (new ProyectoRentec($this->db))->crear($nombre, Permisos::idUsuario(), Permisos::plazaId());
        $this->json(['success' => true, 'id' => $creado['id'], 'folio' => $creado['folio']]);
    }

    // POST ?action=rentecEliminar  id=
    // Sólo folios sin huella: con activos o movimientos encima no se borra
    // (las claves foráneas son RESTRICT y perderíamos la trazabilidad).
    public function rentecEliminar(): void
    {
        $this->requerirPost();
        $b  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($b['id'] ?? 0);

        $model = new ProyectoRentec($this->db);
        $cab   = $model->obtenerCabecera($id);
        if (!$cab || !$this->puedeVerRentec($cab, $model)) {
            $this->json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
        }
        // Lo borra quien lo creó, o un coordinador/admin de su plaza.
        if ((int) $cab['usuario_id'] !== Permisos::idUsuario() && !Permisos::puedeRecibirRentec()) {
            $this->json([
                'success' => false,
                'message' => 'Sólo quien creó el proyecto, o un coordinador, puede borrarlo.',
            ], 403);
        }

        $act = $model->actividad($id);
        if ($act['activos'] > 0 || $act['movimientos'] > 0) {
            $this->json([
                'success' => false,
                'message' => sprintf(
                    'Este proyecto ya tiene movimiento (%d equipo(s) y %d registro(s) en la bitácora), '
                    . 'así que no se puede borrar sin perder el rastro. Ciérralo en vez de borrarlo.',
                    $act['activos'], $act['movimientos']
                ),
                'actividad' => $act,
            ], 409);
        }

        if (!$model->eliminar($id)) {
            $this->json(['success' => false, 'message' => 'No se pudo borrar el proyecto.'], 500);
        }
        $this->json(['success' => true, 'message' => 'Proyecto borrado.']);
    }

    // GET ?action=rentecDetalle&id=
    public function rentecDetalle(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $model = new ProyectoRentec($this->db);
        $cab = $model->obtenerCabecera($id);
        if (!$cab || !$this->puedeVerRentec($cab, $model)) { $this->json(['error' => 'not_found'], 404); }
        $this->json($model->obtenerDetalle($id));
    }

    // POST ?action=rentecCerrar  id= — solo etiqueta el proyecto como cerrado
    // (no bloquea nada: los activos siguen su ciclo de vida normal).
    public function rentecCerrar(): void
    {
        $this->requerirPost();
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($b['id'] ?? 0);
        $model = new ProyectoRentec($this->db);
        $cab = $model->obtenerCabecera($id);
        if (!$cab || !$this->puedeVerRentec($cab, $model)) { $this->json(['success' => false, 'message' => 'No encontrado.'], 404); }
        $model->cerrar($id);
        $this->json(['success' => true, 'proyecto' => $model->obtenerCabecera($id)]);
    }

    private function puedeVerRentec(array $cabecera, ProyectoRentec $model): bool
    {
        if (Permisos::esAdmin()) return true;
        if ((int) $cabecera['usuario_id'] === Permisos::idUsuario()) return true;
        return $model->tocaPlazas((int) $cabecera['id'], Permisos::misPlazas());
    }


    private function bodegasDePlazaApi(int $plazaId): array
    {
        $bModel = new Bodega($this->db);
        $out = [];
        if ($oxxo = $bModel->obtenerPorPlazaYNegocio($plazaId, 'oxxo')) $out[(int) $oxxo['id']] = $oxxo;
        if ($any  = $bModel->obtenerPorPlaza($plazaId))                 $out[(int) $any['id']] = $out[(int) $any['id']] ?? $any;
        return array_values($out);
    }

    private function puedeVerSolicitud(array $sol): bool
    {
        if (Permisos::esAdmin()) return true;
        $uid = Permisos::idUsuario();
        if (in_array($uid, array_map('intval', [
            $sol['solicitante_id'], $sol['origen_usuario_id'] ?? 0,
            $sol['destino_usuario_id'] ?? 0, $sol['aprobador_id'] ?? 0, $sol['aprobador2_id'] ?? 0,
        ]), true)) return true;
        return Permisos::puedeAprobarTraslados()
            && in_array((int) $sol['plaza_id'], Permisos::misPlazas(), true);
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
            // La migración 026 ya renombró 'fs' → 'pfs' en el enum de la BD
            // (verificado contra producción), así que no hay nada que traducir.
            $tipo = strtolower(trim($usuario['tipo'] ?? 'pfs'));

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
            $_SESSION['user_agent']     = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['recordar']       = true; // la app siempre es sesión perpetua

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
            'pfs'   => 'mi_stock',
            'ati'   => 'mi_stock',
            default => 'bodega',
        };
    }

    private function vistaPermitida(string $vista, string $tipo): string
    {
        if ($tipo === 'pfs' && $vista !== 'mi_stock') return 'mi_stock';
        return $vista;
    }

    /**
     * Compatibilidad con la app publicada (navegación por "vista"). La nueva
     * navegación por módulos usa Permisos::modulos(); esto se deriva de ahí.
     */
    private function vistasDisponiblesParaTipo(string $tipo): array
    {
        return match ($tipo) {
            'admin', 'coordinador' => ['bodega', 'todos'],
            'ati'                  => ['bodega', 'mi_stock', 'todos'],
            'pfs'                  => ['mi_stock'],
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
            'proyecto_rentec_id'    => !empty($_POST['proyecto_rentec_id'])    ? (int) $_POST['proyecto_rentec_id']    : null,
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