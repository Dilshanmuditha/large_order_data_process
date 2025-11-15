<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->integer('amount_cents');
            $table->string('reason')->nullable();
            // Idempotency flag: true if KPI reduction has been applied
            $table->boolean('kpi_updated')->default(false);
            $table->string('status')->default('processed');
            $table->timestamps();

            $table->index('order_id');
            $table->index('kpi_updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
