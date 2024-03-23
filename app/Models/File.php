<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class File extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['name', 'type', 'size', 'data', 'uploaded_at'];

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }
}
