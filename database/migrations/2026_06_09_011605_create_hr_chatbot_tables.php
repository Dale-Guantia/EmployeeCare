<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrChatbotTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Stores your HR policy documents
        Schema::create('hr_policy_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('filename');
            $table->string('file_path')->nullable();
            $table->string('category')->default('general'); // leave, benefits, conduct, etc.
            $table->boolean('is_active')->default(true);
            $table->date('effective_date')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('chunk_count')->default(0);
            $table->text('ingest_error')->nullable();
            $table->timestamps();
        });

        // Stores searchable chunks from each PDF
        Schema::create('hr_policy_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('hr_policy_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('section_title')->nullable();
            $table->text('content');
            $table->text('search_vector')->nullable();
            $table->timestamps();

            $table->fullText(['content', 'section_title', 'search_vector']);
        });

        // Logs every chat for HRDO audit
        Schema::create('hr_chat_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // backpack_user()->id
            $table->text('question');
            $table->longtext('answer');
            $table->json('matched_chunk_ids')->nullable();
            $table->tinyInteger('feedback')->nullable(); // 1 = helpful, -1 = not
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hr_chat_logs');
        Schema::dropIfExists('hr_policy_chunks');
        Schema::dropIfExists('hr_policy_documents');
    }
}
