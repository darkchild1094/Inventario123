<?php

namespace App\Controllers;

/** NotificacionController — campana: transferencias recibidas (bodega<->stock). */
class NotificacionController
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
        $navActivo = 'notificaciones';
        require ROOT_PATH . '/app/views/notificacion/index.php';
    }
}
