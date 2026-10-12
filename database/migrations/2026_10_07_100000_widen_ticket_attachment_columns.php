<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class WidenTicketAttachmentColumns extends Migration
{
    public function up()
    {
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
