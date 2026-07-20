<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConversationIdToHrChatLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hr_chat_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('conversation_id')->nullable()->after('user_id');
            $table->index(['conversation_id', 'id'], 'hr_chat_logs_conversation_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hr_chat_logs', function (Blueprint $table) {
            $table->dropIndex('hr_chat_logs_conversation_index');
            $table->dropColumn('conversation_id');
        });
    }
}
