<?php

namespace App\Exports\CreditUnion;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CuExcel
{
    public const COLOR_BRAND = '1D4ED8';
    public const COLOR_BRAND_DARK = '1E3A8A';
    public const COLOR_WHITE = 'FFFFFF';
    public const COLOR_ZEBRA = 'F8FAFC';
    public const COLOR_TOTAL_FILL = 'DBEAFE';
    public const COLOR_BORDER = 'CBD5E1';
    public const COLOR_SUBTITLE = '475569';
    public const COLOR_MUTED = '64748B';

    public static function currentUserName(): string
    {
        try {
            if (function_exists('app') && app()->bound('session')) {
                $sessionUser = session('user_name') ?: session('account_nm');
                if (! empty($sessionUser)) {
                    return (string) $sessionUser;
                }
            }
        } catch (\Throwable) {
            // Fallback bila session driver belum aktif
        }

        try {
            if (function_exists('app') && app()->bound('auth') && auth()->guard('legacy')->check()) {
                $legacyUser = auth()->guard('legacy')->user();
                if ($legacyUser && ! empty($legacyUser->name)) {
                    return (string) $legacyUser->name;
                }
            }
        } catch (\Throwable) {
            // Fallback bila auth guard legacy belum diboot
        }

        return 'Admin Credit Union';
    }

    public static function createSpreadsheet(string $title): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('RUN-ITC Credit Union')
            ->setCompany('International Test Center')
            ->setTitle($title)
            ->setSubject($title);

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        return $spreadsheet;
    }

    /**
     * @param  list<array{label: string, width?: int, align?: string, format?: string}>  $columns
     * @param  list<list<mixed>>  $dataRows
     * @param  list<array{label: string, values: array<int, mixed>, fill?: string, is_subtotal?: bool}>  $totalRows
     * @param  list<array{label: string, value: mixed}>  $summaryPairs
     */
    public static function buildSheet(
        Worksheet $sheet,
        string $title,
        string $subtitle,
        array $columns,
        array $dataRows,
        array $totalRows = [],
        array $summaryPairs = [],
        bool $includeNo = true
    ): void {
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/*\?\[\]:]/', ' ', $title) ?: 'Sheet1', 0, 31));

        $colCount = count($columns) + ($includeNo ? 1 : 0);
        $lastColLetter = Coordinate::stringFromColumnIndex($colCount);

        // 1. KOP
        $sheet->setCellValue('A1', 'KOPERASI SIMPAN PINJAM — CREDIT UNION • INTERNATIONAL TEST CENTER');
        $sheet->getStyle('A1')->getFont()->setSize(10)->setBold(true)->getColor()->setARGB('FF'.self::COLOR_BRAND);

        // 2. JUDUL
        $sheet->setCellValue('A2', $title);
        $sheet->getStyle('A2')->getFont()->setSize(14)->setBold(true)->getColor()->setARGB('FF0F172A');

        // 3. SUBJUDUL
        $sheet->setCellValue('A3', $subtitle);
        $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setARGB('FF'.self::COLOR_SUBTITLE);

        // 4. META
        $genDate = CarbonImmutable::now()->locale('id')->isoFormat('D MMMM Y, HH.mm');
        $metaText = 'Digenerate: '.$genDate.' WIB • Oleh: '.self::currentUserName();
        $sheet->setCellValue('A4', $metaText);
        $sheet->getStyle('A4')->getFont()->setSize(9)->setItalic(true)->getColor()->setARGB('FF'.self::COLOR_MUTED);

        $sheet->getRowDimension(1)->setRowHeight(18);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(16);
        $sheet->getRowDimension(4)->setRowHeight(16);

        $currentRow = 5;

        // 5. SUMMARY PAIRS (jika ada, misal simulasi kredit)
        if (! empty($summaryPairs)) {
            $currentRow = 6;
            foreach ($summaryPairs as $pair) {
                $sheet->setCellValue('A'.$currentRow, $pair['label']);
                $sheet->setCellValue('B'.$currentRow, $pair['value']);
                $sheet->getStyle('A'.$currentRow)->getFont()->setBold(true)->setSize(10);
                if (is_numeric($pair['value']) && ! is_float($pair['value'])) {
                    $sheet->getStyle('B'.$currentRow)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle('B'.$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $currentRow++;
            }
            $currentRow++; // baris jeda
        }

        // Garis pemisah brand sebelum header tabel
        $brandBarRow = $currentRow;
        $sheet->getRowDimension($brandBarRow)->setRowHeight(4);
        $sheet->getStyle('A'.$brandBarRow.':'.$lastColLetter.$brandBarRow)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF'.self::COLOR_BRAND);

        // 6. HEADER TABEL
        $headerRow = $brandBarRow + 1;
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $colIdx = 1;
        if ($includeNo) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$headerRow, 'No');
            $colIdx++;
        }
        foreach ($columns as $col) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$headerRow, $col['label']);
            $colIdx++;
        }

        $headerRange = 'A'.$headerRow.':'.$lastColLetter.$headerRow;
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF'.self::COLOR_BRAND);
        $sheet->getStyle($headerRange)->getFont()
            ->setBold(true)
            ->getColor()->setARGB('FF'.self::COLOR_WHITE);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 7. DATA ROWS
        $startDataRow = $headerRow + 1;
        $currDataRow = $startDataRow;
        $noCounter = 1;

        foreach ($dataRows as $rowData) {
            $sheet->getRowDimension($currDataRow)->setRowHeight(20);
            $cIdx = 1;

            if ($includeNo) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($cIdx).$currDataRow)
                    ->setValueExplicit((string) $noCounter, DataType::TYPE_NUMERIC);
                $cIdx++;
                $noCounter++;
            }

            foreach ($columns as $i => $colSpec) {
                $val = $rowData[$i] ?? null;
                $cellCoordinate = Coordinate::stringFromColumnIndex($cIdx).$currDataRow;
                $format = $colSpec['format'] ?? null;
                $align = $colSpec['align'] ?? null;

                self::applyCellValueAndStyle($sheet, $cellCoordinate, $val, $format, $align);
                $cIdx++;
            }

            // Zebra striping untuk baris genap data
            if (($currDataRow - $startDataRow) % 2 === 1) {
                $sheet->getStyle('A'.$currDataRow.':'.$lastColLetter.$currDataRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF'.self::COLOR_ZEBRA);
            }

            $currDataRow++;
        }

        $endDataRow = $currDataRow - 1;

        // 8. BARIS TOTAL / SUBTOTAL
        if (! empty($totalRows)) {
            foreach ($totalRows as $tRow) {
                $sheet->getRowDimension($currDataRow)->setRowHeight(22);
                $fillColor = $tRow['fill'] ?? self::COLOR_TOTAL_FILL;

                // Cari kolom sheet pertama yang memuat nilai numerik
                $firstValSheetCol = null;
                $cIdx = 1 + ($includeNo ? 1 : 0);
                foreach ($columns as $i => $colSpec) {
                    if (array_key_exists($i, $tRow['values']) && $tRow['values'][$i] !== '' && $tRow['values'][$i] !== null) {
                        $firstValSheetCol ??= $cIdx;
                        $val = $tRow['values'][$i];
                        $cellCoordinate = Coordinate::stringFromColumnIndex($cIdx).$currDataRow;
                        $format = $colSpec['format'] ?? (is_numeric($val) ? 'number' : null);
                        $align = $colSpec['align'] ?? (is_numeric($val) ? Alignment::HORIZONTAL_RIGHT : null);

                        self::applyCellValueAndStyle($sheet, $cellCoordinate, $val, $format, $align);
                    }
                    $cIdx++;
                }

                // Label total di kolom A
                $sheet->setCellValue('A'.$currDataRow, $tRow['label']);
                $sheet->getStyle('A'.$currDataRow)->getFont()->setBold(true);

                // Merge kolom label dari A sampai sebelum kolom nilai pertama bila ada jarak
                if ($firstValSheetCol !== null && $firstValSheetCol > 2) {
                    $mergeEndColLetter = Coordinate::stringFromColumnIndex($firstValSheetCol - 1);
                    $sheet->mergeCells('A'.$currDataRow.':'.$mergeEndColLetter.$currDataRow);
                    $sheet->getStyle('A'.$currDataRow)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                $totRange = 'A'.$currDataRow.':'.$lastColLetter.$currDataRow;
                $sheet->getStyle($totRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF'.$fillColor);
                $sheet->getStyle($totRange)->getFont()->setBold(true);

                $currDataRow++;
            }
        }

        $lastTableContentRow = $currDataRow - 1;

        // 9. BORDERS
        if ($lastTableContentRow >= $headerRow) {
            $tableRange = 'A'.$headerRow.':'.$lastColLetter.$lastTableContentRow;
            $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FF'.self::COLOR_BORDER);
        }

        // 10. FREEZE PANES & AUTOFILTER
        $freezeRow = $headerRow + 1;
        $sheet->freezePane('A'.$freezeRow);

        $filterRange = $endDataRow >= $startDataRow
            ? 'A'.$headerRow.':'.$lastColLetter.$endDataRow
            : $headerRange;
        $sheet->setAutoFilter($filterRange);

        // 11. LEBAR KOLOM
        $colIdx = 1;
        if ($includeNo) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))->setWidth(6);
            $sheet->getStyle('A'.$startDataRow.':A'.max($startDataRow, $endDataRow))
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $colIdx++;
        }
        foreach ($columns as $col) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            if (! empty($col['width'])) {
                $sheet->getColumnDimension($colLetter)->setWidth((float) $col['width']);
            } else {
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
            $colIdx++;
        }

        // 12. PRINT SETUP
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);

        $sheet->getHeaderFooter()
            ->setOddFooter('&L'.mb_substr($title, 0, 40).'&R Halaman &P dari &N');
    }

    private static function applyCellValueAndStyle(
        Worksheet $sheet,
        string $coordinate,
        mixed $val,
        ?string $format,
        ?string $align
    ): void {
        $cell = $sheet->getCell($coordinate);

        if ($val === null || $val === '') {
            $cell->setValueExplicit('', DataType::TYPE_STRING);
        } elseif ($format === 'number' || (is_numeric($val) && $format !== 'text' && $format !== 'code')) {
            $numericVal = is_string($val) && preg_match('/^-?\d+$/', $val) ? (int) $val : (is_numeric($val) ? $val + 0 : $val);
            $cell->setValueExplicit($numericVal, DataType::TYPE_NUMERIC);
            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle($coordinate)->getAlignment()->setHorizontal($align ?? Alignment::HORIZONTAL_RIGHT);
        } else {
            $cell->setValueExplicit((string) $val, DataType::TYPE_STRING);
            if ($align !== null) {
                $sheet->getStyle($coordinate)->getAlignment()->setHorizontal($align);
            } elseif ($format === 'center' || $format === 'code' || $format === 'date') {
                $sheet->getStyle($coordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } else {
                $sheet->getStyle($coordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }
        }

        $sheet->getStyle($coordinate)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    public static function downloadResponse(Spreadsheet $spreadsheet, string $filename): Response
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'cu-excel-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);
        $spreadsheet->disconnectWorksheets();

        $content = file_get_contents($tempPath);
        @unlink($tempPath);

        return new Response($content ?: '', 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'public',
        ]);
    }
}
