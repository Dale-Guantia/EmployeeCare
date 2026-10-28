<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class WidenTicketAttachmentColumns extends Migration
{
    public function up()
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            // DBAL maps text to VARCHAR(MAX), which can lose legacy NVARCHAR characters.
            DB::statement('ALTER TABLE [tickets] ALTER COLUMN [attachments] NVARCHAR(MAX) NULL');
            DB::statement('ALTER TABLE [ticket_comments] ALTER COLUMN [attachment] NVARCHAR(MAX) NULL');
            return;
        }
        Schema::table('tickets', function (Blueprint $table) {
            $table->text('attachments')->nullable()->change();
        });
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->text('attachment')->nullable()->change();
        });
    }

    public function down()
    {
        // Retain widened columns: narrowing would truncate valid existing attachments.
    }
}
