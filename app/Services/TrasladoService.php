<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Movimiento;
use App\Models\SolicitudTraslado;
use PDO;
use RuntimeException;

/**
 * TrasladoService — ejecuta una solicitud de traslado ya APROBADA: mueve cada
 * activo del stock personal del ingeniero ('asignado') a la bodega destino
 * ('en_bodega') y registra un `movimiento` por activo, todos con el mismo
 * grupo_id y el solicitud_traslado_id de la solicitud.
 *
 * No abre transacción propia: el controlador envuelve
 * (marcarAprobada + guardarFirma + ejecutar) en una sola transacción.
 */
class TrasladoService
{
    private PDO $db;
    private Activo $activo;
    private Movimiento $mov;
    private StockResolver $stockResolver;
    private SolicitudTraslado $solicitud;

    public function __construct(PDO $db)
    {
        $this->db            = $db;
        $this->activo        = new Activo($db);
        $this->mov           = new Movimiento($db);
        $this->stockResolver = new StockResolver($db);
        $this->solicitud     = new SolicitudTraslado($db);
    }

    /**
     * @throws RuntimeException si algún activo ya no está 'asignado' o no se
     *         puede resolver la bodega destino.
     */
    public function ejecutar(int $solicitudId, int $actorId): void
    {
        $sol = $this->solicitud->obtenerPorId($solicitudId);
        if (!$sol) {
            throw new RuntimeException('La solicitud no existe.');
        }

        $grupo    = $sol['grupo_id'] ?: Movimiento::nuevoGrupoId();
        $plazaId  = (int) $sol['plaza_id'];
        $bodegaId = (int) $sol['destino_bodega_id'];

        $res = $this->stockResolver->resolver('en_bodega', [
            'plaza_id'  => $plazaId,
            'bodega_id' => $bodegaId,
        ]);
        if (empty($res['stock'])) {
            throw new RuntimeException($res['nota'] ?? 'No se pudo resolver la bodega destino.');
        }
        $stockDestinoId = (int) $res['stock']['id'];

        $activos = $this->solicitud->activosDe($solicitudId);
        if (!$activos) {
            throw new RuntimeException('La solicitud no tiene activos.');
        }

        foreach ($activos as $fila) {
            $antes = $this->activo->obtenerPorId((int) $fila['id']);
            if (!$antes) {
                throw new RuntimeException("El activo #{$fila['id']} ya no existe.");
            }
            if (($antes['status'] ?? '') !== 'asignado') {
                throw new RuntimeException(
                    "El activo con serie " . ($antes['serie'] ?? $fila['id']) .
                    " ya no está asignado al ingeniero; cancela y crea una solicitud nueva."
                );
            }

            $this->activo->actualizar([
                'id'                    => (int) $antes['id'],
                'serie'                 => $antes['serie'],
                'codigo_barras'         => $antes['codigo_barras'],
                'num_activo'            => $antes['num_activo'],
                'modelo_id'             => $antes['modelo_id'],
                'status'                => 'en_bodega',
                'procedencia_tienda_id' => $antes['procedencia_tienda_id'] ?? null,
                'tienda_uso_id'         => null,
                'stock_id'              => $stockDestinoId,
            ]);

            // Activo::actualizar hace trim(serie ?? '') y convertiría un NULL en ''.
            // Restauramos el NULL para no ensuciar el dato (migración 019).
            if ($antes['serie'] === null) {
                $this->db->prepare("UPDATE activo SET serie = NULL WHERE id = :id AND serie = ''")
                    ->execute([':id' => (int) $antes['id']]);
            }

            $despues = $this->activo->obtenerPorId((int) $antes['id']);

            $this->mov->registrar([
                'activo_id'             => (int) $antes['id'],
                'evento'                => 'cambio_status',
                'status_anterior'       => 'asignado',
                'status_nuevo'          => 'en_bodega',
                'stock_anterior_id'     => $antes['stock_id'] ?? null,
                'stock_nuevo_id'        => $stockDestinoId,
                'plaza_id'              => $plazaId ?: ($despues['plaza_id'] ?? null),
                'grupo_id'              => $grupo,
                'solicitud_traslado_id' => $solicitudId,
                'usuario_id'            => $actorId ?: null,
                'nota'                  => mb_substr(
                    'Traslado a bodega aprobado (solicitud #' . $solicitudId . ', firma digital de solicitante y coordinador).',
                    0,
                    255
                ),
                'datos_json'            => MovimientoService::snapshot($despues ?: $antes),
            ]);
        }
    }
}
