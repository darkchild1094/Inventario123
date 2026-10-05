<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Movimiento;
use PDO;
use RuntimeException;

/**
 * MovimientoService — orquesta la escritura de la bitácora `movimiento` y la
 * ejecución de reemplazos (mover el activo saliente + registrar ambos lados).
 *
 * No abre transacciones propias: el controlador envuelve la operación completa
 * (guardar activo + registrar movimiento[s]) en una sola transacción.
 */
class MovimientoService
{
    private PDO $db;
    private Movimiento $mov;
    private Activo $activo;
    private StockResolver $stockResolver;

    public function __construct(PDO $db)
    {
        $this->db            = $db;
        $this->mov           = new Movimiento($db);
        $this->activo        = new Activo($db);
        $this->stockResolver = new StockResolver($db);
    }

    /**
     * Combina el motivo que escribe el usuario con la nota automática del sistema.
     * El motivo va primero (es lo que la gente busca en el historial); la nota
     * automática se conserva como contexto si cabe en varchar(255).
     */
    private static function nota(?string $motivo, ?string $auto): ?string
    {
        $motivo = trim((string) $motivo);
        $auto   = trim((string) $auto);
        if ($motivo === '') {
            return $auto !== '' ? mb_substr($auto, 0, 255) : null;
        }
        $combinada = $auto !== '' ? $motivo . ' — ' . $auto : $motivo;
        return mb_substr($combinada, 0, 255);
    }

    /**
     * Snapshot de un activo para datos_json: se congela con el movimiento para
     * que el historial siga siendo legible aunque el activo cambie o se borre.
     * Incluye dispositivo, marca, modelo, serie, código de barras y N° de activo.
     */
    public static function snapshot(array $a): array
    {
        return [
            'dispositivo'   => $a['dispositivo_nombre'] ?? null,
            'marca'         => $a['marca_nombre'] ?? null,
            'modelo'        => $a['modelo_nombre'] ?? null,
            'serie'         => $a['serie'] ?? null,
            'codigo_barras' => $a['codigo_barras'] ?? null,
            'num_activo'    => $a['num_activo'] ?? null,
            'status'        => $a['status'] ?? null,
        ];
    }

    /**
     * Registra el movimiento derivado de un alta o de una edición/guardado.
     *
     * @param array|null $antes         Activo::obtenerPorId() ANTES (null = alta)
     * @param array      $despues       Activo::obtenerPorId() DESPUÉS de guardar
     * @param int        $actorId       usuario que ejecuta
     * @param array      $opts          evento, nota, motivo, tienda_id, grupo_id, activo_relacionado_id
     */
    public function registrarGuardado(?array $antes, array $despues, int $actorId, array $opts = []): void
    {
        $evento = $opts['evento'] ?? null;
        if ($evento === null) {
            if ($antes === null) {
                $evento = 'alta';
            } elseif (($antes['status'] ?? null) !== ($despues['status'] ?? null)) {
                $evento = 'cambio_status';
            } elseif ((int) ($antes['stock_id'] ?? 0) !== (int) ($despues['stock_id'] ?? 0)) {
                $evento = 'cambio_stock';
            } else {
                $evento = 'edicion';
            }
        }

        $this->mov->registrar([
            'activo_id'             => (int) $despues['id'],
            'evento'                => $evento,
            'status_anterior'       => $antes['status'] ?? null,
            'status_nuevo'          => $despues['status'] ?? null,
            'stock_anterior_id'     => $antes['stock_id'] ?? null,
            'stock_nuevo_id'        => $despues['stock_id'] ?? null,
            'tienda_id'             => $opts['tienda_id'] ?? ($despues['tienda_uso_id'] ?? null),
            'plaza_id'              => $despues['plaza_id'] ?? ($antes['plaza_id'] ?? null),
            'activo_relacionado_id' => $opts['activo_relacionado_id'] ?? null,
            'grupo_id'              => $opts['grupo_id'] ?? null,
            'proyecto_rentec_id'    => $opts['proyecto_rentec_id'] ?? null,
            'usuario_id'            => $actorId ?: null,
            'nota'                  => self::nota($opts['motivo'] ?? null, $opts['nota'] ?? null),
            'datos_json'            => self::snapshot($despues),
        ]);
    }

    /** Registra la eliminación de un activo (llamar ANTES del DELETE físico). */
    public function registrarEliminacion(array $activo, int $actorId): void
    {
        $this->mov->registrar([
            'activo_id'        => (int) $activo['id'],
            'evento'           => 'eliminacion',
            'status_anterior'  => $activo['status'] ?? null,
            'stock_anterior_id' => $activo['stock_id'] ?? null,
            'tienda_id'        => $activo['tienda_uso_id'] ?? null,
            'plaza_id'         => $activo['plaza_id'] ?? null,
            'usuario_id'       => $actorId ?: null,
            'nota'             => 'Activo eliminado del sistema.',
            'datos_json'       => self::snapshot($activo),
        ]);
    }

    /**
     * Mueve el activo SALIENTE de un reemplazo a su destino y registra los dos
     * lados de la bitácora (reemplazo_entra / reemplazo_sale) con el mismo grupo.
     *
     * El activo ENTRANTE ya fue guardado por el controlador (en el stock de la
     * tienda); aquí sólo se registra su lado y se procesa el saliente.
     *
     * @param array $entra    Activo::obtenerPorId() del entrante (ya en la tienda)
     * @param int   $saleId   id del activo que sale
     * @param array $destino  ['status'=>'asignado|en_bodega|garantia|baja',
     *                          'asignado_usuario_id'=>?int, 'ati_usuario_id'=>?int]
     * @param int   $actorId
     * @param ?string $motivo  motivo del movimiento escrito por el usuario
     */
    public function ejecutarReemplazo(array $entra, int $saleId, array $destino, int $actorId, ?string $motivo = null, ?int $proyectoRentecId = null): void
    {
        $grupo    = Movimiento::nuevoGrupoId();
        $tiendaId = (int) ($entra['tienda_uso_id'] ?? 0);
        $plazaId  = (int) ($entra['plaza_id'] ?? 0);

        $sale = $this->activo->obtenerPorId($saleId);
        if (!$sale) {
            return;
        }

        // ── Lado ENTRA ────────────────────────────────────────────────
        $this->mov->registrar([
            'activo_id'             => (int) $entra['id'],
            'evento'                => 'reemplazo_entra',
            'status_nuevo'          => $entra['status'] ?? 'en_uso',
            'stock_nuevo_id'        => $entra['stock_id'] ?? null,
            'tienda_id'             => $tiendaId ?: null,
            'plaza_id'              => $plazaId ?: null,
            'activo_relacionado_id' => $saleId,
            'grupo_id'              => $grupo,
            'proyecto_rentec_id'    => $proyectoRentecId,
            'usuario_id'            => $actorId ?: null,
            'nota'                  => self::nota($motivo, 'Sustituye a la serie ' . ($sale['serie'] ?? '—') . '.'),
            'datos_json'            => self::snapshot($entra) + ['relacionado' => self::snapshot($sale)],
        ]);

        // ── Lado SALE: resolver stock destino y mover ────────────────
        $statusSale = Activo::normalizarStatus($destino['status'] ?? 'asignado');
        $duenoSale  = (int) ($destino['asignado_usuario_id'] ?? 0) ?: $actorId;

        // El reemplazo NO es una puerta trasera para cambiar de custodia sin
        // firma: la misma regla que bloquea la edición aplica aquí. Lo único
        // que pasa sin firma es el retiro al stock del propio actor, que es
        // justo lo que hace el formulario de Tiendas.
        $bloqueo = ActivoGuardado::requiereSolicitudFirmada(
            (string) ($sale['status'] ?? ''), $statusSale, $duenoSale, $actorId
        );
        if ($bloqueo !== null) {
            throw new \RuntimeException(
                "El equipo que sale no se puede {$bloqueo} desde un reemplazo: usa una Solicitud de movimiento firmada."
            );
        }

        $ctx = [
            'plaza_id'              => $plazaId,
            'tienda_id'             => $tiendaId,
            'procedencia_tienda_id' => $tiendaId,
            'asignado_usuario_id'   => $duenoSale,
            'ati_usuario_id'        => (int) ($destino['ati_usuario_id'] ?? 0) ?: null,
        ];
        $res       = $this->stockResolver->resolver($statusSale, $ctx);
        $stockSale = $res['stock'];
        $stockSaleId = $stockSale ? (int) $stockSale['id'] : (int) $sale['stock_id'];

        // El usuario puede corregir la serie / código de barras / N° de activo
        // del activo que sale desde el mismo formulario de reemplazo (p.ej. al
        // elegirlo de una lista, o tecleado a mano si no aparece en ella).
        $serieSale  = array_key_exists('serie', $destino) && trim((string) $destino['serie']) !== ''
                        ? trim((string) $destino['serie']) : $sale['serie'];
        $codigoSale = array_key_exists('codigo_barras', $destino)
                        ? (trim((string) $destino['codigo_barras']) ?: null) : $sale['codigo_barras'];
        $numActivoSale = array_key_exists('num_activo', $destino) && trim((string) $destino['num_activo']) !== ''
                        ? trim((string) $destino['num_activo']) : $sale['num_activo'];

        $datosSale = [
            'id'                    => $saleId,
            'serie'                 => $serieSale,
            'codigo_barras'         => $codigoSale,
            'num_activo'            => $numActivoSale,
            'modelo_id'             => $sale['modelo_id'],
            'status'                => $statusSale,
            'procedencia_tienda_id' => $tiendaId ?: null,
            'tienda_uso_id'         => null,
            'stock_id'              => $stockSaleId,
        ];
        // Foto del equipo retirado, si el formulario la envió.
        if (!empty($destino['foto_equipo'])) {
            $datosSale['foto_equipo'] = (string) $destino['foto_equipo'];
        }
        // Si el UPDATE falla hay que abortar: antes se ignoraba el retorno y se
        // registraba un movimiento de un traslado que nunca ocurrió.
        if (!$this->activo->actualizar($datosSale)) {
            throw new \RuntimeException('No se pudo mover el equipo que sale del reemplazo; no se registró el movimiento.');
        }

        $saleDespues = $this->activo->obtenerPorId($saleId);
        $this->mov->registrar([
            'activo_id'             => $saleId,
            'evento'                => 'reemplazo_sale',
            'status_anterior'       => $sale['status'] ?? null,
            'status_nuevo'          => $statusSale,
            'stock_anterior_id'     => $sale['stock_id'] ?? null,
            'stock_nuevo_id'        => $stockSaleId,
            'tienda_id'             => $tiendaId ?: null,
            'plaza_id'              => $plazaId ?: null,
            'activo_relacionado_id' => (int) $entra['id'],
            'grupo_id'              => $grupo,
            'proyecto_rentec_id'    => $proyectoRentecId,
            'usuario_id'            => $actorId ?: null,
            'nota'                  => self::nota($motivo, trim('Reemplazado por la serie ' . ($entra['serie'] ?? '—') . '. ' . ($res['nota'] ?? ''))),
            'datos_json'            => self::snapshot($saleDespues ?: $sale) + ['relacionado' => self::snapshot($entra)],
        ]);
    }
}
