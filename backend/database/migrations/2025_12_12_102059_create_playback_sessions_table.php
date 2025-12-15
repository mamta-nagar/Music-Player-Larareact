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
    Schema::create('playback_sessions', function (Blueprint $table) {
        $table->id();
        $table->string('session_id')->unique();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('current_song_id')->nullable();
        $table->decimal('current_time', 10, 2)->default(0);
        $table->boolean('is_playing')->default(false);
        $table->decimal('volume', 3, 2)->default(0.70);
        $table->string('active_device_id')->nullable();
        $table->json('connected_devices')->nullable();
        $table->timestamp('last_sync_at')->nullable();
        $table->timestamps();
        
        $table->index('session_id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playback_sessions');
    }
};
