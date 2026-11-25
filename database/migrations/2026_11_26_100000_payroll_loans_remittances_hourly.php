<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * PosInnova payroll, phase 2 (paxhitestbed's staff loans, tax remittances, bank file and hourly pay):
 *  - staff loans and salary advances, repaid by a deduction on each payroll (payroll_details.staff_loan_id);
 *  - remittances: the tax and CNPS withheld for a month, paid over to the authority;
 *  - hourly staff: a rate on the pay details, the hours on each payroll;
 *  - two more kinds of payment-account movement (a loan given, a tax paid over).
 */
return new class extends Migration
{
    private $permissions = ['payroll-loans', 'payroll-remit'];

    public function up(): void
    {
        Schema::table('payroll_staff', function (Blueprint $t) {
            $t->string('pay_type', 10)->default('monthly')->after('basic_salary');   // monthly | hourly
            $t->decimal('hourly_rate', 22, 4)->default(0)->after('pay_type');
        });
        Schema::table('payrolls', function (Blueprint $t) {
            $t->decimal('hours_worked', 8, 2)->nullable()->after('salary_type');
        });
        Schema::table('payroll_details', function (Blueprint $t) {
            $t->unsignedBigInteger('staff_loan_id')->nullable()->index()->after('payroll_id');
        });

        Schema::create('payroll_loans', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('user_id');
            $t->string('kind', 10)->default('loan');            // loan | advance
            $t->string('title', 100);
            $t->decimal('amount', 22, 4);
            $t->decimal('instalment', 22, 4);
            $t->date('start_month');
            $t->date('given_on');
            $t->string('status', 12)->default('active');        // active | cancelled
            $t->text('note')->nullable();
            $t->unsignedInteger('payment_account_id')->nullable();
            $t->unsignedInteger('account_transaction_id')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->unsignedInteger('updated_by')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'user_id', 'status']);
        });

        Schema::create('payroll_remittances', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('liability_code', 10);                   // 431 CNPS, 447 the State …
            $t->date('salary_month');
            $t->decimal('amount', 22, 4);
            $t->date('payment_date');
            $t->unsignedInteger('payment_account_id')->nullable();
            $t->unsignedInteger('account_transaction_id')->nullable();
            $t->string('reference', 100)->nullable();           // the receipt / declaration number
            $t->text('note')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->dateTime('voided_at')->nullable();
            $t->unsignedInteger('voided_by')->nullable();
            $t->string('void_reason', 255)->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'liability_code', 'salary_month'], 'payroll_remit_month_idx');
        });

        DB::statement("ALTER TABLE account_transactions MODIFY sub_type ENUM('opening_balance','fund_transfer','deposit','payroll','staff_loan','tax_remittance') NULL DEFAULT NULL");

        $now = now();
        foreach ($this->permissions as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::insert(['name' => $perm, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->delete();
        DB::table('account_transactions')->whereIn('sub_type', ['staff_loan', 'tax_remittance'])->update(['sub_type' => null]);
        DB::statement("ALTER TABLE account_transactions MODIFY sub_type ENUM('opening_balance','fund_transfer','deposit','payroll') NULL DEFAULT NULL");
        Schema::dropIfExists('payroll_remittances');
        Schema::dropIfExists('payroll_loans');
        Schema::table('payroll_details', fn (Blueprint $t) => $t->dropColumn('staff_loan_id'));
        Schema::table('payrolls', fn (Blueprint $t) => $t->dropColumn('hours_worked'));
        Schema::table('payroll_staff', fn (Blueprint $t) => $t->dropColumn(['pay_type', 'hourly_rate']));
    }
};
