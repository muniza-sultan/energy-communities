<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('energy_community_meter_point', function (Blueprint $table) {
            $table->id();
            $table->foreignId('energy_community_id')->constrained();
            $table->foreignId('meter_point_id')->index()->constrained();
            $table->string('state');
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->date('consent_date');
            $table->integer('status_code')->nullable();
            $table->timestamps();

            $table->index(['energy_community_id', 'state']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE energy_community_meter_point
            ADD CONSTRAINT energy_community_meter_point_period_check
            CHECK (to_date IS NULL OR to_date >= from_date)
        SQL);

        // business rule 7/8
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(<<<'SQL'
            ALTER TABLE energy_community_meter_point
            ADD CONSTRAINT energy_community_meter_point_no_overlap
            EXCLUDE USING gist (
                meter_point_id WITH =,
                daterange(from_date, to_date, '[]') WITH &&
            )
            WHERE (state IN ('new', 'requested', 'message_received', 'accepted'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_community_meter_point');
    }
};
