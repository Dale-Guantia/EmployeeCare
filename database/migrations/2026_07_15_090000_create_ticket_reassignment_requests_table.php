<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketReassignmentRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_reassignment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments');
            $table->foreignId('from_division_id')->nullable()->constrained('divisions');
            $table->foreignId('to_department_id')->constrained('departments');
            $table->foreignId('to_division_id')->nullable()->constrained('divisions');
            $table->foreignId('requested_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('responded_by')->nullable()->constrained('users');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
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
        Schema::dropIfExists('ticket_reassignment_requests');
    }
}
