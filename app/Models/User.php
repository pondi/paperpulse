<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
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
     * @var array<int, string>
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
     */
    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * Get the PulseDav files for the user.
     */
    public function pulseDavFiles()
    {
        return $this->hasMany(PulseDavFile::class);
    }

    /**
     * Get the receipts for the user.
     */
    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Get the categories for the user.
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the tags for the user.
     */
    public function tags()
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Get the batch jobs for the user.
     */
    public function batchJobs()
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

    /**
     * Check if the user is an administrator
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }
}
