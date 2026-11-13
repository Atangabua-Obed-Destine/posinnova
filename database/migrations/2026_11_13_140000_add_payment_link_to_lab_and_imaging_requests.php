<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: lab and imaging requests know the invoice of their fee (to show whether it is paid), and
 * keep a record when results are entered before it is paid: who chose to go on, when, why, and how
 * much was still owed then.
 *
 * Requests billed before this are linked to their invoice through the charge index (visit_charges),
 * whose description ends with the request's reference number.
 */
return new class extends Migration
{
    private $tables = ['lab_requests' => 'lab_request', 'imaging_requests' => 'imaging_request'];

    public function up()
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'transaction_id')) {
                    $t->unsignedInteger('transaction_id')->nullable()->after('fee_charged');
                    $t->index('transaction_id');
                }
                if (! Schema::hasColumn($table, 'unpaid_ack_by')) {
                    $t->unsignedInteger('unpaid_ack_by')->nullable();
                    $t->dateTime('unpaid_ack_at')->nullable();
                    $t->decimal('unpaid_ack_due', 22, 4)->nullable();
                    $t->string('unpaid_ack_reason', 191)->nullable();
                }
            });
        }

        // link the requests billed before this to their invoice
        foreach ($this->tables as $table => $ref_type) {
            $rows = DB::table($table)->where('fee_charged', 1)->whereNull('transaction_id')
                ->select('id', 'contact_id', 'ref_no', 'business_id')->get();
            foreach ($rows as $r) {
                if (empty($r->ref_no)) {
                    continue;
                }
                $tx = DB::table('visit_charges')
                    ->where('business_id', $r->business_id)
                    ->where('contact_id', $r->contact_id)
                    ->where('reference_type', $ref_type)
                    ->where('description', 'like', '%'.$r->ref_no)
                    ->orderBy('id')
                    ->value('reference_id');
                if ($tx) {
                    DB::table($table)->where('id', $r->id)->update(['transaction_id' => $tx]);
                }
            }
        }
    }

    public function down()
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'transaction_id')) {
                    $t->dropIndex(['transaction_id']);
                    $t->dropColumn('transaction_id');
                }
                if (Schema::hasColumn($table, 'unpaid_ack_by')) {
                    $t->dropColumn(['unpaid_ack_by', 'unpaid_ack_at', 'unpaid_ack_due', 'unpaid_ack_reason']);
                }
            });
        }
    }
};
