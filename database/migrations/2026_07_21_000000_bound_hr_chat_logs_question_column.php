<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BoundHrChatLogsQuestionColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // hr_chat_logs.question was created as text() (nvarchar(max) on
        // sqlsrv), which SQL Server refuses to GROUP BY. The controller
        // already validates incoming questions at max:600 chars, so the
        // column was always semantically bounded — this just makes the
        // schema say so, matching HrChatController@ask's validation rule.
        Schema::table('hr_chat_logs', function (Blueprint $table) {
            $table->string('question', 600)->change();
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
            $table->text('question')->change();
        });
    }
}
