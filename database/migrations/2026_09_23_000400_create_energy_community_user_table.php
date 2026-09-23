<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('energy_community_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('energy_community_id')->constrained();
            $table->foreignId('user_id')->index()->constrained();
            $table->string('role');
            $table->timestamps();

            $table->unique(['energy_community_id', 'user_id']); // BR-4: one role per user and community.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_community_user');
    }
};
