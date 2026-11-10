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

class PurchaseReportExport implements FromArray, ShouldAutoSize, WithTitle, WithEvents
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
        return __('lang_v1.purchase') . ' Report';
    }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $output = [];

        $output[] = [$this->business['name'] ?? 'PosInnova'];
        $output[] = ['Report 606 — ' . __('lang_v1.purchase')];
        $output[] = [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by];
        $output[] = [__('report.filters') . ': ' . $filter_summary];
        $output[] = [''];

        $output[] = [__('report.summary')];
        $output[] = [__('report.total_purchase'),         $this->currency_symbol . number_format($this->kpis['total_purchase'] ?? 0, 2)];
        $output[] = [__('report.total_purchase') . ' (' . __('product.exc_of_tax') . ')', $this->currency_symbol . number_format($this->kpis['total_before_tax'] ?? 0, 2)];
        $output[] = [__('sale.tax'),                      $this->currency_symbol . number_format($this->kpis['total_tax'] ?? 0, 2)];
        $output[] = [__('sale.discount'),                 $this->currency_symbol . number_format($this->kpis['total_discount'] ?? 0, 2)];
        $output[] = [__('lang_v1.num_purchases'),         $this->kpis['purchase_count'] ?? 0];
        $output[] = [''];

        $this->data_start_row = count($output) + 1;

        $output[] = [
            __('lang_v1.contact_id'),
            __('contact.supplier'),
            __('purchase.ref_no'),
            __('messages.date'),
            __('sale.total') . ' (' . __('product.exc_of_tax') . ')',
            __('sale.discount'),
            __('sale.tax'),
            __('sale.total') . ' (' . __('product.inc_of_tax') . ')',
            __('lang_v1.payment_method'),
            __('lang_v1.payment_status'),
        ];

        $totals = ['total_before_tax' => 0, 'discount' => 0, 'tax_amount' => 0, 'final_total' => 0];
        foreach ($this->rows as $row) {
            $discount = (float)($row->discount_amount ?? 0);
            if (($row->discount_type ?? '') === 'percentage') {
                $discount = (float)($row->total_before_tax ?? 0) * ($discount / 100);
            }
            $output[] = [
                $row->contact_id ?? '',
                $row->name ?? '',
                $row->ref_no ?? '',
                ($row->purchase_year_month ?? '') . '/' . ($row->purchase_day ?? ''),
                $row->total_before_tax ?? 0,
                $discount,
                $row->tax_amount ?? 0,
                $row->final_total ?? 0,
                $row->payment_method_plain ?? '',
                $row->payment_status ?? '',
            ];
            $totals['total_before_tax'] += (float)($row->total_before_tax ?? 0);
            $totals['discount'] += $discount;
            $totals['tax_amount'] += (float)($row->tax_amount ?? 0);
            $totals['final_total'] += (float)($row->final_total ?? 0);
        }

        $output[] = [
            __('lang_v1.total'), '', '', '',
            $totals['total_before_tax'],
            $totals['discount'],
            $totals['tax_amount'],
            $totals['final_total'],
            '', '',
        ];

        return $output;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last_col = 'J';

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
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                ]);
                for ($r = 7; $r <= 11; $r++) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}:B{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
                    ]);
                }

                $header_row = $this->data_start_row;
                $sheet->getStyle("A{$header_row}:{$last_col}{$header_row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '375623']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $data_row = $header_row + 1;
                $high = $sheet->getHighestRow();
                for ($r = $data_row; $r < $high; $r++) {
                    $bg = ($r % 2 === 0) ? 'F5F5F5' : 'FFFFFF';
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFont()->setSize(9);
                }
                $sheet->getStyle("A{$high}:{$last_col}{$high}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);
                foreach (['E', 'F', 'G', 'H'] as $col) {
                    $sheet->getStyle("{$col}{$data_row}:{$col}{$high}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }
            },
        ];
    }
}
