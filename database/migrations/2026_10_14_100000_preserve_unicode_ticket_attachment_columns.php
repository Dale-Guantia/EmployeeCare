<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class PreserveUnicodeTicketAttachmentColumns extends Migration
{
    public function up()
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            // Forward correction for installations that already ran the DBAL-based widening.
            DB::statement('ALTER TABLE [tickets] ALTER COLUMN [attachments] NVARCHAR(MAX) NULL');
            DB::statement('ALTER TABLE [ticket_comments] ALTER COLUMN [attachment] NVARCHAR(MAX) NULL');
        }
    }

    public function down()
    {
        // Retain Unicode capacity; narrowing/re-encoding could lose existing data.
    }
}
