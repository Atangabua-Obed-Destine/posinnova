<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova clinic:
 *  - clinic_exam_templates: examination forms per specialty (ENT, eye...), built by drag and drop:
 *    section titles, drawings the doctor draws on, one-click findings, text, numbers.
 *  - consultation_exams: the form as filled in for one consultation (a copy of the template's blocks,
 *    the values, and the drawings saved as files).
 *  - consultations: history of the complaint, respiratory rate, differential diagnosis, plan, review date.
 *  - consultation_prescriptions: strength and route.
 *  - patient_details: occupation and marital status.
 *  - clinic_attachments: files on a consultation (endoscope photos / videos), and files added before the
 *    consultation is first saved (draft_token).
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('clinic_exam_templates')) {
            Schema::create('clinic_exam_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->string('name', 191);
                $table->text('specialties')->nullable();   // JSON: the specialties that open this form
                $table->longText('blocks')->nullable();    // JSON: the form's blocks, in order
                $table->boolean('is_active')->default(1);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['business_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('consultation_exams')) {
            Schema::create('consultation_exams', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->unsignedBigInteger('consultation_id')->unique();
                $table->unsignedInteger('contact_id');
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('template_name', 191);
                $table->longText('blocks');                // JSON: the template's blocks when it was filled in
                $table->longText('values')->nullable();    // JSON: block id => value; '_draw' => block id => file
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'contact_id']);
            });
        }

        Schema::table('consultations', function (Blueprint $table) {
            if (! Schema::hasColumn('consultations', 'history_of_complaint')) {
                $table->text('history_of_complaint')->nullable()->after('chief_complaint');
            }
            if (! Schema::hasColumn('consultations', 'vitals_resp_rate')) {
                $table->unsignedSmallInteger('vitals_resp_rate')->nullable()->after('vitals_pulse');
            }
            if (! Schema::hasColumn('consultations', 'differential_diagnosis')) {
                $table->text('differential_diagnosis')->nullable()->after('diagnosis');
            }
            if (! Schema::hasColumn('consultations', 'management_plan')) {
                $table->text('management_plan')->nullable()->after('differential_diagnosis');
            }
            if (! Schema::hasColumn('consultations', 'review_date')) {
                $table->date('review_date')->nullable()->after('management_plan');
            }
            if (! Schema::hasColumn('consultations', 'review_notes')) {
                $table->string('review_notes', 191)->nullable()->after('review_date');
            }
        });

        Schema::table('consultation_prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('consultation_prescriptions', 'strength')) {
                $table->string('strength', 60)->nullable()->after('drug_name');
            }
            if (! Schema::hasColumn('consultation_prescriptions', 'route')) {
                $table->string('route', 60)->nullable()->after('strength');
            }
        });

        Schema::table('patient_details', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_details', 'occupation')) {
                $table->string('occupation', 120)->nullable()->after('blood_group');
            }
            if (! Schema::hasColumn('patient_details', 'marital_status')) {
                $table->string('marital_status', 20)->nullable()->after('occupation');
            }
        });

        Schema::table('clinic_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_attachments', 'draft_token')) {
                $table->string('draft_token', 40)->nullable()->after('attachable_id');
                $table->index('draft_token');
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('consultation_exams');
        Schema::dropIfExists('clinic_exam_templates');
        Schema::table('consultations', function (Blueprint $table) {
            foreach (['history_of_complaint', 'vitals_resp_rate', 'differential_diagnosis', 'management_plan', 'review_date', 'review_notes'] as $c) {
                if (Schema::hasColumn('consultations', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('consultation_prescriptions', function (Blueprint $table) {
            foreach (['strength', 'route'] as $c) {
                if (Schema::hasColumn('consultation_prescriptions', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('patient_details', function (Blueprint $table) {
            foreach (['occupation', 'marital_status'] as $c) {
                if (Schema::hasColumn('patient_details', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('clinic_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_attachments', 'draft_token')) {
                $table->dropIndex(['draft_token']);
                $table->dropColumn('draft_token');
            }
        });
    }
};
