<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRateObservation extends Model
{
    protected $fillable = ['provider', 'currency', 'observation_date', 'rate', 'units'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:10', 'units' => 'integer'];
    }
}
