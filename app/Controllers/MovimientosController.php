<?php

namespace App\Controllers;

use App\Helpers\Permisos;

/** MovimientosController — línea de tiempo de movimientos (reemplaza a Historial). */
class MovimientosController
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
        if (!Permisos::moduloPermitido('movimientos')) {
            $_SESSION['error'] = 'No tienes acceso al módulo Movimientos.';
            header('Location: index.php?controller=dashboard');
            exit;
        }
        $navActivo = 'movimientos';
        require ROOT_PATH . '/app/views/movimientos/index.php';
    }
}
