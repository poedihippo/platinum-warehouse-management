<?php

use App\Enums\PaymentType;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the payments table in the database.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SalesOrder::class);
            $table->foreignIdFor(User::class);
            $table->unsignedFloat('amount', 11, 2)->default(0);
            $table->string('type', 20)->default(PaymentType::CASH);
            $table->text('note')->nullable();
            $table->timestamps();

            // softDeletes must implement deleted_by
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
