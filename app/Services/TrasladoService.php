<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Movimiento;
use App\Models\SolicitudTraslado;
use PDO;
use RuntimeException;

/**
 * TrasladoService — ejecuta una solicitud de movimiento ya con todas sus firmas:
 * mueve cada activo a su nuevo estatus/dueño (asignado, en_bodega, baja o
 * garantía) y registra un `movimiento` por activo, todos con el mismo grupo_id
 * y el solicitud_traslado_id de la solicitud.
 *
 * No abre transacción propia: el controlador envuelve firma + ejecución en una.
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

    /** @throws RuntimeException si un activo ya no está en el estado esperado. */
    public function ejecutar(int $solicitudId, int $actorId): void
    {
        $sol = $this->solicitud->obtenerPorId($solicitudId);
        if (!$sol) {
            throw new RuntimeException('La solicitud no existe.');
        }

        $grupo   = $sol['grupo_id'] ?: Movimiento::nuevoGrupoId();
        $plazaId = (int) $sol['plaza_id'];
        $destino = $sol['destino'];

        // ── Resolver el stock destino según el tipo de movimiento ────────────
        $ctx = [
            'plaza_id'            => $plazaId,
            'bodega_id'           => (int) ($sol['destino_bodega_id'] ?? 0),
            'asignado_usuario_id' => (int) ($sol['destino_usuario_id'] ?? 0),
            'tienda_id'           => (int) ($sol['origen_tienda_id'] ?? 0),
            'procedencia_tienda_id' => (int) ($sol['origen_tienda_id'] ?? 0),
        ];
        $res = $this->stockResolver->resolver($destino, $ctx);
        if (empty($res['stock'])) {
            throw new RuntimeException($res['nota'] ?? 'No se pudo resolver el stock destino.');
        }
        $stockDestinoId = (int) $res['stock']['id'];
        $notaResolver   = $res['nota'] ?? '';

        $activos = $this->solicitud->activosDe($solicitudId);
        if (!$activos) {
            throw new RuntimeException('La solicitud no tiene activos.');
        }

        $etiqueta = SolicitudTraslado::DESTINOS[$destino] ?? $destino;

        foreach ($activos as $fila) {
            $antes = $this->activo->obtenerPorId((int) $fila['id']);
            if (!$antes) {
                throw new RuntimeException("El activo #{$fila['id']} ya no existe.");
            }
            $statusAntes = $antes['status'] ?? '';
            // No se puede mover un activo dado de baja / eliminado.
            if (in_array($statusAntes, ['baja', 'eliminacion'], true)) {
                throw new RuntimeException(
                    'El activo ' . ($antes['serie'] ?: ($antes['codigo_barras'] ?: $fila['id'])) .
                    " está dado de baja; no se puede mover."
                );
            }

            $this->activo->actualizar([
                'id'                    => (int) $antes['id'],
                'serie'                 => $antes['serie'],
                'codigo_barras'         => $antes['codigo_barras'],
                'num_activo'            => $antes['num_activo'],
                'modelo_id'             => $antes['modelo_id'],
                'status'                => $destino,
                'procedencia_tienda_id' => $antes['tienda_uso_id'] ?? ($antes['procedencia_tienda_id'] ?? null),
                'tienda_uso_id'         => null,
                'stock_id'              => $stockDestinoId,
            ]);

            // Activo::actualizar hace trim(serie ?? '') → un NULL se volvería ''.
            if ($antes['serie'] === null) {
                $this->db->prepare("UPDATE activo SET serie = NULL WHERE id = :id AND serie = ''")
                    ->execute([':id' => (int) $antes['id']]);
            }

            $despues = $this->activo->obtenerPorId((int) $antes['id']);

            $this->mov->registrar([
                'activo_id'             => (int) $antes['id'],
                'evento'                => 'cambio_status',
                'status_anterior'       => $statusAntes ?: null,
                'status_nuevo'          => $destino,
                'stock_anterior_id'     => $antes['stock_id'] ?? null,
                'stock_nuevo_id'        => $stockDestinoId,
                'tienda_id'             => (int) ($sol['origen_tienda_id'] ?? 0) ?: null,
                'plaza_id'              => $plazaId ?: ($despues['plaza_id'] ?? null),
                'grupo_id'             => $grupo,
                'solicitud_traslado_id' => $solicitudId,
                'usuario_id'            => $actorId ?: null,
                'nota'                  => mb_substr(
                    trim("{$etiqueta} — solicitud #{$solicitudId}, firma digital. {$notaResolver}"),
                    0,
                    255
                ),
                'datos_json'            => MovimientoService::snapshot($despues ?: $antes),
            ]);
        }
    }
}
