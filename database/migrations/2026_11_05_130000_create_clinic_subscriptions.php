<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova clinic phase 4: monthly plans.
 *
 * A subscription puts a patient on a monthly package. Each billing period is
 * one row in clinic_subscription_periods with its own invoice on the patient
 * account (unique per subscription and start date, so a billing run can never
 * bill the same month twice). A visit covered by the plan points at the period
 * it used (visits.subscription_period_id), which is how visits per month are
 * counted.
 */
return new class extends Migration
{
    public function up()
    {
        // a failed earlier run can leave the new, empty tables behind
        // (MySQL does not roll back schema changes): start them again
        foreach (['clinic_subscription_periods', 'clinic_subscriptions'] as $t) {
            if (Schema::hasTable($t) && \Illuminate\Support\Facades\DB::table($t)->count() == 0) {
                Schema::drop($t);
            }
        }

        Schema::create('clinic_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedBigInteger('package_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->date('start_date');
            $table->date('next_bill_date');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('cancelled_by')->nullable();
            $table->string('cancel_reason', 191)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status', 'next_bill_date']);
            $table->index(['business_id', 'contact_id']);
        });

        Schema::create('clinic_subscription_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedInteger('contact_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 22, 4)->default(0);
            $table->unsignedSmallInteger('visits_allowed')->nullable(); // null = unlimited
            $table->unsignedInteger('transaction_id')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'period_start'], 'csp_subscription_start_unique');
            $table->index(['contact_id', 'period_start', 'period_end'], 'csp_contact_dates_index');
            $table->foreign('subscription_id')->references('id')->on('clinic_subscriptions')->onDelete('cascade');
        });

        if (! Schema::hasColumn('visits', 'subscription_period_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->unsignedBigInteger('subscription_period_id')->nullable();
                $table->index('subscription_period_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('visits', 'subscription_period_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dropIndex(['subscription_period_id']);
                $table->dropColumn('subscription_period_id');
            });
        }
        Schema::dropIfExists('clinic_subscription_periods');
        Schema::dropIfExists('clinic_subscriptions');
    }
};
