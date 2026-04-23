<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('emp_no')->nullable(); //Add unique() if needed
            $table->unsignedTinyInteger('role')->default(2); // default for "employee" role (1=admin, 2=employee)
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->string('username')->nullable()->unique();
            $table->string('email')->unique()->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('avatar_url')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->json('skills')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('notify_ticket_created')->default(true);
            $table->boolean('notify_ticket_assigned')->default(true);
            $table->boolean('notify_ticket_status_changed')->default(true);
            $table->boolean('notify_ticket_commented')->default(true);
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
