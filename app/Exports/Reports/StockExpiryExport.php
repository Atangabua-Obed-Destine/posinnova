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
use Carbon\Carbon;

class StockExpiryExport implements FromArray, ShouldAutoSize, WithTitle, WithEvents
{
    private int $data_start_row = 1;
    private array $row_expiry_map = [];

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
        return __('report.stock_expiry_report');
    }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $output = [];

        $output[] = [$this->business['name'] ?? 'PosInnova'];
        $output[] = [__('report.stock_expiry_report')];
        $output[] = [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by];
        $output[] = [__('report.filters') . ': ' . $filter_summary];
        $output[] = [''];

        $output[] = [__('report.summary')];
        $output[] = [__('lang_v1.expired'),         $this->kpis['expired_count'] ?? 0];
        $output[] = [__('lang_v1.expiring_30_days'), $this->kpis['expiring_30'] ?? 0];
        $output[] = [__('lang_v1.expiring_90_days'), $this->kpis['expiring_90'] ?? 0];
        $output[] = [__('lang_v1.total_items'),      $this->kpis['total_items'] ?? 0];
        $output[] = [''];

        $this->data_start_row = count($output) + 1;

        $output[] = [
            __('product.product_name'),
            __('product.variation'),
            __('lang_v1.lot_number'),
            __('lang_v1.mfg_date'),
            __('lang_v1.exp_date'),
            __('lang_v1.days_to_expiry'),
            __('report.current_stock'),
            __('product.unit'),
            __('purchase.ref_no'),
        ];

        $today = Carbon::today();
        foreach ($this->rows as $i => $row) {
            $exp_date = !empty($row->exp_date) ? Carbon::parse($row->exp_date) : null;
            $days = $exp_date ? $today->diffInDays($exp_date, false) : null;

            $this->row_expiry_map[] = $days;

            $output[] = [
                $row->product ?? '',
                $row->variation ?? '',
                $row->lot_number ?? '',
                $row->mfg_date ?? '',
                $row->exp_date ?? '',
                $days !== null ? intval($days) : '',
                (float)($row->qty_available ?? 0),
                $row->unit ?? '',
                $row->ref_no ?? '',
            ];
        }

        return $output;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last_col = 'I';

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
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
                ]);
                for ($r = 7; $r <= 10; $r++) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}:B{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFBEA');
                }

                $header_row = $this->data_start_row;
                $sheet->getStyle("A{$header_row}:{$last_col}{$header_row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7B5E00']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $data_row = $header_row + 1;
                foreach ($this->row_expiry_map as $i => $days) {
                    $r = $data_row + $i;
                    if ($days === null) continue;
                    if ($days < 0) {
                        $color = 'FF4444';  // expired — red
                    } elseif ($days <= 30) {
                        $color = 'FF8C00';  // < 30 days — orange
                    } elseif ($days <= 90) {
                        $color = 'FFD700';  // < 90 days — yellow
                    } else {
                        $color = 'C6EFCE';  // safe — light green
                    }
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFont()->setSize(9);
                }
            },
        ];
    }
}
