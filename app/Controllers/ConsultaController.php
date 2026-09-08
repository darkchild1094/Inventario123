<?php

namespace App\Controllers;

use App\Helpers\Permisos;

/**
 * ConsultaController — módulo "Consulta".
 * Una sola caja de texto: escanear o teclear serie / código de barras / N° de
 * activo y ver a qué tienda pertenece, dónde está ahora y su historial.
 * El trabajo real lo hace ApiController::consultar (alcance global, sólo
 * lectura); esta pantalla sólo pinta el formulario y consume ese endpoint.
 */
class ConsultaController
{
    private $db;
    private AuthController $auth;

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->auth = new AuthController($db);
        $this->auth->requerirAutenticacion();
    }

    public function index(): void
    {
        $tipo      = Permisos::tipo();
        $navActivo  = 'consulta';
        $q          = trim((string) ($_GET['q'] ?? ''));
        require ROOT_PATH . '/app/views/consulta/index.php';
    }
}
