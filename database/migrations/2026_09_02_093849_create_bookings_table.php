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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('staff_member_id')->nullable()->constrained();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('source')->default('online'); // online|walk_in|phone
            $table->string('status')->default('pending');
            // pending|confirmed|arrived|in_service|completed|no_show|cancelled
            $table->unsignedInteger('queue_number')->nullable();
            // tanggal antrean di zona waktu usaha, dipakai untuk penomoran per hari
            $table->date('queue_date')->nullable();
            $table->string('code', 8)->unique();
            $table->string('public_token', 64)->unique();
            $table->dateTime('called_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Jaring pengaman terakhir: cegah dua booking staf yang sama di jam yang sama.
            $table->unique(['staff_member_id', 'starts_at'], 'bookings_staff_slot_unique');
            $table->unique(['business_id', 'queue_date', 'queue_number'], 'bookings_queue_number_unique');
            $table->index(['business_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
