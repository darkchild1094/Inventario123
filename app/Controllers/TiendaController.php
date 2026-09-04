<?php

namespace App\Controllers;

use App\Models\Tienda;
use App\Models\Plaza;
use App\Helpers\Permisos;

/**
 * TiendaController — pantalla de administración para asignar el ATI responsable
 * (garantía / baja) de cada tienda. Solo admin.
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
        if (!Permisos::puedeGestionarTiendas()) {
            $_SESSION['error'] = 'Acceso restringido a administradores.';
            header('Location: index.php');
            exit;
        }

        $plazas   = (new Plaza($this->db))->obtenerTodas();
        $plazaId  = (int) ($_GET['plaza_id'] ?? ($plazas[0]['id'] ?? 0));
        $busqueda = trim((string) ($_GET['busqueda'] ?? ''));

        $tiendaModel = new Tienda($this->db);
        $tiendas     = $plazaId > 0 ? $tiendaModel->obtenerPorPlaza($plazaId) : [];
        if ($busqueda !== '') {
            $needle  = mb_strtolower($busqueda);
            $tiendas = array_values(array_filter($tiendas, fn($t) =>
                str_contains(mb_strtolower($t['nombre'] . ' ' . $t['cr_tienda']), $needle)));
        }
        $atis = $plazaId > 0 ? $tiendaModel->atisDePlaza($plazaId) : [];

        require ROOT_PATH . '/app/views/tiendas/index.php';
    }
}
