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
                // El motivo real lo sabe el modelo. El mensaje viejo culpaba
                // siempre a la serie, que ni siquiera tiene índice único.
                return ['ok' => false, 'id' => null,
                        'error' => $this->activo->ultimoError() ?? 'No se pudo guardar el activo.'];
            }
            $id      = $this->activo->ultimoId();
            $despues = $this->activo->obtenerPorId($id);

            $motivo = trim((string) ($post['motivo'] ?? '')) ?: null;
            $this->mov->registrarGuardado(null, $despues, (int) $actor['id'], [
                'tienda_id'          => $prep['ctx']['tienda_id'] ?? null,
                'nota'               => $prep['nota'],
                'motivo'             => $motivo,
                'proyecto_rentec_id' => $this->proyectoRentecId($post),
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
                return ['ok' => false, 'id' => null,
                        'error' => $this->activo->ultimoError() ?? 'No se pudo actualizar el activo.'];
            }
            $despues = $this->activo->obtenerPorId($id);

            $motivo = trim((string) ($post['motivo'] ?? '')) ?: null;
            $this->mov->registrarGuardado($antes, $despues, (int) $actor['id'], [
                'tienda_id'          => $prep['ctx']['tienda_id'] ?? null,
                'nota'               => $prep['nota'],
                'motivo'             => $motivo,
                'proyecto_rentec_id' => $this->proyectoRentecId($post),
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

    /**
     * ÚNICA fuente de verdad de "¿este cambio de custodia exige una Solicitud
     * de movimiento firmada?". Devuelve la etiqueta de la acción bloqueada, o
     * null si el cambio se puede hacer directo.
     *
     * La firma protege que un equipo no cambie de MANOS sin que alguien lo
     * autorice. Mover equipo que ya traes contigo a un lugar físico (una tienda
     * o la bodega) no cambia de manos, así que no la pide.
     *
     * Permitido sin firma:
     *   · cualquier cosa → 'en_uso'     (instalar o mover entre tiendas)
     *   · cualquier cosa → 'en_bodega'  (retiro a bodega: el equipo ya está en
     *     manos del técnico; escanearlo en bodega mueve el registro y queda
     *     asentado en la bitácora con quién y cuándo)
     *   · el estatus no cambia
     *   · 'en_uso' → 'asignado' cuando el destino es el propio actor
     *     (retiro directo a tu stock: la custodia no pasa a un tercero)
     *   · el activo no estaba bajo custodia (p. ej. un alta)
     *
     * Sigue exigiendo firma: traspasar a OTRO ingeniero, dar de baja y enviar a
     * garantía. Quién puede tocar cada activo lo decide aparte
     * Permisos::puedeEditarActivoConcreto().
     *
     * La llaman prepararStock() (edición) y MovimientoService::ejecutarReemplazo()
     * (el equipo que sale de un reemplazo).
     *
     * @param int $duenoNuevo dueño destino YA resuelto (cuando $stNuevo es 'asignado')
     * @param int $duenoAntes dueño actual, si el activo estaba en un stock personal
     */
    public static function requiereSolicitudFirmada(
        string $stAntes,
        string $stNuevo,
        int $duenoNuevo,
        int $actorId,
        int $duenoAntes = 0
    ): ?string {
        // Mover a un lugar físico no cambia de manos.
        if ($stNuevo === 'en_uso' || $stNuevo === 'en_bodega') return null;
        // Un activo que no estaba bajo custodia (un alta) no tiene nada que proteger.
        if (!in_array($stAntes, ['asignado', 'en_uso', 'en_bodega'], true)) return null;

        if ($stNuevo === 'asignado') {
            // OJO: aquí no basta con "el estatus no cambió". Un activo puede ir de
            // 'asignado' a 'asignado' y aun así cambiar de dueño, que es
            // exactamente el traspaso que la firma debe cubrir. Antes se permitía
            // porque la guarda sólo miraba el estatus.
            if ($stAntes === 'asignado' && $duenoNuevo > 0 && $duenoNuevo === $duenoAntes) {
                return null; // sigue con el mismo ingeniero
            }
            // Retiro directo de una tienda al stock personal de quien lo retira.
            if ($stAntes === 'en_uso' && $duenoNuevo === $actorId) return null;

            return 'traspasar equipo a otro ingeniero';
        }

        // baja | garantia: si ya estaba así, no hay cambio que autorizar.
        if ($stNuevo === $stAntes) return null;

        return [
            'baja'     => 'dar de baja',
            'garantia' => 'enviar a garantía',
        ][$stNuevo] ?? null;
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

        if ($antes !== null && !empty($antes['id'])) {
            // 1) Activo con una solicitud de movimiento 'pendiente' → bloqueado
            //    hasta que se resuelva (aprobar / rechazar / cancelar).
            $enPendiente = (new \App\Models\SolicitudTraslado($this->db))
                ->activosEnSolicitudPendiente([(int) $antes['id']]);
            if ($enPendiente) {
                return $this->err('Este activo tiene una solicitud de movimiento pendiente; no se puede modificar hasta resolverla.');
            }

            // 2) Cambio de custodia → exige Solicitud firmada. La regla vive en
            //    requiereSolicitudFirmada() para que el reemplazo la aplique
            //    también (antes el reemplazo la esquivaba por completo).
            // El dueño destino que se evalúa tiene que ser el EFECTIVO, el mismo
            // que resolverá más abajo: si no viene en el POST se asume uno mismo,
            // y a un pfs siempre se le fuerza a sí mismo. Comparar el valor crudo
            // bloqueaba ediciones legítimas (el formulario no manda el campo) y
            // daba un error confuso cuando un pfs posteaba otro usuario.
            $actorId = (int) ($actor['id'] ?? 0);
            $duenoEfectivo = (int) ($post['asignado_usuario_id'] ?? 0) ?: $actorId;
            if (($actor['tipo'] ?? '') === 'pfs') $duenoEfectivo = $actorId;

            $lbl = self::requiereSolicitudFirmada(
                (string) ($antes['status'] ?? ''),
                $status,
                $duenoEfectivo,
                $actorId,
                (int) ($antes['usuario_stock_id'] ?? 0)
            );
            if ($lbl !== null) {
                return $this->err("Para {$lbl} usa una Solicitud de movimiento (requiere firma y autorización). No se puede cambiar el estatus directo aquí.");
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
            if ($tipo === 'pfs') {
                $destino = $actorId;
            } elseif (in_array($tipo, ['ati', 'coordinador'], true) && $destino !== $actorId) {
                $um = new Usuario($this->db);
                if (!$um->obtenerPorId($destino) || !$um->perteneceAPlaza($destino, $plazaId)) {
                    return $this->err('Solo puedes asignar activos a usuarios de la plaza seleccionada.');
                }
            }
            $ctx['asignado_usuario_id'] = $destino;
        } elseif ($status === 'en_bodega') {
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
        // Corrección opcional de serie / código de barras / N° de activo del
        // que sale (num_activo no se muestra en la app, pero sí se exporta
        // en el Excel de RENTEC).
        if (array_key_exists('salida_serie', $post)) {
            $destino['serie'] = (string) $post['salida_serie'];
        }
        if (array_key_exists('salida_codigo_barras', $post)) {
            $destino['codigo_barras'] = (string) $post['salida_codigo_barras'];
        }
        if (array_key_exists('salida_num_activo', $post)) {
            $destino['num_activo'] = (string) $post['salida_num_activo'];
        }
        // Fotos del equipo que sale (ya procesadas por el controlador → nombre de
        // archivo). Son las mismas tres que lleva el que entra: equipo, serie y
        // código de barras. Antes solo viajaba la del equipo.
        foreach ([
            'salida_foto_equipo' => 'foto_equipo',
            'salida_foto_serie'  => 'foto_serie',
            'salida_foto_activo' => 'foto_activo',
        ] as $origen => $campo) {
            if (!empty($post[$origen])) $destino[$campo] = (string) $post[$origen];
        }
        $this->mov->ejecutarReemplazo($entra, $reemplazaId, $destino, $actorId, $motivo, $this->proyectoRentecId($post));
    }

    private function proyectoRentecId(array $post): ?int
    {
        return !empty($post['proyecto_rentec_id']) ? (int) $post['proyecto_rentec_id'] : null;
    }

    private function err(string $msg): array
    {
        return ['stock_id' => null, 'tienda_uso_id' => null, 'ctx' => [], 'nota' => null, 'error' => $msg];
    }
}
