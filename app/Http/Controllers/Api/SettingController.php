<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:setting_access', ['only' => 'index']);
        $this->middleware('permission:setting_read', ['only' => 'index']);
        $this->middleware('permission:setting_edit', ['only' => 'update']);
    }

    public function index()
    {
        abort_if(!auth('sanctum')->user()->tokenCan('setting_access'), 403);

        return SettingResource::collection(Setting::all());
    }

    public function update(Setting $setting, Request $request)
    {
        abort_if(!auth('sanctum')->user()->tokenCan('setting_edit'), 403);

        $request->validate([
            'value' => 'required'
        ]);

        $setting->update(['value' => $request->value]);

        return (new SettingResource($setting))->response()->setStatusCode(202);
    }
}
