<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Bodega;
use App\Models\Tienda;
use PDO;

/**
 * StockResolver — única fuente de verdad de "¿a qué stock pertenece un activo
 * según su estatus?". Centraliza la lógica que antes estaba duplicada y
 * dispersa en HomeController y ApiController.
 *
 *   estatus      →  dueño del stock
 *   ─────────────────────────────────────────────────────────────────────
 *   en_uso       →  la TIENDA          (stock.tipo = 'tienda')
 *   asignado     →  un USUARIO         (stock.tipo = 'usuario', scopeado a plaza)
 *   en_bodega    →  la BODEGA de plaza (stock.tipo = 'bodega')
 *   garantia|baja→  el ATI de la tienda (stock.tipo = 'usuario' del ati_usuario_id)
 *
 * El resolver CONFÍA en el $ctx que le pasa el controlador: la validación de
 * permisos (perteneceAPlaza, fs→sí mismo, etc.) se hace antes, en el controlador.
 */
class StockResolver
{
    private PDO $db;
    private Stock $stock;
    private Bodega $bodega;
    private Tienda $tienda;

    public function __construct(PDO $db)
    {
        $this->db     = $db;
        $this->stock  = new Stock($db);
        $this->bodega = new Bodega($db);
        $this->tienda = new Tienda($db);
    }

    /**
     * Devuelve la fila `stock` destino para el estatus dado, creándola si hace falta.
     *
     * @param array $ctx  [
     *   'plaza_id'              => int,
     *   'tienda_id'             => ?int,   // tienda destino (en_uso) u origen (garantia/baja)
     *   'procedencia_tienda_id' => ?int,
     *   'asignado_usuario_id'   => ?int,   // destino de 'asignado' (ya validado)
     *   'ati_usuario_id'        => ?int,   // ATI elegido explícitamente en el formulario
     *   'bodega_id'             => ?int,   // bodega destino explícita (admin/coordinador)
     * ]
     * @return array{stock: array|false, nota: ?string}
     */
    public function resolver(string $status, array $ctx): array
    {
        $status  = strtolower(trim($status));
        $plazaId = (int) ($ctx['plaza_id'] ?? 0);

        return match ($status) {
            'en_uso'             => $this->paraTienda((int) ($ctx['tienda_id'] ?? 0)),
            'asignado'           => $this->paraUsuario((int) ($ctx['asignado_usuario_id'] ?? 0), $plazaId),
            'garantia', 'baja'   => $this->paraGarantiaBaja($ctx, $plazaId),
            default              => $this->paraBodega($plazaId, (int) ($ctx['bodega_id'] ?? 0)),
        };
    }

    private function paraTienda(int $tiendaId): array
    {
        if ($tiendaId <= 0) {
            return ['stock' => false, 'nota' => 'Falta la tienda de uso.'];
        }
        return ['stock' => $this->stock->obtenerPorTienda($tiendaId), 'nota' => null];
    }

    private function paraUsuario(int $usuarioId, int $plazaId): array
    {
        if ($usuarioId <= 0) {
            return ['stock' => false, 'nota' => 'Falta el usuario asignado.'];
        }
        return ['stock' => $this->stock->obtenerPorUsuario($usuarioId, $plazaId), 'nota' => null];
    }

    private function paraBodega(int $plazaId, int $bodegaIdExplicita = 0): array
    {
        $bodega = $bodegaIdExplicita > 0
            ? $this->bodega->obtenerPorId($bodegaIdExplicita)
            : $this->bodega->obtenerPorPlaza($plazaId);

        if (!$bodega) {
            return ['stock' => false, 'nota' => 'La plaza no tiene una bodega configurada.'];
        }

        $stock = $this->stock->obtenerPorBodega((int) $bodega['id']);
        if ($stock && (int) ($stock['bodega_id'] ?? 0) !== (int) $bodega['id']) {
            $this->stock->crearParaBodega((int) $bodega['id']);
            $stock = $this->stock->obtenerPorBodega((int) $bodega['id']);
        }
        return ['stock' => $stock, 'nota' => null];
    }

    /**
     * garantia / baja → stock del ATI responsable de la tienda.
     * Orden de resolución del ATI:
     *   1. ctx.ati_usuario_id (elegido en el formulario)
     *   2. tienda(ctx.tienda_id).ati_usuario_id
     *   3. tienda(ctx.procedencia_tienda_id).ati_usuario_id
     *   4. primer ATI de la plaza
     *   5. fallback: bodega de la plaza (se registra en la nota del movimiento)
     */
    private function paraGarantiaBaja(array $ctx, int $plazaId): array
    {
        $atiId = (int) ($ctx['ati_usuario_id'] ?? 0);

        if ($atiId <= 0) {
            foreach ([(int) ($ctx['tienda_id'] ?? 0), (int) ($ctx['procedencia_tienda_id'] ?? 0)] as $tId) {
                if ($tId <= 0) {
                    continue;
                }
                $t = $this->tienda->obtenerPorId($tId);
                if ($t && !empty($t['ati_usuario_id'])) {
                    $atiId = (int) $t['ati_usuario_id'];
                    break;
                }
            }
        }

        if ($atiId <= 0) {
            $atiId = $this->tienda->primerAtiDePlaza($plazaId);
        }

        if ($atiId > 0) {
            return ['stock' => $this->stock->obtenerPorUsuario($atiId, $plazaId), 'nota' => null];
        }

        // Sin ATI en toda la plaza → cae a la bodega, dejando constancia.
        $fallback = $this->paraBodega($plazaId);
        $fallback['nota'] = 'La tienda/plaza no tiene ATI responsable; el activo se envió a la bodega.';
        return $fallback;
    }
}
