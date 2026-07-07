<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            endpoint: $validated['endpoint'],
            key: $validated['keys']['p256dh'],
            token: $validated['keys']['auth'],
            contentEncoding: $validated['contentEncoding'] ?? null,
        );

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate(['endpoint' => ['required', 'string']]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->noContent();
    }
}
