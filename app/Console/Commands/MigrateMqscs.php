<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Migrate the MAAR QUINCAILLERIE STOCK CONTROL SYSTEM (mqscs) database
 * into the SAMMY AND BROSS business (id=2) of posinnova.
 *
 * Imports ONLY:
 *   - Locations (warehouses)            → business_locations
 *   - Categories (families)             → categories
 *   - Products (articles) + 2 prices    → products + variations + variation_group_prices ("Wholesale")
 *   - Customers (clients)               → contacts (type=customer)
 *   - Customer opening balances         → contacts.balance + opening_balance transaction
 *   - Opening stock per (product,location) → opening_stock transactions + purchase_lines + variation_location_details
 *
 * No users, no transactions/receipts/payments, no movement history.
 * Negative stocks and negative balances are corrected to 0 and logged.
 *
 * Usage:
 *   php artisan mqscs:migrate --sql="mqscs (1).sql" --business-id=2 --user-id=2 [--dry-run]
 */
class MigrateMqscs extends Command
{
    protected $signature = 'mqscs:migrate
        {--sql= : Path to the mqscs SQL dump file (relative to base_path or absolute)}
        {--business-id=2 : Destination business id (SAMMY AND BROSS)}
        {--user-id=2 : Fallback user id (created_by for imported records)}
        {--price-group=Wholesale : Name of the wholesale selling price group}
        {--dry-run : Parse, validate and import inside a transaction then ROLL BACK}';

    protected $description = 'Migrate MQSCS (MAAR QUINCAILLERIE) data into a posinnova business — products, prices, locations, customers (with opening balances) and opening stock only.';

    /** @var array<string, array<int, array<string, mixed>>> */
    protected $src = [];

    /** @var array<string, array<int, int>> old_id => new_id maps */
    protected $map = [
        'warehouse'  => [],
        'family'     => [],
        'unit'       => [],
        'article'    => [], // article id => product id
        'variation'  => [], // article id => variation id
        'client'     => [],
    ];

    /** @var array<int, string> Warnings/corrections log */
    protected $log = [];

    public function handle(): int
    {
        $sqlPath      = $this->option('sql');
        $businessId   = (int) $this->option('business-id');
        $userId       = (int) $this->option('user-id');
        $priceGroup   = (string) $this->option('price-group');
        $dryRun       = (bool) $this->option('dry-run');

        if (!$sqlPath) {
            $this->error('--sql is required.');
            return self::FAILURE;
        }
        if (!is_file($sqlPath)) {
            $abs = base_path($sqlPath);
            if (!is_file($abs)) {
                $this->error("SQL file not found: {$sqlPath}");
                return self::FAILURE;
            }
            $sqlPath = $abs;
        }

        $this->info("=== MQSCS → posinnova migration ===");
        $this->info("Source     : {$sqlPath}");
        $this->info("Business   : #{$businessId}");
        $this->info("User       : #{$userId}");
        $this->info("Price grp  : {$priceGroup}");
        $this->info($dryRun ? "Mode       : DRY RUN (will rollback)" : "Mode       : LIVE");
        $this->newLine();

        // Validate destination
        $business = DB::table('business')->where('id', $businessId)->first();
        if (!$business) {
            $this->error("Business id={$businessId} not found.");
            return self::FAILURE;
        }
        $user = DB::table('users')->where('id', $userId)->where('business_id', $businessId)->first();
        if (!$user) {
            $this->error("User id={$userId} not found in business #{$businessId}.");
            return self::FAILURE;
        }

        // Parse source SQL
        $this->info('Parsing source dump…');
        $this->parseSql($sqlPath);

        $this->table(['Source table', 'Rows'], collect($this->src)->map(fn($r, $t) => [$t, count($r)])->values());

        DB::beginTransaction();
        try {
            $this->importWarehouses($businessId);
            $this->importFamilies($businessId);
            $this->importArticles($businessId, $priceGroup);
            $this->importClients($businessId, $userId);
            $this->importOpeningStock($businessId, $userId);

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn('DRY RUN — all changes rolled back.');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('Migration committed successfully.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Migration failed and rolled back: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }

        $this->writeLog();
        $this->newLine();
        $this->info('=== Summary ===');
        $this->line('Locations created : ' . count($this->map['warehouse']));
        $this->line('Categories created: ' . count($this->map['family']));
        $this->line('Products created  : ' . count($this->map['article']));
        $this->line('Customers created : ' . count($this->map['client']));
        $this->line('Warnings logged   : ' . count($this->log));

        return self::SUCCESS;
    }

    /* ============================================================
     * Source-SQL parser (regex-based; sufficient for this dump)
     * ============================================================ */
    protected function parseSql(string $path): void
    {
        $sql = file_get_contents($path);

        $tables = [
            'families'         => ['id', 'code', 'name', 'track_stock'],
            'warehouses'       => ['id', 'code', 'name', 'is_active', 'created_at'],
            'articles'         => ['id', 'code', 'name', 'family_id', 'unit', 'cost_price', 'selling_price', 'wholesale_price', 'is_active'],
            'clients'          => ['id', 'name', 'phone', 'address', 'is_active', 'created_at'],
            'receipts'         => ['id', 'receipt_no', 'customer_name', 'customer_phone', 'user_id', 'total_expected', 'total_paid', 'balance', 'created_at', 'client_id', 'warehouse_id'],
            'stock_movements'  => ['id', 'movement_type', 'move_date', 'article_id', 'qty', 'unit_price', 'user_id', 'ref_id', 'note', 'created_at', 'is_locked', 'warehouse_id'],
            'stock_transfers'  => ['id', 'transfer_no', 'from_warehouse_id', 'to_warehouse_id', 'move_date', 'user_id', 'note', 'created_at'],
        ];

        foreach ($tables as $table => $cols) {
            $this->src[$table] = $this->extractInserts($sql, $table, $cols);
        }
    }

    /**
     * Extract all rows from INSERT INTO `table` ... VALUES (...),(...);
     */
    protected function extractInserts(string $sql, string $table, array $columns): array
    {
        $rows = [];
        $pattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s*(?:\([^)]*\))?\s*VALUES\s*(.*?);/is';
        if (!preg_match_all($pattern, $sql, $matches)) {
            return $rows;
        }

        foreach ($matches[1] as $valuesBlock) {
            $tuples = $this->splitTuples($valuesBlock);
            foreach ($tuples as $tuple) {
                $values = $this->splitValues($tuple);
                if (count($values) !== count($columns)) {
                    // skip malformed
                    continue;
                }
                $rows[] = array_combine($columns, $values);
            }
        }

        return $rows;
    }

    /**
     * Split a "(...),(...),(...)" block into individual tuple bodies (without the surrounding parens).
     */
    protected function splitTuples(string $block): array
    {
        $tuples = [];
        $len    = strlen($block);
        $depth  = 0;
        $start  = -1;
        $inStr  = false;
        $esc    = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $block[$i];

            if ($inStr) {
                if ($esc) { $esc = false; continue; }
                if ($ch === '\\') { $esc = true; continue; }
                if ($ch === "'") { $inStr = false; }
                continue;
            }

            if ($ch === "'") { $inStr = true; continue; }
            if ($ch === '(') {
                if ($depth === 0) $start = $i + 1;
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0 && $start >= 0) {
                    $tuples[] = substr($block, $start, $i - $start);
                    $start = -1;
                }
            }
        }
        return $tuples;
    }

    /**
     * Split a tuple body "1,'foo','bar',NULL" into PHP values.
     */
    protected function splitValues(string $tuple): array
    {
        $values = [];
        $len    = strlen($tuple);
        $buf    = '';
        $inStr  = false;
        $esc    = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $tuple[$i];

            if ($inStr) {
                if ($esc) { $buf .= $ch; $esc = false; continue; }
                if ($ch === '\\') { $esc = true; continue; }
                if ($ch === "'") { $inStr = false; continue; }
                $buf .= $ch;
                continue;
            }

            if ($ch === "'") { $inStr = true; continue; }
            if ($ch === ',') {
                $values[] = $this->castValue($buf);
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        $values[] = $this->castValue($buf);
        return $values;
    }

    protected function castValue(string $raw)
    {
        $raw = trim($raw);
        if ($raw === '' ) return '';
        if (strcasecmp($raw, 'NULL') === 0) return null;
        // numeric?
        if (preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
            return strpos($raw, '.') !== false ? (float) $raw : (int) $raw;
        }
        return $raw;
    }

    /* ============================================================
     * Importers
     * ============================================================ */

    protected function importWarehouses(int $businessId): void
    {
        $this->info('→ Importing locations…');
        $now    = now();
        $existing = DB::table('business_locations')
            ->where('business_id', $businessId)
            ->pluck('name')->map(fn($n) => mb_strtolower($n))->toArray();

        $nextSeq = (int) DB::table('business_locations')
            ->where('business_id', $businessId)
            ->selectRaw("MAX(CAST(SUBSTR(location_id, 3) AS UNSIGNED)) AS m")
            ->value('m');

        foreach ($this->src['warehouses'] as $w) {
            if (in_array(mb_strtolower($w['name']), $existing, true)) {
                $existingId = DB::table('business_locations')
                    ->where('business_id', $businessId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($w['name'])])
                    ->value('id');
                $this->map['warehouse'][$w['id']] = (int) $existingId;
                $this->log[] = "Warehouse '{$w['name']}' already exists in business — reused id={$existingId}.";
                continue;
            }

            $nextSeq++;
            $locationId = 'BL' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            $newId = DB::table('business_locations')->insertGetId([
                'business_id' => $businessId,
                'location_id' => $locationId,
                'name'        => $w['name'],
                'landmark'    => 'N/A',
                'country'     => 'Cameroon',
                'state'       => 'Centre',
                'city'        => 'Yaounde',
                'zip_code'    => '0000',
                'invoice_scheme_id'        => 1,
                'invoice_layout_id'        => 1,
                'sale_invoice_layout_id'   => 1,
                'is_active'   => $w['is_active'] ? 1 : 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $this->map['warehouse'][$w['id']] = $newId;
            $this->line("  + location '{$w['name']}' (#{$newId})");
        }
    }

    protected function importFamilies(int $businessId): void
    {
        $this->info('→ Importing categories…');
        $now = now();

        // Auto-create "Uncategorized" for null family_id
        $uncId = DB::table('categories')
            ->where('business_id', $businessId)
            ->whereRaw('LOWER(name) = ?', ['uncategorized'])
            ->where('category_type', 'product')
            ->value('id');

        if (!$uncId) {
            $uncId = DB::table('categories')->insertGetId([
                'name'          => 'Uncategorized',
                'business_id'   => $businessId,
                'category_type' => 'product',
                'created_by'    => (int) $this->option('user-id'),
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
        $this->map['family'][0] = $uncId;

        foreach ($this->src['families'] as $f) {
            $existingId = DB::table('categories')
                ->where('business_id', $businessId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($f['name'])])
                ->where('category_type', 'product')
                ->value('id');
            if ($existingId) {
                $this->map['family'][$f['id']] = (int) $existingId;
                continue;
            }
            $newId = DB::table('categories')->insertGetId([
                'name'          => $f['name'],
                'short_code'    => $f['code'] ?: null,
                'business_id'   => $businessId,
                'category_type' => 'product',
                'created_by'    => (int) $this->option('user-id'),
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $this->map['family'][$f['id']] = $newId;
            $this->line("  + category '{$f['name']}' (#{$newId})");
        }
    }

    protected function getOrCreateUnit(int $businessId, ?string $unit, int $userId): int
    {
        $unit = trim((string) $unit);
        if ($unit === '') $unit = 'Pcs';

        if (isset($this->map['unit'][$unit])) {
            return $this->map['unit'][$unit];
        }

        $existingId = DB::table('units')
            ->where('business_id', $businessId)
            ->whereRaw('LOWER(actual_name) = ? OR LOWER(short_name) = ?', [mb_strtolower($unit), mb_strtolower($unit)])
            ->value('id');

        if ($existingId) {
            return $this->map['unit'][$unit] = (int) $existingId;
        }

        $now = now();
        $newId = DB::table('units')->insertGetId([
            'business_id'         => $businessId,
            'actual_name'         => $unit,
            'short_name'          => mb_substr($unit, 0, 5),
            'allow_decimal'       => 1,
            'created_by'          => $userId,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
        $this->log[] = "Auto-created unit '{$unit}' (#{$newId}).";
        return $this->map['unit'][$unit] = $newId;
    }

    protected function getOrCreateWholesalePriceGroup(int $businessId, string $name, int $userId): int
    {
        $existingId = DB::table('selling_price_groups')
            ->where('business_id', $businessId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->value('id');
        if ($existingId) return (int) $existingId;

        $now = now();
        return DB::table('selling_price_groups')->insertGetId([
            'name'        => $name,
            'description' => 'Imported wholesale price group from MQSCS',
            'business_id' => $businessId,
            'is_active'   => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    protected function importArticles(int $businessId, string $priceGroupName): void
    {
        $this->info('→ Importing products…');
        $now    = now();
        $userId = (int) $this->option('user-id');
        $taxId  = null;

        $priceGroupId = $this->getOrCreateWholesalePriceGroup($businessId, $priceGroupName, $userId);

        // Default product variation template
        $usedSkus = DB::table('products')->where('business_id', $businessId)->pluck('sku')->map(fn($s) => mb_strtolower($s))->toArray();

        foreach ($this->src['articles'] as $a) {
            $sku = (string) $a['code'];
            // dedupe
            $base = $sku; $i = 1;
            while (in_array(mb_strtolower($sku), $usedSkus, true)) {
                $i++; $sku = $base . '-' . $i;
            }
            $usedSkus[] = mb_strtolower($sku);
            if ($sku !== (string) $a['code']) {
                $this->log[] = "Article #{$a['id']} SKU '{$a['code']}' collided — renamed to '{$sku}'.";
            }

            $familyId = $a['family_id'] ?? null;
            $catId    = $this->map['family'][$familyId] ?? $this->map['family'][0];
            if (!$familyId) {
                $this->log[] = "Article #{$a['id']} ('{$a['name']}') had no family — placed in Uncategorized.";
            }

            $unitId = $this->getOrCreateUnit($businessId, $a['unit'], $userId);

            // family.track_stock
            $family = collect($this->src['families'])->firstWhere('id', $familyId);
            $enableStock = $family ? (int) ($family['track_stock'] ?? 1) : 1;

            $productId = DB::table('products')->insertGetId([
                'name'                  => $a['name'],
                'business_id'           => $businessId,
                'type'                  => 'single',
                'unit_id'               => $unitId,
                'sub_unit_ids'          => null,
                'brand_id'              => null,
                'category_id'           => $catId,
                'sub_category_id'       => null,
                'tax'                   => $taxId,
                'tax_type'              => 'exclusive',
                'enable_stock'          => $enableStock,
                'alert_quantity'        => 0,
                'sku'                   => $sku,
                'barcode_type'          => 'C128',
                'expiry_period'         => null,
                'expiry_period_type'    => null,
                'enable_sr_no'          => 0,
                'is_inactive'           => $a['is_active'] ? 0 : 1,
                'not_for_selling'       => 0,
                'product_description'   => null,
                'product_custom_field1' => null,
                'product_custom_field2' => null,
                'product_custom_field3' => null,
                'product_custom_field4' => null,
                'image'                 => null,
                'created_by'            => $userId,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);

            $cost  = (float) ($a['cost_price'] ?? 0);
            $sell  = (float) ($a['selling_price'] ?? 0);
            $whole = (float) ($a['wholesale_price'] ?? 0);

            // product variation grouping (single)
            $pvId = DB::table('product_variations')->insertGetId([
                'product_id'    => $productId,
                'name'          => 'DUMMY',
                'is_dummy'      => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            $variationId = DB::table('variations')->insertGetId([
                'name'                       => 'DUMMY',
                'product_id'                 => $productId,
                'product_variation_id'       => $pvId,
                'sub_sku'                    => $sku,
                'default_purchase_price'     => $cost,
                'dpp_inc_tax'                => $cost,
                'profit_percent'             => $cost > 0 ? round((($sell - $cost) / $cost) * 100, 2) : 0,
                'default_sell_price'         => $sell,
                'sell_price_inc_tax'         => $sell,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ]);

            $this->map['article'][$a['id']]   = $productId;
            $this->map['variation'][$a['id']] = $variationId;

            // Wholesale price group entry
            if ($whole > 0) {
                DB::table('variation_group_prices')->insert([
                    'variation_id'   => $variationId,
                    'price_group_id' => $priceGroupId,
                    'price_inc_tax'  => $whole,
                    'price_type'     => 'fixed',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }

            // Make the product available at every imported location
            foreach ($this->map['warehouse'] as $newLocId) {
                DB::table('product_locations')->insert([
                    'product_id'  => $productId,
                    'location_id' => $newLocId,
                ]);
            }
        }
        $this->line('  imported ' . count($this->map['article']) . ' products.');
    }

    protected function importClients(int $businessId, int $userId): void
    {
        $this->info('→ Importing customers + opening balances…');
        $now = now();

        // Compute per-client outstanding balance from receipts (their debt)
        $balances = [];
        foreach ($this->src['receipts'] as $r) {
            $cid = $r['client_id'];
            if (!$cid) continue;
            $bal = (float) ($r['balance'] ?? 0);
            $balances[$cid] = ($balances[$cid] ?? 0) + $bal;
        }

        $nextSeq = (int) DB::table('contacts')
            ->where('business_id', $businessId)
            ->where('type', 'customer')
            ->selectRaw("MAX(CAST(SUBSTR(contact_id, 3) AS UNSIGNED)) AS m")
            ->value('m');

        foreach ($this->src['clients'] as $c) {
            $balance = (float) ($balances[$c['id']] ?? 0);
            if ($balance < 0) {
                $this->log[] = "Client #{$c['id']} ('{$c['name']}') had negative balance {$balance} — corrected to 0.";
                $balance = 0;
            }

            $nextSeq++;
            $contactId = 'CO' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            $newId = DB::table('contacts')->insertGetId([
                'business_id'      => $businessId,
                'type'             => 'customer',
                'supplier_business_name' => null,
                'name'             => $c['name'],
                'first_name'       => $c['name'],
                'last_name'        => null,
                'prefix'           => null,
                'middle_name'      => null,
                'email'            => null,
                'contact_id'       => $contactId,
                'mobile'           => $c['phone'] ?: '',
                'address_line_1'   => $c['address'] ?: null,
                'country'          => 'Cameroon',
                'balance'          => 0, // will be populated by opening_balance txn below
                'created_by'       => $userId,
                'created_at'       => $c['created_at'] ?? $now,
                'updated_at'       => $now,
            ]);
            $this->map['client'][$c['id']] = $newId;

            if ($balance > 0) {
                $this->createOpeningBalanceTxn($businessId, $userId, $newId, $balance);
            }
        }
        $this->line('  imported ' . count($this->map['client']) . ' customers.');
    }

    protected function createOpeningBalanceTxn(int $businessId, int $userId, int $contactId, float $amount): void
    {
        $now = now();

        // Pick first location for the opening_balance txn (UltimatePOS requires location_id)
        $locationId = (int) DB::table('business_locations')
            ->where('business_id', $businessId)->orderBy('id')->value('id');

        $invoiceNo = 'OB' . str_pad((string) $contactId, 6, '0', STR_PAD_LEFT);

        $txnId = DB::table('transactions')->insertGetId([
            'business_id'        => $businessId,
            'location_id'        => $locationId,
            'type'               => 'opening_balance',
            'status'             => 'final',
            'sub_status'         => null,
            'contact_id'         => $contactId,
            'invoice_no'         => $invoiceNo,
            'ref_no'             => $invoiceNo,
            'transaction_date'   => $now,
            'total_before_tax'   => $amount,
            'final_total'        => $amount,
            'tax_amount'         => 0,
            'discount_amount'    => 0,
            'discount_type'      => 'fixed',
            'payment_status'     => 'due',
            'created_by'         => $userId,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        // Update contacts.balance (UltimatePOS keeps cached balance)
        DB::table('contacts')->where('id', $contactId)->update([
            'balance'    => $amount,
            'updated_at' => $now,
        ]);
    }

    /* ============================================================
     * Opening stock — computed per (article, warehouse) from
     * stock_movements + stock_transfers; negative → 0.
     * ============================================================ */
    protected function importOpeningStock(int $businessId, int $userId): void
    {
        $this->info('→ Computing & importing opening stock…');
        $now = now();

        // qty[ articleId ][ warehouseId ] = decimal
        $qty = [];
        $warehouses = array_keys($this->map['warehouse']);

        // Apply stock_movements with sign rules
        foreach ($this->src['stock_movements'] as $m) {
            $aid = $m['article_id'];
            $wid = $m['warehouse_id'];
            if (!$aid || !$wid) continue;
            $q   = (float) $m['qty'];

            $sign = match ($m['movement_type']) {
                'IN'      => +1,
                'OUT', 'SALE' => -1,
                'ADJUST'  => +1, // signed at source
                default   => 0,
            };
            if ($sign === 0) continue;

            $qty[$aid][$wid] = ($qty[$aid][$wid] ?? 0) + ($sign * $q);
        }

        // Apply stock_transfers via paired stock_movements with same ref_id (already covered above).

        // Group stock per location for opening_stock transactions
        $perLocation = []; // newLocId => [ [productId, variationId, qty, cost], ... ]
        $createdLines = 0;
        $negatives    = 0;
        foreach ($qty as $articleId => $byWh) {
            if (!isset($this->map['article'][$articleId])) continue;
            $productId   = $this->map['article'][$articleId];
            $variationId = $this->map['variation'][$articleId];

            // article cost
            $article = collect($this->src['articles'])->firstWhere('id', $articleId);
            $cost    = (float) ($article['cost_price'] ?? 0);

            foreach ($byWh as $whId => $q) {
                if ($q < 0) {
                    $this->log[] = "Article #{$articleId} at warehouse #{$whId} had negative stock {$q} — corrected to 0.";
                    $negatives++;
                    $q = 0;
                }
                if ($q == 0) continue;
                if (!isset($this->map['warehouse'][$whId])) continue;
                $newLocId = $this->map['warehouse'][$whId];

                $perLocation[$newLocId][] = [
                    'product_id'   => $productId,
                    'variation_id' => $variationId,
                    'quantity'     => $q,
                    'purchase_price' => $cost,
                ];
                $createdLines++;
            }
        }

        $this->line("  computed {$createdLines} stock lines across " . count($perLocation) . " location(s) ({$negatives} corrected).");

        // Create one opening_stock transaction per location with all its lines
        foreach ($perLocation as $locId => $lines) {
            $totalBefore = 0;
            foreach ($lines as $ln) $totalBefore += $ln['quantity'] * $ln['purchase_price'];

            $txnId = DB::table('transactions')->insertGetId([
                'business_id'      => $businessId,
                'location_id'      => $locId,
                'type'             => 'opening_stock',
                'status'           => 'received',
                'sub_status'       => null,
                'contact_id'       => null,
                'invoice_no'       => 'OS-' . $locId . '-' . date('Ymd'),
                'ref_no'           => 'OS-' . $locId,
                'transaction_date' => $now,
                'total_before_tax' => $totalBefore,
                'final_total'      => $totalBefore,
                'tax_amount'       => 0,
                'discount_amount'  => 0,
                'discount_type'    => 'fixed',
                'payment_status'   => 'paid',
                'created_by'       => $userId,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            foreach ($lines as $ln) {
                DB::table('purchase_lines')->insert([
                    'transaction_id'         => $txnId,
                    'product_id'             => $ln['product_id'],
                    'variation_id'           => $ln['variation_id'],
                    'quantity'               => $ln['quantity'],
                    'pp_without_discount'    => $ln['purchase_price'],
                    'discount_percent'       => 0,
                    'purchase_price'         => $ln['purchase_price'],
                    'purchase_price_inc_tax' => $ln['purchase_price'],
                    'item_tax'               => 0,
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ]);

                // variation_location_details
                $existing = DB::table('variation_location_details')
                    ->where('product_id', $ln['product_id'])
                    ->where('variation_id', $ln['variation_id'])
                    ->where('location_id', $locId)
                    ->first();
                if ($existing) {
                    DB::table('variation_location_details')
                        ->where('id', $existing->id)
                        ->update([
                            'qty_available' => DB::raw('qty_available + ' . (float) $ln['quantity']),
                            'updated_at'    => $now,
                        ]);
                } else {
                    // Need product_variation_id
                    $pvId = DB::table('variations')->where('id', $ln['variation_id'])->value('product_variation_id');
                    DB::table('variation_location_details')->insert([
                        'product_id'           => $ln['product_id'],
                        'product_variation_id' => $pvId,
                        'variation_id'         => $ln['variation_id'],
                        'location_id'          => $locId,
                        'qty_available'        => $ln['quantity'],
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ]);
                }
            }
        }
    }

    protected function writeLog(): void
    {
        if (!$this->log) return;
        $path = storage_path('logs/mqscs-migration-' . date('Ymd-His') . '.log');
        file_put_contents($path, implode(PHP_EOL, $this->log) . PHP_EOL);
        $this->newLine();
        $this->warn('Warnings written to: ' . $path);
    }
}
