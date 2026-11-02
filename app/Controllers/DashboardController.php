<?php

namespace App\Controllers;

/**
 * DashboardController — pantalla principal para todos los roles, igual que
 * en la app Android: 4 tarjetas, avance RENTEC, inventario de tiendas,
 * bodega por plaza, mi stock por tipo. Todo se calcula en vivo en el JS de
 * la vista contra ApiController::resumenDashboard() + rentecListar().
 */
class DashboardController
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
        $navActivo = 'dashboard';
        require ROOT_PATH . '/app/views/dashboard/index.php';
    }
}
