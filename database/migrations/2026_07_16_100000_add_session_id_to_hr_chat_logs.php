<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSessionIdToHrChatLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hr_chat_logs', function (Blueprint $table) {
            $table->uuid('session_id')->nullable()->after('user_id');
            $table->index(['user_id', 'session_id', 'id'], 'hr_chat_logs_user_session_index');
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
            $table->dropIndex('hr_chat_logs_user_session_index');
            $table->dropColumn('session_id');
        });
    }
}
