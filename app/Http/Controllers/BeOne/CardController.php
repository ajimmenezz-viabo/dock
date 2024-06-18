<?php

namespace App\Http\Controllers\BeOne;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Subaccounts\SubaccountController as MainSubaccountController;
use App\Http\Controllers\Card\MainCardController;
use App\Http\Controllers\Transfer\TransferController;
use App\Http\Controllers\Wallet\WalletController;
use App\Http\Controllers\CardMovements\CardMovementController;

use App\Models\Card\Card;
use App\Models\CardMovements\CardMovements;
use App\Models\Account\Subaccount;
use App\Models\Wallet\AccountWallet;
use App\Models\Wallet\WalletMovement;
use App\Models\Shared\AuthorizationRequest;
use App\Models\CardSetups\CardSetups;
use App\Models\CardSetups\CardSetupsChange;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\DockApiService;

use Ramsey\Uuid\Uuid;

use Exception;

class CardController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/b1/subaccount/{uuid}/cards",
     *      tags={"BeOne Cards"},
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
     *          response=404,
     *          description="Subaccount not found or you do not have permission to access it",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Subaccount not found or you do not have permission to access it", description="Message")
     *         )
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
        $this->validate($request, [
            'page' => 'integer'
        ]);

        try {
            $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();

            if (!$subaccount) {
                return response()->json([
                    'message' => 'Subaccount not found or you do not have permission to access it'
                ], 404);
            }

            return response()->json(self::cards($subaccount->Id, $request->page ?? 1), 200);
        } catch (Exception $e) {
            return self::error('Error getting cards', 400, $e);
        }
    }

    public static function cards($subaccount_id, $page)
    {
        $limit = 500;

        $cards = Card::where('CreatorId', auth()->user()->Id)->where('SubAccountId', $subaccount_id);

        $count = $cards;

        $cards = $cards->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $cards_array = [];
        foreach ($cards as $card) {
            MainCardController::fixNonCustomerId($card);
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

    /** 
     *  @OA\Get(
     *      path="/api/b1/card/{bin}",
     *      tags={"BeOne Cards"}, 
     *      summary="Get card by bin",
     *      description="Returns card by bin",
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\Parameter(
     *          name="bin",
     *          in="path",
     *          description="Card BIN",
     *          required=true,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Card retrieved successfully",
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
     *          response=404,
     *          description="Card not found or you do not have permission to access it",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Card not found", description="Message")
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *         )
     *      )
     * )
     * 
     */

    public function show($bin)
    {
        try {
            $card = self::card_by_bin($bin);

            if ($card) {
                return response()->json(MainCardController::cardObject($card->UUID), 200);
            } else {
                return response()->json([
                    'message' => 'Card not found or you do not have permission to access it'
                ], 404);
            }
        } catch (Exception $e) {
            return self::error('Error getting card', 400, $e);
        }
    }

    /** 
     *  @OA\Get(
     *      path="/api/b1/card/{bin}/balance",
     *      tags={"BeOne Cards"}, 
     *      summary="Get card balance by bin",
     *      description="Returns card balance by bin",
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\Parameter(
     *          name="bin",
     *          in="path",
     *          description="Card BIN",
     *          required=true,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Card retrieved successfully",
     *          @OA\JsonContent(
     *             @OA\Property(property="balance", type="string", example="100.00", description="Card Balance")
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response=404,
     *          description="Card not found or you do not have permission to access it",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Card not found", description="Message")
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *         )
     *      )
     * )
     * 
     */

    public function balance($bin)
    {
        try {
            $card = self::card_by_bin($bin);

            if ($card) {
                return response()->json([
                    'balance' => number_format(self::decrypt($card->Balance), 2, '.', '')
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Card not found or you do not have permission to access it'
                ], 404);
            }
        } catch (Exception $e) {
            return self::error('Error getting card balance', 400, $e);
        }
    }

    /**
     *  @OA\Get(
     *      path="/api/b1/card/{bin}/movements",
     *      tags={"BeOne Cards"},       
     *      summary="Get card movements",
     *      description="Returns card movements",
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\Parameter(
     *          name="bin",
     *          in="path",
     *          description="Card bin",
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
    public function movements(Request $request, $bin)
    {
        $card = self::card_by_bin($bin);

        if ($card) {
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
        } else {
            return response()->json([
                'message' => 'Card not found or you do not have permission to access it'
            ], 404);
        }
    }

    /**
     *      @OA\Post(
     *          path="/api/b1/card/{bin}/deposit", 
     *          tags={"BeOne Cards"},
     *          summary="Deposit to card",
     *          description="Deposit to card",
     *          security={{"bearerAuth":{}}},
     * 
     *          @OA\Parameter(
     *              name="bin",
     *              in="path",
     *              description="Card BIN",
     *              required=true,
     *              @OA\Schema(
     *                  type="string"
     *              )
     *          ),
     * 
     *          @OA\RequestBody(
     *              required=true,
     *              @OA\JsonContent(
     *                  @OA\Property(property="amount", type="string", example="100.00", description="Amount to deposit"),
     *                  @OA\Property(property="description", type="string", example="Deposit", description="Deposit description")
     *              )
     *          ),
     * 
     *          @OA\Response(
     *              response="200",
     *              description="Deposit applied successfully",
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="Deposit applied successfully", description="Message")
     *              )
     *          ),
     * 
     *          @OA\Response(
     *              response=404,
     *              description="Card not found or you do not have permission to access it",
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="Card not found or you do not have permission to access it", description="Message")
     *              )
     *          ),
     * 
     *          @OA\Response(
     *              response=400,
     *              description="Error applying deposit to card",
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="Error applying deposit to card", description="Message")
     *              )
     *          ),
     * 
     *          @OA\Response(
     *              response=401,
     *              description="Unauthorized",
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *              )
     *          )
     *     )    
     */

    public function deposit(Request $request, $bin)
    {
        $this->validate($request, [
            'amount' => 'required|numeric',
            'description' => 'string|max:255|required'
        ]);

        try {
            $card = self::card_by_bin($bin);
            if ($card) {
                $deposit = self::transfer('deposit', $card, $request->amount, $request->description);
                if ($deposit == '') {
                    return response()->json([
                        'message' => 'Deposit applied successfully'
                    ], 200);
                } else {
                    return response()->json([
                        'message' => $deposit
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => 'Card not found or you do not have permission to access it'
                ], 404);
            }
        } catch (Exception $e) {
            return self::error('Error applying deposit to card', 400, $e);
        }
    }

    /**
     *      @OA\Post(
     *          path="/api/b1/card/{bin}/reverse", 
     *          tags={"BeOne Cards"},
     *          summary="Reverse from card to subaccount",
     *          description="Reverse from card to subaccount",
     *          security={{"bearerAuth":{}}},
     * 
     *          @OA\Parameter(
     *              name="bin",
     *              in="path",
     *              description="Card BIN",
     *              required=true,
     *              @OA\Schema(
     *                  type="string"
     *              )
     *          ),
     * 
     *          @OA\RequestBody(
     *              required=true,
     *              @OA\JsonContent(
     *                  @OA\Property(property="amount", type="string", example="100.00", description="Amount to reverse"),
     *                  @OA\Property(property="description", type="string", example="Reverse", description="Reverse description")
     *              )
     *          ),
     * 
     *          @OA\Response(
     *              response="200",
     *              description="Reverse applied successfully",
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="Reverse applied successfully", description="Message")
     *              )
     *          ),
     * 
     *          @OA\Response(
     *              response=404,
     *              description="Card not found or you do not have permission to access it",
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="Card not found or you do not have permission to access it", description="Message")
     *              )
     *          ),
     * 
     *          @OA\Response(
     *              response=400,
     *              description="Error applying reverse to card",
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="Error applying reverse to card", description="Message")
     *              )
     *          ),
     * 
     *          @OA\Response(
     *              response=401,
     *              description="Unauthorized",
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *              )
     *          )
     *     )    
     */

    public function reverse(Request $request, $bin)
    {
        $this->validate($request, [
            'amount' => 'required|numeric',
            'description' => 'string|max:255|required'
        ]);

        try {
            $card = self::card_by_bin($bin);
            if ($card) {
                $reverse = self::transfer('reverse', $card, $request->amount, $request->description);
                if ($reverse == '') {
                    return response()->json([
                        'message' => 'Reverse applied successfully'
                    ], 200);
                } else {
                    return response()->json([
                        'message' => $reverse
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => 'Card not found or you do not have permission to access it'
                ], 404);
            }
        } catch (Exception $e) {
            return self::error('Error getting card balance', 400, $e);
        }
    }

    /**
     *   @OA\Post(
     *       path="/api/b1/card/{bin}/block",
     *       summary="Block a card by bin",
     *       description="Block a card by bin.",
     *       tags={"BeOne Cards"},
     *       security={{"bearerAuth": {}}},
     *       @OA\Parameter(
     *           name="bin",
     *           in="path",
     *           description="Card bin",
     *           required=true,
     *           @OA\Schema(
     *               type="string"
     *           )
     *       ),
     *       @OA\Response(
     *           response=200,
     *           description="Card blocked successfully",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Card blocked successfully", description="Success message"),
     *               @OA\Property(property="card",type="object",description="Card object",
     *                   @OA\Property(property="card_id",type="string",example="f4b3b3b3-4b3b-4b3b-4b3b-4b3b4b3b4b3b",description="Card UUID"),
     *                   @OA\Property(property="card_type",type="string",example="virtual",description="Card type"),
     *                   @OA\Property(property="brand",type="string",example="Mastercard",description="Card active function"),
     *                   @OA\Property(property="bin",type="string",example="98765437",description="Card BIN"),
     *                   @OA\Property(property="balance",type="string",example="0.00",description="Card balance"),
     *                   @OA\Property(property="clabe",type="string",example="0123456789",description="Card CLABE"),
     *                   @OA\Property(property="status",type="string",example="BLOCKED",description="Card status"),
     *               )
     *           )
     *       ),
     *       @OA\Response(
     *           response=404,
     *           description="Card not found or you do not have permission to access it",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Card not found or you do not have permission to access it", description="Error message")
     *           )
     *       ),
     *       @OA\Response(
     *           response=400,
     *           description="Error blocking card",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Error blocking card", description="Error message")
     *           )
     *       ),
     *       @OA\Response(
     *           response=401,
     *           description="Unauthorized",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Unauthorized")
     *           )
     *       )
     *   )
     *
     */
    public function block($bin)
    {
        try {
            $card = self::card_by_bin($bin);

            if ($card) {
                $dockRaw = [
                    'status' => 'BLOCKED',
                    'status_reason' => 'OWNER_REQUEST'
                ];

                $response = DockApiService::request(
                    ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/status',
                    'PUT',
                    [],
                    [],
                    'bearer',
                    $dockRaw
                );

                DB::beginTransaction();

                $card_setup = CardSetups::where('CardId', $card->Id)->first();

                CardSetupsChange::create([
                    'UserId' => auth()->user()->Id,
                    'CardId' => $card->Id,
                    'Field' => 'Status',
                    'OldValue' => $card_setup->Status,
                    'NewValue' => $response->status,
                    'StatusReason' => $response->status_reason
                ]);

                CardSetups::where('CardId', $card->Id)->update([
                    'Status' => $response->status,
                    'StatusReason' => $response->status_reason
                ]);

                DB::commit();

                return response()->json(['message' => 'Card blocked successfully', 'card' => MainCardController::cardObject($card->UUID)], 200);
            } else {
                return response()->json([
                    'message' => 'Card not found or you do not have permission to access it'
                ], 404);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error blocking card', 400, $e);
        }
    }


    /**
     *   @OA\Post(
     *       path="/api/b1/card/{bin}/unblock",
     *       summary="Unblock a card by bin",
     *       description="Unblock a card by bin.",
     *       tags={"BeOne Cards"},
     *       security={{"bearerAuth": {}}},
     *       @OA\Parameter(
     *           name="bin",
     *           in="path",
     *           description="Card bin",
     *           required=true,
     *           @OA\Schema(
     *               type="string"
     *           )
     *       ),
     *       @OA\Response(
     *           response=200,
     *           description="Card unblocked successfully",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Card blocked successfully", description="Success message"),
     *               @OA\Property(property="card",type="object",description="Card object",
     *                   @OA\Property(property="card_id",type="string",example="f4b3b3b3-4b3b-4b3b-4b3b-4b3b4b3b4b3b",description="Card UUID"),
     *                   @OA\Property(property="card_type",type="string",example="virtual",description="Card type"),
     *                   @OA\Property(property="brand",type="string",example="Mastercard",description="Card active function"),
     *                   @OA\Property(property="bin",type="string",example="98765437",description="Card BIN"),
     *                   @OA\Property(property="balance",type="string",example="0.00",description="Card balance"),
     *                   @OA\Property(property="clabe",type="string",example="0123456789",description="Card CLABE"),
     *                   @OA\Property(property="status",type="string",example="BLOCKED",description="Card status"),
     *               )
     *           )
     *       ),
     *       @OA\Response(
     *           response=404,
     *           description="Card not found or you do not have permission to access it",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Card not found or you do not have permission to access it", description="Error message")
     *           )
     *       ),
     *       @OA\Response(
     *           response=400,
     *           description="Error blocking card",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Error blocking card", description="Error message")
     *           )
     *       ),
     *       @OA\Response(
     *           response=401,
     *           description="Unauthorized",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Unauthorized")
     *           )
     *       )
     *   )
     *
     */
    public function unblock($bin)
    {
        try {
            $card = self::card_by_bin($bin);

            if ($card) {
                $dockRaw = [
                    'status' => 'NORMAL',
                    'status_reason' => null
                ];

                $response = DockApiService::request(
                    ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/status',
                    'PUT',
                    [],
                    [],
                    'bearer',
                    $dockRaw
                );

                DB::beginTransaction();

                $card_setup = CardSetups::where('CardId', $card->Id)->first();

                CardSetupsChange::create([
                    'UserId' => auth()->user()->Id,
                    'CardId' => $card->Id,
                    'Field' => 'Status',
                    'OldValue' => $card_setup->Status,
                    'NewValue' => $response->status,
                    'StatusReason' => $response->status_reason
                ]);

                CardSetups::where('CardId', $card->Id)->update([
                    'Status' => $response->status,
                    'StatusReason' => $response->status_reason
                ]);

                DB::commit();

                return response()->json(['message' => 'Card unblocked successfully', 'card' => MainCardController::cardObject($card->UUID)], 200);
            } else {
                return response()->json([
                    'message' => 'Card not found or you do not have permission to access it'
                ], 404);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error blocking card', 400, $e);
        }
    }

    private function card_by_bin($bin)
    {
        $last4 = substr($bin, -4);
        $cards = Card::where('MaskedPan', 'LIKE', '%' . $last4)
            ->where('CreatorId', auth()->user()->Id)
            ->get();
        if ($cards->count() == 0) {
            return null;
        } else {
            foreach ($cards as $card) {
                $pan = self::decrypt($card->Pan);
                if (substr($pan, -8) == $bin) {
                    return $card;
                }
            }
        }

        return null;
    }

    public static function transfer($type, $card, $amount, $description)
    {
        try {
            DB::beginTransaction();
            $amount = abs($amount);
            $cardBalance = self::decrypt($card->Balance);
            $subaccount = MainSubaccountController::subaccountObject($card->SubAccountId);
            switch ($type) {
                case 'deposit':
                    if (floatval($subaccount['wallet']['balance']) < floatval($amount)) {
                        return "Insufficient funds";
                    } else {
                        $origin = self::registerSubaccountTransaction($subaccount['subaccount_id'], $amount * -1, $description, $card);
                        $destination = self::registerCardTransaction($card, $amount, $description);
                    }
                    break;
                case 'reverse':
                    if (floatval($cardBalance) < floatval($amount)) {
                        return "Insufficient funds";
                    } else {
                        $origin = self::registerCardTransaction($card, $amount * -1, $description);
                        $destination = self::registerSubaccountTransaction($subaccount['subaccount_id'], $amount, $description, $card);
                    }
                    break;
            }

            DB::commit();

            return '';
        } catch (Exception $e) {
            DB::rollBack();
            return $e->getMessage();
        }
    }

    public static function registerSubaccountTransaction($uuid, $amount, $description, $card)
    {
        $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();

        $wallet = AccountWallet::where('AccountId', auth()->user()->Id)->where('SubAccountId', $subaccount->Id)->first();
        if (!$wallet) {
            $wallet = AccountWallet::create([
                'UUID' => Uuid::uuid7()->toString(),
                'AccountId' => auth()->user()->Id,
                'SubAccountId' => $subaccount->Id,
                'Balance' => self::encrypt('0.00')
            ]);
        }

        $balance = self::decrypt($wallet->Balance);

        $newBalance = floatval($balance) + floatval($amount);

        $wallet->Balance = self::encrypt($newBalance);
        $wallet->save();

        $movement = WalletMovement::create([
            'UUID' => Uuid::uuid7()->toString(),
            'WalletId' => $wallet->Id,
            'ApprovedBy' => auth()->user()->Id,
            'Type' => ($amount < 0 ? 'Transfer Out' : 'Transfer In'),
            'Description' => $description,
            'Amount' => floatval($amount),
            'Balance' => floatval($newBalance),
            'Reference' => null,
            'CardId' => $card->Id
        ]);

        return [
            'movement' => WalletController::movement_object($movement),
            'balance' => $newBalance
        ];
    }

    public static function registerCardTransaction($card, $amount, $description)
    {
        $cardBalance = self::decrypt($card->Balance);
        $newBalance = floatval($cardBalance) + floatval($amount);

        $card->Balance = self::encrypt($newBalance);
        $card->save();

        $authorization = AuthorizationRequest::create([
            'UUID' => Uuid::uuid7()->toString(),
            'ExternalId' => '',
            'AuthorizationCode' => TransferController::getAuthorizationCode('TR'),
            'Endpoint' => 'transfer',
            'Headers' => '',
            'Body' => '',
            'Response' => '',
            'Error' => '',
            'Code' => 200,
            'CardExternalId' => $card->ExternalId
        ]);

        $movement = CardMovements::create([
            'UUID' => Uuid::uuid7()->toString(),
            'CardId' => $card->Id,
            'AuthorizationRequestId' => $authorization->Id,
            'Type' => 'TRANSFER',
            'Description' => $description,
            'Amount' => floatval($amount),
            'Balance' => floatval($newBalance)
        ]);

        return [
            'movement' => WalletController::movement_object($movement),
            'balance' => $newBalance
        ];
    }
}
