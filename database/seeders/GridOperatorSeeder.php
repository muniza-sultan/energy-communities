<?php

namespace Database\Seeders;

use App\Models\GridOperator;
use Illuminate\Database\Seeder;

class GridOperatorSeeder extends Seeder
{
    /**
     * A handful of operators to register metering points against (BR-1).
     * AT003000 is the example from the brief; the other codes are sample data.
     */
    public function run(): void
    {
        $operators = [
            'AT001000' => 'Wiener Netze GmbH',
            'AT002000' => 'Netz Niederösterreich GmbH',
            'AT003000' => 'Netz Oberösterreich GmbH',
            'AT004000' => 'Salzburg Netz GmbH',
            'AT007000' => 'Kärnten Netz GmbH',
        ];

        foreach ($operators as $identifier => $name) {
            GridOperator::updateOrCreate(['identifier' => $identifier], ['name' => $name]);
        }
    }
}
