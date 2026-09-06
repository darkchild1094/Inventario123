<?php

namespace App\Helpers;

/**
 * Helpers de presentación para el historial de movimientos.
 * Los datos ya vienen resueltos por Movimiento::enriquecer() (eq_* / rel_*),
 * con el snapshot de datos_json como fuente preferente.
 */
class HistorialVista
{
    /** Nunca muestra "null": vacío / null → guion. */
    public static function d(?string $v): string
    {
        $v = trim((string) $v);
        return $v === '' ? '—' : $v;
    }

    /** "DISPOSITIVO · MARCA MODELO" a partir de un prefijo de claves (eq_ | rel_). */
    public static function titulo(array $m, string $p = 'eq_'): string
    {
        $marcaModelo = trim(($m[$p . 'marca'] ?? '') . ' ' . ($m[$p . 'modelo'] ?? ''));
        $disp        = trim((string) ($m[$p . 'dispositivo'] ?? ''));
        if ($disp !== '' && $marcaModelo !== '') return $disp . ' · ' . $marcaModelo;
        return $disp ?: ($marcaModelo ?: '—');
    }

    /** "S/N: x · CB: y · AF: z" con guion en los faltantes. */
    public static function identificadores(array $m, string $p = 'eq_'): string
    {
        return 'S/N: ' . self::d($m[$p . 'serie'] ?? null)
            . '  ·  CB: ' . self::d($m[$p . 'codigo_barras'] ?? null)
            . '  ·  AF: ' . self::d($m[$p . 'num_activo'] ?? null);
    }

    /** ¿El movimiento trae un equipo relacionado (reemplazo)? */
    public static function tieneRelacionado(array $m): bool
    {
        return !empty($m['rel_serie']) || !empty($m['rel_codigo_barras'])
            || !empty($m['rel_num_activo']) || !empty($m['rel_modelo'])
            || !empty($m['rel_dispositivo']);
    }

    /** Rótulo del equipo relacionado según el evento. */
    public static function relLabel(string $evento): string
    {
        return match ($evento) {
            'reemplazo_entra' => 'Sale (equipo retirado)',
            'reemplazo_sale'  => 'Entra (equipo instalado)',
            default           => 'Equipo relacionado',
        };
    }
}
