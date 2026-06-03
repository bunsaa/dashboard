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
        Schema::create('google_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_id')->unique(); // Google review ID (hash)
            $table->string('author_name');
            $table->string('author_url')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->integer('rating'); // 1-5
            $table->text('text')->nullable();
            $table->string('language')->default('id');
            $table->timestamp('review_time'); // waktu review asli dari Google
            $table->boolean('is_it_related')->default(false);
            $table->json('it_keywords_found')->nullable(); // keyword IT yang ditemukan
            $table->text('recommendation')->nullable(); // rekomendasi untuk review negatif IT
            $table->string('sentiment')->nullable(); // positive / negative / neutral
            $table->timestamps();

            $table->index(['is_it_related', 'review_time']);
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_reviews');
    }
};
