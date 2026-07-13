<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOcrFieldsToHrPolicyTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hr_policy_documents', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->after('file_path');
            $table->longText('extracted_text')->nullable()->after('ingest_error');
            $table->unsignedTinyInteger('ocr_confidence')->nullable()->after('ocr_used');
            $table->index('file_hash', 'hr_policy_documents_file_hash_index');
        });

        Schema::table('hr_policy_chunks', function (Blueprint $table) {
            $table->unsignedInteger('page_number')->nullable()->after('chunk_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hr_policy_documents', function (Blueprint $table) {
            $table->dropIndex('hr_policy_documents_file_hash_index');
            $table->dropColumn(['file_hash', 'extracted_text', 'ocr_confidence']);
        });

        Schema::table('hr_policy_chunks', function (Blueprint $table) {
            $table->dropColumn('page_number');
        });
    }
}
