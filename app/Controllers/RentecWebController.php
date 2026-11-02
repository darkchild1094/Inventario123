<?php

namespace App\Controllers;

/** RentecWebController — proyectos RENTEC: lista + detalle + instalar. */
class RentecWebController
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
        $navActivo = 'rentec';
        require ROOT_PATH . '/app/views/rentec/index.php';
    }

    public function detalle(): void
    {
        $navActivo = 'rentec';
        $id = (int) ($_GET['id'] ?? 0);
        require ROOT_PATH . '/app/views/rentec/detalle.php';
    }
}
