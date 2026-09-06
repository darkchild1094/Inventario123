<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\Dispositivo;
use App\Models\Plaza;
use App\Models\Tienda;
use App\Helpers\Permisos;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_once ROOT_PATH . '/vendor/autoload.php';

/**
 * ExportController — femsa_assets
 *
 * Genera Excel de inventario:
 *  - Una pestaña por Negocio-Plaza (activos en bodega)
 *  - Una pestaña por usuario/fs (activos en stock personal)
 */
class ExportController
{
    private $db;

    // Colores por status (ENUM: en_bodega, en_uso, baja, garantia, asignado)
    private const COLORES_STATUS = [
        'EN_BODEGA'  => ['fondo' => 'FF198754', 'texto' => Color::COLOR_WHITE],
        'EN_USO'     => ['fondo' => 'FF6C757D', 'texto' => Color::COLOR_WHITE],
        'BAJA'       => ['fondo' => 'FFDC3545', 'texto' => Color::COLOR_WHITE],
        'GARANTIA'   => ['fondo' => 'FFFFC107', 'texto' => Color::COLOR_BLACK],
        'ASIGNADO'   => ['fondo' => 'FF0D6EFD', 'texto' => Color::COLOR_WHITE],
        'DEFAULT'    => ['fondo' => 'FF212529', 'texto' => Color::COLOR_WHITE],
    ];

    private const FILA_INICIO  = 5;

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    /**
     * Punto de entrada de "Exportar".
     *  - Petición del navegador sin marcar → muestra el formulario de filtros.
     *  - Con ?generar=1 (o desde la app, que manda X-Requested-With) → genera
     *    el Excel ya acotado por rol + los filtros elegidos.
     */
    public function inventario(): void
    {
        $this->verificarPermisos();

        $esApp    = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $generar  = $esApp || isset($_GET['generar']);

        if (!$generar) {
            $this->mostrarFiltros();
            return;
        }

        // No dejes que un catálogo grande reviente por el límite de 120 s / memoria.
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $filtros      = $this->filtrosPeticion();
        $resultado    = (new Activo($this->db))->obtenerTodosFiltrado($filtros, 1, 999_999);
        $listaActivos = $resultado['activos'] ?? [];

        if (empty($listaActivos)) {
            $_SESSION['error'] = 'No hay activos que coincidan con esos filtros.';
            header('Location: index.php?controller=export&action=inventario');
            exit;
        }

        [$activosBodega, $activosUsuario] = $this->agruparActivos($listaActivos);

        $rutaPlantilla = ROOT_PATH . '/storage/templates/inventario_bodega.xlsx';
        if (!file_exists($rutaPlantilla)) {
            die('Error: No se encontró la plantilla de Excel.');
        }

        $spreadsheet = IOFactory::load($rutaPlantilla);
        $hojaBase    = $spreadsheet->getActiveSheet();
        $hojaMolde   = clone $hojaBase;
        $esPrimera   = true;

        foreach ($activosBodega as $nombre => $activos) {
            $hoja = $esPrimera ? $hojaBase : clone $hojaMolde;
            if (!$esPrimera) $spreadsheet->addSheet($hoja);
            $this->llenarHoja($hoja, $activos, $nombre, false);
            $esPrimera = false;
        }

        foreach ($activosUsuario as $nombre => $activos) {
            $hoja = $esPrimera ? $hojaBase : clone $hojaMolde;
            if (!$esPrimera) $spreadsheet->addSheet($hoja);
            $this->llenarHoja($hoja, $activos, $nombre, true);
            $esPrimera = false;
        }

        $this->descargarExcel($spreadsheet, 'Inventario_' . date('Y-m-d_H-i') . '.xlsx');
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    /** Renderiza el formulario de filtros previo a la exportación. */
    private function mostrarFiltros(): void
    {
        $tipo = Permisos::tipo();

        $dispositivos = (new Dispositivo($this->db))->leerTodos();

        // Plazas / tiendas visibles según el rol.
        $plazasTodas = (new Plaza($this->db))->obtenerTodas();
        $tiendasTodas = (new Tienda($this->db))->obtenerTodas();

        if ($tipo === 'admin') {
            $plazas  = $plazasTodas;
            $tiendas = $tiendasTodas;
        } elseif ($tipo === 'coordinador') {
            $mis     = Permisos::plazasIds() ?: [Permisos::plazaId()];
            $plazas  = array_values(array_filter($plazasTodas, fn($p) => in_array((int) $p['id'], $mis, true)));
            $tiendas = array_values(array_filter($tiendasTodas, fn($t) => in_array((int) $t['plaza_id'], $mis, true)));
        } elseif ($tipo === 'ati') {
            $pid     = Permisos::plazaId();
            $plazas  = array_values(array_filter($plazasTodas, fn($p) => (int) $p['id'] === $pid));
            $tiendas = array_values(array_filter($tiendasTodas, fn($t) => (int) $t['plaza_id'] === $pid));
        } else { // fs: sólo su stock personal, sin selector de plaza/tienda
            $plazas  = [];
            $tiendas = [];
        }

        $statusOpts = [
            'en_bodega' => 'En bodega', 'en_uso' => 'En uso',
            'asignado' => 'Asignado', 'garantia' => 'Garantía', 'baja' => 'Baja',
        ];

        require ROOT_PATH . '/app/views/export/filtros.php';
    }

    /**
     * Filtros efectivos = los elegidos en el formulario ∩ el alcance del rol.
     * El alcance del rol siempre gana (un coordinador no puede exportar otra plaza).
     */
    private function filtrosPeticion(): array
    {
        $scope = Permisos::filtrosExportar();

        $elegidos = [];
        if (!empty($_GET['dispositivo_id'])) $elegidos['dispositivo_id'] = (int) $_GET['dispositivo_id'];
        if (!empty($_GET['tienda_id']))      $elegidos['tienda_id']      = (int) $_GET['tienda_id'];
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $elegidos['status'] = Activo::normalizarStatus((string) $_GET['status']);
        }
        if (trim((string) ($_GET['busqueda'] ?? '')) !== '') {
            $elegidos['busqueda'] = trim((string) $_GET['busqueda']);
        }

        // Plaza: sólo se acepta si cae dentro del alcance del rol.
        if (!empty($_GET['plaza_id'])) {
            $pedida = (int) $_GET['plaza_id'];
            if (!isset($scope['plaza_id'])) {
                $elegidos['plaza_id'] = $pedida;                       // admin
            } else {
                $permitidas = array_map('intval', (array) $scope['plaza_id']);
                if (in_array($pedida, $permitidas, true)) {
                    $elegidos['plaza_id'] = $pedida;
                }
            }
        }

        // El alcance del rol se aplica al final (sobrescribe lo que haga falta).
        $filtros = array_merge($elegidos, $scope);
        if (isset($elegidos['plaza_id'])) {
            $filtros['plaza_id'] = $elegidos['plaza_id'];              // ya validada arriba
        }
        return $filtros;
    }

    private function agruparActivos(array $activos): array
    {
        $porBodega  = [];
        $porUsuario = [];

        foreach ($activos as $a) {
            if ($a['stock_tipo'] === 'bodega') {
                $clave = $this->claveUbicacion($a);
                $porBodega[$clave][] = $a;
            } else {
                $clave = $this->claveUsuario($a);
                $porUsuario[$clave][] = $a;
            }
        }

        ksort($porBodega);
        ksort($porUsuario);

        return [$porBodega, $porUsuario];
    }

    private function claveUbicacion(array $a): string
    {
        $negocio = mb_strtoupper(trim($a['negocio_nombre'] ?? ''));
        $plaza   = mb_strtoupper(trim($a['plaza_nombre']   ?? ''));

        if ($negocio !== '' && $plaza !== '') return "{$negocio} - {$plaza}";
        if ($negocio !== '') return $negocio;
        return mb_strtoupper(trim($a['bodega_nombre'] ?? 'SIN UBICACION'));
    }

    private function claveUsuario(array $a): string
    {
        $nombre = mb_strtoupper(trim($a['usuario_nombre'] ?? 'SIN USUARIO'));
        $partes = preg_split('/\s+/', $nombre, -1, PREG_SPLIT_NO_EMPTY);
        return $partes[0] . (isset($partes[1]) ? ' ' . $partes[1] : '');
    }

    private function llenarHoja($sheet, array $activos, string $nombre, bool $esDeUsuario): void
    {
        if (empty($activos)) return;

        // La plantilla (storage/templates/inventario_bodega.xlsx) ya trae
        // los logos de OXXO y Getic integrados en las filas 1-3, y el
        // encabezado de columnas (fila 4: CANT. | DESCRIPCIÓN | SERIE |
        // ACTIVO | ESTATUS | TIENDA | PROCEDENCIA) con su estilo. No se
        // toca esa parte, solo se llenan los datos a partir de la fila 5.

        $tab = mb_substr(preg_replace('/[*:\\/\\\\?\[\]—–]/', '-', $nombre), 0, 31);
        try {
            $sheet->setTitle($tab);
        } catch (\Exception) {
            $sheet->setTitle(mb_substr($tab, 0, 26) . '_' . rand(10, 99));
        }

        $fila = self::FILA_INICIO;

        // 1) Volcado de valores (rápido). El estilo por celda de estatus (color
        //    variable) se guarda para aplicarlo agrupado después.
        $porColor = [];
        foreach ($activos as $a) {
            $statusKey = mb_strtoupper($a['status'] ?? '');

            $sheet->setCellValue("B{$fila}", 1);
            $sheet->setCellValue("C{$fila}", trim(($a['dispositivo_nombre'] ?? '') . ' ' . ($a['modelo_nombre'] ?? '')));
            $sheet->setCellValue("D{$fila}", $a['serie'] ?? '');
            $sheet->setCellValue("E{$fila}", $a['codigo_barras'] ?? '');
            $sheet->setCellValue("F{$fila}", $statusKey);
            if ($esDeUsuario) {
                $sheet->setCellValue("G{$fila}", trim(($a['negocio_nombre'] ?? '') . ' ' . ($a['plaza_nombre'] ?? '')));
            } else {
                $sheet->setCellValue("G{$fila}", $a['bodega_nombre'] ?? 'BODEGA');
            }
            $sheet->setCellValue("H{$fila}", $a['procedencia_nombre'] ?? '');

            $porColor[$statusKey][] = $fila;
            $fila++;
        }
        $ultima = $fila - 1;
        if ($ultima < self::FILA_INICIO) return;

        // 2) Estilo de bloque en UNA sola pasada (antes era por fila → O(n) objetos
        //    de estilo y el timeout de PhpSpreadsheet).
        $rango = "B" . self::FILA_INICIO . ":H{$ultima}";
        $sheet->getStyle($rango)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($rango)->getFont()->setName('Century Gothic')->setSize(10);
        $sheet->getStyle("B" . self::FILA_INICIO . ":B{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D" . self::FILA_INICIO . ":F{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G" . self::FILA_INICIO . ":H{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 3) Color de estatus: un setStyle por grupo de filas contiguas del mismo color.
        foreach ($porColor as $statusKey => $filas) {
            $color = self::COLORES_STATUS[$statusKey] ?? self::COLORES_STATUS['DEFAULT'];
            $estilo = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color['fondo']]],
                'font' => ['color' => ['argb' => $color['texto']], 'bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];
            foreach ($this->rangosContiguos($filas) as [$ini, $fin]) {
                $sheet->getStyle("F{$ini}:F{$fin}")->applyFromArray($estilo);
            }
        }
    }

    /** Agrupa una lista de números de fila en pares [inicio, fin] contiguos. */
    private function rangosContiguos(array $filas): array
    {
        sort($filas);
        $rangos = [];
        $ini = $prev = $filas[0];
        foreach (array_slice($filas, 1) as $f) {
            if ($f === $prev + 1) { $prev = $f; continue; }
            $rangos[] = [$ini, $prev];
            $ini = $prev = $f;
        }
        $rangos[] = [$ini, $prev];
        return $rangos;
    }

    private function descargarExcel(Spreadsheet $spreadsheet, string $nombre): never
    {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$nombre}\"");
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    private function verificarPermisos(): void
    {
        $tiposPermitidos = ['admin', 'coordinador', 'ati', 'fs'];
        $tipoSesion      = $_SESSION['usuario_tipo'] ?? $_SESSION['usuario']['tipo'] ?? '';
        if (!in_array($tipoSesion, $tiposPermitidos, true)) {
            $esAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

            if ($esAjax) {
                if (ob_get_length()) ob_end_clean();
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para exportar datos.']);
                exit;
            }

            $_SESSION['error'] = 'No tienes permisos para exportar datos.';
            header('Location: index.php');
            exit;
        }
    }
}