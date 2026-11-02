<?php

namespace App\Controllers;

use App\Helpers\Permisos;

/** MiStockController — módulo "Mi stock": activos asignados al usuario en sesión. */
class MiStockController
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
        if (!Permisos::moduloPermitido('mi_stock')) {
            $_SESSION['error'] = 'No tienes acceso al módulo Mi Stock.';
            header('Location: index.php?controller=dashboard');
            exit;
        }
        $navActivo = 'mi_stock';
        require ROOT_PATH . '/app/views/mistock/index.php';
    }
}
