<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Movimiento;
use PDO;

/**
 * EnvioService — "Enviar" activos de golpe (Bodega↔Mi stock↔usuario),
 * inmediato y sin aceptación del receptor (a diferencia del viejo
 * SolicitudTraslado/TrasladoService, que exigía firma o aceptación). Mueve
 * cada activo y deja un solo `grupo_id` compartido en la bitácora para todo
 * el lote; la notificación del destinatario se arma aparte (ver
 * ApiController::enviarActivos / Models\Notificacion).
 */
class EnvioService
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
     * @param int[] $activoIds
     * @param string $destinoTipo  'bodega' | 'usuario'
     * @param array $ctx  ['plaza_id'=>int (bodega destino), 'asignado_usuario_id'=>int (usuario destino)]
     * @return array{ok:bool, grupo_id:?string, error:?string}
     */
    public function enviar(array $activoIds, string $destinoTipo, array $ctx, int $actorId): array
    {
        $statusNuevo = $destinoTipo === 'bodega' ? 'en_bodega' : 'asignado';
        $prep = $this->stockResolver->resolver($statusNuevo, $ctx);
        if (!$prep['stock']) {
            return ['ok' => false, 'grupo_id' => null, 'error' => $prep['nota'] ?? 'No se pudo resolver el destino.'];
        }
        $stockDestinoId = (int) $prep['stock']['id'];
        $plazaDestino   = (int) ($prep['stock']['plaza_id'] ?? $ctx['plaza_id'] ?? 0);
        $grupoId = Movimiento::nuevoGrupoId();

        try {
            $this->db->beginTransaction();

            foreach ($activoIds as $id) {
                $antes = $this->activo->obtenerPorId((int) $id);
                if (!$antes) continue;

                $datos = $antes;
                $datos['id']       = $antes['id'];
                $datos['status']   = $statusNuevo;
                $datos['stock_id'] = $stockDestinoId;
                // en_bodega/asignado no llevan tienda de uso ni procedencia nueva.
                $datos['tienda_uso_id'] = null;

                if (!$this->activo->actualizar($datos)) {
                    $this->db->rollBack();
                    return ['ok' => false, 'grupo_id' => null,
                            'error' => $this->activo->ultimoError() ?? 'No se pudo mover un activo.'];
                }
                $despues = $this->activo->obtenerPorId((int) $id);
                $despues['plaza_id'] = $despues['plaza_id'] ?? $plazaDestino;
                $this->mov->registrarGuardado($antes, $despues, $actorId, [
                    'evento'   => 'cambio_stock',
                    'grupo_id' => $grupoId,
                ]);
            }

            $this->db->commit();
            return ['ok' => true, 'grupo_id' => $grupoId, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['ok' => false, 'grupo_id' => null, 'error' => 'No se pudo completar el envío: ' . $e->getMessage()];
        }
    }
}
