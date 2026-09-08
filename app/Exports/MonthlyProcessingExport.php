<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use ZipArchive;

class MonthlyProcessingExport
{
    public function __construct(private readonly Collection $rows, private readonly array $totals) {}

    public function download(string $filename)
    {
        $path = tempnam(sys_get_temp_dir(), 'monthly-processing-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    private function sheet(): string
    {
        $rows = [['No Anggota', 'Nama Member', 'Saving', 'Loan', 'Cicilan ke', 'Expense', 'Total']];
        foreach ($this->rows as $row) {
            $rows[] = [$row['member_icuno'], $row['member_name'], $row['saving'], $row['loan'], $row['installments'], $row['expense'], $row['total']];
        }
        $rows[] = ['TOTAL', '', $this->totals['saving'], $this->totals['loan'], '', $this->totals['expense'], $this->totals['total']];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowNumber => $row) {
            $xml .= '<row r="'.($rowNumber + 1).'">';
            foreach ($row as $column => $value) {
                $cell = chr(65 + $column).($rowNumber + 1);
                if (is_int($value)) {
                    $xml .= '<c r="'.$cell.'"><v>'.$value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.e((string) $value).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Monthly Processing" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    }
}
