<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Subaccounts\SubaccountCardController;
use Exception;

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
}
