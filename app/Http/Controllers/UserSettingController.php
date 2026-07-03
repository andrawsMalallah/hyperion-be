<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserSettingRequest;
use App\Models\UserSetting;
use Illuminate\Http\Request;

class UserSettingController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'data' => $this->settingsFor($request),
        ]);
    }

    public function update(UpdateUserSettingRequest $request)
    {
        $settings = $this->settingsFor($request);
        $settings->update($request->validated());

        return response()->json([
            'data' => $settings,
        ]);
    }

    private function settingsFor(Request $request): UserSetting
    {
        return $request->user()->settings ?? $request->user()->settings()->create([
            'timer_enabled' => true,
            'default_rest_time' => 90,
            'weight_unit' => 'kg',
        ]);
    }
}
