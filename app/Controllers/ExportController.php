<?php

namespace App\Controllers;

use App\Models\Activo;
use App\Models\Movimiento;
use App\Models\ProyectoRentec;
use App\Helpers\Permisos;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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
 * Genera Excel de inventario y movimientos (descarga directa, un clic).
 * Todas las hojas se construyen desde cero con un diseño propio (título +
 * subtítulo con fecha/total, encabezado oscuro, zebra striping, autofiltro,
 * fila congelada) en vez de depender de una plantilla externa — así cada
 * columna nueva (como N° de Activo) no exige editar un .xlsx a mano.
 * Todo acotado al alcance del rol (Permisos::filtrosExportar()/filtrosModulo()).
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

    private const COLOR_ENCABEZADO = 'FF212529'; // BsDark
    private const COLOR_BANDA      = 'FFF8F9FA'; // franja cebra, muy claro
    private const COLOR_BORDE      = 'FFDEE2E6';
    private const COLOR_SUBTITULO  = 'FF6C757D';

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    /** Descarga directa del Excel de inventario completo, acotado por el rol. */
    public function inventario(): void
    {
        $this->verificarPermisos();
        @set_time_limit(600);
        @ini_set('memory_limit', '768M');

        $filtros      = Permisos::filtrosExportar();
        $resultado    = (new Activo($this->db))->obtenerTodosFiltrado($filtros, 1, 999_999);
        $listaActivos = $resultado['activos'] ?? [];

        [$activosBodega, $activosUsuario, $activosTienda] = $this->agruparActivos($listaActivos);

        $spreadsheet = new Spreadsheet();
        $hojaIdx     = 0;
        $movModel    = new Movimiento($this->db);

        foreach ($activosBodega as $nombre => $activos) {
            $this->llenarHoja($this->hojaEn($spreadsheet, $hojaIdx++), $activos, $nombre, 'bodega');
        }
        foreach ($activosUsuario as $nombre => $activos) {
            $this->llenarHoja($this->hojaEn($spreadsheet, $hojaIdx++), $activos, $nombre, 'usuario');
            $engId = (int) ($activos[0]['usuario_stock_id'] ?? 0);
            if ($engId > 0) {
                $movs = $movModel->listar(['usuario_id' => $engId], 1, 100_000)['movimientos'] ?? [];
                if ($movs) {
                    $this->llenarHojaMovimientos($this->hojaEn($spreadsheet, $hojaIdx++), $movs, $nombre . ' - MOV');
                }
            }
        }
        foreach ($activosTienda as $nombre => $activos) {
            $this->llenarHoja($this->hojaEn($spreadsheet, $hojaIdx++), $activos, $nombre, 'tienda');
        }

        if ($hojaIdx === 0) { $this->llenarHoja($this->hojaEn($spreadsheet, 0), [], 'SIN DATOS', 'bodega'); }
        $spreadsheet->setActiveSheetIndex(0);
        $this->descargarExcel($spreadsheet, 'Inventario_' . date('Y-m-d_H-i') . '.xlsx');
    }

    /**
     * Exporta SÓLO los activos de un módulo (Tiendas, Bodega, Mi Stock,
     * Stock PFS, ATI), acotado al rol. ?modulo=<m>[&tienda_id=][&plaza_id=][&proyecto_rentec_id=]
     */
    public function modulo(): void
    {
        $this->verificarPermisos();
        @set_time_limit(600);
        @ini_set('memory_limit', '768M');

        $modulo = trim((string) ($_GET['modulo'] ?? ''));
        if ($modulo === '' || !Permisos::moduloPermitido($modulo)) {
            $_SESSION['error'] = 'Módulo inválido para exportar.';
            header('Location: index.php?controller=dashboard');
            exit;
        }

        $filtros = Permisos::filtrosModulo($modulo);

        $tiendaId = (int) ($_GET['tienda_id'] ?? 0);
        if ($tiendaId > 0) {
            $t = (new \App\Models\Tienda($this->db))->obtenerPorId($tiendaId);
            if ($t && (Permisos::esAdmin() || in_array((int) $t['plaza_id'], Permisos::misPlazas(), true))) {
                $filtros['tienda_id'] = $tiendaId;
            }
        }
        $plazaGet = (int) ($_GET['plaza_id'] ?? 0);
        if ($plazaGet > 0 && (Permisos::esAdmin() || in_array($plazaGet, Permisos::misPlazas(), true))) {
            $filtros['plaza_id'] = $plazaGet;
        }
        $rentecGet = (int) ($_GET['proyecto_rentec_id'] ?? 0);
        if ($rentecGet > 0) {
            $filtros['proyecto_rentec_id'] = $rentecGet;
        }

        $activos = (new Activo($this->db))->obtenerTodosFiltrado($filtros, 1, 999_999)['activos'] ?? [];

        // Agrupar según el módulo.
        $grupos = [];
        $modo   = 'bodega';
        switch ($modulo) {
            case 'tiendas':
                $modo = 'tienda';
                foreach ($activos as $a) $grupos[$this->claveTienda($a)][] = $a;
                break;
            case 'bodega':
                $modo = 'bodega';
                foreach ($activos as $a) $grupos[$this->claveUbicacion($a)][] = $a;
                break;
            default: // mi_stock | stock_usuarios | stock_pfs | ati → stock personal, por ingeniero
                $modo = 'usuario';
                foreach ($activos as $a) $grupos[$this->claveUsuario($a)][] = $a;
                break;
        }
        ksort($grupos);
        if (!$grupos) { $grupos = ['SIN DATOS' => []]; }

        $spreadsheet = new Spreadsheet();
        $hojaIdx     = 0;
        $movModel    = new Movimiento($this->db);

        foreach ($grupos as $nombre => $lista) {
            $this->llenarHoja($this->hojaEn($spreadsheet, $hojaIdx++), $lista, (string) $nombre, $modo);

            // Para stock personal, añade la pestaña de movimientos del ingeniero.
            if ($modo === 'usuario' && !empty($lista)) {
                $engId = (int) ($lista[0]['usuario_stock_id'] ?? 0);
                if ($engId > 0) {
                    $movs = $movModel->listar(['usuario_id' => $engId], 1, 100_000)['movimientos'] ?? [];
                    if ($movs) {
                        $this->llenarHojaMovimientos($this->hojaEn($spreadsheet, $hojaIdx++), $movs, $nombre . ' - MOV');
                    }
                }
            }
        }

        $spreadsheet->setActiveSheetIndex(0);
        $etiqueta = preg_replace('/[^A-Za-z0-9_]+/', '', ucfirst($modulo));
        $this->descargarExcel($spreadsheet, "Inventario_{$etiqueta}_" . date('Y-m-d_H-i') . '.xlsx');
    }

    /**
     * Export de los movimientos de una pestaña del módulo "Movimientos"
     * (Bodega / Usuario / Tienda), con los mismos filtros que la pantalla.
     * ?lugar_tipo=bodega|usuario|tienda [&serie=]
     */
    public function movimientos(): void
    {
        $this->verificarPermisos();
        @set_time_limit(300);

        $lugarTipo = trim((string) ($_GET['lugar_tipo'] ?? ''));
        $filtros = array_filter([
            'lugar_tipo' => in_array($lugarTipo, ['bodega', 'usuario', 'tienda'], true) ? $lugarTipo : null,
            'serie'      => $_GET['serie'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
        $filtros = array_merge(Permisos::filtrosHistorial(), $filtros);

        $movs = (new Movimiento($this->db))->listar($filtros, 1, 200_000)['movimientos'] ?? [];

        $spreadsheet = new Spreadsheet();
        $etiqueta = ['bodega' => 'Bodega', 'usuario' => 'Usuario', 'tienda' => 'Tienda'][$lugarTipo] ?? 'Todos';
        $this->llenarHojaMovimientos($spreadsheet->getActiveSheet(), $movs, "Movimientos {$etiqueta}");

        $this->descargarExcel($spreadsheet, "Movimientos_{$etiqueta}_" . date('Y-m-d_H-i') . '.xlsx');
    }

    /**
     * Export de un proyecto RENTEC: tres bloques (instalados con su retiro
     * pareado si lo hubo, y los retiros sueltos), con N° de Activo de ambos
     * lados — es justo el dato que antes faltaba para auditar un folio.
     */
    public function rentec(): void
    {
        $this->verificarPermisos();
        $id = (int) ($_GET['id'] ?? 0);
        $model = new ProyectoRentec($this->db);
        $cab = $model->obtenerCabecera($id);
        if (!$cab || !$this->puedeVerRentec($cab, $model)) {
            $_SESSION['error'] = 'Proyecto no encontrado.';
            header('Location: index.php?controller=dashboard');
            exit;
        }

        $detalle = $model->obtenerDetalle($id);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RENTEC');

        $colFin = 'N';
        $titulo = trim(($cab['folio'] ?? '') . ' · ' . ($cab['nombre'] ?? ''));
        $total = count($detalle['detalle_instalados'] ?? []) + count($detalle['detalle_retirados_sin_reemplazo'] ?? []);
        $this->bloqueTitulo($sheet, $colFin, $titulo, $total, 'movimiento(s)');

        $filaEnc = 4;
        $headers = [
            'CR', 'TIENDA', 'PFS',
            'INSTALADO', 'Modelo instalado', 'Serie instalada', 'CB instalado', 'N° Activo instalado',
            'RETIRADO', 'Modelo retirado', 'Serie retirada', 'CB retirado', 'N° Activo retirado',
            'FECHA',
        ];
        $this->estiloEncabezado($sheet, $filaEnc, $headers);

        $fila = $filaEnc + 1;
        foreach ($detalle['detalle_instalados'] ?? [] as $row) {
            $hayRetirado = !empty($row['activo_relacionado_id']);
            $vals = [
                $row['cr_tienda'] ?? '', $row['tienda_nombre'] ?? '', $this->claveUsuario($row),
                $row['dispositivo_nombre'] ?? '',
                trim(($row['marca_nombre'] ?? '') . ' ' . ($row['modelo_nombre'] ?? '')),
                $row['serie_entra'] ?? '', $row['codigo_entra'] ?? '', $row['num_activo_entra'] ?? '',
                $hayRetirado ? ($row['dispositivo_sale_nombre'] ?? '') : '(equipo adicional, sin retiro)',
                $hayRetirado ? trim(($row['marca_sale_nombre'] ?? '') . ' ' . ($row['modelo_sale_nombre'] ?? '')) : '',
                $hayRetirado ? ($row['serie_sale'] ?? '') : '',
                $hayRetirado ? ($row['codigo_sale'] ?? '') : '',
                $hayRetirado ? ($row['num_activo_sale'] ?? '') : '',
                substr((string) ($row['creado_en'] ?? ''), 0, 16),
            ];
            $this->escribirFila($sheet, $fila, $vals, [6, 7, 8, 11, 12, 13]);
            $fila++;
        }
        foreach ($detalle['detalle_retirados_sin_reemplazo'] ?? [] as $row) {
            $vals = [
                $row['cr_tienda'] ?? '', $row['tienda_nombre'] ?? '', $this->claveUsuario($row),
                '(sin instalación — solo retiro)', '', '', '', '',
                $row['dispositivo_nombre'] ?? '',
                trim(($row['marca_nombre'] ?? '') . ' ' . ($row['modelo_nombre'] ?? '')),
                $row['serie'] ?? '', $row['codigo_barras'] ?? '', $row['num_activo'] ?? '',
                substr((string) ($row['creado_en'] ?? ''), 0, 16),
            ];
            $this->escribirFila($sheet, $fila, $vals, [6, 7, 8, 11, 12, 13]);
            $fila++;
        }

        $ultima = $fila - 1;
        $this->estiloDatos($sheet, $filaEnc, $ultima, $colFin, [1 => 'center', 3 => 'center']);
        $anchos = ['A' => 10, 'B' => 26, 'C' => 14, 'D' => 20, 'E' => 18, 'F' => 18, 'G' => 14, 'H' => 16,
                   'I' => 20, 'J' => 18, 'K' => 18, 'L' => 14, 'M' => 16, 'N' => 16];
        foreach ($anchos as $col => $w) $sheet->getColumnDimension($col)->setWidth($w);
        $sheet->freezePane('A' . ($filaEnc + 1));
        $sheet->setAutoFilter("A{$filaEnc}:{$colFin}{$filaEnc}");

        $folio = preg_replace('/[^A-Za-z0-9_-]+/', '', $cab['folio'] ?? "id{$id}");
        $this->descargarExcel($spreadsheet, "RENTEC_{$folio}_" . date('Y-m-d_H-i') . '.xlsx');
    }

    private function puedeVerRentec(array $cabecera, ProyectoRentec $model): bool
    {
        if (Permisos::esAdmin()) return true;
        if ((int) $cabecera['usuario_id'] === Permisos::idUsuario()) return true;
        return $model->tocaPlazas((int) $cabecera['id'], Permisos::misPlazas());
    }

    // ── Privados: agrupación ─────────────────────────────────────────────────

    private function agruparActivos(array $activos): array
    {
        $porBodega  = [];
        $porUsuario = [];
        $porTienda  = [];

        $idsTienda = [];
        foreach ($activos as $a) {
            if (($a['stock_tipo'] ?? '') === 'tienda') {
                $idsTienda[] = (int) $a['id'];
            }
        }
        $conOrigenBodega = $idsTienda ? $this->activosConOrigenBodega($idsTienda) : [];

        foreach ($activos as $a) {
            $st = $a['stock_tipo'] ?? '';
            if ($st === 'bodega') {
                $porBodega[$this->claveUbicacion($a)][] = $a;
            } elseif ($st === 'tienda') {
                if (isset($conOrigenBodega[(int) $a['id']])) {
                    $porTienda[$this->claveTienda($a)][] = $a;
                }
            } else {
                $porUsuario[$this->claveUsuario($a)][] = $a;
            }
        }

        ksort($porBodega);
        ksort($porUsuario);
        ksort($porTienda);

        return [$porBodega, $porUsuario, $porTienda];
    }

    private function activosConOrigenBodega(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];

        $out = [];
        foreach (array_chunk($ids, 1000) as $lote) {
            $ph  = implode(',', array_fill(0, count($lote), '?'));
            $sql = "SELECT DISTINCT m.activo_id
                    FROM movimiento m
                    JOIN stock s ON s.id = m.stock_anterior_id
                    WHERE s.tipo = 'bodega' AND m.activo_id IN ({$ph})";
            $st = $this->db->prepare($sql);
            $st->execute($lote);
            foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $id) {
                $out[(int) $id] = true;
            }
        }
        return $out;
    }

    private function claveTienda(array $a): string
    {
        $tienda = mb_strtoupper(trim($a['tienda_stock_nombre'] ?? ''));
        return $tienda !== '' ? $tienda : mb_strtoupper(trim($a['plaza_nombre'] ?? 'SIN TIENDA'));
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

    // ── Privados: construcción visual de hojas ──────────────────────────────

    /** Primera hoja del libro en el índice 0, el resto se crean nuevas. */
    private function hojaEn(Spreadsheet $spreadsheet, int $idx)
    {
        if ($idx === 0) return $spreadsheet->getActiveSheet();
        return $spreadsheet->createSheet();
    }

    /** Título (fila 1) + subtítulo con fecha/total (fila 2), mergeados A:colFin. */
    private function bloqueTitulo($sheet, string $colFin, string $titulo, int $total, string $unidad = 'equipo(s)'): void
    {
        $sheet->mergeCells("A1:{$colFin}1");
        $sheet->setCellValue('A1', 'INVENTARIO123 · ' . $titulo);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'name' => 'Calibri', 'color' => ['argb' => self::COLOR_ENCABEZADO]],
        ]);
        $sheet->mergeCells("A2:{$colFin}2");
        $sheet->setCellValue('A2', "{$total} {$unidad} · generado el " . date('d/m/Y H:i'));
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'name' => 'Calibri', 'color' => ['argb' => self::COLOR_SUBTITULO]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(14);
    }

    /** Fila de encabezado de columnas (oscuro, texto blanco, centrado). */
    private function estiloEncabezado($sheet, int $fila, array $headers): void
    {
        foreach ($headers as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, $fila, $h);
        }
        $colFin = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A{$fila}:{$colFin}{$fila}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_ENCABEZADO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($fila)->setRowHeight(26);
    }

    /**
     * Escribe una fila de valores a partir de la columna A, forzando texto
     * (DataType::TYPE_STRING) en las columnas indicadas por índice 1-based
     * para que series/códigos/N° de activo con ceros a la izquierda no se
     * conviertan en número.
     */
    private function escribirFila($sheet, int $fila, array $valores, array $columnasTexto = []): void
    {
        foreach ($valores as $i => $v) {
            $col = $i + 1;
            if (in_array($col, $columnasTexto, true)) {
                $sheet->setCellValueExplicitByColumnAndRow($col, $fila, (string) $v, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValueByColumnAndRow($col, $fila, $v);
            }
        }
    }

    /**
     * Bordes finos + fuente + zebra striping sobre el rango de datos
     * (filaEnc+1 .. ultima). $centradas: [colIndice1based => 'center'].
     */
    private function estiloDatos($sheet, int $filaEnc, int $ultima, string $colFin, array $centradas = []): void
    {
        $filaIni = $filaEnc + 1;
        if ($ultima < $filaIni) return;

        $sheet->getStyle("A{$filaIni}:{$colFin}{$ultima}")->applyFromArray([
            'font'    => ['name' => 'Calibri', 'size' => 10],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDE]]],
        ]);
        foreach ($centradas as $colIdx => $_) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->getStyle("{$col}{$filaIni}:{$col}{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        // Zebra: filas pares del bloque de datos con fondo gris muy claro.
        for ($f = $filaIni + 1; $f <= $ultima; $f += 2) {
            $sheet->getStyle("A{$f}:{$colFin}{$f}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_BANDA]],
            ]);
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
     * Hoja de inventario de un grupo (bodega/tienda/usuario): título,
     * encabezado, N° de Activo, estatus con color, zebra, autofiltro.
     * @param string $modo  'bodega' | 'usuario' | 'tienda' — cambia qué va en la columna Ubicación.
     */
    private function llenarHoja($sheet, array $activos, string $nombre, string $modo = 'bodega'): void
    {
        $tab = mb_substr(preg_replace('/[*:\\/\\\\?\[\]—–]/', '-', $nombre), 0, 31);
        try {
            $sheet->setTitle($tab ?: 'Hoja');
        } catch (\Throwable) {
            $sheet->setTitle(mb_substr($tab, 0, 26) . '_' . rand(10, 99));
        }
        if (empty($activos)) {
            $sheet->setCellValue('A1', 'Sin activos para ' . $nombre . '.');
            return;
        }

        $colFin = 'H';
        $this->bloqueTitulo($sheet, $colFin, $nombre, count($activos));

        $filaEnc = 4;
        $this->estiloEncabezado($sheet, $filaEnc, [
            '#', 'Equipo', 'Serie', 'Código de barras', 'N° de Activo', 'Estatus', 'Ubicación', 'Procedencia',
        ]);

        $fila = $filaEnc + 1;
        $porColor = [];
        foreach ($activos as $idx => $a) {
            $statusKey = mb_strtoupper($a['status'] ?? '');
            $ubicacion = match ($modo) {
                'usuario' => trim(($a['negocio_nombre'] ?? '') . ' ' . ($a['plaza_nombre'] ?? '')),
                'tienda'  => $a['tienda_stock_nombre'] ?? ($a['plaza_nombre'] ?? 'TIENDA'),
                default   => $a['bodega_nombre'] ?? 'BODEGA',
            };
            $this->escribirFila($sheet, $fila, [
                $idx + 1,
                trim(($a['dispositivo_nombre'] ?? '') . ' ' . ($a['marca_nombre'] ?? '') . ' ' . ($a['modelo_nombre'] ?? '')),
                $a['serie'] ?? '', $a['codigo_barras'] ?? '', $a['num_activo'] ?? '',
                $statusKey, $ubicacion, $a['procedencia_nombre'] ?? '',
            ], [3, 4, 5]);
            $porColor[$statusKey][] = $fila;
            $fila++;
        }
        $ultima = $fila - 1;

        $this->estiloDatos($sheet, $filaEnc, $ultima, $colFin, [1 => 'center', 3 => 'c', 4 => 'c', 5 => 'c', 6 => 'c']);

        foreach ($porColor as $statusKey => $filas) {
            $color = self::COLORES_STATUS[$statusKey] ?? self::COLORES_STATUS['DEFAULT'];
            $estilo = [
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color['fondo']]],
                'font'      => ['color' => ['argb' => $color['texto']], 'bold' => true, 'name' => 'Calibri', 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];
            foreach ($this->rangosContiguos($filas) as [$ini, $fin]) {
                $sheet->getStyle("F{$ini}:F{$fin}")->applyFromArray($estilo);
            }
        }

        $sheet->freezePane('A' . ($filaEnc + 1));
        $sheet->setAutoFilter("A{$filaEnc}:{$colFin}{$filaEnc}");
        $anchos = ['A' => 5, 'B' => 36, 'C' => 20, 'D' => 18, 'E' => 16, 'F' => 14, 'G' => 26, 'H' => 24];
        foreach ($anchos as $col => $w) $sheet->getColumnDimension($col)->setWidth($w);
    }

    /**
     * Pestaña con el historial de movimientos (de un ingeniero, o de una
     * pestaña Bodega/Usuario/Tienda del módulo Movimientos): qué dio de
     * alta, qué dio de baja y, en los reemplazos, el equipo que entró y el
     * que salió — con N° de Activo de ambos lados.
     */
    private function llenarHojaMovimientos($sheet, array $movs, string $nombre): void
    {
        $tab = mb_substr(preg_replace('/[*:\\/\\\\?\[\]—–]/', '-', $nombre), 0, 31);
        try {
            $sheet->setTitle($tab ?: 'Movimientos');
        } catch (\Throwable) {
            $sheet->setTitle(mb_substr($tab, 0, 26) . '_' . rand(10, 99));
        }

        $colFin = 'M';
        $this->bloqueTitulo($sheet, $colFin, $nombre, count($movs), 'movimiento(s)');

        $filaEnc = 4;
        $this->estiloEncabezado($sheet, $filaEnc, [
            'Fecha', 'Evento', 'Equipo', 'Serie', 'Código', 'N° de Activo',
            'Estatus', 'Relación', 'Equipo relacionado', 'Serie rel.',
            'Código rel.', 'N° de Activo rel.', 'Nota',
        ]);

        $labels = Movimiento::EVENTOS;
        $relLabel = fn(string $ev) => match ($ev) {
            'reemplazo_entra' => 'Sale (retirado)',
            'reemplazo_sale'  => 'Entra (instalado)',
            default           => '',
        };

        $fila = $filaEnc + 1;
        foreach ($movs as $m) {
            $eq  = trim(($m['eq_dispositivo'] ?? '') . ' · '
                 . trim(($m['eq_marca'] ?? '') . ' ' . ($m['eq_modelo'] ?? '')), ' ·');
            $rel = trim(($m['rel_dispositivo'] ?? '') . ' · '
                 . trim(($m['rel_marca'] ?? '') . ' ' . ($m['rel_modelo'] ?? '')), ' ·');
            $estatus = trim(($m['status_anterior'] ?? '—') . ' → ' . ($m['status_nuevo'] ?? '—'), ' →');

            $this->escribirFila($sheet, $fila, [
                substr((string) ($m['creado_en'] ?? ''), 0, 16),
                $labels[$m['evento']] ?? $m['evento'],
                $eq, $m['eq_serie'] ?? '', $m['eq_codigo_barras'] ?? '', $m['eq_num_activo'] ?? '',
                $estatus, $relLabel($m['evento']), $rel,
                $m['rel_serie'] ?? '', $m['rel_codigo_barras'] ?? '', $m['rel_num_activo'] ?? '',
                $m['nota'] ?? '',
            ], [4, 5, 6, 10, 11, 12]);
            $fila++;
        }
        $ultima = $fila - 1;

        $this->estiloDatos($sheet, $filaEnc, $ultima, $colFin, [2 => 'c']);

        $anchos = ['A' => 16, 'B' => 18, 'C' => 30, 'D' => 20, 'E' => 14, 'F' => 16,
                   'G' => 20, 'H' => 16, 'I' => 28, 'J' => 20, 'K' => 14, 'L' => 16, 'M' => 45];
        foreach ($anchos as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->freezePane('A' . ($filaEnc + 1));
        $sheet->setAutoFilter("A{$filaEnc}:{$colFin}{$filaEnc}");
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
        $tiposPermitidos = ['admin', 'coordinador', 'ati', 'pfs'];
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
