<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DockApiService;

class DockRegisterController extends Controller
{
    public function store(Request $request)
    {
        try {
            $responses = [];
            $events = $this->getEvents();
            foreach ($events as $event) {
                $raw = [
                    "event_name" => $event->event_name,
                    "url" => ((env('APP_ENV') === 'production') ? env('PRODUCTION_WH_ENDPOINT') : env('STAGING_WH_ENDPOINT')) . 'wh/v1/dock_events',
                    "headers" => [
                        "Authorization" => "Basic ". base64_encode(env('BASIC_AUTH_USER') . ":" . env('BASIC_AUTH_PASS'))
                    ]
                ];

                $response = DockApiService::request(
                    ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'notifications/v1/webhooks',
                    'POST',
                    [],
                    [],
                    'bearer',
                    $raw
                );

                $responses[] = $response;
            }

            return response()->json($responses, 200);
        } catch (\Exception $e) {
            return self::error('Error getting events', 500, $e);
        }
    }

    private function getEvents()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'notifications/v1/events',
            'GET',
            [],
            [],
            'bearer',
            null
        );

        return $response;
    }
}
