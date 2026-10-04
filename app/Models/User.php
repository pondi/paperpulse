<?php

namespace App\Models;

use App\Services\Files\AccountDeletionService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected static function booted(): void
    {
        static::deleting(fn (self $user) => app(AccountDeletionService::class)->prepare($user));
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(function (): ?bool {
            static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            return parent::delete();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'timezone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Get the user's preferences.
     *
     * @return HasOne<UserPreference, $this>
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * Get the PulseDav files for the user.
     *
     * @return HasMany<PulseDavFile, $this>
     */
    public function pulseDavFiles(): HasMany
    {
        return $this->hasMany(PulseDavFile::class);
    }

    /**
     * Get the receipts for the user.
     *
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Get the categories for the user.
     *
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the tags for the user.
     *
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Get the batch jobs for the user.
     *
     * @return HasMany<BatchJob, $this>
     */
    public function batchJobs(): HasMany
    {
        return $this->hasMany(BatchJob::class);
    }

    /**
     * Get user preference value with fallback to default
     */
    public function getPreference($key, $default = null)
    {
        if (! $this->preferences) {
            return $default ?? UserPreference::defaultPreferences()[$key] ?? null;
        }

        return $this->preferences->$key ?? $default;
    }

    /**
     * Get user preference value with fallback to default (alias for getPreference)
     */
    public function preference($key, $default = null)
    {
        return $this->getPreference($key, $default);
    }

    /** @return array{language: string, timezone: string, date_format: string, currency: string} */
    public function formattingPreferences(): array
    {
        $options = UserPreference::getOptions();
        $defaults = UserPreference::defaultPreferences();
        $preferences = [];
        foreach (['language' => 'languages', 'date_format' => 'date_formats', 'currency' => 'currencies'] as $field => $option) {
            $value = $this->preference($field, $defaults[$field]);
            $preferences[$field] = array_key_exists($value, $options[$option]) ? $value : $defaults[$field];
        }
        $timezone = $this->preference('timezone', 'UTC');
        $preferences['timezone'] = in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';

        return $preferences;
    }

    public function currentDate(): Carbon
    {
        return Carbon::today($this->formattingPreferences()['timezone']);
    }

    /**
     * Check if the user is an administrator
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }
}
