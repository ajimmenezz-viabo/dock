<?php

namespace App\Http\Controllers\PersonManagement;

use App\Http\Controllers\Controller;
use App\Services\DockApiService;
use App\Http\Controllers\Caradhras\Auth\TokenController;
use Exception;

class LegalPersonController extends Controller
{
    public function index()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'person/v1/legal-persons',
            'GET',
            [],
            [],
            'bearer'
        );

        return response($response)->header('Content-Type', 'application/json');
    }

    public function show($id)
    {
        return 'LegalPersonController@show';
    }

    public function store()
    {
        return 'LegalPersonController@store';
    }

    public function update($id)
    {
        return 'LegalPersonController@update';
    }

    public function destroy($id)
    {
        return 'LegalPersonController@destroy';
    }
}
