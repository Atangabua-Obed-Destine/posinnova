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

class ProductSellExport implements FromArray, ShouldAutoSize, WithTitle, WithEvents
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
        return __('report.product_sell_report');
    }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $output = [];

        $output[] = [$this->business['name'] ?? 'PosInnova'];
        $output[] = [__('report.product_sell_report')];
        $output[] = [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by];
        $output[] = [__('report.filters') . ': ' . $filter_summary];
        $output[] = [''];

        $output[] = [__('report.summary')];
        $output[] = [__('report.total_items_sold'),   $this->kpis['total_qty'] ?? 0];
        $output[] = [__('lang_v1.subtotal'),          $this->currency_symbol . number_format($this->kpis['total_subtotal'] ?? 0, 2)];
        $output[] = [__('report.total_discount'),     $this->currency_symbol . number_format($this->kpis['total_discount'] ?? 0, 2)];
        $output[] = [__('report.total_tax'),          $this->currency_symbol . number_format($this->kpis['total_tax'] ?? 0, 2)];
        $output[] = [''];

        $this->data_start_row = count($output) + 1;

        $output[] = [
            __('product.product_name'),
            __('product.sku'),
            __('product.variation'),
            __('sale.invoice_no'),
            __('messages.date'),
            __('contact.customer'),
            __('report.unit_price'),
            __('report.sale_price'),
            __('report.qty'),
            __('sale.discount'),
            __('sale.tax'),
            __('lang_v1.subtotal'),
            __('lang_v1.payment_method'),
        ];

        $totals = ['qty' => 0, 'subtotal' => 0, 'discount' => 0, 'tax' => 0];
        foreach ($this->rows as $row) {
            $qty = (float)($row->quantity ?? 0);
            $unit_price = (float)($row->unit_price_before_discount ?? 0);
            $sale_price = (float)($row->unit_price_inc_tax ?? 0);
            $line_discount_amount = (float)($row->line_discount_amount ?? 0);
            if (($row->line_discount_type ?? '') === 'percentage') {
                $line_discount_amount = $unit_price * $qty * ($line_discount_amount / 100);
            }
            $item_tax = (float)($row->item_tax ?? 0) * $qty;
            $subtotal = (float)($row->subtotal ?? ($sale_price * $qty));

            $output[] = [
                $row->product_name ?? '',
                $row->sub_sku ?? '',
                $row->variation ?? '',
                $row->invoice_no ?? '',
                $row->transaction_date ?? '',
                $row->customer_name ?? '',
                $unit_price,
                $sale_price,
                $qty,
                $line_discount_amount,
                $item_tax,
                $subtotal,
                $row->payment_methods ?? '',
            ];
            $totals['qty'] += $qty;
            $totals['subtotal'] += $subtotal;
            $totals['discount'] += $line_discount_amount;
            $totals['tax'] += $item_tax;
        }

        $output[] = [
            __('lang_v1.total'), '', '', '', '', '',
            '', '', $totals['qty'], $totals['discount'], $totals['tax'], $totals['subtotal'], '',
        ];

        return $output;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last_col = 'M';

                $sheet->mergeCells('A1:' . $last_col . '1');
                $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $sheet->mergeCells('A2:' . $last_col . '2');
                $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 13], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                foreach ([3, 4] as $r) { $sheet->mergeCells("A{$r}:{$last_col}{$r}"); $sheet->getStyle("A{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9]]); }
                $sheet->mergeCells('A6:' . $last_col . '6');
                $sheet->getStyle('A6')->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']]]);
                for ($r = 7; $r <= 10; $r++) { $sheet->getStyle("A{$r}")->getFont()->setBold(true); }

                $hr = $this->data_start_row;
                $sheet->getStyle("A{$hr}:{$last_col}{$hr}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $dr = $hr + 1;
                $high = $sheet->getHighestRow();
                for ($r = $dr; $r < $high; $r++) {
                    $bg = ($r % 2 === 0) ? 'F5F5F5' : 'FFFFFF';
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFont()->setSize(9);
                }
                $sheet->getStyle("A{$high}:{$last_col}{$high}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]]]);
                foreach (['G', 'H', 'I', 'J', 'K', 'L'] as $col) {
                    $sheet->getStyle("{$col}{$dr}:{$col}{$high}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            },
        ];
    }
}
