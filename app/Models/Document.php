<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use App\Traits\ShareableModel;
use App\Traits\TaggableModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * Document Model
 *
 * @property int $id
 * @property int $file_id
 * @property int $user_id
 * @property int|null $category_id
 * @property string $title
 * @property string|null $description
 * @property string|null $note
 * @property string|null $summary
 * @property string $document_type
 * @property string|null $content
 * @property array|null $extracted_text
 * @property array|null $entities
 * @property array|null $ai_entities
 * @property array|null $metadata
 * @property string|null $language
 * @property Carbon|null $document_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read File $file
 * @property-read User $user
 * @property-read Category|null $category
 * @property-read Collection|Tag[] $tags
 * @property-read Collection|FileShare[] $shares
 */
use App\Contracts\Taggable;

class Document extends Model implements Taggable
{
    use BelongsToUser;
    use HasFactory;
    use Searchable;
    use ShareableModel;
    use TaggableModel;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'file_id',
        'user_id',
        'category_id',
        'title',
        'description',
        'note',
        'summary',
        'content',
        'document_type',
        'extracted_text',
        'entities',
        'ai_entities',
        'metadata',
        'language',
        'document_date',
        'page_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'extracted_text' => 'array',
        'entities' => 'array',
        'ai_entities' => 'array',
        'metadata' => 'array',
        'document_date' => 'datetime',
        'note' => 'string',
        'summary' => 'string',
    ];

    /**
     * Get the file that owns the document.
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Get the user that owns the document.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owner of the document (alias for user relation).
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the category that the document belongs to.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the shareable type for FileShare records.
     */
    protected function getShareableType(): string
    {
        return 'document';
    }

    /**
     * Get the taggable type for the pivot table.
     */
    protected function getTaggableType(): string
    {
        return 'document';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        // Load relationships if not already loaded
        if (!$this->relationLoaded('category')) {
            $this->load('category');
        }
        if (!$this->relationLoaded('tags')) {
            $this->load('tags');
        }
        if (!$this->relationLoaded('file')) {
            $this->load('file');
        }

        return [
            // Ensure search engine can filter by user
            'user_id' => $this->user_id,
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'note' => $this->note,
            'summary' => $this->summary,
            'document_type' => $this->document_type,
            'extracted_text' => $this->extracted_text,
            'entities' => $this->entities,
            'language' => $this->language,
            'document_date' => $this->document_date?->format('Y-m-d'),
            'category_name' => $this->category?->name,
            'tags' => $this->tags?->pluck('name')->toArray() ?? [],
            'file_name' => $this->file?->original_filename,
            'file_type' => $this->file?->mime_type,
            'file_size' => $this->file?->file_size,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Determine if the model should be searchable.
     *
     * @return bool
     */
    public function shouldBeSearchable()
    {
        return $this->file?->status === 'completed';
    }
}
