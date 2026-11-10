<?php

namespace App\Exports\Reports;

use App\Utils\ReportExportUtil;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProfitLossExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private array $pl_data,
        private array $profit_by_product,
        private array $profit_by_category,
        private array $profit_by_brand,
        private array $profit_by_location,
        private array $profit_by_date,
        private array $profit_by_customer,
        private array $business,
        private array $filters,
        private string $generated_by,
        private string $currency_symbol
    ) {}

    public function sheets(): array
    {
        return [
            new ProfitLossSummarySheet($this->pl_data, $this->business, $this->filters, $this->generated_by, $this->currency_symbol),
            new ProfitLossDimensionSheet('Products', $this->profit_by_product, ['product', 'gross_profit'], $this->business, $this->filters, $this->generated_by, $this->currency_symbol),
            new ProfitLossDimensionSheet('Categories', $this->profit_by_category, ['category', 'gross_profit'], $this->business, $this->filters, $this->generated_by, $this->currency_symbol),
            new ProfitLossDimensionSheet('Brands', $this->profit_by_brand, ['brand', 'gross_profit'], $this->business, $this->filters, $this->generated_by, $this->currency_symbol),
            new ProfitLossDimensionSheet('Locations', $this->profit_by_location, ['location', 'gross_profit'], $this->business, $this->filters, $this->generated_by, $this->currency_symbol),
            new ProfitLossDimensionSheet('By Date', $this->profit_by_date, ['transaction_date', 'gross_profit'], $this->business, $this->filters, $this->generated_by, $this->currency_symbol),
            new ProfitLossDimensionSheet('By Customer', $this->profit_by_customer, ['customer', 'gross_profit'], $this->business, $this->filters, $this->generated_by, $this->currency_symbol),
        ];
    }
}

// ─── Summary Sheet ─────────────────────────────────────────────────────────────

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ProfitLossSummarySheet implements FromArray, ShouldAutoSize, WithTitle, WithEvents
{
    public function __construct(
        private array $data,
        private array $business,
        private array $filters,
        private string $generated_by,
        private string $currency_symbol
    ) {}

    public function title(): string { return __('report.profit_loss'); }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $d = $this->data;
        $sym = $this->currency_symbol;

        $fmt = fn($v) => $sym . number_format((float)($v ?? 0), 2);

        return [
            [$this->business['name'] ?? 'PosInnova'],
            [__('report.profit_loss')],
            [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by],
            [__('report.filters') . ': ' . $filter_summary],
            [''],
            [__('report.profit_loss_statement')],
            [''],
            [__('lang_v1.opening_stock'),          $fmt($d['opening_stock'] ?? 0)],
            [__('lang_v1.closing_stock'),           $fmt($d['closing_stock'] ?? 0)],
            [''],
            [__('report.total_purchase') . ' (' . __('product.exc_of_tax') . ')',  $fmt($d['total_purchase'] ?? 0)],
            [__('lang_v1.total_purchase_return'),   '(' . $fmt($d['total_purchase_return'] ?? 0) . ')'],
            [''],
            [__('report.total_sell') . ' (' . __('product.exc_of_tax') . ')',      $fmt($d['total_sell'] ?? 0)],
            [__('lang_v1.total_sell_return'),       '(' . $fmt($d['total_sell_return'] ?? 0) . ')'],
            [''],
            [__('lang_v1.total_expense'),           $fmt($d['total_expense'] ?? 0)],
            [__('lang_v1.inventory_reconciliation') . ' (' . __('lang_v1.no_profit_impact') . ')', $fmt($d['total_reconciliation'] ?? 0)],
            [''],
            [__('lang_v1.gross_profit'),            $fmt($d['gross_profit'] ?? 0)],
            [__('lang_v1.net_profit'),              $fmt($d['net_profit'] ?? 0)],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:C1');
                $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $sheet->mergeCells('A2:C2');
                $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 13], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                foreach ([3, 4] as $r) { $sheet->mergeCells("A{$r}:C{$r}"); }
                $sheet->mergeCells('A6:C6');
                $sheet->getStyle('A6')->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']]]);
                foreach ([8, 9, 11, 12, 14, 15, 17, 20, 21] as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                }
                // Row 18: inventory reconciliation — informational, muted grey
                $sheet->getStyle('A18:B18')->applyFromArray(['font' => ['italic' => true, 'color' => ['rgb' => '888888']]]);
                $sheet->getStyle('A20:B20')->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '375623']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]]]);
                $sheet->getStyle('A21:B21')->applyFromArray(['font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1F4E79']], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_DOUBLE]]]);
                $sheet->getColumnDimension('A')->setWidth(40);
                $sheet->getColumnDimension('B')->setWidth(20);
            },
        ];
    }
}

// ─── Dimension Sheet ───────────────────────────────────────────────────────────

class ProfitLossDimensionSheet implements FromArray, ShouldAutoSize, WithTitle, WithEvents
{
    public function __construct(
        private string $sheet_title,
        private array $rows,
        private array $columns,
        private array $business,
        private array $filters,
        private string $generated_by,
        private string $currency_symbol
    ) {}

    public function title(): string { return $this->sheet_title; }

    public function array(): array
    {
        $filter_summary = ReportExportUtil::buildFilterSummary($this->filters);
        $output = [
            [$this->business['name'] ?? 'PosInnova'],
            [__('lang_v1.profit_by') . ' ' . $this->sheet_title],
            [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '    ' . __('messages.by') . ': ' . $this->generated_by],
            [__('report.filters') . ': ' . $filter_summary],
            [''],
        ];

        // Headers
        $headers = [];
        foreach ($this->columns as $col) {
            $headers[] = match($col) {
                'gross_profit' => __('lang_v1.gross_profit'),
                'product'      => __('product.product_name'),
                'category'     => __('product.category'),
                'brand'        => __('product.brand'),
                'location'     => __('business.location_name'),
                'customer'     => __('contact.customer'),
                'transaction_date' => __('messages.date'),
                default        => ucfirst(str_replace('_', ' ', $col)),
            };
        }
        $output[] = $headers;

        $total_profit = 0;
        foreach ($this->rows as $row) {
            $r_arr = $row instanceof \stdClass ? (array)$row : (is_array($row) ? $row : $row->toArray());
            $line = [];
            foreach ($this->columns as $col) {
                $val = $r_arr[$col] ?? '';
                $line[] = $col === 'gross_profit' ? (float)$val : $val;
                if ($col === 'gross_profit') $total_profit += (float)$val;
            }
            $output[] = $line;
        }

        $total_line = array_fill(0, count($this->columns), '');
        $total_line[0] = __('lang_v1.total');
        $total_line[count($this->columns) - 1] = $total_profit;
        $output[] = $total_line;

        return $output;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last_col = chr(64 + count($this->columns));
                $sheet->mergeCells("A1:{$last_col}1");
                $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F4E79']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $sheet->mergeCells("A2:{$last_col}2");
                $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                foreach ([3, 4] as $r) { $sheet->mergeCells("A{$r}:{$last_col}{$r}"); }
                // Header row = 6
                $sheet->getStyle("A6:{$last_col}6")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']]]);
                $high = $sheet->getHighestRow();
                // Totals row
                $sheet->getStyle("A{$high}:{$last_col}{$high}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]]]);
                // Alternating
                for ($r = 7; $r < $high; $r++) {
                    $bg = ($r % 2 === 0) ? 'F5F5F5' : 'FFFFFF';
                    $sheet->getStyle("A{$r}:{$last_col}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
                }
                $sheet->getStyle("{$last_col}7:{$last_col}{$high}")->getNumberFormat()->setFormatCode('#,##0.00');
            },
        ];
    }
}
