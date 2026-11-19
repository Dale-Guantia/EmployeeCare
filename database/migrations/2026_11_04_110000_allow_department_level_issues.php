<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('issues')->whereNull('division_id')->exists()) {
            throw new RuntimeException('Assign divisions to department-level issues before rolling back.');
        }
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable(false)->change();
        });
    }
};
