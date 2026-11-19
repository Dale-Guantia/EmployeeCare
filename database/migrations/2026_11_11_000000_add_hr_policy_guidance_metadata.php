<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hr_policy_documents', function (Blueprint $table) {
            $table->string('policy_key', 120)->nullable();
            $table->string('edition', 120)->nullable();
            $table->date('end_date')->nullable();
            $table->string('audience', 255)->nullable();
            $table->string('authority_status', 30)->default('unreviewed');
            $table->text('relationship_notes')->nullable();
            $table->index(['policy_key', 'authority_status'], 'hr_policy_key_authority_index');
        });

        Schema::table('hr_policy_chunks', function (Blueprint $table) {
            $table->unsignedInteger('end_page_number')->nullable();
        });

        Schema::table('hr_chat_logs', function (Blueprint $table) {
            $table->json('source_snapshot')->nullable();
        });

        Schema::table('hr_conversations', function (Blueprint $table) {
            $table->json('context_state')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hr_conversations', fn (Blueprint $table) => $table->dropColumn('context_state'));
        Schema::table('hr_chat_logs', fn (Blueprint $table) => $table->dropColumn('source_snapshot'));
        Schema::table('hr_policy_chunks', fn (Blueprint $table) => $table->dropColumn('end_page_number'));
        Schema::table('hr_policy_documents', function (Blueprint $table) {
            $table->dropIndex('hr_policy_key_authority_index');
            $table->dropColumn(['policy_key', 'edition', 'end_date', 'audience', 'authority_status', 'relationship_notes']);
        });
    }
};
