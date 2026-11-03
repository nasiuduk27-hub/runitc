<?php

namespace App\Exports\CreditUnion;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\ReferenceHelper;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollReconciliationExport
{
    public function download(string $period, string $company, string $source, iterable $members, iterable $bank): StreamedResponse
    {
        $book = IOFactory::load(resource_path('templates/credit-union/payroll-reconciliation.xlsx'));
        $summary = $book->getSheetByName('Ringkasan');
        foreach (['B5' => $period, 'B6' => $company, 'B7' => $source] as $cell => $value) {
            $summary->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
        }
        $this->fill($book->getSheetByName('Anggota'), $members, ['A', 'B', 'C', 'D', 'E', 'F', 'H', 'I', 'L'], ['G', 'J', 'K']);
        $this->fill($book->getSheetByName('Bank'), $bank, ['A', 'B', 'C'], ['D']);
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(static function () use ($book): void {
            try {
                $writer = new Xlsx($book);
                // Excel recalculates the editable template, including full-column duplicate checks.
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, "Rekon-{$period}-{$company}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function fill(Worksheet $sheet, iterable $rows, array $inputs, array $formulas): void
    {
        // The master has example entries and styled input slots in rows 8–37.
        foreach ($inputs as $column) {
            for ($i = 8; $i <= 37; $i++) {
                $sheet->setCellValue($column.$i, null);
            }
        }
        $i = 7;
        foreach ($rows as $values) {
            $i++;
            if ($i > 37) {
                foreach ($formulas as $column) {
                    $formula = $sheet->getCell($column.'37')->getValue();
                    $sheet->setCellValue($column.$i, ReferenceHelper::getInstance()->updateFormulaReferencesAnyWorksheet($formula, 0, $i - 37));
                }
                foreach (array_merge($inputs, $formulas) as $column) {
                    $style = $sheet->getCell($column.'37')->getXfIndex();
                    $sheet->getCell($column.$i)->setXfIndex($style);
                }
            }
            foreach ($inputs as $index => $column) {
                $value = $values[$index];
                if ($value !== '') {
                    $sheet->setCellValueExplicit($column.$i, $value, is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
            }
        }
        if ($i > 37) {
            $status = end($formulas);
            $rules = $sheet->getConditionalStyles("{$status}8:{$status}37");
            if ($rules !== []) {
                $sheet->removeConditionalStyles("{$status}8:{$status}37");
                $sheet->setConditionalStyles("{$status}8:{$status}{$i}", $rules);
            }
        }
    }
}
