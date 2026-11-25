<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * PosInnova payroll (paxhitestbed's payroll, per business): each staff member's pay details, the
 * payroll taxes (groups of bands and single taxes, Cameroon defaults), allowance and deduction
 * types, the monthly payrolls with their lines and itemised taxes, and the settings.
 * Paying a payroll posts one journal entry (App\Utils\Payroll\PayrollPayer).
 */
return new class extends Migration
{
    private $permissions = ['payroll-view', 'payroll-action', 'payroll-approve', 'payroll-setting', 'payroll-print', 'payroll-report'];

    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->unique();
            $t->longText('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_staff', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('user_id');
            $t->decimal('basic_salary', 22, 4)->default(0);
            $t->date('joining_date')->nullable();
            $t->date('ending_date')->nullable();
            $t->string('job_title', 120)->nullable();
            $t->string('cnps_no', 60)->nullable();
            $t->string('tax_no', 60)->nullable();
            $t->unsignedInteger('payment_account_id')->nullable();
            $t->string('bank_name', 120)->nullable();
            $t->string('bank_account_no', 80)->nullable();
            $t->boolean('is_active')->default(1);
            $t->timestamps();
            $t->unique(['business_id', 'user_id']);
        });

        Schema::create('payroll_tax_groups', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('code', 20)->nullable();
            $t->string('title', 150);
            $t->boolean('is_income_tax')->default(0);
            $t->boolean('is_progressive')->default(0);
            $t->boolean('status')->default(1);
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->unsignedInteger('display_order')->default(0);
            $t->string('liability_code', 10)->nullable();   // the ledger account the tax is owed on: 431 CNPS, 447 State
            $t->timestamps();
        });

        Schema::create('payroll_tax_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedBigInteger('tax_group_id')->nullable()->index();
            $t->boolean('is_dependent')->default(0);
            $t->string('depends_on_type', 20)->nullable();   // tax_group | tax_setting
            $t->unsignedBigInteger('depends_on_id')->nullable();
            $t->string('title', 150);
            $t->unsignedInteger('bracket_order')->default(0);
            $t->unsignedTinyInteger('tax_type')->default(1);  // 1 percentage | 2 fixed amount
            $t->string('paid_by', 10)->default('employee');   // employee | employer | both
            $t->decimal('min_amount', 22, 4)->default(0);
            $t->decimal('max_amount', 22, 4)->default(50000000);
            $t->decimal('percentage', 10, 4)->default(0);
            $t->decimal('employer_percentage', 10, 4)->default(0);
            $t->decimal('fixed_amount', 22, 4)->default(0);
            $t->decimal('employer_fixed_amount', 22, 4)->default(0);
            $t->decimal('threshold', 22, 4)->default(0);       // paxhitestbed max_no_taxable_amount
            $t->boolean('status')->default(1);
            $t->string('liability_code', 10)->nullable();   // the ledger account the tax is owed on: 431 CNPS, 447 State
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->string('note', 190)->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_line_types', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('kind', 12);                            // allowance | deduction
            $t->string('title', 150);
            $t->boolean('is_taxable')->default(0);
            $t->boolean('status')->default(1);
            $t->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('user_id');
            $t->date('salary_month');                          // the 1st of the month
            $t->decimal('basic_salary', 22, 4)->default(0);
            $t->unsignedTinyInteger('salary_type')->default(1);
            $t->decimal('total_earning', 22, 4)->default(0);
            $t->decimal('paid_days', 6, 2)->nullable();
            $t->decimal('period_days', 6, 2)->nullable();
            $t->decimal('total_allowance', 22, 4)->default(0);
            $t->decimal('total_deduction', 22, 4)->default(0);
            $t->decimal('gross_salary', 22, 4)->default(0);
            $t->decimal('taxable_salary', 22, 4)->default(0);
            $t->decimal('tax', 22, 4)->default(0);
            $t->decimal('employer_tax', 22, 4)->default(0);
            $t->decimal('net_salary', 22, 4)->default(0);
            $t->decimal('total_cost', 22, 4)->default(0);
            $t->unsignedTinyInteger('status')->default(0);      // 0 unpaid | 1 paid
            $t->date('pay_date')->nullable();
            $t->unsignedInteger('payment_account_id')->nullable();
            $t->string('payment_method', 30)->nullable();
            $t->text('note')->nullable();
            $t->unsignedInteger('approved_by')->nullable();
            $t->dateTime('approved_at')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->unsignedInteger('account_transaction_id')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->unsignedInteger('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'user_id', 'salary_month']);
            $t->index(['business_id', 'salary_month']);
        });

        Schema::create('payroll_details', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('payroll_id')->index();
            $t->string('title', 150);
            $t->decimal('amount', 22, 4);
            $t->boolean('is_taxable')->nullable();
            $t->unsignedTinyInteger('status');                  // 1 allowance | 0 deduction
            $t->timestamps();
        });

        Schema::create('payroll_tax_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('payroll_id')->index();
            $t->unsignedInteger('user_id');
            $t->date('salary_month');
            $t->unsignedBigInteger('tax_group_id')->nullable();
            $t->unsignedBigInteger('tax_setting_id')->nullable();
            $t->string('label', 150);
            $t->decimal('employee_amount', 22, 4)->default(0);
            $t->decimal('employer_amount', 22, 4)->default(0);
            $t->string('liability_code', 10)->nullable();   // the ledger account the tax is owed on: 431 CNPS, 447 State
            $t->timestamps();
            $t->index(['business_id', 'salary_month']);
        });

        $now = now();
        foreach ($this->permissions as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::insert(['name' => $perm, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        app()['cache']->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        foreach (['payroll_tax_lines', 'payroll_details', 'payrolls', 'payroll_line_types', 'payroll_tax_settings', 'payroll_tax_groups', 'payroll_staff', 'payroll_settings'] as $t) {
            Schema::dropIfExists($t);
        }
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
