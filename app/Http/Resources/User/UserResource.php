<?php

namespace App\Http\Resources\User;

use App\Helpers\PermissionsHelper;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\Role\RoleResource;
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
        $data = parent::toArray($request);
        if ($this->type) {
            $data['type'] = $this->type->getDescription();
        }
        $data['roles'] = RoleResource::collection($this->whenLoaded('roles'));
        $data['warehouses'] = DefaultResource::collection($this->whenLoaded('warehouses'));

        if ($request->getRequestUri() === '/api/users/me') {
            return [
                ...$data,
                'permissions' => PermissionsHelper::getMyPermissions(),
            ];
        }

        return $data;
    }
}
