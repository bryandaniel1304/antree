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
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // null = jam usaha (jam operasional keseluruhan)
            $table->foreignId('staff_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 (Minggu) - 6 (Sabtu)
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'staff_member_id', 'day_of_week'], 'working_hours_unique_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
