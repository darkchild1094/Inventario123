<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\Movimiento;
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
 * Genera Excel de inventario (descarga directa, un clic):
 *  - Una pestaña por Negocio-Plaza (activos en bodega)
 *  - Una pestaña por ingeniero (activos en su stock personal)
 *  - Una pestaña por ingeniero con su HISTORIAL de movimientos
 *    (altas, bajas y reemplazos: equipo que entró y el que salió)
 * Todo acotado al alcance del rol (Permisos::filtrosExportar()).
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

    /** Descarga directa del Excel de inventario, acotado por el rol. */
    public function inventario(): void
    {
        $this->verificarPermisos();

        // Catálogos grandes + PhpSpreadsheet: sube el límite para no reventar a los 120 s.
        @set_time_limit(600);
        @ini_set('memory_limit', '768M');

        $filtros      = Permisos::filtrosExportar();
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

        // 1) una pestaña por bodega (negocio - plaza)
        foreach ($activosBodega as $nombre => $activos) {
            $hoja = $esPrimera ? $hojaBase : clone $hojaMolde;
            if (!$esPrimera) $spreadsheet->addSheet($hoja);
            $this->llenarHoja($hoja, $activos, $nombre, false);
            $esPrimera = false;
        }

        // 2) por cada ingeniero: su stock personal + su historial de movimientos
        $movModel = new Movimiento($this->db);
        foreach ($activosUsuario as $nombre => $activos) {
            $hoja = $esPrimera ? $hojaBase : clone $hojaMolde;
            if (!$esPrimera) $spreadsheet->addSheet($hoja);
            $this->llenarHoja($hoja, $activos, $nombre, true);
            $esPrimera = false;

            $engId = (int) ($activos[0]['usuario_stock_id'] ?? 0);
            if ($engId > 0) {
                $movs = $movModel->listar(['usuario_id' => $engId], 1, 100_000)['movimientos'] ?? [];
                if ($movs) {
                    $hojaMov = $spreadsheet->createSheet();
                    $this->llenarHojaMovimientos($hojaMov, $movs, $nombre . ' - MOV');
                }
            }
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

    /**
     * Pestaña con el historial de movimientos de un ingeniero: qué dio de alta,
     * qué dio de baja y, en los reemplazos, el equipo que entró y el que salió.
     * Formato propio (no usa la plantilla).
     */
    private function llenarHojaMovimientos($sheet, array $movs, string $nombre): void
    {
        $tab = mb_substr(preg_replace('/[*:\\/\\\\?\[\]—–]/', '-', $nombre), 0, 31);
        try {
            $sheet->setTitle($tab);
        } catch (\Throwable) {
            $sheet->setTitle(mb_substr($tab, 0, 26) . '_' . rand(10, 99));
        }

        $enc = ['Fecha', 'Evento', 'Equipo', 'Serie', 'Código', 'N° activo',
                'Estatus', 'Relación', 'Equipo relacionado', 'Serie rel.',
                'Código rel.', 'N° activo rel.', 'Nota'];
        foreach ($enc as $i => $txt) {
            $sheet->setCellValue([$i + 1, 1], $txt);
        }

        $labels = Movimiento::EVENTOS;
        $relLabel = fn(string $ev) => match ($ev) {
            'reemplazo_entra' => 'Sale (retirado)',
            'reemplazo_sale'  => 'Entra (instalado)',
            default           => '',
        };

        $fila = 2;
        foreach ($movs as $m) {
            $eq  = trim(($m['eq_dispositivo'] ?? '') . ' · '
                 . trim(($m['eq_marca'] ?? '') . ' ' . ($m['eq_modelo'] ?? '')), ' ·');
            $rel = trim(($m['rel_dispositivo'] ?? '') . ' · '
                 . trim(($m['rel_marca'] ?? '') . ' ' . ($m['rel_modelo'] ?? '')), ' ·');
            $estatus = trim(($m['status_anterior'] ?? '—') . ' → ' . ($m['status_nuevo'] ?? '—'), ' →');

            $vals = [
                substr((string) ($m['creado_en'] ?? ''), 0, 16),
                $labels[$m['evento']] ?? $m['evento'],
                $eq,
                $m['eq_serie'] ?? '',
                $m['eq_codigo_barras'] ?? '',
                $m['eq_num_activo'] ?? '',
                $estatus,
                $relLabel($m['evento']),
                $rel,
                $m['rel_serie'] ?? '',
                $m['rel_codigo_barras'] ?? '',
                $m['rel_num_activo'] ?? '',
                $m['nota'] ?? '',
            ];
            foreach ($vals as $i => $v) {
                $sheet->setCellValue([$i + 1, $fila], $v);
            }
            $fila++;
        }
        $ultima = $fila - 1;

        // estilo en una pasada
        $sheet->getStyle("A1:M1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF212529']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        if ($ultima >= 2) {
            $sheet->getStyle("A2:M{$ultima}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A2:M{$ultima}")->getFont()->setName('Century Gothic')->setSize(9);
        }
        // Anchos fijos (autoSize sobre muchas filas es un cuello de botella).
        $anchos = ['A' => 16, 'B' => 18, 'C' => 30, 'D' => 20, 'E' => 14, 'F' => 14,
                   'G' => 20, 'H' => 16, 'I' => 28, 'J' => 20, 'K' => 14, 'L' => 14, 'M' => 45];
        foreach ($anchos as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->freezePane('A2');
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