<?php

namespace App\Controllers;

use App\Models\Tienda;
use App\Models\Plaza;
use App\Helpers\Permisos;

/**
 * TiendaController — módulo "Tiendas": lista de tiendas (acotada al rol) con
 * el nº de activos de cada una. Al entrar a una tienda se ven sus activos vía
 * ?modulo=tiendas&tienda_id=X (HomeController). Sólo admin puede además asignar
 * el ATI responsable (garantía / baja) de cada tienda desde esta pantalla.
 */
class TiendaController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    public function index(): void
    {
        if (!Permisos::moduloPermitido('tiendas')) {
            $_SESSION['error'] = 'No tienes acceso al módulo de Tiendas.';
            header('Location: index.php?controller=dashboard');
            exit;
        }

        $esAdmin  = Permisos::esAdmin();
        $busqueda = trim((string) ($_GET['busqueda'] ?? ''));
        $plazaGet = (int) ($_GET['plaza_id'] ?? 0);

        // Plazas disponibles en el filtro
        $plazasTodas = (new Plaza($this->db))->obtenerTodas();
        if ($esAdmin) {
            $plazas = $plazasTodas;
        } else {
            $mis    = Permisos::misPlazas();
            $plazas = array_values(array_filter($plazasTodas, fn($p) => in_array((int) $p['id'], $mis, true)));
        }

        // Scope de la consulta
        if ($esAdmin) {
            $plazaIds = $plazaGet > 0 ? [$plazaGet] : [];
        } else {
            $mis      = Permisos::misPlazas();
            $plazaIds = ($plazaGet > 0 && in_array($plazaGet, $mis, true)) ? [$plazaGet] : $mis;
        }
        $plazaId = $plazaGet;

        $tiendaModel = new Tienda($this->db);
        // admin sin plaza elegida => todas ($plazaIds vacío); el resto siempre acotado.
        $tiendas = ($esAdmin || $plazaIds)
            ? $tiendaModel->listarConConteo($plazaIds, $busqueda ?: null)
            : [];

        // ATIs para el select (sólo admin, y sólo si hay una plaza elegida)
        $atis = ($esAdmin && $plazaId > 0) ? $tiendaModel->atisDePlaza($plazaId) : [];
        $puedeAsignarAti = Permisos::puedeGestionarTiendas();

        $navActivo = 'tiendas';
        require ROOT_PATH . '/app/views/tiendas/index.php';
    }
}
