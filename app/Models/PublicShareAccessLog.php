<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PublicShareAction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $public_collection_link_id
 * @property string $ip_address
 * @property string|null $user_agent
 * @property PublicShareAction $action
 * @property array|null $metadata
 * @property Carbon $accessed_at
 * @property-read PublicCollectionLink $publicCollectionLink
 */
class PublicShareAccessLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'public_collection_link_id',
        'ip_address',
        'user_agent',
        'action',
        'metadata',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => PublicShareAction::class,
            'metadata' => 'array',
            'accessed_at' => 'datetime',
        ];
    }

    public function publicCollectionLink(): BelongsTo
    {
        return $this->belongsTo(PublicCollectionLink::class);
    }
}
