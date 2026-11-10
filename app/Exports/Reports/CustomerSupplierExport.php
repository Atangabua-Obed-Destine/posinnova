<?php

namespace App\Exports\Reports;

use App\Utils\ReportExportUtil;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CustomerSupplierExport implements FromArray, ShouldAutoSize, WithTitle, WithEvents
{
    private int $data_start_row = 1;

    public function __construct(
        private array $rows,
        private array $kpis,
        private array $business,
        private array $filters,
        private string $generated_by,
        private string $currency_symbol
    ) {}

    public function title(): string
    {
        return __('report.customer_supplier_groups');
    }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $output = [];

        $output[] = [$this->business['name'] ?? 'PosInnova'];
        $output[] = [__('report.customer_supplier_groups')];
        $output[] = [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by];
        $output[] = [__('report.filters') . ': ' . $filter_summary];
        $output[] = [''];

        $output[] = [__('report.summary')];
        $output[] = [__('report.total_purchase'),   $this->currency_symbol . number_format($this->kpis['total_purchase'] ?? 0, 2)];
        $output[] = [__('report.total_sales'),      $this->currency_symbol . number_format($this->kpis['total_invoice'] ?? 0, 2)];
        $output[] = [__('lang_v1.total_due'),       $this->currency_symbol . number_format($this->kpis['total_due'] ?? 0, 2)];
        $output[] = [__('report.total_contacts'),   $this->kpis['contact_count'] ?? 0];
        $output[] = [''];

        $this->data_start_row = count($output) + 1;

        $output[] = [
            __('contact.name'),
            __('lang_v1.contact_type'),
            __('report.total_purchase'),
            __('lang_v1.purchase_return'),
            __('report.total_sales'),
            __('lang_v1.sell_return'),
            __('lang_v1.opening_balance'),
            __('lang_v1.total_due'),
        ];

        $totals = ['purchase' => 0, 'purchase_return' => 0, 'invoice' => 0, 'sell_return' => 0, 'due' => 0];
        foreach ($this->rows as $row) {
            $name = $row->name ?? '';
            if (!empty($row->supplier_business_name)) {
                $name .= ' (' . $row->supplier_business_name . ')';
            }
            $total_ledger_discount_sell = $row->total_ledger_discount_sell ?? 0;
            $total_ledger_discount_purchase = $row->total_ledger_discount_purchase ?? 0;
            $due = ((float)($row->total_invoice ?? 0) - (float)($row->invoice_received ?? 0) - $total_ledger_discount_sell)
                - ((float)($row->total_purchase ?? 0) - (float)($row->purchase_paid ?? 0) - $total_ledger_discount_purchase)
                - ((float)($row->total_sell_return ?? 0) - (float)($row->sell_return_paid ?? 0))
                + ((float)($row->total_purchase_return ?? 0) - (float)($row->purchase_return_received ?? 0));
            if (($row->contact_type ?? '') === 'supplier') {
                $due -= (float)($row->opening_balance ?? 0) - (float)($row->opening_balance_paid ?? 0);
            } else {
                $due += (float)($row->opening_balance ?? 0) - (float)($row->opening_balance_paid ?? 0);
            }
            $output[] = [
                $name,
                ucfirst($row->contact_type ?? ''),
                (float)($row->total_purchase ?? 0),
                (float)($row->total_purchase_return ?? 0),
                (float)($row->total_invoice ?? 0),
                (float)($row->total_sell_return ?? 0),
                (float)($row->opening_balance ?? 0),
                $due,
            ];
            $totals['purchase'] += (float)($row->total_purchase ?? 0);
            $totals['purchase_return'] += (float)($row->total_purchase_return ?? 0);
            $totals['invoice'] += (float)($row->total_invoice ?? 0);
            $totals['sell_return'] += (float)($row->total_sell_return ?? 0);
            $totals['due'] += $due;
        }

        $output[] = [__('lang_v1.total'), '', $totals['purchase'], $totals['purchase_return'], $totals['invoice'], $totals['sell_return'], '', $totals['due']];

        return $output;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last_col = 'H';

                $sheet->mergeCells('A1:' . $last_col . '1');
                $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $sheet->mergeCells('A2:' . $last_col . '2');
                $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 13], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                foreach ([3, 4] as $r) { $sheet->mergeCells("A{$r}:{$last_col}{$r}"); }
                $sheet->mergeCells('A6:' . $last_col . '6');
                $sheet->getStyle('A6')->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']]]);
                for ($r = 7; $r <= 10; $r++) { $sheet->getStyle("A{$r}")->getFont()->setBold(true); }

                $hr = $this->data_start_row;
                $sheet->getStyle("A{$hr}:{$last_col}{$hr}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '833C00']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $dr = $hr + 1;
                $high = $sheet->getHighestRow();
                for ($r = $dr; $r < $high; $r++) {
                    $bg = ($r % 2 === 0) ? 'F5F5F5' : 'FFFFFF';
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFont()->setSize(9);
                }
                $sheet->getStyle("A{$high}:{$last_col}{$high}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]]]);

                // Highlight rows where due > 0 with light red
                for ($r = $dr; $r < $high; $r++) {
                    $due_val = $sheet->getCell("H{$r}")->getValue();
                    if (is_numeric($due_val) && (float)$due_val > 0) {
                        $sheet->getStyle("H{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCDD2');
                    }
                }

                foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
                    $sheet->getStyle("{$col}{$dr}:{$col}{$high}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            },
        ];
    }
}
