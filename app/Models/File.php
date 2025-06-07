<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class File extends Model
{
    use BelongsToUser;
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'size',
        'data',
        'uploaded_at',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }
}
