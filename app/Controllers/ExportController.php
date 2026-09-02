<?php

namespace App\Controllers;

use App\Models\Activo;
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

    public function inventario(): void
    {
        $this->verificarPermisos();

        $filtros      = \App\Helpers\Permisos::filtrosExportar();
        $resultado    = (new Activo($this->db))->obtenerTodosFiltrado($filtros, 1, 999_999);
        $listaActivos = $resultado['activos'] ?? [];

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

        foreach ($activos as $a) {
            // Convertir status ENUM a mayúsculas para mapear color
            $statusKey = mb_strtoupper(str_replace('_', '_', $a['status'] ?? ''));

            // B: Cantidad (siempre 1, cada fila es una unidad/activo individual)
            $sheet->setCellValue("B{$fila}", 1);

            // C: Descripción (Dispositivo + Modelo)
            $sheet->setCellValue("C{$fila}", trim(
                ($a['dispositivo_nombre'] ?? '') . ' ' .
                ($a['modelo_nombre']      ?? '')
            ));

            // D: Serie
            $sheet->setCellValue("D{$fila}", $a['serie'] ?? '');

            // E: Activo (placa / número de activo fijo)
            $sheet->setCellValue("E{$fila}", $a['placa'] ?? '');

            // F: Estatus con color
            $sheet->setCellValue("F{$fila}", $statusKey);
            $this->aplicarEstiloStatus($sheet, "F{$fila}", $statusKey);

            // G: Tienda / ubicación
            if ($esDeUsuario) {
                $sheet->setCellValue("G{$fila}", trim(
                    ($a['negocio_nombre'] ?? '') . ' ' .
                    ($a['plaza_nombre']   ?? '')
                ));
            } else {
                $sheet->setCellValue("G{$fila}", $a['bodega_nombre'] ?? 'BODEGA');
            }

            // H: Procedencia
            $sheet->setCellValue("H{$fila}", $a['procedencia_nombre'] ?? '');

            $sheet->getStyle("B{$fila}:H{$fila}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            $sheet->getStyle("B{$fila}:H{$fila}")
                ->getFont()->setName('Century Gothic')->setSize(10);

            $sheet->getStyle("B{$fila}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$fila}:E{$fila}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$fila}:H{$fila}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $fila++;
        }
    }

    private function aplicarEstiloStatus($sheet, string $celda, string $status): void
    {
        $color = self::COLORES_STATUS[$status] ?? self::COLORES_STATUS['DEFAULT'];
        $style = $sheet->getStyle($celda);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color['fondo']);
        $style->getFont()->setColor(new Color($color['texto']))->setBold(true);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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