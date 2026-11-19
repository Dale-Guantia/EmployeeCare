<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('priorities', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        foreach (['issues', 'tickets'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['priority_id']);
                $table->foreign('priority_id')->references('id')->on('priorities')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['issues', 'tickets'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['priority_id']);
                $table->foreign('priority_id')->references('id')->on('priorities')->noActionOnDelete();
            });
        }

        Schema::table('priorities', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
