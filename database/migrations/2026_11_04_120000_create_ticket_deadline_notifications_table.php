<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_deadline_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // Avoid multiple cascade paths on SQL Server; recipient IDs are a delivery ledger.
            $table->unsignedBigInteger('user_id');
            $table->string('stage', 20);
            $table->dateTime('deadline_at');
            $table->timestamp('created_at');
            $table->unique(['ticket_id', 'user_id', 'stage', 'deadline_at'], 'ticket_deadline_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_deadline_notifications');
    }
};
