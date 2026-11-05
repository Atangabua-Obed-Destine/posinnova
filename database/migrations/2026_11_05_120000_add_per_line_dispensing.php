<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova clinic phase 3: per-line dispensing.
 *
 * What was given is no longer a yes/no flag set on every line when any sale
 * was saved. Each POS sale line is linked to the prescription line it fills
 * (transaction_sell_lines.consultation_prescription_id), and the quantity
 * given is read from finished sales (status final, minus returns), so drafts,
 * quotations, deleted sales and returns count correctly without extra hooks.
 *
 * The dispensary can close a line it will not fill (out of stock, patient
 * declined, substituted) with a reason, so it leaves the waiting list.
 *
 * transactions.consultation_id already exists (2026_06_26_130300, never
 * filled until now); this only indexes it. Lines marked dispensed by the old
 * flag keep that as legacy_given.
 *
 * Every step checks first, so the migration can be re-run after a partial run
 * (MySQL does not roll back schema changes).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('consultation_prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('consultation_prescriptions', 'legacy_given')) {
                $table->decimal('legacy_given', 22, 4)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('consultation_prescriptions', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('dispensed_by');
                $table->unsignedInteger('closed_by')->nullable()->after('closed_at');
                $table->string('close_reason', 191)->nullable()->after('closed_by');
            }
        });

        if (! Schema::hasColumn('transaction_sell_lines', 'consultation_prescription_id')) {
            Schema::table('transaction_sell_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('consultation_prescription_id')->nullable();
                $table->index('consultation_prescription_id', 'tsl_consultation_rx_index');
            });
        }

        if (! Schema::hasColumn('transactions', 'consultation_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('consultation_id')->nullable();
            });
        }
        if (! $this->hasIndex('transactions', 'transactions_consultation_id_index')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index('consultation_id');
            });
        }

        DB::table('consultation_prescriptions')->where('is_dispensed', 1)->whereNull('legacy_given')
            ->update(['legacy_given' => DB::raw('quantity')]);
    }

    public function down()
    {
        if ($this->hasIndex('transactions', 'transactions_consultation_id_index')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex(['consultation_id']);
            });
        }
        if (Schema::hasColumn('transaction_sell_lines', 'consultation_prescription_id')) {
            Schema::table('transaction_sell_lines', function (Blueprint $table) {
                $table->dropIndex('tsl_consultation_rx_index');
                $table->dropColumn('consultation_prescription_id');
            });
        }
        Schema::table('consultation_prescriptions', function (Blueprint $table) {
            $table->dropColumn(['legacy_given', 'closed_at', 'closed_by', 'close_reason']);
        });
    }

    private function hasIndex($table, $index)
    {
        return ! empty(DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]));
    }
};
