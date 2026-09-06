<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Tienda;
use App\Models\Usuario;
use PDO;
use Throwable;

/**
 * ActivoGuardado — orquesta el alta y la edición de un activo:
 *   1. valida el destino según rol (asignado a quién, tienda de qué plaza…)
 *   2. resuelve el stock con StockResolver (estatus → dueño)
 *   3. persiste el activo (Activo::crear / actualizar)
 *   4. registra la bitácora (MovimientoService) y, si aplica, el reemplazo
 *
 * Reemplaza la lógica de stock duplicada que vivía en HomeController y
 * ApiController. Ambos controladores ahora sólo parsean su POST y delegan aquí.
 */
class ActivoGuardado
{
    private PDO $db;
    private Activo $activo;
    private StockResolver $stockResolver;
    private MovimientoService $mov;

    public function __construct(PDO $db)
    {
        $this->db            = $db;
        $this->activo        = new Activo($db);
        $this->stockResolver = new StockResolver($db);
        $this->mov           = new MovimientoService($db);
    }

    /**
     * @param array $datos   serie, codigo_barras, num_activo, modelo_id, status, procedencia_tienda_id,
     *                       tienda_uso_id (+ fotos ya resueltas por el controlador)
     * @param array $post    $_POST crudo (para asignado_usuario_id, reemplaza_activo_id, etc.)
     * @param array $actor   ['id'=>int, 'tipo'=>string, 'plazas'=>int[], 'plaza_id'=>int]
     * @return array{ok:bool, id:?int, error:?string}
     */
    public function crear(array $datos, array $post, array $actor): array
    {
        $prep = $this->prepararStock($datos, $post, $actor, null);
        if ($prep['error']) {
            return ['ok' => false, 'id' => null, 'error' => $prep['error']];
        }
        $datos['stock_id']     = $prep['stock_id'];
        $datos['tienda_uso_id'] = $prep['tienda_uso_id'];

        try {
            $this->db->beginTransaction();

            if (!$this->activo->crear($datos)) {
                $this->db->rollBack();
                return ['ok' => false, 'id' => null, 'error' => 'Error al guardar. Verifique que la serie no esté duplicada.'];
            }
            $id      = $this->activo->ultimoId();
            $despues = $this->activo->obtenerPorId($id);

            $motivo = trim((string) ($post['motivo'] ?? '')) ?: null;
            $this->mov->registrarGuardado(null, $despues, (int) $actor['id'], [
                'tienda_id' => $prep['ctx']['tienda_id'] ?? null,
                'nota'      => $prep['nota'],
                'motivo'    => $motivo,
            ]);

            $this->procesarReemplazo($despues, $datos['status'], $post, (int) $actor['id']);

            $this->db->commit();
            return ['ok' => true, 'id' => $id, 'error' => null];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('ActivoGuardado::crear ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'error' => 'Ocurrió un error al registrar el activo.'];
        }
    }

    /**
     * @param array $antes  Activo::obtenerPorId() previo (ya validado el permiso por el controlador)
     */
    public function actualizar(int $id, array $datos, array $antes, array $post, array $actor): array
    {
        $prep = $this->prepararStock($datos, $post, $actor, $antes);
        if ($prep['error']) {
            return ['ok' => false, 'id' => null, 'error' => $prep['error']];
        }
        $datos['id']            = $id;
        $datos['stock_id']      = $prep['stock_id'];
        $datos['tienda_uso_id'] = $prep['tienda_uso_id'];

        try {
            $this->db->beginTransaction();

            if (!$this->activo->actualizar($datos)) {
                $this->db->rollBack();
                return ['ok' => false, 'id' => null, 'error' => 'Error al actualizar. Verifique que la serie no esté duplicada.'];
            }
            $despues = $this->activo->obtenerPorId($id);

            $motivo = trim((string) ($post['motivo'] ?? '')) ?: null;
            $this->mov->registrarGuardado($antes, $despues, (int) $actor['id'], [
                'tienda_id' => $prep['ctx']['tienda_id'] ?? null,
                'nota'      => $prep['nota'],
                'motivo'    => $motivo,
            ]);

            $this->procesarReemplazo($despues, $datos['status'], $post, (int) $actor['id']);

            $this->db->commit();
            return ['ok' => true, 'id' => $id, 'error' => null];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('ActivoGuardado::actualizar ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'error' => 'Ocurrió un error al actualizar el activo.'];
        }
    }

    // ── internos ─────────────────────────────────────────────────────

    /**
     * Valida el destino según rol y resuelve el stock.
     * @return array{stock_id:?int, tienda_uso_id:?int, ctx:array, nota:?string, error:?string}
     */
    private function prepararStock(array $datos, array $post, array $actor, ?array $antes): array
    {
        $status  = Activo::normalizarStatus($datos['status'] ?? 'en_bodega');
        $tipo    = $actor['tipo'] ?? '';

        // Activo con una solicitud de traslado 'pendiente' → bloqueado hasta
        // que el coordinador la resuelva (aprobar/rechazar) o el ingeniero la cancele.
        if ($antes !== null && !empty($antes['id'])) {
            $enPendiente = (new \App\Models\SolicitudTraslado($this->db))
                ->activosEnSolicitudPendiente([(int) $antes['id']]);
            if ($enPendiente) {
                return $this->err('Este activo tiene una solicitud de traslado pendiente de aprobación; no se puede modificar hasta resolverla.');
            }
        }
        $actorId = (int) ($actor['id'] ?? 0);
        $misPlazas = array_map('intval', $actor['plazas'] ?? []);
        if (!$misPlazas && !empty($actor['plaza_id'])) $misPlazas = [(int) $actor['plaza_id']];

        // plaza de trabajo: la posteada si el usuario tiene acceso, o la del activo, o la 1ª suya
        $plazaId = (int) ($post['plaza_id'] ?? 0);
        if ($tipo !== 'admin' && $plazaId > 0 && $misPlazas && !in_array($plazaId, $misPlazas, true)) {
            $plazaId = 0;
        }
        if ($plazaId <= 0) {
            $plazaId = (int) ($antes['plaza_id'] ?? 0) ?: ($misPlazas[0] ?? (int) ($actor['plaza_id'] ?? 0));
        }

        $ctx = [
            'plaza_id'              => $plazaId,
            'tienda_id'             => null,
            'procedencia_tienda_id' => !empty($datos['procedencia_tienda_id']) ? (int) $datos['procedencia_tienda_id'] : null,
            'asignado_usuario_id'   => null,
            'ati_usuario_id'        => !empty($post['ati_usuario_id']) ? (int) $post['ati_usuario_id'] : null,
            'bodega_id'             => null,
        ];
        $tiendaUsoId = null;

        if ($status === 'en_uso') {
            $tiendaUsoId = (int) ($datos['tienda_uso_id'] ?? 0);
            if ($tiendaUsoId <= 0) {
                return $this->err('Debes seleccionar la tienda donde queda en uso el activo.');
            }
            $tienda = (new Tienda($this->db))->obtenerPorId($tiendaUsoId);
            if (!$tienda) {
                return $this->err('La tienda seleccionada no existe.');
            }
            if ($tipo !== 'admin' && $misPlazas && !in_array((int) $tienda['plaza_id'], $misPlazas, true)) {
                return $this->err('La tienda seleccionada no pertenece a tu plaza.');
            }
            $ctx['tienda_id'] = $tiendaUsoId;
            $ctx['plaza_id']  = (int) $tienda['plaza_id'];
        } elseif ($status === 'asignado') {
            $destino = (int) ($post['asignado_usuario_id'] ?? 0) ?: $actorId;
            if ($tipo === 'fs') {
                $destino = $actorId;
            } elseif (in_array($tipo, ['ati', 'coordinador'], true) && $destino !== $actorId) {
                $um = new Usuario($this->db);
                if (!$um->obtenerPorId($destino) || !$um->perteneceAPlaza($destino, $plazaId)) {
                    return $this->err('Solo puedes asignar activos a usuarios de la plaza seleccionada.');
                }
            }
            $ctx['asignado_usuario_id'] = $destino;
        } elseif ($status === 'en_bodega') {
            // Un ingeniero (fs) NO puede mandar su stock 'asignado' a bodega
            // directo: debe pasar por una Solicitud de traslado (doble firma).
            if ($tipo === 'fs' && ($antes['status'] ?? null) === 'asignado') {
                return $this->err('Para mandar equipo a bodega usa una Solicitud de traslado (requiere la firma del coordinador).');
            }
            $destino = trim((string) ($post['stock_destino'] ?? ''));
            if ($destino !== '' && str_starts_with($destino, 'bodega_') && in_array($tipo, ['admin', 'coordinador'], true)) {
                $ctx['bodega_id'] = (int) explode('_', $destino, 2)[1];
            }
        } else { // garantia | baja
            $ctx['tienda_id'] = (int) ($datos['tienda_uso_id'] ?? ($antes['tienda_uso_id'] ?? 0)) ?: null;
        }

        $res = $this->stockResolver->resolver($status, $ctx);
        if (!$res['stock']) {
            return $this->err($res['nota'] ?? 'No se pudo determinar el stock de destino.');
        }

        return [
            'stock_id'      => (int) $res['stock']['id'],
            'tienda_uso_id' => $status === 'en_uso' ? $tiendaUsoId : (!empty($datos['tienda_uso_id']) ? (int) $datos['tienda_uso_id'] : null),
            'ctx'           => $ctx,
            'nota'          => $res['nota'],
            'error'         => null,
        ];
    }

    private function procesarReemplazo(array $entra, string $status, array $post, int $actorId): void
    {
        $reemplazaId = (int) ($post['reemplaza_activo_id'] ?? 0);
        if ($status !== 'en_uso' || $reemplazaId <= 0 || $reemplazaId === (int) $entra['id']) {
            return;
        }
        $motivo = trim((string) ($post['motivo'] ?? '')) ?: null;
        $destino = [
            'status'              => $post['salida_destino'] ?? 'asignado',
            'asignado_usuario_id' => (int) ($post['salida_usuario_id'] ?? 0),
            'ati_usuario_id'      => (int) ($post['salida_ati_usuario_id'] ?? 0),
        ];
        // Corrección opcional de serie / código de barras del activo que sale.
        if (array_key_exists('salida_serie', $post)) {
            $destino['serie'] = (string) $post['salida_serie'];
        }
        if (array_key_exists('salida_codigo_barras', $post)) {
            $destino['codigo_barras'] = (string) $post['salida_codigo_barras'];
        }
        $this->mov->ejecutarReemplazo($entra, $reemplazaId, $destino, $actorId, $motivo);
    }

    private function err(string $msg): array
    {
        return ['stock_id' => null, 'tienda_uso_id' => null, 'ctx' => [], 'nota' => null, 'error' => $msg];
    }
}
