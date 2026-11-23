<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova clinic: a correction to a FINISHED consultation. The consultation keeps the corrected
 * text; each correction keeps who, when, why, which parts changed and the consultation as it was
 * just before (notes, vitals, prescription, examination form) so the earlier version stays readable.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('consultation_corrections')) {
            Schema::create('consultation_corrections', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('consultation_id')->index();
                $table->unsignedInteger('corrected_by');
                $table->text('reason');
                $table->text('changed')->nullable();
                $table->longText('before');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('consultation_corrections');
    }
};
