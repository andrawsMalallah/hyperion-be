<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserSettingController extends Controller
{
    public function show(Request $request)
    {
        $settings = $request->user()->settings;
        
        if (!$settings) {
            $settings = $request->user()->settings()->create([
                'timer_enabled' => true,
                'default_rest_time' => 90,
                'weight_unit' => 'kg',
            ]);
        }
        
        return response()->json([
            'data' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $settings = $request->user()->settings;
        
        if (!$settings) {
            $settings = $request->user()->settings()->create([
                'timer_enabled' => true,
                'default_rest_time' => 90,
                'weight_unit' => 'kg',
            ]);
        }
        
        $validated = $request->validate([
            'timer_enabled' => 'sometimes|boolean',
            'default_rest_time' => 'sometimes|integer|min:0',
            'weight_unit' => 'sometimes|string|in:kg,lbs',
        ]);
        
        $settings->update($validated);
        
        return response()->json([
            'data' => $settings
        ]);
    }
}
