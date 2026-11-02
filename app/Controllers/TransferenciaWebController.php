<?php

namespace App\Controllers;

use App\Helpers\Permisos;

/** TransferenciaWebController — altas administrativas pendientes con FEMSA (solo ATI). */
class TransferenciaWebController
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
        if (Permisos::tipo() !== 'ati') {
            $_SESSION['error'] = 'Sólo ATI puede ver Transferencias.';
            header('Location: index.php?controller=dashboard');
            exit;
        }
        $navActivo = 'transferencias';
        require ROOT_PATH . '/app/views/transferencias/index.php';
    }
}
