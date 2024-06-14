<?php

namespace App\Http\Controllers\EmbossingManagement;

use App\Http\Controllers\Controller;
use App\Services\DockApiService;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;


class EmbossingLayoutController extends Controller
{
    public function index()
    {
        $layouts = $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'embossing/v1/layouts',
            'GET',
            [],
            [],
            'bearer',
            []
        );

        var_dump($layouts);
        die();

        // return response()->json($batches, 200);
    }
}
