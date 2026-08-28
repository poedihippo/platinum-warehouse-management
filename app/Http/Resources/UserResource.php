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
        $data = parent::toArray($request);
        if ($this->type) {
            $data['type'] = $this->type->description;
        }
        $data['roles'] = RoleResource::collection($this->whenLoaded('roles'));
        $data['warehouses'] = WarehouseResource::collection($this->whenLoaded('warehouses'));

        if ($request->getRequestUri() === '/api/users/me') {
            return [
                ...$data,
                'permissions' => PermissionsHelper::getMyPermissions(),
            ];
        }

        return $data;
    }
}
