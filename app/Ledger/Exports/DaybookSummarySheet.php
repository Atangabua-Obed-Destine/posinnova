<?php

namespace App\Ledger\Exports;

use App\Ledger\Models\FiscalYear;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The Monthly Summary in the screen's column order: Monthly · BB Forward ·
 * Cumulative (paxhitestbed App\Exports\DaybookSummarySheet, without the
 * Budget and Balance columns: PosInnova keeps no budgets). Named "<Month> MS"
 * as the workbook names its tabs.
 */
class DaybookSummarySheet implements FromArray, WithTitle, WithColumnWidths, WithEvents, WithStrictNullComparison
{
    protected array $data;

    protected int $headingRow = 0;

    protected int $lastRow = 0;

    protected array $sectionRows = [];

    protected array $totalRows = [];

    protected array $headerRows = [];

    protected array $subtotalRows = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('~[:\\\\/?*\[\]]~', '-', FiscalYear::periodLabel($this->data['period'])).' MS', 0, 31);
    }

    public function array(): array
    {
        $summary = $this->data['summary'];
        $totals = $summary['totals'];
        $period = FiscalYear::periodLabel($this->data['period']);
        $account = $this->data['account'];
        $n = fn ($v) => abs((float) $v) < 0.005 ? null : round((float) $v, 2);

        $rows = [];
        $rows[] = [$this->data['businessName'] ?? ''];
        $rows[] = [mb_strtoupper(__('ledger_daybook.monthly_summary_title'))];
        $rows[] = [$period.'   |   '.__('ledger_daybook.fiscal_year').' '.$this->data['year']->name.'   |   '.($account ? $account->label : __('ledger_daybook.all_cash_boxes'))];
        $rows[] = [__('ledger_daybook.generated').' '.now()->format('d/m/Y H:i').'   |   '.__('ledger_daybook.amounts_in', ['currency' => session('currency')['symbol'] ?? 'FCFA'])];
        $rows[] = [''];

        $this->headingRow = count($rows) + 1;
        $rows[] = [__('ledger_daybook.code'), __('ledger_daybook.description'), __('ledger_daybook.monthly'), __('ledger_daybook.bb_forward'), __('ledger_daybook.cumulative')];

        $labels = [
            'income' => __('ledger_daybook.sec_income'),
            'expenditure' => __('ledger_daybook.sec_expenditure'),
            'capital' => __('ledger_daybook.sec_capital'),
            'transfers' => __('ledger_daybook.sec_transfers'),
        ];
        foreach ($labels as $section => $label) {
            if ($section === 'transfers' && empty($summary['lines']['transfers'])) {
                continue;
            }
            $rows[] = [$label, '', '', '', ''];
            $this->sectionRows[] = count($rows);
            foreach ($summary['lines'][$section] as $row) {
                if ($row['kind'] === 'subtotal') {
                    $rows[] = ['', '    '.__('ledger_daybook.total').' '.$row['name'], $n($row['monthly']), $n($row['brought']), $n($row['cumulative'])];
                    $this->subtotalRows[] = count($rows);
                    continue;
                }
                $rows[] = [$row['code'], ($row['kind'] === 'child' ? '    ' : '').($row['kind'] === 'header' ? mb_strtoupper($row['name']) : $row['name']),
                    $n($row['monthly']), $n($row['brought']), $n($row['cumulative'])];
                if ($row['kind'] === 'header') {
                    $this->headerRows[] = count($rows);
                }
            }
            $rows[] = ['', __('ledger_daybook.total').' '.$label, $n($totals[$section]['monthly']), $n($totals[$section]['brought']), $n($totals[$section]['cumulative'])];
            $this->totalRows[] = count($rows);
        }
        $rows[] = ['', __('ledger_daybook.net_balance'), $n($summary['net']['monthly']), $n($summary['net']['brought']), $n($summary['net']['cumulative'])];
        $this->totalRows[] = count($rows);
        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 10, 'B' => 50, 'C' => 16, 'D' => 16, 'E' => 16];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max($this->lastRow, $this->headingRow);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $h = "A{$this->headingRow}:E{$this->headingRow}";
                $sheet->getStyle($h)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($h)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('34495E');
                $sheet->getStyle('C'.($this->headingRow + 1).':E'.$last)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);"—"');
                foreach ($this->sectionRows as $row) {
                    $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('212B36');
                }
                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF1F6');
                }
                foreach ($this->subtotalRows as $row) {
                    $sheet->getStyle("A{$row}:E{$row}")->getFont()->setItalic(true)->setBold(true);
                }
                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:E{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('34495E');
                }
                $sheet->freezePane('C'.($this->headingRow + 1));
            },
        ];
    }
}
