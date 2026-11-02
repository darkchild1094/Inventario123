<?php

namespace App\Controllers;

use App\Helpers\Permisos;

/** AuditoriaController — resultados de checklists de tienda ya cerrados (solo lectura). */
class AuditoriaController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) session_start();
        (new AuthController($db))->requerirAutenticacion();
    }

    private function requerirAcceso(): void
    {
        if (!Permisos::moduloPermitido('auditoria')) {
            $_SESSION['error'] = 'No tienes acceso al módulo Auditoría.';
            header('Location: index.php?controller=dashboard');
            exit;
        }
    }

    public function index(): void
    {
        $this->requerirAcceso();
        $navActivo = 'auditoria';
        require ROOT_PATH . '/app/views/auditoria/index.php';
    }

    public function detalle(): void
    {
        $this->requerirAcceso();
        $navActivo = 'auditoria';
        $id = (int) ($_GET['id'] ?? 0);
        require ROOT_PATH . '/app/views/auditoria/detalle.php';
    }
}
