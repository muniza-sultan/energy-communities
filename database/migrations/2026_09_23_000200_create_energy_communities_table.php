<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('energy_communities', function (Blueprint $table) {
            $table->id();
            $table->string('ecid')->unique();
            $table->string('name')->nullable();
            $table->string('state');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_communities');
    }
};
