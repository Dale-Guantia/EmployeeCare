<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['departments', 'divisions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }
    }

    public function down(): void
    {
        foreach (['divisions', 'departments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
