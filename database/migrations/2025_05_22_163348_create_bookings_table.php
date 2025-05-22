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
            $table->uuid('id')->primary();
            $table->string('booking_code')->unique(); // Kode booking unik
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('destination_id')->constrained('destinations')->onDelete('restrict'); // Jangan hapus destinasi jika ada booking
            $table->foreignUuid('slot_id')->nullable()->constrained('slots')->onDelete('set null');

            $table->date('visit_date');
            $table->unsignedInteger('num_tickets');
            $table->decimal('total_price', 12, 2);
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, completed
            $table->string('payment_method')->nullable();
            $table->string('payment_id_external')->nullable(); // ID dari payment gateway
            $table->jsonb('payment_details')->nullable(); // Detail tambahan dari payment gateway
            $table->timestamps();
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
