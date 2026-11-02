<?php

namespace App\Controllers;

use App\Helpers\Permisos;

/**
 * StockUsuariosController — módulo "Stock usuarios" (solo lectura): el stock
 * personal de cada usuario de la plaza. También cubre "Stock PFS" (web-only),
 * que es el mismo listado acotado a los PFS.
 */
class StockUsuariosController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) session_start();
        (new AuthController($db))->requerirAutenticacion();
    }

    public function index(): void
    {
        if (!Permisos::moduloPermitido('stock_usuarios') && !Permisos::moduloPermitido('stock_pfs')) {
            $_SESSION['error'] = 'No tienes acceso a este módulo.';
            header('Location: index.php?controller=dashboard');
            exit;
        }
        $navActivo = 'stock_usuarios';
        require ROOT_PATH . '/app/views/stockusuarios/index.php';
    }
}
