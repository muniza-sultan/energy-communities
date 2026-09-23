<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_points', function (Blueprint $table) {
            $table->id();
            $table->string('name', 33)->unique();
            $table->foreignId('user_id')->index()->constrained();
            $table->string('energy_direction');
            $table->string('grid_operator_id', 10)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_points');
    }
};
