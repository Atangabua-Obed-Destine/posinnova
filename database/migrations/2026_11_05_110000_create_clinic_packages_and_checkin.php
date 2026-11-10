<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova clinic, phase 2: consultation packages and check-in.
 *
 * clinic_packages  what reception can check a patient in under (Standard, VIP,
 *                  ...). Each package is backed by a non-stock service product
 *                  so the check-in invoice carries a real line, prints on the
 *                  receipt and shows in product sales reports.
 *                  billing = per_visit is charged at every check-in; monthly
 *                  packages are stored now and billed by the plans phase.
 *
 * visits           gains the check-in data: the doctor seen, the package, the
 *                  day's queue number, where the patient is in the queue, the
 *                  invoice raised at check-in, and who let an unpaid patient
 *                  through when the clinic takes payment first.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('clinic_packages')) {
            Schema::create('clinic_packages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->string('name', 120);
                $table->text('description')->nullable(); // what the package covers
                $table->decimal('price', 22, 4)->default(0);
                $table->enum('billing', ['per_visit', 'monthly'])->default('per_visit');
                $table->unsignedSmallInteger('visits_per_month')->nullable(); // monthly: null = unlimited
                $table->unsignedInteger('product_id')->nullable();
                $table->unsignedInteger('variation_id')->nullable();
                $table->boolean('is_active')->default(1);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unsignedInteger('created_by')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->index(['business_id', 'is_active']);
            });
        }

        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'doctor_id')) {
                $table->unsignedInteger('doctor_id')->nullable()->after('location_id');
                $table->unsignedBigInteger('package_id')->nullable()->after('doctor_id');
                $table->unsignedSmallInteger('queue_no')->nullable()->after('package_id');
                $table->enum('queue_status', ['waiting', 'with_doctor', 'seen', 'no_show', 'cancelled'])->nullable()->after('queue_no');
                $table->timestamp('checked_in_at')->nullable()->after('queue_status');
                $table->timestamp('called_at')->nullable()->after('checked_in_at');
                $table->timestamp('seen_at')->nullable()->after('called_at');
                $table->unsignedInteger('fee_transaction_id')->nullable()->after('seen_at');
                $table->unsignedInteger('pay_override_by')->nullable()->after('fee_transaction_id');
                $table->timestamp('pay_override_at')->nullable()->after('pay_override_by');

                $table->index(['business_id', 'visit_date', 'doctor_id']);
            }
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'doctor_id')) {
                $table->dropIndex(['business_id', 'visit_date', 'doctor_id']);
                $table->dropColumn(['doctor_id', 'package_id', 'queue_no', 'queue_status', 'checked_in_at',
                    'called_at', 'seen_at', 'fee_transaction_id', 'pay_override_by', 'pay_override_at']);
            }
        });
        Schema::dropIfExists('clinic_packages');
    }
};
