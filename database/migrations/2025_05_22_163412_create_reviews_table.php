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
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('destination_id')->constrained('destinations')->onDelete('cascade');
            $table->foreignUuid('booking_id')->nullable()->constrained('bookings')->onDelete('set null'); // Untuk validasi

            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->jsonb('images_urls')->nullable(); // Array URL gambar ulasan
            $table->timestamps();

            $table->index(['user_id', 'destination_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
