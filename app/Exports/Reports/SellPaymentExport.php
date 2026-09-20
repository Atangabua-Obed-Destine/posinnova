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

class SellPaymentExport implements FromArray, ShouldAutoSize, WithTitle, WithEvents
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
        return __('report.sell_payment_report');
    }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $output = [];

        $output[] = [$this->business['name'] ?? 'PosInnova'];
        $output[] = [__('report.sell_payment_report')];
        $output[] = [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by];
        $output[] = [__('report.filters') . ': ' . $filter_summary];
        $output[] = [''];

        $output[] = [__('report.payment_method_summary')];
        $output[] = [__('lang_v1.cash'),          $this->currency_symbol . number_format($this->kpis['cash'] ?? 0, 2)];
        $output[] = [__('lang_v1.card'),           $this->currency_symbol . number_format($this->kpis['card'] ?? 0, 2)];
        $output[] = [__('lang_v1.cheque'),         $this->currency_symbol . number_format($this->kpis['cheque'] ?? 0, 2)];
        $output[] = [__('lang_v1.bank_transfer'),  $this->currency_symbol . number_format($this->kpis['bank_transfer'] ?? 0, 2)];
        $output[] = [__('lang_v1.other'),          $this->currency_symbol . number_format($this->kpis['other'] ?? 0, 2)];
        $output[] = [__('lang_v1.total_payments'), $this->currency_symbol . number_format($this->kpis['total'] ?? 0, 2)];
        $output[] = [''];

        $this->data_start_row = count($output) + 1;

        $output[] = [
            __('contact.customer'),
            __('sale.invoice_no'),
            __('report.payment_date'),
            __('lang_v1.payment_method'),
            __('report.amount'),
            __('lang_v1.payment_ref_no'),
            __('lang_v1.transaction_no'),
        ];

        $total = 0;
        foreach ($this->rows as $row) {
            $output[] = [
                ($row->name ?? '') . (!empty($row->business_name) ? ' (' . $row->business_name . ')' : ''),
                $row->invoice_no ?? '',
                $row->paid_on ?? '',
                $row->payment_method ?? '',
                (float)($row->amount ?? 0),
                $row->payment_ref_no ?? '',
                $row->transaction_no ?? '',
            ];
            $total += (float)($row->amount ?? 0);
        }

        $output[] = [__('lang_v1.total'), '', '', '', $total, '', ''];

        return $output;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last_col = 'G';

                $sheet->mergeCells('A1:' . $last_col . '1');
                $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $sheet->mergeCells('A2:' . $last_col . '2');
                $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 13], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                foreach ([3, 4] as $r) { $sheet->mergeCells("A{$r}:{$last_col}{$r}"); $sheet->getStyle("A{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9]]); }
                $sheet->mergeCells('A6:' . $last_col . '6');
                $sheet->getStyle('A6')->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']]]);
                for ($r = 7; $r <= 12; $r++) { $sheet->getStyle("A{$r}")->getFont()->setBold(true); }

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
                $sheet->getStyle("E{$dr}:E{$high}")->getNumberFormat()->setFormatCode('#,##0.00');
            },
        ];
    }
}
