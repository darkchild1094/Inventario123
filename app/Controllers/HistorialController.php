<?php

namespace App\Controllers;

use App\Models\Movimiento;
use App\Models\Tienda;
use App\Models\Usuario;
use App\Models\Plaza;
use App\Helpers\Permisos;

/**
 * HistorialController — pestaña "Historial": bitácora filtrable de todos los
 * movimientos de activos (altas, cambios de estatus/stock, reemplazos, bajas).
 * Mismo patrón AJAX que HomeController::index (filtros en vivo, sin recarga).
 */
class HistorialController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    public function index(): void
    {
        if (!Permisos::puedeVerHistorial()) {
            $_SESSION['error'] = 'No tienes acceso al historial.';
            $this->redirigir('index.php');
        }

        // Scope por rol + filtros de la URL
        $filtros = array_merge(Permisos::filtrosHistorial(), array_filter([
            'serie'      => trim((string) ($_GET['serie']      ?? '')),
            'evento'     => $_GET['evento']     ?? null,
            'tienda_id'  => $_GET['tienda_id']  ?? null,
            'usuario_id' => $_GET['usuario_id'] ?? null,
            'desde'      => $_GET['desde']      ?? null,
            'hasta'      => $_GET['hasta']      ?? null,
            'activo_id'  => $_GET['activo_id']  ?? null,
        ], fn($v) => $v !== null && $v !== ''));

        $pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
        $resultado = (new Movimiento($this->db))->listar($filtros, $pagina, 30);

        $movimientos = $resultado['movimientos'];
        $paginacion  = $resultado['paginacion'];
        $eventos     = Movimiento::EVENTOS;

        // Variables para conservar el estado de los filtros en la vista
        $f_serie      = $_GET['serie']      ?? '';
        $f_evento     = $_GET['evento']     ?? '';
        $f_tienda_id  = $_GET['tienda_id']  ?? '';
        $f_usuario_id = $_GET['usuario_id'] ?? '';
        $f_desde      = $_GET['desde']      ?? '';
        $f_hasta      = $_GET['hasta']      ?? '';
        $f_activo_id  = $_GET['activo_id']  ?? '';

        // Catálogos para los selects de filtro, acotados por rol
        [$tiendasFiltro, $usuariosFiltro] = $this->catalogosFiltro();

        if ($this->esPeticionAjax()) {
            require ROOT_PATH . '/app/views/historial/_resultados.php';
            return;
        }
        require ROOT_PATH . '/app/views/historial/index.php';
    }

    /** @return array{0: array, 1: array} [tiendas, usuarios] para los filtros */
    private function catalogosFiltro(): array
    {
        $tipo = Permisos::tipo();

        if ($tipo === 'admin') {
            return [(new Tienda($this->db))->obtenerTodas(), (new Usuario($this->db))->obtenerTodos()];
        }

        $plazas = $tipo === 'coordinador' ? Permisos::misPlazas() : [Permisos::plazaId()];
        $tiendas = [];
        $usuarios = [];
        foreach ($plazas as $pid) {
            foreach ((new Tienda($this->db))->obtenerPorPlaza((int) $pid) as $t) {
                $tiendas[(int) $t['id']] = $t;
            }
            foreach ((new Usuario($this->db))->obtenerPorPlaza((int) $pid) as $u) {
                $usuarios[(int) $u['id']] = $u;
            }
        }
        return [array_values($tiendas), array_values($usuarios)];
    }

    private function esPeticionAjax(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    private function redirigir(string $url): never
    {
        header("Location: {$url}");
        exit;
    }
}
