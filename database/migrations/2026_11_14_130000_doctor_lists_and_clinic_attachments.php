<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova clinic, less typing for doctors:
 *  - clinic_options: the clinic's lists (specialties, complaints, findings, diagnoses, dosage, frequency,
 *    duration, instructions). Filled with defaults the first time a list is used; the clinic adds its own.
 *  - clinic_attachments: photos / scans / PDFs on a lab request or an imaging request, kept out of the
 *    public folder and served only to staff who may see the request, or to the patient once it is paid.
 *  - lab_tests.result_options: the usual answers of a test ("Negative, Positive") offered when entering it.
 *  - imaging_catalogue.report_template: the study's normal report, inserted in one click.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('clinic_options')) {
            Schema::create('clinic_options', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->string('list_key', 40);
                $table->string('value', 191);
                $table->integer('sort_order')->default(0);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'list_key', 'sort_order'], 'clinic_options_list_index');
            });
        }

        if (! Schema::hasTable('clinic_attachments')) {
            Schema::create('clinic_attachments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->string('attachable_type', 40); // lab_request | imaging_request
                $table->unsignedBigInteger('attachable_id');
                $table->unsignedInteger('contact_id')->nullable();
                $table->string('file_name', 191); // stored name under storage/app/clinic_attachments
                $table->string('original_name', 191);
                $table->string('mime', 100)->nullable();
                $table->unsignedInteger('size')->default(0);
                $table->string('caption', 191)->nullable();
                $table->unsignedInteger('uploaded_by')->nullable();
                $table->timestamps();
                $table->index(['attachable_type', 'attachable_id'], 'clinic_attachments_owner_index');
                $table->index(['business_id', 'contact_id']);
            });
        }

        if (Schema::hasTable('lab_tests') && ! Schema::hasColumn('lab_tests', 'result_options')) {
            Schema::table('lab_tests', function (Blueprint $table) {
                $table->string('result_options', 500)->nullable()->after('unit');
            });
        }

        if (Schema::hasTable('imaging_catalogue') && ! Schema::hasColumn('imaging_catalogue', 'report_template')) {
            Schema::table('imaging_catalogue', function (Blueprint $table) {
                $table->text('report_template')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('clinic_options');
        Schema::dropIfExists('clinic_attachments');
        if (Schema::hasColumn('lab_tests', 'result_options')) {
            Schema::table('lab_tests', fn (Blueprint $t) => $t->dropColumn('result_options'));
        }
        if (Schema::hasColumn('imaging_catalogue', 'report_template')) {
            Schema::table('imaging_catalogue', fn (Blueprint $t) => $t->dropColumn('report_template'));
        }
    }
};
