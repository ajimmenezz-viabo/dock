<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Subaccounts\SubaccountCardController;
use App\Models\Account\Subaccount;
use App\Models\Card\Card;
use Exception;
use Illuminate\Support\Facades\DB;

class AccountCardController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/account/cards",
     *      tags={"Account Cards"},
     *      summary="Get all account cards",
     *      description="Returns all cards for the account associated with the token",
     *      security={{"bearerAuth":{}}},
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
    public function index(Request $request)
    {
        try {
            $account_id = auth()->user()->Id;

            $this->validate($request, [
                'page' => 'integer'
            ]);

            $page = request('page', 1);

            return response()->json(SubaccountCardController::cards($account_id, null, $page), 200);
        } catch (Exception $e) {
            return self::error('Error getting cards', 400, $e);
        }
    }

    /** 
     *  @OA\Post(
     *      path="/api/v1/account/cards/assign",
     *      tags={"Account Cards"},
     *      summary="Assign cards to a subaccount",
     *      description="Assigns a number of cards to a subaccount",
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="subaccount_id", type="string", example="123456", description="Subaccount UUID"),
     *              @OA\Property(property="card_type", type="string", example="virtual", description="Card Type"),
     *              @OA\Property(property="quantity", type="integer", example="1", description="Number of cards to assign")  
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response="200",  
     *          description="Cards assigned successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="cards", type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="card_id", type="string", example="123456", description="Card UUID"),
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
     *         )
     *     ),
     * 
     *     @OA\Response(
     *          response=400,
     *          description="Error assigning card",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Error assigning card", description="Message")
     *          )
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
     *          description="Subaccount not found or you do not have permission to access it",
     *          @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Not enough cards available | Subaccount not found or you do not have permission to access it", description="Message")
     *          )
     *      )
     * )
     * 
    */
    public function assign(Request $request)
    {
        try {
            DB::beginTransaction();
            $account_id = auth()->user()->Id;

            $this->validate($request, [
                'subaccount_id' => 'required|string',
                'card_type' => 'required|string|in:virtual,physical',
                'quantity' => 'required|integer'
            ]);

            if (self::assign_cards_to_subaccount($account_id, $request->subaccount_id, $request->card_type, $request->quantity)) {
                DB::commit();
                return response()->json(SubaccountCardController::cards($account_id, null, 1), 200);
            }
        } catch (Exception $e) {
            return self::error('Error assigning card. ' . $e->getMessage(), 400, $e);
        }
    }



    public static function assign_cards_to_subaccount($account_id, $subaccount_id, $card_type, $quantity)
    {
        $subaccount = Subaccount::where('UUID', $subaccount_id)->where('AccountId', $account_id)->first();
        if (!$subaccount) {
            abort(404, 'Subaccount not found or you do not have permission to access it');
        }

        $count_available_cards = Card::where('CreatorId', $account_id)->whereNull('SubaccountId')->where('Type', $card_type)->count();
        if ($count_available_cards < $quantity) {
            abort(400, 'Not enough ' . $card_type . ' cards available');
        }

        $cards = Card::where('CreatorId', $account_id)->whereNull('SubaccountId')->where('Type', $card_type)->limit($quantity)->get();
        foreach ($cards as $card) {
            $card->SubaccountId = $subaccount->Id;
            $card->save();
        }

        return true;
    }
}
