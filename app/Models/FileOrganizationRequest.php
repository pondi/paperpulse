<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileOrganizationRequest extends Model
{
    protected $fillable = ['user_id', 'file_id', 'generation', 'status', 'last_error'];
}
