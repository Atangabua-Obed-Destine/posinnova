<?php

namespace App\Exports\Reports;

use App\Utils\ReportExportUtil;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SaleReportExport implements FromArray, ShouldAutoSize, WithTitle, WithEvents
{
    private int $data_start_row = 1;
    private int $total_columns = 9;

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
        return __('business.sale') . ' Report';
    }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $output = [];

        // Header block
        $output[] = [$this->business['name'] ?? 'PosInnova'];
        $output[] = ['Report 607 — ' . __('business.sale')];
        $output[] = [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by];
        $output[] = [__('report.filters') . ': ' . $filter_summary];
        $output[] = [''];

        // KPI block
        $output[] = [__('report.summary')];
        $output[] = [__('lang_v1.total_sales'),         $this->currency_symbol . number_format($this->kpis['total_sales'] ?? 0, 2)];
        $output[] = [__('sale.total') . ' (' . __('product.exc_of_tax') . ')', $this->currency_symbol . number_format($this->kpis['total_before_tax'] ?? 0, 2)];
        $output[] = [__('sale.tax'),                    $this->currency_symbol . number_format($this->kpis['total_tax'] ?? 0, 2)];
        $output[] = [__('sale.discount'),               $this->currency_symbol . number_format($this->kpis['total_discount'] ?? 0, 2)];
        $output[] = [__('lang_v1.num_invoices'),        $this->kpis['invoice_count'] ?? 0];
        $output[] = [''];

        $this->data_start_row = count($output) + 1;

        // Column headers
        $output[] = [
            __('lang_v1.contact_id'),
            __('sale.customer_name'),
            __('sale.invoice_no'),
            __('messages.date'),
            __('sale.total') . ' (' . __('product.exc_of_tax') . ')',
            __('sale.discount'),
            __('sale.tax'),
            __('sale.total') . ' (' . __('product.inc_of_tax') . ')',
            __('lang_v1.payment_method'),
        ];

        // Data rows
        $totals = ['total_before_tax' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'final_total' => 0];
        foreach ($this->rows as $row) {
            $output[] = [
                $row->contact_id ?? '',
                $row->name ?? '',
                $row->invoice_no ?? '',
                $row->sale_date ?? '',
                $row->total_before_tax ?? 0,
                $row->discount_amount ?? 0,
                $row->tax_amount ?? 0,
                $row->final_total ?? 0,
                $row->payment_methods_plain ?? '',
            ];
            $totals['total_before_tax'] += (float)($row->total_before_tax ?? 0);
            $totals['discount_amount'] += (float)($row->discount_amount ?? 0);
            $totals['tax_amount'] += (float)($row->tax_amount ?? 0);
            $totals['final_total'] += (float)($row->final_total ?? 0);
        }

        // Totals row
        $output[] = [
            __('lang_v1.total'), '', '', '',
            $totals['total_before_tax'],
            $totals['discount_amount'],
            $totals['tax_amount'],
            $totals['final_total'],
            '',
        ];

        return $output;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last_col = 'I';

                // Business name — row 1
                $sheet->mergeCells('A1:' . $last_col . '1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Report title — row 2
                $sheet->mergeCells('A2:' . $last_col . '2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Generated / filter rows — rows 3–4
                foreach ([3, 4] as $r) {
                    $sheet->mergeCells("A{$r}:{$last_col}{$r}");
                    $sheet->getStyle("A{$r}")->applyFromArray([
                        'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '555555']],
                    ]);
                }

                // KPI header — row 6
                $sheet->mergeCells('A6:' . $last_col . '6');
                $sheet->getStyle('A6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']],
                ]);

                // KPI data rows 7–11
                for ($r = 7; $r <= 11; $r++) {
                    $sheet->getStyle("A{$r}:B{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
                        'font' => ['size' => 10],
                    ]);
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                }

                // Column header row
                $header_row = $this->data_start_row;
                $sheet->getStyle("A{$header_row}:{$last_col}{$header_row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
                ]);

                // Data rows — alternating shading
                $data_row = $header_row + 1;
                $high = $sheet->getHighestRow();
                $total_row = $high;

                for ($r = $data_row; $r < $total_row; $r++) {
                    $bg = ($r % 2 === 0) ? 'F5F5F5' : 'FFFFFF';
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                        'font' => ['size' => 9],
                    ]);
                }

                // Totals row
                $sheet->getStyle("A{$total_row}:{$last_col}{$total_row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);

                // Number formatting for currency columns (E, F, G, H)
                foreach (['E', 'F', 'G', 'H'] as $col) {
                    $sheet->getStyle("{$col}{$data_row}:{$col}{$total_row}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }
            },
        ];
    }
}
