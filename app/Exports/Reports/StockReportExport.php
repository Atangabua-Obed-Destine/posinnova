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

class StockReportExport implements FromArray, ShouldAutoSize, WithTitle, WithEvents
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
        return __('report.stock_report');
    }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $output = [];

        $output[] = [$this->business['name'] ?? 'PosInnova'];
        $output[] = [__('report.stock_report')];
        $output[] = [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by];
        $output[] = [__('report.filters') . ': ' . $filter_summary];
        $output[] = [''];

        $output[] = [__('report.summary')];
        $output[] = [__('report.total_sku'),                  $this->kpis['total_sku'] ?? 0];
        $output[] = [__('report.low_stock_alert_count'),       $this->kpis['low_stock_count'] ?? 0];
        $output[] = [__('lang_v1.stock_value_by_purchase_price'), $this->currency_symbol . number_format($this->kpis['total_stock_value_purchase'] ?? 0, 2)];
        $output[] = [__('lang_v1.stock_value_by_sale_price'),  $this->currency_symbol . number_format($this->kpis['total_stock_value_sale'] ?? 0, 2)];
        $output[] = [__('lang_v1.potential_profit'),           $this->currency_symbol . number_format($this->kpis['total_potential_profit'] ?? 0, 2)];
        $output[] = [''];

        $this->data_start_row = count($output) + 1;

        $output[] = [
            __('product.product_name'),
            __('product.variation'),
            __('product.sku'),
            __('report.current_stock'),
            __('report.total_sold'),
            __('report.total_transferred'),
            __('report.total_adjusted'),
            __('lang_v1.stock_value_by_purchase_price'),
            __('lang_v1.stock_value_by_sale_price'),
            __('lang_v1.potential_profit'),
            __('product.category'),
            __('product.brand'),
        ];

        foreach ($this->rows as $row) {
            $stock = (float)($row->stock ?? 0);
            $unit_price = (float)(($row->group_price ?? 0) > 0 ? $row->group_price : ($row->unit_price ?? 0));
            $stock_value_sale = $stock * $unit_price;
            $stock_price = (float)($row->stock_price ?? 0);
            $potential_profit = $stock_value_sale - $stock_price;
            $variation = '';
            if (($row->type ?? '') === 'variable') {
                $variation = ($row->product_variation ?? '') . '-' . ($row->variation_name ?? '');
            }
            $output[] = [
                $row->product ?? '',
                $variation,
                $row->sub_sku ?? '',
                $stock,
                (float)($row->total_sold ?? 0),
                (float)($row->total_transfered ?? 0),
                (float)($row->total_adjusted ?? 0),
                $stock_price,
                $stock_value_sale,
                $potential_profit,
                $row->category ?? '',
                $row->brand ?? '',
            ];
        }

        return $output;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last_col = 'L';

                $sheet->mergeCells('A1:' . $last_col . '1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->mergeCells('A2:' . $last_col . '2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                foreach ([3, 4] as $r) {
                    $sheet->mergeCells("A{$r}:{$last_col}{$r}");
                    $sheet->getStyle("A{$r}")->applyFromArray([
                        'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '555555']],
                    ]);
                }
                $sheet->mergeCells('A6:' . $last_col . '6');
                $sheet->getStyle('A6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']],
                ]);
                for ($r = 7; $r <= 11; $r++) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}:B{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F5');
                }

                $header_row = $this->data_start_row;
                $sheet->getStyle("A{$header_row}:{$last_col}{$header_row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '833C00']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $data_row = $header_row + 1;
                $high = $sheet->getHighestRow();
                for ($r = $data_row; $r <= $high; $r++) {
                    $bg = ($r % 2 === 0) ? 'F5F5F5' : 'FFFFFF';
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFont()->setSize(9);
                }

                // Highlight low-stock rows in light red (stock <= 0 means col D <= 0)
                for ($r = $data_row; $r <= $high; $r++) {
                    $val = $sheet->getCell("D{$r}")->getValue();
                    if (is_numeric($val) && (float)$val <= 5) {
                        $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFE0E0');
                    }
                }

                foreach (['D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                    $sheet->getStyle("{$col}{$data_row}:{$col}{$high}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }
            },
        ];
    }
}
