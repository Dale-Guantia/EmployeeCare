<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCcChoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cc_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('cc_questions')->cascadeOnDelete();
            $table->integer('choice_no'); // 1,2,3,4...
            $table->text('choice_desc');
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
        Schema::dropIfExists('cc_choices');
    }
}
