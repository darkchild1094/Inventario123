<?php

namespace App\Controllers;

use App\Helpers\Permisos;

/**
 * BodegaController — módulo "Bodega", igual que en la app Android: lista de
 * activos en bodega por plaza, selección múltiple (enviar/eliminar), Excel.
 * Toda la lógica vive en el JS de la vista contra ApiController (listarActivos
 * modulo=bodega, enviarActivos, eliminarActivo) — el mismo backend que usa
 * el celular.
 */
class BodegaController
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
        if (!Permisos::moduloPermitido('bodega')) {
            $_SESSION['error'] = 'No tienes acceso al módulo Bodega.';
            header('Location: index.php?controller=dashboard');
            exit;
        }
        $navActivo = 'bodega';
        require ROOT_PATH . '/app/views/bodega/index.php';
    }
}
