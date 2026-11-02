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
        Schema::create('program_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->date('event_date');
            $table->string('time_range', 32);
            $table->string('title');
            $table->text('description');
            $table->string('responsible')->nullable();
            $table->string('presenter')->nullable();
            $table->string('production')->nullable();
            $table->string('materials')->nullable();
            $table->string('donation_challenge')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_items');
    }
};
