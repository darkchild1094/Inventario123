<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\Bodega;
use App\Models\Movimiento;
use App\Models\SolicitudTraslado;
use App\Services\TrasladoService;
use App\Helpers\ImageHelper;
use App\Helpers\Permisos;

/**
 * SolicitudTrasladoController — flujo "ingeniero manda su stock 'asignado' a
 * bodega con doble firma digital".
 *
 *   fs          → crea la solicitud, firma y la envía (queda 'pendiente')
 *   coordinador → ve las pendientes de sus plazas, firma y aprueba / rechaza
 *   admin       → como coordinador pero sobre cualquier plaza
 *
 * Al aprobar: TrasladoService mueve los activos a 'en_bodega' y registra la
 * bitácora (un movimiento por activo, mismo grupo_id + solicitud_traslado_id).
 */
class SolicitudTrasladoController
{
    private $db;
    private AuthController $auth;

    public function __construct($db)
    {
        $this->db   = $db;
        $this->auth = new AuthController($db);
        $this->auth->requerirAutenticacion();
    }

    // ── Índice ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!Permisos::puedeVerTraslados()) {
            $this->error('No tienes acceso a Traslados.', 'index.php');
        }

        $model    = new SolicitudTraslado($this->db);
        $estado   = $_GET['estado'] ?? null;
        $estado   = isset(SolicitudTraslado::ESTADOS[$estado]) ? $estado : null;
        $puedeAprobar = Permisos::puedeAprobarTraslados();

        if ($puedeAprobar) {
            if (Permisos::esAdmin()) {
                $solicitudes = $model->listarTodas($estado);
                $pendientes  = $model->contarPendientesTodas();
            } else {
                $plazas      = Permisos::misPlazas();
                $solicitudes = $model->listarPorPlazas($plazas, $estado);
                $pendientes  = $model->contarPendientesPorPlazas($plazas);
            }
        } else {
            $solicitudes = $model->listarDeUsuario(Permisos::idUsuario());
            $pendientes  = 0;
        }

        $estados = SolicitudTraslado::ESTADOS;
        $fEstado = $estado ?? '';
        require ROOT_PATH . '/app/views/solicitudes/index.php';
    }

    // ── Crear (fs) ───────────────────────────────────────────────────────────

    public function crear(): void
    {
        if (!Permisos::puedeCrearSolicitudTraslado()) {
            $this->error('Solo los ingenieros de campo pueden crear solicitudes de traslado.', 'index.php?controller=solicitud&action=index');
        }

        $plazaId  = Permisos::plazaId();
        $activos  = $this->activosAsignadosDelUsuario(Permisos::idUsuario());
        $bodegas  = $this->bodegasDePlaza($plazaId);
        $bodegaDefault = $bodegas[0]['id'] ?? 0;

        require ROOT_PATH . '/app/views/solicitudes/crear.php';
    }

    public function guardar(): void
    {
        if (!Permisos::puedeCrearSolicitudTraslado()) {
            $this->error('Sin permiso.', 'index.php?controller=solicitud&action=index');
        }
        $this->requerirPost();

        $userId   = Permisos::idUsuario();
        $plazaId  = Permisos::plazaId();
        $activos  = array_values(array_unique(array_map('intval', $_POST['activos'] ?? [])));
        $bodegaId = (int) ($_POST['destino_bodega_id'] ?? 0);
        $nota     = trim((string) ($_POST['nota'] ?? ''));
        $firmaRaw = (string) ($_POST['firma'] ?? '');

        if (!$activos) {
            $this->error('Selecciona al menos un activo.', 'index.php?controller=solicitud&action=crear');
        }
        if ($bodegaId <= 0) {
            $this->error('Selecciona la bodega destino.', 'index.php?controller=solicitud&action=crear');
        }
        if ($firmaRaw === '') {
            $this->error('Falta tu firma.', 'index.php?controller=solicitud&action=crear');
        }

        // Todos los activos deben ser stock personal del ingeniero y estar 'asignado'.
        $validosIds = array_column($this->activosAsignadosDelUsuario($userId), 'id');
        $validosIds = array_map('intval', $validosIds);
        foreach ($activos as $aid) {
            if (!in_array($aid, $validosIds, true)) {
                $this->error('Uno de los activos no está en tu stock personal o ya no está asignado.', 'index.php?controller=solicitud&action=crear');
            }
        }

        $model = new SolicitudTraslado($this->db);
        $yaEnPendiente = $model->activosEnSolicitudPendiente($activos);
        if ($yaEnPendiente) {
            $this->error('Uno de los activos ya está en otra solicitud pendiente.', 'index.php?controller=solicitud&action=crear');
        }

        // Validar bodega dentro de la plaza del ingeniero.
        $bodegasOk = array_map('intval', array_column($this->bodegasDePlaza($plazaId), 'id'));
        if (!in_array($bodegaId, $bodegasOk, true)) {
            $this->error('La bodega seleccionada no pertenece a tu plaza.', 'index.php?controller=solicitud&action=crear');
        }

        $firma = ImageHelper::guardarFirma($firmaRaw, 'firma_sol');
        if (!$firma) {
            $this->error('No se pudo procesar tu firma. Intenta de nuevo.', 'index.php?controller=solicitud&action=crear');
        }

        $id = $model->crear([
            'plaza_id'          => $plazaId,
            'origen_usuario_id' => $userId,
            'destino_bodega_id' => $bodegaId,
            'solicitante_id'    => $userId,
            'firma_solicitante' => $firma,
            'nota'              => $nota,
            'grupo_id'          => Movimiento::nuevoGrupoId(),
            'activos'           => $activos,
        ]);

        if ($id <= 0) {
            $this->error('No se pudo crear la solicitud.', 'index.php?controller=solicitud&action=crear');
        }

        $_SESSION['success'] = "Solicitud #{$id} enviada. Queda pendiente de la firma del coordinador.";
        $this->redirigir('index.php?controller=solicitud&action=ver&id=' . $id);
    }

    // ── Ver / resolver ───────────────────────────────────────────────────────

    public function ver(): void
    {
        $id  = $this->idGet();
        $model = new SolicitudTraslado($this->db);
        $sol   = $model->obtenerPorId($id);
        if (!$sol) {
            $this->error('Solicitud no encontrada.', 'index.php?controller=solicitud&action=index');
        }
        if (!$this->puedeVerSolicitud($sol)) {
            $this->error('No tienes acceso a esta solicitud.', 'index.php?controller=solicitud&action=index');
        }

        $activos      = $model->activosDe($id);
        $puedeResolver = $this->puedeResolver($sol);
        $puedeCancelar = $sol['estado'] === 'pendiente'
            && in_array(Permisos::idUsuario(), [(int) $sol['solicitante_id'], (int) $sol['origen_usuario_id']], true);
        $movimientos  = $sol['estado'] === 'aprobada' && !empty($sol['grupo_id'])
            ? $this->movimientosDelGrupo((string) $sol['grupo_id'])
            : [];
        $estados = SolicitudTraslado::ESTADOS;

        require ROOT_PATH . '/app/views/solicitudes/ver.php';
    }

    public function aprobar(): void
    {
        $this->requerirPost();
        $id    = (int) ($_POST['id'] ?? 0);
        $model = new SolicitudTraslado($this->db);
        $sol   = $model->obtenerPorId($id);

        if (!$sol || $sol['estado'] !== 'pendiente') {
            $this->error('La solicitud no está pendiente.', 'index.php?controller=solicitud&action=index');
        }
        if (!$this->puedeResolver($sol)) {
            $this->error('No puedes aprobar esta solicitud.', 'index.php?controller=solicitud&action=ver&id=' . $id);
        }

        $firmaRaw = (string) ($_POST['firma'] ?? '');
        if ($firmaRaw === '') {
            $this->error('Falta tu firma para aprobar.', 'index.php?controller=solicitud&action=ver&id=' . $id);
        }
        $firma = ImageHelper::guardarFirma($firmaRaw, 'firma_apr');
        if (!$firma) {
            $this->error('No se pudo procesar tu firma. Intenta de nuevo.', 'index.php?controller=solicitud&action=ver&id=' . $id);
        }

        try {
            $this->db->beginTransaction();
            if (!$model->marcarAprobada($id, Permisos::idUsuario(), $firma)) {
                throw new \RuntimeException('La solicitud cambió de estado.');
            }
            (new TrasladoService($this->db))->ejecutar($id, Permisos::idUsuario());
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->error('No se pudo aprobar: ' . $e->getMessage(), 'index.php?controller=solicitud&action=ver&id=' . $id);
        }

        $_SESSION['success'] = "Solicitud #{$id} aprobada. Los activos pasaron a bodega.";
        $this->redirigir('index.php?controller=solicitud&action=ver&id=' . $id);
    }

    public function rechazar(): void
    {
        $this->requerirPost();
        $id     = (int) ($_POST['id'] ?? 0);
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $model  = new SolicitudTraslado($this->db);
        $sol    = $model->obtenerPorId($id);

        if (!$sol || $sol['estado'] !== 'pendiente') {
            $this->error('La solicitud no está pendiente.', 'index.php?controller=solicitud&action=index');
        }
        if (!$this->puedeResolver($sol)) {
            $this->error('No puedes rechazar esta solicitud.', 'index.php?controller=solicitud&action=ver&id=' . $id);
        }
        if ($motivo === '') {
            $this->error('Indica el motivo del rechazo.', 'index.php?controller=solicitud&action=ver&id=' . $id);
        }

        if (!$model->marcarRechazada($id, Permisos::idUsuario(), $motivo)) {
            $this->error('No se pudo rechazar (¿cambió de estado?).', 'index.php?controller=solicitud&action=ver&id=' . $id);
        }

        $_SESSION['success'] = "Solicitud #{$id} rechazada. Los activos siguen con el ingeniero.";
        $this->redirigir('index.php?controller=solicitud&action=ver&id=' . $id);
    }

    public function cancelar(): void
    {
        $this->requerirPost();
        $id    = (int) ($_POST['id'] ?? 0);
        $model = new SolicitudTraslado($this->db);

        if (!$model->marcarCancelada($id, Permisos::idUsuario())) {
            $this->error('No se pudo cancelar (solo el solicitante puede, y solo si está pendiente).', 'index.php?controller=solicitud&action=index');
        }
        $_SESSION['success'] = "Solicitud #{$id} cancelada.";
        $this->redirigir('index.php?controller=solicitud&action=index');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Activos del stock PERSONAL del usuario que están 'asignado'. */
    private function activosAsignadosDelUsuario(int $usuarioId): array
    {
        $res = (new Activo($this->db))->obtenerTodosFiltrado([
            'stock_usuario_id' => $usuarioId,
            'status'           => 'asignado',
        ], 1, 1000);
        return $res['activos'] ?? [];
    }

    /** Bodegas candidatas de una plaza (OXXO primero, luego cualquiera). */
    private function bodegasDePlaza(int $plazaId): array
    {
        $bModel = new Bodega($this->db);
        $out = [];
        $oxxo = $bModel->obtenerPorPlazaYNegocio($plazaId, 'oxxo');
        if ($oxxo) {
            $out[(int) $oxxo['id']] = $oxxo;
        }
        $cualquiera = $bModel->obtenerPorPlaza($plazaId);
        if ($cualquiera) {
            $out[(int) $cualquiera['id']] = $out[(int) $cualquiera['id']] ?? $cualquiera;
        }
        return array_values($out);
    }

    private function puedeVerSolicitud(array $sol): bool
    {
        if (Permisos::esAdmin()) {
            return true;
        }
        $uid = Permisos::idUsuario();
        if (in_array($uid, [(int) $sol['solicitante_id'], (int) $sol['origen_usuario_id']], true)) {
            return true;
        }
        return Permisos::puedeAprobarTraslados()
            && in_array((int) $sol['plaza_id'], Permisos::misPlazas(), true);
    }

    private function puedeResolver(array $sol): bool
    {
        if (!Permisos::puedeAprobarTraslados() || $sol['estado'] !== 'pendiente') {
            return false;
        }
        return Permisos::esAdmin()
            || in_array((int) $sol['plaza_id'], Permisos::misPlazas(), true);
    }

    private function movimientosDelGrupo(string $grupoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id, m.activo_id, m.evento, m.creado_en, a.serie, a.codigo_barras
             FROM movimiento m
             LEFT JOIN activo a ON a.id = m.activo_id
             WHERE m.grupo_id = :g ORDER BY m.id"
        );
        $stmt->execute([':g' => $grupoId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function requerirPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir('index.php?controller=solicitud&action=index');
        }
    }

    private function idGet(): int
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirigir('index.php?controller=solicitud&action=index');
        }
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
