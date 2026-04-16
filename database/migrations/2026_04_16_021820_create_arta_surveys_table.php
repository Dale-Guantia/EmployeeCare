<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArtaSurveysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('arta_surveys', function (Blueprint $table) {
            $table->id();

            // Demographics
            $table->string('client_type')->nullable();
            $table->string('sex')->nullable();
            $table->integer('age')->nullable();
            $table->unsignedTinyInteger('region')->nullable();
            $table->string('service_availed')->nullable();

            // CC Questions (tinyInteger is lightweight and perfect for 1-5 choices)
            $table->tinyInteger('cc1')->nullable();
            $table->tinyInteger('cc2')->nullable();
            $table->tinyInteger('cc3')->nullable();

            // SQD Questions (Stored as strings: 'Strongly Agree', 'Disagree', etc.)
            $table->string('sqd0')->nullable();
            $table->string('sqd1')->nullable();
            $table->string('sqd2')->nullable();
            $table->string('sqd3')->nullable();
            $table->string('sqd4')->nullable();
            $table->string('sqd5')->nullable();
            $table->string('sqd6')->nullable();
            $table->string('sqd7')->nullable();
            $table->string('sqd8')->nullable();

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
        Schema::dropIfExists('arta_surveys');
    }
}
