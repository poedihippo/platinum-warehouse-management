<?php

use App\Enums\UserType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
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
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('type')->default(UserType::Customer);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_address')->nullable();
            $table->string('provider_id')->unique()->nullable();
            $table->string('provider_name')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->unique()->nullable();
            $table->string('contact_person')->nullable();
            $table->string('web_page')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
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
};
