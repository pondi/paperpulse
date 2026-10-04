<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAlias extends Model
{
    use BelongsToUser;
    use HasFactory;

    protected $fillable = ['user_id', 'kind', 'alias_key', 'canonical_name'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function identity(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? ''));
    }

    public static function key(string $name): string
    {
        return hash('sha256', self::identity($name));
    }
}
