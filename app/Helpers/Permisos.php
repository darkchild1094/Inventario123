<?php

namespace App\Helpers;

/**
 * Permisos — Matriz de roles para femsa_assets
 *
 * admin       → todo, todas las plazas y negocios
 * coordinador → todo menos gestionar usuarios, solo sus plazas
 * pfs         → registra/visualiza solo su stock, puede editar su perfil, exportar su stock
 * ati         → registra activos en su stock, visualiza activos de su plaza, exportar excel de su plaza
 *
 * El rol de campo se llama 'pfs' (antes 'fs'). La migración 026 renombró el
 * valor en la BD; aquí ya no debe aparecer 'fs' salvo compatibilidad de login.
 */
class Permisos
{
    // ── Obtener tipo del usuario en sesión ────────────────────────────────────

    public static function tipo(): string
    {
        return $_SESSION['usuario']['tipo'] ?? $_SESSION['usuario_tipo'] ?? '';
    }

    public static function idUsuario(): int
    {
        return (int) ($_SESSION['usuario']['id'] ?? $_SESSION['usuario_id'] ?? 0);
    }

    public static function plazaId(): int
    {
        return (int) ($_SESSION['usuario']['plaza_id'] ?? 0);
    }

    public static function plazasIds(): array
    {
        return array_map('intval', $_SESSION['usuario']['plaza_ids'] ?? []);
    }

    public static function plazaActiva(): array
    {
        return [
            'id'     => self::plazaId(),
            'nombre' => $_SESSION['usuario']['plaza_nombre'] ?? null,
        ];
    }

    // ── Checks de rol ─────────────────────────────────────────────────────────

    public static function esAdmin(): bool         { return self::tipo() === 'admin'; }
    public static function esCoordinador(): bool   { return self::tipo() === 'coordinador'; }
    public static function esPfs(): bool           { return self::tipo() === 'pfs'; }
    public static function esAti(): bool           { return self::tipo() === 'ati'; }

    /** @deprecated usa esPfs(). Se mantiene por si alguna vista vieja lo llama. */
    public static function esFs(): bool            { return self::esPfs(); }

    // ── Permisos específicos ──────────────────────────────────────────────────

    /** Puede ver activos de todas las plazas (no restringido a su plaza) */
    public static function puedeVerTodasPlazas(): bool
    {
        return self::esAdmin();
    }

    /** Puede usar el filtro de negocio/plaza en el listado (admin: todas, coordinador: las suyas) */
    public static function puedeFiltrarPorPlaza(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador'], true);
    }

    /** Puede ver activos de su plaza (coordinador y ati) o solo su stock (pfs) */
    public static function puedeVerSuPlaza(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador', 'ati']);
    }

    /** PFS: solo ve su propio stock */
    public static function soloSuStock(): bool
    {
        return self::esPfs();
    }

    /** Puede registrar nuevos activos */
    public static function puedeCrearActivo(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador', 'pfs', 'ati']);
    }

    /** Puede editar activos */
    public static function puedeEditarActivo(): bool
    {
        // admin, coordinador, pfs y ati pueden editar (ati/pfs solo lo suyo,
        // validado aparte en puedeEditarActivoConcreto)
        return in_array(self::tipo(), ['admin', 'coordinador', 'pfs', 'ati']);
    }

    /** Ids de plaza del usuario en sesión (asignadas o, en su defecto, la principal). */
    public static function misPlazas(): array
    {
        return self::plazasIds() ?: array_filter([self::plazaId()]);
    }

    /** Puede editar un activo específico: pfs y ati solo pueden editar activos de su stock */
    public static function puedeEditarActivoConcreto(array $activo): bool
    {
        if (self::esAdmin() || self::esCoordinador()) return true;

        $esSuStockPersonal = ($activo['stock_tipo'] ?? '') === 'usuario'
            && (int) ($activo['usuario_stock_id'] ?? 0) === self::idUsuario();

        // Los activos EN USO viven en el stock de la tienda: pfs/ati pueden operarlos
        // (incluye hacer reemplazos) si la tienda está en su(s) plaza(s).
        $esTiendaDeSuPlaza = ($activo['stock_tipo'] ?? '') === 'tienda'
            && in_array((int) ($activo['plaza_id'] ?? 0), self::misPlazas(), true);

        if (self::esPfs() || self::tipo() === 'ati') {
            return $esSuStockPersonal || $esTiendaDeSuPlaza;
        }

        return false;
    }

    /** Puede ver el detalle de un activo específico, según el alcance de su rol */
    public static function puedeVerActivoConcreto(array $activo): bool
    {
        if (self::esAdmin()) return true;

        $tipo = self::tipo();

        if ($tipo === 'coordinador') {
            return in_array((int) ($activo['plaza_id'] ?? 0), self::misPlazas(), true);
        }
        if ($tipo === 'ati') {
            return (int) ($activo['plaza_id'] ?? 0) === self::plazaId();
        }
        if ($tipo === 'pfs') {
            $esSuStock = ($activo['stock_tipo'] ?? '') === 'usuario'
                && (int) ($activo['usuario_stock_id'] ?? 0) === self::idUsuario();
            $esTiendaDeSuPlaza = ($activo['stock_tipo'] ?? '') === 'tienda'
                && in_array((int) ($activo['plaza_id'] ?? 0), self::misPlazas(), true);
            return $esSuStock || $esTiendaDeSuPlaza;
        }

        return false;
    }

    /** Puede eliminar un activo específico: admin siempre, ati solo lo asignado a él mismo */
    public static function puedeEliminarActivo(?array $activo = null): bool
    {
        if (self::esAdmin()) return true;

        if (self::tipo() === 'ati' && $activo !== null) {
            return $activo['stock_tipo'] === 'usuario'
                && (int) ($activo['usuario_stock_id'] ?? 0) === self::idUsuario();
        }

        return false;
    }

    /** Puede gestionar usuarios (crear, editar, eliminar) */
    public static function puedeGestionarUsuarios(): bool
    {
        return self::esAdmin();
    }

    /** Puede exportar Excel */
    public static function puedeExportar(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador', 'pfs', 'ati']);
    }

    /**
     * Scope de exportación según la matriz de roles:
     * admin       → todo
     * coordinador → sus plazas
     * ati         → su plaza
     * pfs         → su stock personal
     */
    public static function filtrosExportar(): array
    {
        return match(self::tipo()) {
            'admin'       => [],
            'coordinador' => ['plaza_id' => self::plazasIds() ?: [self::plazaId()]],
            'ati'         => ['plaza_id' => self::plazaId()],
            'pfs'         => ['stock_usuario_id' => self::idUsuario()],
            default       => ['plaza_id' => -1],
        };
    }

    /** Puede ver el menú Bodega (vista general) */
    public static function puedeVerBodega(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador', 'ati']);
    }

    /** Puede ver la pestaña Historial */
    public static function puedeVerHistorial(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador', 'pfs', 'ati'], true);
    }

    /** Puede gestionar la asignación de ATI por tienda (pantalla "Tiendas") */
    public static function puedeGestionarTiendas(): bool
    {
        return self::esAdmin();
    }

    /** Puede gestionar el catálogo de modelos (alta / edición / borrado) */
    public static function puedeGestionarModelos(): bool
    {
        return self::esAdmin();
    }

    // ── Solicitudes de movimiento con firma ──────────────────────────────────
    // Todo cambio de dueño/estatus (salvo alta y instalación en tienda) pasa por
    // una solicitud firmada. Cualquier rol puede crearlas.

    /** Todos los roles operativos pueden iniciar una solicitud de movimiento. */
    public static function puedeCrearSolicitudTraslado(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador', 'pfs', 'ati'], true);
    }

    /** Coordinador y ATI aprueban solicitudes (según el destino); admin cualquiera. */
    public static function puedeAprobarTraslados(): bool
    {
        return in_array(self::tipo(), ['coordinador', 'ati', 'admin'], true);
    }

    /** Ve la pantalla de Traslados: todos los roles operativos. */
    public static function puedeVerTraslados(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador', 'pfs', 'ati'], true);
    }

    /** ¿Puede este usuario firmar una solicitud con este `destino`, según su rol? */
    public static function puedeAprobarDestino(string $destino): bool
    {
        $t = self::tipo();
        return match ($destino) {
            'en_bodega' => in_array($t, ['coordinador', 'admin'], true),
            'baja'      => in_array($t, ['ati', 'admin'], true),
            'garantia'  => in_array($t, ['ati', 'coordinador', 'admin'], true),
            'asignado'  => true, // lo valida el destino_usuario_id, no el rol
            default     => false,
        };
    }

    /** El "rol" con el que este usuario firma una solicitud (slot de firma). */
    public static function rolAprobacion(): string
    {
        return match (self::tipo()) {
            'ati'                  => 'ati',
            'coordinador'          => 'coordinador',
            'admin'               => 'admin', // el controlador decide el slot que falte
            default               => 'ingeniero',
        };
    }

    /** admin → [] (todas); coordinador/ati → sus plazas; otros → [-1]. */
    public static function plazasParaAprobar(): array
    {
        return match (self::tipo()) {
            'admin'                => [],
            'coordinador', 'ati'   => self::misPlazas(),
            default               => [-1],
        };
    }

    /**
     * Scope del Historial:
     *   admin       → todo
     *   coordinador → sus plazas asignadas
     *   ati         → su plaza
     *   pfs         → su propio stock personal + todo lo de tiendas (pfs_scope)
     */
    public static function filtrosHistorial(): array
    {
        return match (self::tipo()) {
            'admin'       => [],
            'coordinador' => ['plaza_id' => self::misPlazas()],
            'ati'         => ['plaza_id' => self::plazaId()],
            'pfs'         => ['pfs_scope' => self::idUsuario()],
            default       => ['plaza_id' => [-1]],
        };
    }

    // ── Filtros de visibilidad para consultas ─────────────────────────────────

    /**
     * Devuelve los filtros de scope que deben aplicarse según el rol.
     * Se mezclan con los filtros de la URL en el controlador.
     */
    public static function filtrosScope(): array
    {
        $tipo    = self::tipo();
        $plazaId = self::plazaId();

        return match($tipo) {
            'admin'       => [],                                       // sin restricción
            'coordinador' => ['plaza_id' => self::plazasIds() ?: [$plazaId]], // TODAS sus plazas asignadas
            'ati'         => ['plaza_id' => $plazaId],                  // su única plaza
            'pfs'         => ['stock_usuario_id' => self::idUsuario()], // solo su stock
            default       => ['plaza_id' => -1],                       // nadie más
        };
    }

    // ── Módulos de navegación (fuente única para navbar, API y app Android) ────

    /**
     * Definición de todos los módulos. `editable` indica si el rol puede hacer
     * CRUD de activos dentro del módulo (los que no, es solo lectura).
     * El orden del array es el orden en que se pintan en el menú.
     */
    private const MODULOS_POR_ROL = [
        'coordinador' => [
            ['clave' => 'dashboard', 'etiqueta' => 'Inicio',      'icono' => 'fa-gauge-high',          'editable' => false],
            ['clave' => 'consulta',  'etiqueta' => 'Consulta',    'icono' => 'fa-barcode',             'editable' => false],
            ['clave' => 'tiendas',   'etiqueta' => 'Tiendas',     'icono' => 'fa-store',               'editable' => true],
            ['clave' => 'rentec',    'etiqueta' => 'RENTEC',      'icono' => 'fa-arrows-rotate',       'editable' => true],
            ['clave' => 'bodega',    'etiqueta' => 'Bodega',      'icono' => 'fa-warehouse',           'editable' => true],
            ['clave' => 'mi_stock',  'etiqueta' => 'Mi Stock',    'icono' => 'fa-toolbox',             'editable' => true],
            ['clave' => 'stock_pfs', 'etiqueta' => 'Stock PFS',   'icono' => 'fa-people-carry-box',    'editable' => false],
        ],
        'ati' => [
            ['clave' => 'dashboard', 'etiqueta' => 'Inicio',      'icono' => 'fa-gauge-high',          'editable' => false],
            ['clave' => 'consulta',  'etiqueta' => 'Consulta',    'icono' => 'fa-barcode',             'editable' => false],
            ['clave' => 'tiendas',   'etiqueta' => 'Tiendas',     'icono' => 'fa-store',               'editable' => true],
            ['clave' => 'rentec',    'etiqueta' => 'RENTEC',      'icono' => 'fa-arrows-rotate',       'editable' => true],
            ['clave' => 'mi_stock',  'etiqueta' => 'Mi Stock',    'icono' => 'fa-toolbox',             'editable' => true],
            ['clave' => 'bodega',    'etiqueta' => 'Bodega',      'icono' => 'fa-warehouse',           'editable' => false],
            ['clave' => 'stock_pfs', 'etiqueta' => 'Stock PFS',   'icono' => 'fa-people-carry-box',    'editable' => false],
        ],
        'pfs' => [
            ['clave' => 'dashboard', 'etiqueta' => 'Inicio',      'icono' => 'fa-gauge-high',          'editable' => false],
            ['clave' => 'consulta',  'etiqueta' => 'Consulta',    'icono' => 'fa-barcode',             'editable' => false],
            ['clave' => 'mi_stock',  'etiqueta' => 'Mi Stock',    'icono' => 'fa-toolbox',             'editable' => true],
            ['clave' => 'tiendas',   'etiqueta' => 'Tiendas',     'icono' => 'fa-store',               'editable' => true],
            ['clave' => 'rentec',    'etiqueta' => 'RENTEC',      'icono' => 'fa-arrows-rotate',       'editable' => true],
            ['clave' => 'bodega',    'etiqueta' => 'Bodega',      'icono' => 'fa-warehouse',           'editable' => false],
        ],
        'admin' => [
            ['clave' => 'dashboard', 'etiqueta' => 'Inicio',      'icono' => 'fa-gauge-high',          'editable' => false],
            ['clave' => 'consulta',  'etiqueta' => 'Consulta',    'icono' => 'fa-barcode',             'editable' => false],
            ['clave' => 'mi_stock',  'etiqueta' => 'Mi Stock',    'icono' => 'fa-toolbox',             'editable' => true],
            ['clave' => 'bodega',    'etiqueta' => 'Bodega',      'icono' => 'fa-warehouse',           'editable' => true],
            ['clave' => 'rentec',    'etiqueta' => 'RENTEC',      'icono' => 'fa-arrows-rotate',       'editable' => true],
            ['clave' => 'ati',       'etiqueta' => 'ATI',         'icono' => 'fa-user-gear',           'editable' => true],
            ['clave' => 'stock_pfs', 'etiqueta' => 'Stock PFS',   'icono' => 'fa-people-carry-box',    'editable' => true],
            ['clave' => 'tiendas',   'etiqueta' => 'Tiendas',     'icono' => 'fa-store',               'editable' => true],
            ['clave' => 'usuarios',  'etiqueta' => 'Usuarios',    'icono' => 'fa-users-cog',           'editable' => true],
        ],
    ];

    /** Módulos visibles para el rol en sesión, en orden de menú. */
    public static function modulos(): array
    {
        return self::MODULOS_POR_ROL[self::tipo()] ?? [];
    }

    /** ¿El rol en sesión puede ver este módulo? */
    public static function moduloPermitido(string $modulo): bool
    {
        foreach (self::modulos() as $m) {
            if ($m['clave'] === $modulo) return true;
        }
        return false;
    }

    /** ¿El rol en sesión puede hacer CRUD de activos dentro de este módulo? */
    public static function moduloEditable(string $modulo): bool
    {
        foreach (self::modulos() as $m) {
            if ($m['clave'] === $modulo) return (bool) $m['editable'];
        }
        return false;
    }

    /**
     * Scope por plaza para módulos que NO son personales (tiendas, bodega,
     * stock_pfs, ati): admin ve todo; el resto, solo sus plazas asignadas.
     * A diferencia de filtrosScope(), a pfs también lo acota por plaza y no
     * por su stock personal.
     */
    private static function scopePlazas(): array
    {
        if (self::esAdmin()) return [];
        $plazas = self::misPlazas();
        return ['plaza_id' => $plazas ?: [-1]];
    }

    /**
     * Filtros de datos de un módulo = scope por plaza del rol + el filtro
     * propio del módulo. Lo consumen listarActivos y la exportación por módulo.
     * Devuelve el scope base para módulos que no listan activos (dashboard,
     * consulta, usuarios).
     */
    public static function filtrosModulo(string $modulo): array
    {
        return match ($modulo) {
            'tiendas'   => array_merge(self::scopePlazas(), ['solo_tienda' => true]),
            'bodega'    => array_merge(self::scopePlazas(), ['solo_bodega' => true]),
            'mi_stock'  => ['stock_usuario_id' => self::idUsuario()],
            'stock_pfs' => array_merge(self::scopePlazas(), ['stock_usuario_tipo' => 'pfs']),
            'ati'       => array_merge(self::scopePlazas(), ['stock_usuario_tipo' => 'ati']),
            default     => self::filtrosScope(),
        };
    }

    // ── Helpers de sesión ─────────────────────────────────────────────────────

    /**
     * Aborta con redirect si el usuario no tiene alguno de los tipos indicados.
     */
    public static function requerir(array $tipos, string $redirect = 'index.php'): void
    {
        if (!in_array(self::tipo(), $tipos, true)) {
            $_SESSION['error'] = 'No tienes permisos para esta acción.';
            header("Location: {$redirect}");
            exit;
        }
    }
}