<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('business', function (Blueprint $table) {
            if (! Schema::hasColumn('business', 'dept_issue_approval_required')) {
                $table->boolean('dept_issue_approval_required')->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('business', function (Blueprint $table) {
            if (Schema::hasColumn('business', 'dept_issue_approval_required')) {
                $table->dropColumn('dept_issue_approval_required');
            }
        });
    }
};
