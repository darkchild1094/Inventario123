<?php

namespace App\Helpers;

/**
 * Permisos — Matriz de roles para femsa_assets
 *
 * admin       → todo, todas las plazas y negocios
 * coordinador → todo menos gestionar usuarios, solo su plaza
 * fs          → registra/visualiza solo su stock, puede editar su perfil, exportar su stock
 * ati         → registra activos en su stock, visualiza activos de su plaza, exportar excel de su plaza
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
    public static function esFs(): bool            { return self::tipo() === 'fs'; }
    public static function esAti(): bool           { return self::tipo() === 'ati'; }

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

    /** Puede ver activos de su plaza (coordinador y ati) o solo su stock (fs) */
    public static function puedeVerSuPlaza(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador', 'ati']);
    }

    /** FS: solo ve su propio stock */
    public static function soloSuStock(): bool
    {
        return self::esFs();
    }

    /** Puede registrar nuevos activos */
    public static function puedeCrearActivo(): bool
    {
        return in_array(self::tipo(), ['admin', 'coordinador', 'fs', 'ati']);
    }

    /** Puede editar activos */
    public static function puedeEditarActivo(): bool
    {
        // admin, coordinador, fs y ati pueden editar (ati/fs solo lo suyo,
        // validado aparte en puedeEditarActivoConcreto)
        return in_array(self::tipo(), ['admin', 'coordinador', 'fs', 'ati']);
    }

    /** Ids de plaza del usuario en sesión (asignadas o, en su defecto, la principal). */
    public static function misPlazas(): array
    {
        return self::plazasIds() ?: array_filter([self::plazaId()]);
    }

    /** Puede editar un activo específico: fs y ati solo pueden editar activos de su stock */
    public static function puedeEditarActivoConcreto(array $activo): bool
    {
        if (self::esAdmin() || self::esCoordinador()) return true;

        $esSuStockPersonal = ($activo['stock_tipo'] ?? '') === 'usuario'
            && (int) ($activo['usuario_stock_id'] ?? 0) === self::idUsuario();

        // Los activos EN USO viven en el stock de la tienda: fs/ati pueden operarlos
        // (incluye hacer reemplazos) si la tienda está en su(s) plaza(s).
        $esTiendaDeSuPlaza = ($activo['stock_tipo'] ?? '') === 'tienda'
            && in_array((int) ($activo['plaza_id'] ?? 0), self::misPlazas(), true);

        if (self::esFs() || self::tipo() === 'ati') {
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
        if ($tipo === 'fs') {
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
        return in_array(self::tipo(), ['admin', 'coordinador', 'fs', 'ati']);
    }

    /**
     * Scope de exportación según la matriz de roles:
     * admin       → todo
     * coordinador → su plaza
     * ati         → su plaza
     * fs          → su stock personal
     */
    public static function filtrosExportar(): array
    {
        return match(self::tipo()) {
            'admin'       => [],
            'coordinador' => ['plaza_id' => self::plazasIds() ?: [self::plazaId()]],
            'ati'         => ['plaza_id' => self::plazaId()],
            'fs'          => ['stock_usuario_id' => self::idUsuario()],
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
        return in_array(self::tipo(), ['admin', 'coordinador', 'fs', 'ati'], true);
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

    // ── Solicitudes de traslado a bodega (doble firma) ────────────────────────

    /** Puede crear una solicitud de traslado (mandar su stock 'asignado' a bodega). */
    public static function puedeCrearSolicitudTraslado(): bool
    {
        return self::esFs();
    }

    /** Puede aprobar / rechazar solicitudes de traslado. */
    public static function puedeAprobarTraslados(): bool
    {
        return in_array(self::tipo(), ['coordinador', 'admin'], true);
    }

    /** Puede ver la pantalla de Traslados (fs, coordinador, admin). */
    public static function puedeVerTraslados(): bool
    {
        return in_array(self::tipo(), ['fs', 'coordinador', 'admin'], true);
    }

    /**
     * Plazas sobre las que el usuario puede aprobar traslados.
     * admin → [] (todas, sin filtro); coordinador → sus plazas; otros → [-1].
     */
    public static function plazasParaAprobar(): array
    {
        return match (self::tipo()) {
            'admin'       => [],
            'coordinador' => self::misPlazas(),
            default       => [-1],
        };
    }

    /**
     * Scope del Historial:
     *   admin       → todo
     *   coordinador → sus plazas asignadas
     *   ati         → su plaza
     *   fs          → su propio stock personal + todo lo de tiendas (fs_scope)
     */
    public static function filtrosHistorial(): array
    {
        return match (self::tipo()) {
            'admin'       => [],
            'coordinador' => ['plaza_id' => self::misPlazas()],
            'ati'         => ['plaza_id' => self::plazaId()],
            'fs'          => ['fs_scope' => self::idUsuario()],
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
            'fs'          => ['stock_usuario_id' => self::idUsuario()], // solo su stock
            default       => ['plaza_id' => -1],                       // nadie más
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