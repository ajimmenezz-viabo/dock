<?php

namespace App\Http\Controllers\Subaccounts;

use App\Http\Controllers\Card\MainCardController;
use App\Http\Controllers\Controller;
use App\Models\Account\Subaccount;
use Illuminate\Http\Request;
use App\Models\Card\Card;
use Exception;

class SubaccountCardController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/subaccounts/{uuid}/cards",
     *      tags={"Subaccount Cards"},
     *      summary="Get all cards for the subaccount specified",
     *      description="Returns all cards for the subaccount specified",
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          description="Subaccount UUID",
     *          required=true,
     *          @OA\Schema(
     *              type="string"
     *          )
     *     ),
     * 
     *     @OA\RequestBody(
     *          required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="page", type="integer", example="1", description="Page number")
     *         )
     *     ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Cards retrieved successfully", 
     *          @OA\JsonContent(
     *              @OA\Property(property="cards", type="array",
     *                  @OA\Items(
     *                     @OA\Property(property="card_id", type="string", example="123456", description="Card UUID"),  
     *                      @OA\Property(property="card_type", type="string", example="credit", description="Card Type"),
     *                      @OA\Property(property="brand", type="string", example="master", description="Card Brand"),
     *                      @OA\Property(property="masked_pan", type="string", example="1234xxxxxxxx9876", description="Masked Pan"),
     *                      @OA\Property(property="bin", type="string", example="12345678", description="Card BIN"),
     *                      @OA\Property(property="balance", type="string", example="100.00", description="Card Balance"),
     *                      @OA\Property(property="clabe", type="string", example="123456", description="Card CLABE"),
     *                      @OA\Property(property="status", type="string", example="active", description="Card Status")
     *                  )
     *              ),
     *              @OA\Property(property="page", type="integer", example="1", description="Current page"),
     *              @OA\Property(property="total_pages", type="integer", example="1", description="Total pages"),
     *              @OA\Property(property="total_records", type="integer", example="1", description="Total records")
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
    public function index(Request $request, $uuid)
    {
        try {
            $account_id = auth()->user()->Id;

            $this->validate($request, [
                'page' => 'integer'
            ]);

            $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', $account_id)->first();

            if (!$subaccount) {
                return response()->json([
                    'message' => 'Subaccount not found or you do not have permission to access it'
                ], 404);
            }

            $page = request('page', 1);

            return response()->json(self::cards($account_id, $subaccount->Id, $page), 200);
        } catch (Exception $e) {
            return self::error('Error getting cards', 400, $e);
        }
    }

    public static function cards($account_id, $subaccount_id = null, $page = 1)
    {
        $limit = 10000;

        $cards = Card::where('CreatorId', $account_id);
        if ($subaccount_id) {
            $cards = $cards->where('SubaccountId', $subaccount_id);
        } else {
            $cards = $cards->whereNull('SubaccountId');
        }

        $count = $cards;

        $cards = $cards->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $cards_array = [];
        foreach ($cards as $card) {
            MainCardController::fixNonCustomerId($card, auth()->user()->prefix);

            array_push($cards_array, MainCardController::cardObject($card->UUID));
        }

        $count = $count->count();

        return [
            'cards' => $cards_array,
            'page' => $page,
            'total_pages' => ceil($count / $limit),
            'total_records' => $count
        ];
    }
}
