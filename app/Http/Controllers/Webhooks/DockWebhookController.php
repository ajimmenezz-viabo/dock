<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Shared\WebhookRequest;
use Illuminate\Http\Request;

class DockWebhookController extends Controller
{
    public function store(Request $request)
    {
        try {
            WebhookRequest::create([
                'Url' => $request->fullUrl(),
                'Method' => $request->method(),
                'Headers' => json_encode($request->header()),
                'QueryParams' => json_encode($request->query()),
                'Body' => $request->getContent()
            ]);

            return response()->json(['message' => 'Request registered'], 200);
        } catch (\Exception $e) {
            return self::error('Error registering request', 500, $e);
        }
    }
}
