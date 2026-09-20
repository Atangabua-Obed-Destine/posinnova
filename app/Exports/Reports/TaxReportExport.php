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

class TaxReportExport implements FromArray, ShouldAutoSize, WithTitle, WithEvents
{
    private int $data_start_row = 1;

    public function __construct(
        private array $input_tax,
        private array $output_tax,
        private array $expense_tax,
        private float $tax_diff,
        private array $sell_rows,
        private array $purchase_rows,
        private array $business,
        private array $filters,
        private string $generated_by,
        private string $currency_symbol
    ) {}

    public function title(): string
    {
        return __('report.tax_report');
    }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $sym = $this->currency_symbol;
        $fmt = fn($v) => $sym . number_format((float)($v ?? 0), 2);

        $output = [];
        $output[] = [$this->business['name'] ?? 'PosInnova'];
        $output[] = [__('report.tax_report')];
        $output[] = [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by];
        $output[] = [__('report.filters') . ': ' . $filter_summary];
        $output[] = [''];

        // Tax summary block
        $output[] = [__('report.tax_summary')];
        $output[] = [__('lang_v1.input_tax') . ' (' . __('lang_v1.on_purchases') . ')', $fmt($this->input_tax['total_tax'] ?? 0)];
        $output[] = [__('lang_v1.output_tax') . ' (' . __('lang_v1.on_sales') . ')',    $fmt($this->output_tax['total_tax'] ?? 0)];
        $output[] = [__('lang_v1.expense_tax'),                                          $fmt($this->expense_tax['total_tax'] ?? 0)];
        $output[] = [$this->tax_diff >= 0 ? __('lang_v1.net_tax_payable') : __('lang_v1.net_tax_claimable'), $fmt(abs($this->tax_diff))];
        $output[] = [''];

        // Sales tax detail section
        if (!empty($this->sell_rows)) {
            $output[] = [__('lang_v1.sales_tax_details')];
            $output[] = [
                __('sale.invoice_no'),
                __('messages.date'),
                __('contact.customer'),
                __('lang_v1.taxable_value'),
                __('report.tax_amount'),
                __('lang_v1.tax_rate'),
            ];
            $this->data_start_row = count($output);
            $total_tax = 0;
            foreach ($this->sell_rows as $row) {
                $output[] = [
                    $row->ref_no ?? '',
                    $row->transaction_date ?? '',
                    $row->contact_name ?? '',
                    (float)($row->taxable_value ?? 0),
                    (float)($row->tax_amount ?? 0),
                    ($row->tax_rate ?? '') . '%',
                ];
                $total_tax += (float)($row->tax_amount ?? 0);
            }
            $output[] = [__('lang_v1.total'), '', '', '', $total_tax, ''];
            $output[] = [''];
        }

        // Purchase tax detail section
        if (!empty($this->purchase_rows)) {
            $output[] = [__('lang_v1.purchase_tax_details')];
            $output[] = [
                __('purchase.ref_no'),
                __('messages.date'),
                __('contact.supplier'),
                __('lang_v1.taxable_value'),
                __('report.tax_amount'),
                __('lang_v1.tax_rate'),
            ];
            $total_tax = 0;
            foreach ($this->purchase_rows as $row) {
                $output[] = [
                    $row->ref_no ?? '',
                    $row->transaction_date ?? '',
                    $row->contact_name ?? '',
                    (float)($row->taxable_value ?? 0),
                    (float)($row->tax_amount ?? 0),
                    ($row->tax_rate ?? '') . '%',
                ];
                $total_tax += (float)($row->tax_amount ?? 0);
            }
            $output[] = [__('lang_v1.total'), '', '', '', $total_tax, ''];
        }

        return $output;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last_col = 'F';

                $sheet->mergeCells('A1:' . $last_col . '1');
                $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $sheet->mergeCells('A2:' . $last_col . '2');
                $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 13], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                foreach ([3, 4] as $r) { $sheet->mergeCells("A{$r}:{$last_col}{$r}"); }
                $sheet->mergeCells('A6:' . $last_col . '6');
                $sheet->getStyle('A6')->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']]]);
                for ($r = 7; $r <= 10; $r++) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}:B{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFBEA');
                }
                // Net payable row highlight
                $sheet->getStyle('A10:B10')->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFD700']]]);
            },
        ];
    }
}
