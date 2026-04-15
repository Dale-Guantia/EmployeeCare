<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArtaFormTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('arta_form', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->id();
            $table->string('client_type')->nullable();
            $table->timestamp('date')->now();
            $table->boolean('sex')->nullable();
            $table->tinyInteger('age')->nullable();
            $table->string('region')->nullable();
            $table->string('service_availed')->nullable();
            $table->foreignId('CC1')->constrained('cc_choices')->cascadeOnDelete();
            $table->foreignId('CC2')->constrained('cc_choices')->cascadeOnDelete();
            $table->foreignId('CC3')->constrained('cc_choices')->cascadeOnDelete();
            $table->string('SQD0')->nullable();
            $table->string('SQD1')->nullable();
            $table->string('SQD2')->nullable();
            $table->string('SQD3')->nullable();
            $table->string('SQD4')->nullable();
            $table->string('SQD5')->nullable();
            $table->string('SQD6')->nullable();
            $table->string('SQD7')->nullable();
            $table->string('SQD8')->nullable();
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
        Schema::dropIfExists('arta_form');
    }
}
