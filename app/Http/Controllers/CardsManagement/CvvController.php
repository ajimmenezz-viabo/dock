<?php

namespace App\Http\Controllers\CardsManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\DockApiService;
use App\Http\Controllers\Caradhras\Security\Encryption as DockEncryption;
use Carbon\Carbon;
use Exception;

class CvvController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function create($card)
    {
        try {
            $expiration_date = Carbon::now(env('APP_TIMEZONE'))->addMinutes(2);

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/dynamic-cvv',
                'POST',
                [],
                [],
                'bearer',
                [
                    'expiration_date' => $expiration_date->format('Y-m-d\TH:i:s\Z')
                ]
            );

            $mode = isset($response->mode) ? $response->mode : 'gcm';

            return [
                'cvv' => DockEncryption::decrypt($response->aes, $response->iv, $response->cvv, $mode),
                'expiration_date' => $expiration_date->timestamp
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function show($uuid)
    {
        try {
            $card = CardsController::validateCardPermission($uuid);
            if (!$card) return response()->json(['message' => 'Card not found or you do not have permission to access it'], 404);

            if ($card->Type != 'virtual') {
                $card = CardsController::fillSensitiveData($card);
                $date = Carbon::parse(self::decrypt($card->ExpirationDate));
                $dateInUtc = $date->setTimezone('UTC');

                return response()->json([
                    'cvv' => self::decrypt($card->CVV),
                    'expiration' => $dateInUtc->timestamp
                ], 200);
            }

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/dynamic-cvv',
                'GET',
                [],
                [],
                'bearer',
                []
            );

            if (!isset($response->cvv)) {
                $cvv_data = self::create($card);
                if (empty($cvv_data)) {
                    return self::error('Error getting CVV, please try again later', 500, new Exception('Error getting CVV'));
                } else {
                    return response()->json([
                        'cvv' => $cvv_data['cvv'],
                        'expiration_date' => $cvv_data['expiration_date']
                    ], 200);
                }
            }

            $mode = isset($response->mode) ? $response->mode : 'gcm';

            $date = Carbon::parse($response->expiration_date);
            $dateInUtc = $date->setTimezone('UTC');

            return response()->json([
                'cvv' => DockEncryption::decrypt($response->aes, $response->iv, $response->cvv, $mode),
                'expiration_date' => $dateInUtc->timestamp
            ], 200);
        } catch (\Exception $e) {
            return self::error('Error getting CVV, please try again later', 500, $e);
        }
    }
}
