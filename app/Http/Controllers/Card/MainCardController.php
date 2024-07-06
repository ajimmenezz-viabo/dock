<?php

namespace App\Http\Controllers\Card;

use App\Http\Controllers\Caradhras\Security\Encryption;
use App\Http\Controllers\CardMovements\CardMovementController;
use App\Http\Controllers\CardsManagement\CardsController;
use App\Http\Controllers\Controller;
use App\Models\Card\Card;
use App\Models\CardMovements\CardMovements;
use App\Models\CardSetups\CardSetups;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\DockApiService;
use Exception;

class MainCardController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/card/{uuid}",
     *      tags={"Cards"},
     *      summary="Get card details",
     *      description="Returns card details",
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
     *          description="Cards retrieved successfully", 
     *          @OA\JsonContent(
     *              @OA\Property(property="card_id", type="string", example="123456", description="Card UUID"),  
     *              @OA\Property(property="card_type", type="string", example="credit", description="Card Type"),
     *              @OA\Property(property="brand", type="string", example="master", description="Card Brand"),
     *              @OA\Property(property="masked_pan", type="string", example="1234xxxxxxxx9876", description="Masked Pan"),
     *              @OA\Property(property="bin", type="string", example="12345678", description="Card BIN"),
     *              @OA\Property(property="balance", type="string", example="100.00", description="Card Balance"),
     *              @OA\Property(property="clabe", type="string", example="123456", description="Card CLABE"),
     *              @OA\Property(property="status", type="string", example="active", description="Card Status")
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *         )
     *    ),
     * )
     */
    public function show($uuid)
    {
        $card = Card::where('UUID', $uuid)->first();
        if (!$card) {
            return self::error('Card not found or you do not have permission to access it', 404, new \Exception('Card not found'));
        }

        if (!$this->validate_card_owner($card)) {
            return self::error('Card not found or you do not have permission to access it', 403, new \Exception('Unauthorized'));
        }

        return response()->json(self::cardObject($uuid, auth()->user()->prefix), 200);
    }

    /**
     *  @OA\Get(
     *      path="/api/v1/card/{uuid}/movements",
     *      tags={"Cards"},       
     *      summary="Get card movements",
     *      description="Returns card movements",
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
     *      @OA\RequestBody(
     *          required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="from", type="string", example="1234567890", description="From date (Unix timestamp)"),
     *              @OA\Property(property="to", type="string", example="1234567890", description="To date (Unix timestamp)")
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Movements retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="movements", type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="movement_id", type="string", example="123456", description="Movement UUID"),
     *                      @OA\Property(property="date", type="string", example="1234567890", description="Movement Date (Unix timestamp)"),
     *                      @OA\Property(property="type", type="string", example="deposit", description="Movement Type"),
     *                      @OA\Property(property="amount", type="string", example="100.00", description="Movement Amount"),
     *                      @OA\Property(property="authorization_code", type="string", example="123456", description="Authorization Code"),
     *                      @OA\Property(property="description", type="string", example="Deposit", description="Movement Description")
     *                  ),
     *              ),
     *              @OA\Property(property="total_records", type="integer", example=1, description="Total records"),
     *              @OA\Property(property="from", type="string", example="1234567890", description="From date (Unix timestamp)"),
     *              @OA\Property(property="to", type="string", example="1234567890", description="To date (Unix timestamp)")
     *          )
     *     ), 
     *              
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *          )
     *      ),
     * 
     * )
     * 
     */
    public function movements(Request $request, $uuid)
    {
        $card = Card::where('UUID', $uuid)->first();
        if (!$card) {
            return self::error('Card not found or you do not have permission to access it', 404, new \Exception('Card not found'));
        }

        if (!$this->validate_card_owner($card)) {
            return self::error('Card not found or you do not have permission to access it', 403, new \Exception('Unauthorized'));
        }

        $from = isset($request['from']) ? Carbon::createFromTimestamp($request->from) : Carbon::now()->subMonth();
        $to = isset($request['to']) ? Carbon::createFromTimestamp($request->to) : Carbon::now();

        if ($from > $to) return response()->json(['message' => 'Invalid date range'], 400);

        if (strtotime($to) - strtotime($from) > (2592000 * 3)) return self::error('Date range exceeds 90 days', 400, new \Exception('Date range exceeds 90 days'));

        $movements = CardMovementController::movements($card->Id, $from, $to);

        return response()->json([
            'movements' => $movements,
            'total_records' => count($movements),
            'from' => $from->timestamp,
            'to' => $to->timestamp
        ], 200);
    }

    public static function cardObject($uuid)
    {
        $card = Card::where('UUID', $uuid)->first();
        $card = CardsController::fillSensitiveData($card);
        $setup = self::fillSetups($card);

        $bin = is_null($card->Pan) ? null : substr(self::decrypt($card->Pan), -8);

        return [
            'card_id' => $card->UUID,
            'card_type' => $card->Type,
            'brand' => $card->Brand,
            'bin' => (is_null($card->Pan) ? null : substr(self::decrypt($card->Pan), -8)),
            'masked_pan' => $card->MaskedPan,
            'balance' => number_format(floatval(self::decrypt($card->Balance)), 2, '.', ''),
            'clabe' => $card->STPAccount,
            'status' => $setup->Status
        ];
    }

    private function validate_card_owner($card)
    {
        if (auth()->user()->profile == 'superadmin') {
            return true;
        }

        if (auth()->user()->profile == 'admin_account' && $card->CreatorId == auth()->user()->Id) {
            return true;
        }

        return false;
    }

    public static function fixNonCustomerId($card)
    {
        if ($card->CustomerId == null || $card->CustomerId == '') {
            $external_card = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId,
                'GET',
                [],
                [],
                'bearer',
                null
            );

            $prefix = null;
            $client_id = null;
            if (isset($external_card->metadata) && isset($external_card->metadata->key) && $external_card->metadata->key == 'text1') {
                $prefix = substr($external_card->metadata->value, 0, 2);
                $client_id = intval(substr($external_card->metadata->value, 2));
            }

            $card->CustomerId = $client_id;
            $card->CustomerPrefix = $prefix;
            $card->CardObject = json_encode($external_card);

            $card->save();
        }
    }

    public static function fillSetups($card)
    {
        try {
            $setups = CardSetups::where('CardId', $card->Id)->first();
            if ($setups) return $setups;

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId,
                'GET',
                [],
                [],
                'bearer',
                null
            );

            $setups = CardSetups::create([
                'CardId' => $card->Id,
                'Status' => $response->status,
                'StatusReason' => $response->status_reason,
                'Ecommerce' => $response->settings->transaction->ecommerce,
                'International' => $response->settings->transaction->international,
                'Stripe' => $response->settings->transaction->stripe,
                'Wallet' => $response->settings->transaction->wallet,
                'Withdrawal' => $response->settings->transaction->withdrawal,
                'Contactless' => $response->settings->transaction->contactless,
                'PinOffline' => $response->settings->security->pin_offline,
                'PinOnUs' => $response->settings->security->pin_on_us
            ]);

            return $setups;
        } catch (Exception $e) {
            return null;
        }
    }

    public function updatePin(Request $request, $uuid)
    {
        $card = Card::where('UUID', $uuid)->first();
        if (!$card) {
            return self::error('Card not found or you do not have permission to access it', 404, new \Exception('Card not found'));
        }

        if (!$this->validate_card_owner($card)) {
            return self::error('Card not found or you do not have permission to access it', 403, new \Exception('Unauthorized'));
        }

        if ($request->pin == null || $request->pin == '') {
            return response()->json(['message' => 'New pin is required'], 400);
        }

        if (strlen($request->pin) != 4) {
            return response()->json(['message' => 'Pin must be 4 digits'], 400);
        }

        if (!is_numeric($request->pin)) {
            return response()->json(['message' => 'Pin must be numeric'], 400);
        }

        try {
            $encrytedData = json_decode(Encryption::encryptD($request->pin));
            $rawData = [
                "pin" => $encrytedData->encrypt,
                "aes" => $encrytedData->aes,
                "iv" => $encrytedData->iv,
                "mode" => "GCM"
            ];

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/pin',
                'PUT',
                [],
                [],
                'bearer',
                $rawData
            );

            if ($response->id == $card->ExternalId) {
                $card->Pin = self::encrypt($request->pin);
                $card->save();
            }

            return response()->json(['message' => 'Pin updated successfully'], 200);
        } catch (Exception $e) {
            return self::error('Error while updating pin', 500, $e);
        }
    }
}
