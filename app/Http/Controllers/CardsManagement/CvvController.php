<?php

namespace App\Http\Controllers\CardsManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\DockApiService;
use App\Http\Controllers\Caradhras\Security\Encryption;
use Exception;

class CvvController extends Controller
{
    private $dock_encrypter;

    public function __construct()
    {
        parent::__construct();
        $this->dock_encrypter = new Encryption();
    }

    public function create($uuid, Request $request)
    {
        try {
            $card = CardsController::validateCardPermission($uuid);
            if (!$card) return response()->json(['message' => 'Card not found or you do not have permission to access it'], 404);

            if ($card->Type != 'virtual')
                return response()->json(['message' => 'Dynamic CVV is only available for virtual cards'], 400);

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/dynamic-cvv',
                'POST',
                [],
                [],
                'bearer',
                [
                    'expiration_date' => $request->input('expiration_date')
                ]
            );

            $mode = isset($response->mode) ? $response->mode : 'gcm';

            return response()->json(['data' => $response, 'cvv' => $this->dock_encrypter->decrypt($response->aes, $response->iv, $response->cvv, $mode)], 200);
        } catch (\Exception $e) {
            return self::error('Error creating CVV', 500, $e);
        }
    }

    public function show($uuid)
    {
        try {
            $card = CardsController::validateCardPermission($uuid);
            if (!$card) return response()->json(['message' => 'Card not found or you do not have permission to access it'], 404);

            if ($card->Type != 'virtual')
                return response()->json(['message' => 'Dynamic CVV is only available for virtual cards'], 400);

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/dynamic-cvv',
                'GET',
                [],
                [],
                'bearer',
                []
            );

            $mode = isset($response->mode) ? $response->mode : 'gcm';
            $cvv = $this->dock_encrypter->decrypt($response->aes, $response->iv, $response->cvv, $mode);

            if (env('DEV_MODE') === true) {
                return response()->json([
                    'cvv' => $this->encrypter->encrypt($cvv),
                    'expiration_date' => $response->expiration_date,
                    'cvv_raw' => $cvv
                ], 200);
            } else {
                return response()->json([
                    'cvv' => $this->dock_encrypter->decrypt($response->aes, $response->iv, $response->cvv, $mode),
                    'expiration_date' => $response->expiration_date
                ], 200);
            }
        } catch (\Exception $e) {
            return self::error('Error showing CVV', 500, $e);
        }
    }
}
