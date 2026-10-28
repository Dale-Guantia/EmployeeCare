<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPolicyIngestionQueue extends Migration
{
    public function up()
    {
        Schema::create('policy_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::table('hr_policy_documents', function (Blueprint $table) {
            $table->uuid('ingestion_version')->nullable();
            $table->timestamp('ingestion_queued_at')->nullable();
            $table->timestamp('ingestion_started_at')->nullable();
        });
    }

    public function down()
    {
        // Retain queued jobs and generation markers; dropping them would discard recovery state.
    }
}
