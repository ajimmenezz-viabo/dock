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


    /**
     * @OA\Get(
     *      path="/api/v1/card/{uuid}/cvv",
     *      operationId="show",
     *      tags={"Cards"},
     *      summary="Get CVV for the card specified",
     *      description="Returns the CVV for the card specified. If the card is a virtual card, the CVV will be generated and expire in 2 minutes.",
     *      security={{"bearerAuth":{}}},
     *     
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          description="Card UUID",
     *          required=true,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="CVV retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="cvv", type="string", example="123", description="Card CVV"),
     *              @OA\Property(property="expiration", type="integer", example="1234567890", description="CVV expiration date")
     *         )
     *      ),
     * 
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response=404,
     *          description="Card not found or you do not have permission to access it",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Card not found or you do not have permission to access it", description="Message")
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response=500,
     *          description="Error getting CVV, please try again later",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Error getting CVV, please try again later", description="Message")
     *          )
     *      )
     *  
     *  )
     *      
     */

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
