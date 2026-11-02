<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Setting\SettingResource;
use App\Interfaces\Services\Setting\SettingServiceInterface;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends BaseController
{
    public function __construct(private SettingServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:setting_access', ['only' => 'index']);
        $this->middleware('permission:setting_read', ['only' => 'index']);
        $this->middleware('permission:setting_edit', ['only' => 'update']);
    }

    public function index()
    {
        abort_if(! auth('sanctum')->user()->tokenCan('setting_access'), 403);

        return SettingResource::collection($this->service->all());
    }

    public function update(Setting $setting, Request $request)
    {
        abort_if(! auth('sanctum')->user()->tokenCan('setting_edit'), 403);

        $request->validate([
            'value' => 'required',
        ]);

        $this->service->update($setting, ['value' => $request->value]);

        return (new SettingResource($setting))->response()->setStatusCode(202);
    }
}
