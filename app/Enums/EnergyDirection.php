<?php

namespace App\Enums;

enum EnergyDirection: string
{
    case Generation = 'generation';
    case Consumption = 'consumption';
}
