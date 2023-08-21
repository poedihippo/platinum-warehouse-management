<?php

namespace App\Http\Resources;

use App\Helpers\PermissionsHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if ($request->getRequestUri() === '/api/users/me') {
            return [
                ...parent::toArray($request),
                'permissions' => PermissionsHelper::getMyPermissions()
            ];
        }

        return parent::toArray($request);
    }
}
