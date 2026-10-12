<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSurveySubmissionIds extends Migration
{
    public function up()
    {
        foreach (['surveys', 'arta_surveys'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->uuid('submission_id')->nullable();
            });
            if (DB::connection()->getDriverName() === 'sqlsrv') {
                DB::statement("CREATE UNIQUE INDEX {$name}_submission_id_unique ON {$name} (submission_id) WHERE submission_id IS NOT NULL");
            } else {
                Schema::table($name, function (Blueprint $table) {
                    $table->unique('submission_id');
                });
            }
        }
    }

    public function down()
    {
        foreach (['surveys', 'arta_surveys'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique($table->getTable() . '_submission_id_unique');
                $table->dropColumn('submission_id');
            });
        }
    }
}
