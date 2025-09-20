<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\BaseApiResource;

class UserResource extends BaseApiResource
{
    public function toArray($request)
    {
        return array_merge($this->commonFields(), [
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => $this->is_admin ?? false,
            'timezone' => $this->timezone,
        ]);
    }
}
