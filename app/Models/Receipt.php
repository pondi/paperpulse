<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Receipt extends Model
{
    use HasFactory;
    use Searchable;

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function lineItems()
    {
        return $this->hasMany(LineItem::class);
    }
}
