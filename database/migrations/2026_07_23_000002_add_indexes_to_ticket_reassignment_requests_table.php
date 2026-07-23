<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToTicketReassignmentRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('ticket_reassignment_requests', function (Blueprint $table) {
            $table->index(['ticket_id', 'status']);
            $table->index('to_department_id');
            $table->index('to_division_id');
        });
    }

    public function down()
    {
        Schema::table('ticket_reassignment_requests', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'status']);
            $table->dropIndex(['to_department_id']);
            $table->dropIndex(['to_division_id']);
        });
    }
}
