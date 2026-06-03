<?php
/**
 * One-off helper: regenerate SKUs for all products of the SAMMY AND BROSS business.
 * Migration imported the product NAME into the SKU column; we want unique random SKUs.
 *
 * Run: php update_skus_sammybross.php
 * Re-runnable safely (only changes products whose sku still looks like a name).
 * Pass --force to overwrite ALL skus regardless.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$businessId = 2; // SAMMY AND BROSS
$force      = in_array('--force', $argv);

$business = DB::table('business')->where('id', $businessId)->first();
if (!$business) {
    echo "Business id={$businessId} not found.\n";
    exit(1);
}
echo "Updating SKUs for business: {$business->name} (id={$businessId})\n";
echo "Mode: " . ($force ? 'FORCE (overwrite all)' : 'SAFE (only sku that looks like name)') . "\n";

// Pull existing SKUs across the whole platform to avoid global collisions.
$existing = DB::table('products')->pluck('sku')->map(fn($s) => strtoupper(trim((string)$s)))->all();
$existingSubs = DB::table('variations')->pluck('sub_sku')->map(fn($s) => strtoupper(trim((string)$s)))->all();
$taken = array_flip(array_merge($existing, $existingSubs));

$prefix = 'SB';
$gen = function () use (&$taken, $prefix): string {
    do {
        // SB + 6 digits + 1 letter, e.g. SB048213K -> ~234M combos, plenty.
        $candidate = $prefix . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT)
            . chr(random_int(65, 90));
    } while (isset($taken[$candidate]));
    $taken[$candidate] = true;
    return $candidate;
};

$products = DB::table('products')->where('business_id', $businessId)->get(['id', 'name', 'sku']);
echo "Products found: " . count($products) . "\n";

$updated = 0;
$skipped = 0;

DB::beginTransaction();
try {
    foreach ($products as $p) {
        $currentSku = (string)$p->sku;
        // "Looks like a name" heuristic: equals the name (case-insensitive) OR contains a space
        // OR contains 3+ consecutive letters with no digits. Force mode bypasses all this.
        $looksLikeName = (
            strcasecmp(trim($currentSku), trim((string)$p->name)) === 0
            || str_contains($currentSku, ' ')
            || (preg_match('/[A-Za-z]/', $currentSku) && !preg_match('/\d/', $currentSku))
        );

        if (!$force && !$looksLikeName) {
            $skipped++;
            continue;
        }

        $newSku = $gen();
        DB::table('products')->where('id', $p->id)->update(['sku' => $newSku]);

        // Update child variation sub_skus that were derived from the parent sku.
        // Strategy: for single-variation products sub_sku == product sku;
        // for variable products sub_sku is typically "<sku>-<n>". We rewrite both patterns.
        $variations = DB::table('variations')
            ->join('product_variations', 'product_variations.id', '=', 'variations.product_variation_id')
            ->where('product_variations.product_id', $p->id)
            ->select('variations.id', 'variations.sub_sku')
            ->get();

        $i = 1;
        foreach ($variations as $v) {
            $newSub = count($variations) > 1 ? ($newSku . '-' . $i) : $newSku;
            // Ensure uniqueness across sub_skus as well.
            $u = strtoupper($newSub);
            while (isset($taken[$u]) && $u !== strtoupper($newSku)) {
                $newSub = $newSku . '-' . (++$i);
                $u = strtoupper($newSub);
            }
            $taken[$u] = true;
            DB::table('variations')->where('id', $v->id)->update(['sub_sku' => $newSub]);
            $i++;
        }

        $updated++;
    }
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done. Updated: {$updated}, Skipped (already look like SKUs): {$skipped}\n";
