<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\Movimiento;
use App\Models\SolicitudTraslado;
use App\Models\Tienda;
use App\Models\Usuario;
use App\Models\Modelo;
use App\Helpers\Permisos;

/**
 * DashboardController — pantalla principal para todos los roles.
 * Muestra KPIs de inventario (acotados por el scope del rol), el desglose por
 * tipo de equipo y por plaza, los movimientos recientes y accesos rápidos.
 */
class DashboardController
{
    private $db;
    private AuthController $auth;

    public function __construct($db)
    {
        $this->db   = $db;
        $this->auth = new AuthController($db);
        $this->auth->requerirAutenticacion();
    }

    public function index(): void
    {
        $tipo = Permisos::tipo();

        $resumen = (new Activo($this->db))->resumen(Permisos::filtrosScope());

        $movs = (new Movimiento($this->db))
            ->listar(Permisos::filtrosHistorial(), 1, 8)['movimientos'] ?? [];

        // Traslados pendientes (coordinador / admin).
        $pendTraslados = 0;
        if (in_array($tipo, ['coordinador', 'admin'], true)) {
            $st = new SolicitudTraslado($this->db);
            $pendTraslados = $tipo === 'admin'
                ? $st->contarPendientesTodas()
                : $st->contarPendientesPorPlazas(Permisos::misPlazas());
        }

        // Contadores de catálogo (solo admin).
        $extras = [];
        if ($tipo === 'admin') {
            $extras = [
                'tiendas'  => count((new Tienda($this->db))->obtenerTodas()),
                'usuarios' => count((new Usuario($this->db))->obtenerTodos()),
                'modelos'  => count((new Modelo($this->db))->obtenerTodos()),
            ];
        }

        $navActivo = 'dashboard';
        require ROOT_PATH . '/app/views/dashboard/index.php';
    }
}
