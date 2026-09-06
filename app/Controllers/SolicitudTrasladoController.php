<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\Bodega;
use App\Models\Movimiento;
use App\Models\SolicitudTraslado;
use App\Models\Tienda;
use App\Models\Usuario;
use App\Services\TrasladoService;
use App\Helpers\ImageHelper;
use App\Helpers\Permisos;

/**
 * SolicitudTrasladoController — solicitudes de MOVIMIENTO de activos con firma.
 *
 *   destino    origen              lo aprueba / firma
 *   --------   -----------------   --------------------------------
 *   asignado   ingeniero A         el ingeniero B que recibe
 *   en_bodega  ingeniero / tienda  un coordinador de la plaza
 *   baja       ingeniero / tienda  un ATI de la plaza
 *   garantia   ingeniero / tienda  un ATI  Y  un coordinador (doble firma)
 *
 * Nada se ejecuta hasta reunir todas las firmas: ver App\Services\TrasladoService.
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

        $model  = new SolicitudTraslado($this->db);
        $estado = $_GET['estado'] ?? null;
        $estado = isset(SolicitudTraslado::ESTADOS[$estado]) ? $estado : null;

        $uid       = Permisos::idUsuario();
        $mias      = $model->listarDeUsuario($uid);
        $porFirmar = Permisos::puedeAprobarTraslados()
            ? $model->pendientesParaResolver($uid, Permisos::misPlazas(),
                in_array(Permisos::tipo(), ['coordinador', 'admin'], true),
                in_array(Permisos::tipo(), ['ati', 'admin'], true),
                Permisos::esAdmin())
            : [];

        // La lista principal: unión de "mías" + "por firmar", sin duplicados.
        $vistos = [];
        $solicitudes = [];
        foreach (array_merge($porFirmar, $mias) as $s) {
            if (isset($vistos[$s['id']])) continue;
            $vistos[$s['id']] = true;
            if ($estado !== null && $s['estado'] !== $estado) continue;
            $solicitudes[] = $s;
        }
        $pendientes = count($porFirmar);
        $porFirmarIds = array_column($porFirmar, 'id');

        $estados = SolicitudTraslado::ESTADOS;
        $fEstado = $estado ?? '';
        require ROOT_PATH . '/app/views/solicitudes/index.php';
    }

    // ── Crear ───────────────────────────────────────────────────────────────

    public function crear(): void
    {
        if (!Permisos::puedeCrearSolicitudTraslado()) {
            $this->error('Sin permiso para crear solicitudes.', 'index.php?controller=solicitud&action=index');
        }

        $uid     = Permisos::idUsuario();
        $plazaId = Permisos::plazaId();

        $misAsignados = $this->activosAsignadosDe($uid);
        $bodegas      = $this->bodegasDePlaza($plazaId);
        $ingenieros   = $this->ingenierosDePlaza($plazaId, $uid);
        $tiendas      = $this->tiendasOperables($plazaId);
        $destinos     = SolicitudTraslado::DESTINOS;
        // Sacar equipo DE una bodega también requiere solicitud firmada; solo lo
        // puede iniciar quien administra la bodega (coordinador / admin).
        $puedeOrigenBodega = in_array(Permisos::tipo(), ['coordinador', 'admin'], true);

        require ROOT_PATH . '/app/views/solicitudes/crear.php';
    }

    public function guardar(): void
    {
        if (!Permisos::puedeCrearSolicitudTraslado()) {
            $this->error('Sin permiso.', 'index.php?controller=solicitud&action=index');
        }
        $this->requerirPost();

        $uid      = Permisos::idUsuario();
        $plazaId  = Permisos::plazaId();
        $destino  = (string) ($_POST['destino'] ?? '');
        $origen   = (string) ($_POST['origen_tipo'] ?? 'asignado'); // 'asignado' | 'tienda' | 'bodega'
        $activos  = array_values(array_unique(array_map('intval', $_POST['activos'] ?? [])));
        $nota     = trim((string) ($_POST['nota'] ?? ''));
        $firmaRaw = (string) ($_POST['firma'] ?? '');
        $volver   = 'index.php?controller=solicitud&action=crear';

        if (!isset(SolicitudTraslado::DESTINOS[$destino])) {
            $this->error('Destino inválido.', $volver);
        }
        if (!$activos) {
            $this->error('Selecciona al menos un activo.', $volver);
        }
        if ($firmaRaw === '') {
            $this->error('Falta tu firma.', $volver);
        }

        $datos = [
            'destino'        => $destino,
            'plaza_id'       => $plazaId,
            'solicitante_id' => $uid,
            'nota'           => $nota,
            'grupo_id'       => Movimiento::nuevoGrupoId(),
            'activos'        => $activos,
        ];

        // ── Origen ──────────────────────────────────────────────────────────
        if ($origen === 'tienda') {
            $tiendaId = (int) ($_POST['origen_tienda_id'] ?? 0);
            $tiendasOk = array_map('intval', array_column($this->tiendasOperables($plazaId), 'id'));
            if (!in_array($tiendaId, $tiendasOk, true)) {
                $this->error('Tienda de origen inválida.', $volver);
            }
            $validos = array_map('intval', array_column($this->activosEnUsoDeTienda($tiendaId), 'id'));
            $datos['origen_tienda_id'] = $tiendaId;
        } elseif ($origen === 'bodega') {
            if (!in_array(Permisos::tipo(), ['coordinador', 'admin'], true)) {
                $this->error('Solo un coordinador o admin puede sacar equipo de bodega.', $volver);
            }
            $bodegaId  = (int) ($_POST['origen_bodega_id'] ?? 0);
            $bodegasOk = array_map('intval', array_column($this->bodegasDePlaza($plazaId), 'id'));
            if (!in_array($bodegaId, $bodegasOk, true)) {
                $this->error('Bodega de origen inválida.', $volver);
            }
            if ($destino === 'en_bodega') {
                $this->error('El equipo ya está en bodega; elige otro destino.', $volver);
            }
            $validos = array_map('intval', array_column($this->activosEnBodega($bodegaId), 'id'));
            $datos['origen_bodega_id'] = $bodegaId;
        } else {
            $validos = array_map('intval', array_column($this->activosAsignadosDe($uid), 'id'));
            $datos['origen_usuario_id'] = $uid;
        }
        foreach ($activos as $aid) {
            if (!in_array($aid, $validos, true)) {
                $this->error('Un activo no pertenece al origen seleccionado o ya cambió de estado.', $volver);
            }
        }

        // ── Destino ─────────────────────────────────────────────────────────
        if ($destino === 'en_bodega') {
            $bodegaId  = (int) ($_POST['destino_bodega_id'] ?? 0);
            $bodegasOk = array_map('intval', array_column($this->bodegasDePlaza($plazaId), 'id'));
            if (!in_array($bodegaId, $bodegasOk, true)) {
                $this->error('Bodega destino inválida.', $volver);
            }
            $datos['destino_bodega_id'] = $bodegaId;
        } elseif ($destino === 'asignado') {
            $destUid = (int) ($_POST['destino_usuario_id'] ?? 0);
            if ($destUid <= 0 || $destUid === $uid) {
                $this->error('Elige al ingeniero que recibe el equipo.', $volver);
            }
            $um = new Usuario($this->db);
            if (!$um->obtenerPorId($destUid) || !$um->perteneceAPlaza($destUid, $plazaId)) {
                $this->error('El ingeniero que recibe no pertenece a tu plaza.', $volver);
            }
            $datos['destino_usuario_id'] = $destUid;
        }
        // baja / garantia: sin campo de destino extra.

        $model = new SolicitudTraslado($this->db);
        if ($model->activosEnSolicitudPendiente($activos)) {
            $this->error('Un activo ya está en otra solicitud pendiente.', $volver);
        }

        $firma = ImageHelper::guardarFirma($firmaRaw, 'firma_sol');
        if (!$firma) {
            $this->error('No se pudo procesar tu firma. Intenta de nuevo.', $volver);
        }
        $datos['firma_solicitante'] = $firma;

        $id = $model->crear($datos);
        if ($id <= 0) {
            $this->error('No se pudo crear la solicitud.', $volver);
        }

        $quien = match ($destino) {
            'asignado'  => 'del ingeniero que recibe',
            'en_bodega' => 'de un coordinador',
            'baja'      => 'del ATI',
            'garantia'  => 'del ATI y de un coordinador',
            default     => 'del aprobador',
        };
        $_SESSION['success'] = "Solicitud #{$id} enviada. Queda pendiente de la firma {$quien}.";
        $this->redirigir('index.php?controller=solicitud&action=ver&id=' . $id);
    }

    // ── Ver / firmar / rechazar / cancelar ──────────────────────────────────

    public function ver(): void
    {
        $id    = $this->idGet();
        $model = new SolicitudTraslado($this->db);
        $sol   = $model->obtenerPorId($id);
        if (!$sol) {
            $this->error('Solicitud no encontrada.', 'index.php?controller=solicitud&action=index');
        }
        if (!$this->puedeVerSolicitud($sol)) {
            $this->error('No tienes acceso a esta solicitud.', 'index.php?controller=solicitud&action=index');
        }

        $activos      = $model->activosDe($id);
        $puedeFirmar  = $this->slotDeUsuario($sol) !== null;
        $puedeCancelar = $sol['estado'] === 'pendiente'
            && in_array(Permisos::idUsuario(), [(int) $sol['solicitante_id'], (int) $sol['origen_usuario_id']], true);
        $movimientos  = $sol['estado'] === 'aprobada' && !empty($sol['grupo_id'])
            ? $this->movimientosDelGrupo((string) $sol['grupo_id'])
            : [];
        $estados  = SolicitudTraslado::ESTADOS;
        $destinos = SolicitudTraslado::DESTINOS;

        require ROOT_PATH . '/app/views/solicitudes/ver.php';
    }

    /** Registra la firma del usuario actual y, si ya están todas, ejecuta el movimiento. */
    public function aprobar(): void
    {
        $this->requerirPost();
        $id    = (int) ($_POST['id'] ?? 0);
        $model = new SolicitudTraslado($this->db);
        $sol   = $model->obtenerPorId($id);
        $ver   = 'index.php?controller=solicitud&action=ver&id=' . $id;

        if (!$sol || $sol['estado'] !== 'pendiente') {
            $this->error('La solicitud no está pendiente.', 'index.php?controller=solicitud&action=index');
        }
        $slot = $this->slotDeUsuario($sol);
        if ($slot === null) {
            $this->error('No te corresponde firmar esta solicitud.', $ver);
        }

        $firmaRaw = (string) ($_POST['firma'] ?? '');
        if ($firmaRaw === '') {
            $this->error('Falta tu firma.', $ver);
        }
        $firma = ImageHelper::guardarFirma($firmaRaw, 'firma_apr');
        if (!$firma) {
            $this->error('No se pudo procesar tu firma.', $ver);
        }

        $rol = $sol['destino'] === 'garantia'
            ? ($slot === 1 ? 'ati' : 'coordinador')
            : 'unico';

        try {
            $this->db->beginTransaction();
            $r = $model->firmarAprobacion($id, Permisos::idUsuario(), $firma, $rol);
            if ($r === false)  throw new \RuntimeException('La solicitud cambió de estado.');
            if ($r === 'duplicada')  throw new \RuntimeException('Ese rol ya había firmado.');
            if ($r === 'no_aplica')  throw new \RuntimeException('No te corresponde firmar esta solicitud.');
            if ($r === 'lista') {
                (new TrasladoService($this->db))->ejecutar($id, Permisos::idUsuario());
                $model->marcarAprobada($id);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->error('No se pudo firmar: ' . $e->getMessage(), $ver);
        }

        $_SESSION['success'] = ($r === 'lista')
            ? "Solicitud #{$id} aprobada. El movimiento se ejecutó."
            : "Firma registrada. Falta la otra firma para ejecutar el movimiento.";
        $this->redirigir($ver);
    }

    public function rechazar(): void
    {
        $this->requerirPost();
        $id     = (int) ($_POST['id'] ?? 0);
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $model  = new SolicitudTraslado($this->db);
        $sol    = $model->obtenerPorId($id);
        $ver    = 'index.php?controller=solicitud&action=ver&id=' . $id;

        if (!$sol || $sol['estado'] !== 'pendiente') {
            $this->error('La solicitud no está pendiente.', 'index.php?controller=solicitud&action=index');
        }
        if ($this->slotDeUsuario($sol) === null) {
            $this->error('No te corresponde resolver esta solicitud.', $ver);
        }
        if ($motivo === '') {
            $this->error('Indica el motivo del rechazo.', $ver);
        }
        if (!$model->marcarRechazada($id, Permisos::idUsuario(), $motivo)) {
            $this->error('No se pudo rechazar.', $ver);
        }
        $_SESSION['success'] = "Solicitud #{$id} rechazada. Los activos no se movieron.";
        $this->redirigir($ver);
    }

    public function cancelar(): void
    {
        $this->requerirPost();
        $id = (int) ($_POST['id'] ?? 0);
        if (!(new SolicitudTraslado($this->db))->marcarCancelada($id, Permisos::idUsuario())) {
            $this->error('No se pudo cancelar (solo el solicitante, y solo si está pendiente).', 'index.php?controller=solicitud&action=index');
        }
        $_SESSION['success'] = "Solicitud #{$id} cancelada.";
        $this->redirigir('index.php?controller=solicitud&action=index');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function activosAsignadosDe(int $usuarioId): array
    {
        return (new Activo($this->db))->obtenerTodosFiltrado(
            ['stock_usuario_id' => $usuarioId, 'status' => 'asignado'], 1, 1000
        )['activos'] ?? [];
    }

    private function activosEnUsoDeTienda(int $tiendaId): array
    {
        return (new Activo($this->db))->obtenerTodosFiltrado(
            ['tienda_id' => $tiendaId, 'status' => 'en_uso'], 1, 2000
        )['activos'] ?? [];
    }

    private function activosEnBodega(int $bodegaId): array
    {
        return (new Activo($this->db))->obtenerTodosFiltrado(
            ['bodega_id' => $bodegaId, 'status' => 'en_bodega'], 1, 5000
        )['activos'] ?? [];
    }

    private function bodegasDePlaza(int $plazaId): array
    {
        $b = new Bodega($this->db);
        $out = [];
        if ($oxxo = $b->obtenerPorPlazaYNegocio($plazaId, 'oxxo')) $out[(int) $oxxo['id']] = $oxxo;
        if ($any  = $b->obtenerPorPlaza($plazaId))                 $out[(int) $any['id']] = $out[(int) $any['id']] ?? $any;
        return array_values($out);
    }

    private function ingenierosDePlaza(int $plazaId, int $exceptoId): array
    {
        $us = (new Usuario($this->db))->obtenerPorPlaza($plazaId);
        return array_values(array_filter($us, fn($u) =>
            (int) $u['id'] !== $exceptoId && in_array($u['tipo'] ?? '', ['fs', 'ati', 'coordinador'], true)));
    }

    private function tiendasOperables(int $plazaId): array
    {
        if (Permisos::esAdmin()) {
            return (new Tienda($this->db))->obtenerTodas();
        }
        $out = [];
        foreach (Permisos::misPlazas() ?: [$plazaId] as $pid) {
            foreach ((new Tienda($this->db))->obtenerPorPlaza((int) $pid) as $t) {
                $out[(int) $t['id']] = $t;
            }
        }
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

    /**
     * ¿En qué slot de firma puede firmar el usuario actual? 1, 2 o null.
     *   destino=asignado → slot 1 solo el destino_usuario_id
     *   destino=en_bodega → slot 1 coordinador (o admin) de la plaza
     *   destino=baja → slot 1 ATI (o admin) de la plaza
     *   destino=garantia → slot 1 ATI, slot 2 coordinador (o admin en el que falte)
     */
    private function slotDeUsuario(array $sol): ?int
    {
        if ($sol['estado'] !== 'pendiente') return null;
        $uid  = Permisos::idUsuario();
        $tipo = Permisos::tipo();
        $enPlaza = Permisos::esAdmin() || in_array((int) $sol['plaza_id'], Permisos::misPlazas(), true);

        switch ($sol['destino']) {
            case 'asignado':
                return ((int) ($sol['destino_usuario_id'] ?? 0) === $uid && empty($sol['aprobador_id'])) ? 1 : null;

            case 'en_bodega':
                return ($enPlaza && in_array($tipo, ['coordinador', 'admin'], true) && empty($sol['aprobador_id'])) ? 1 : null;

            case 'baja':
                return ($enPlaza && in_array($tipo, ['ati', 'admin'], true) && empty($sol['aprobador_id'])) ? 1 : null;

            case 'garantia':
                if (!$enPlaza) return null;
                if (in_array($tipo, ['ati', 'admin'], true) && empty($sol['aprobador_id'])) return 1;
                if (in_array($tipo, ['coordinador', 'admin'], true) && empty($sol['aprobador2_id'])) return 2;
                return null;
        }
        return null;
    }

    private function movimientosDelGrupo(string $grupoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id, m.activo_id, m.evento, m.status_anterior, m.status_nuevo, m.creado_en, a.serie, a.codigo_barras
             FROM movimiento m LEFT JOIN activo a ON a.id = m.activo_id
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
        if ($id <= 0) $this->redirigir('index.php?controller=solicitud&action=index');
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
