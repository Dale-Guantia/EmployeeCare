<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToTicketsTable extends Migration
{
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('status_id');
            $table->index(['department_id', 'division_id']);
            $table->index('assigned_to');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('issue_id');
            $table->index(['department_id', 'division_id', 'status_id']);
        });
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status_id']);
            $table->dropIndex(['department_id', 'division_id']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['issue_id']);
            $table->dropIndex(['department_id', 'division_id', 'status_id']);
        });
    }
}
