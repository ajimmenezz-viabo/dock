<?php

namespace App\Http\Controllers\Subaccounts;

use App\Http\Controllers\Card\MainCardController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Transfer\TransferController;
use App\Models\Account\Subaccount;
use Illuminate\Http\Request;
use App\Models\Card\Card;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet\AccountWallet;
use App\Models\Wallet\WalletMovement;
use App\Models\Shared\AuthorizationRequest;
use Ramsey\Uuid\Uuid;
use App\Models\CardMovements\CardMovements;
use Illuminate\Support\Facades\Log;

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

        $cards = Card::where('CreatorId', $account_id)->whereNotNull('Pan');
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
     * @OA\Post(
     *      path="/api/v1/subaccounts/{uuid}/fund_layout",
     *      tags={"Subaccount Cards"},
     *      summary="Fund cards for the subaccount specified through a layout",
     *      description="Funds cards for the subaccount specified through a layout",
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
     *      ),
     * 
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="layout",
     *                      description="Layout file",
     *                      type="file"
     *                  )
     *              )
     *         )
     *      ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Subaccount retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="subaccount_id", type="string", example="123456", description="Subaccount UUID"),
     *              @OA\Property(property="external_id", type="string", example="123456", description="Subaccount ExternalId"),
     *              @OA\Property(property="description", type="string", example="My subaccount", description="Subaccount Description"),
     *              @OA\Property(property="wallet", type="object",
     *                  @OA\Property(property="wallet_id", type="string", example="123456", description="Wallet UUID"),
     *                  @OA\Property(property="balance", type="string", example="0.00", description="Wallet Balance"),
     *                  @OA\Property(property="clabe", type="string", example="123456", description="Wallet CLABE"),
     *                  @OA\Property(property="last_movements", type="array", description="Last movements",  
     *                      @OA\Items(
     *                          @OA\Property(property="movement_id", type="string", example="123456", description="Movement UUID"),    
     *                          @OA\Property(property="card", type="object",
     *                          @OA\Property(property="card_id", type="string", example="123456", description="Card UUID"),     
     *                          @OA\Property(property="masked_pan", type="string", example="123456", description="Card Masked PAN"),
     *                       ),
     *                       @OA\Property(property="type", type="string", example="deposit", description="Movement Type"),
     *                       @OA\Property(property="description", type="string", example="Deposit", description="Movement Description"),
     *                       @OA\Property(property="amount", type="string", example="100.00", description="Movement Amount"),
     *                       @OA\Property(property="balance", type="string", example="100.00", description="Movement Balance"),
     *                       @OA\Property(property="date", type="string", example="1716611739", description="Movement Date / Unix Timestamp"),
     *                   )
     *              )
     *         )
     *     ),
     * 
     *      @OA\Response(
     *          response=400,
     *          description="Error funding cards | Error loading layout | Insufficient funds in the subaccount",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Error funding cards. Error message", description="Message")
     *          )
     *      ),
     *  )
     * )
     * 
     */

    public function fund_layout(Request $request, $uuid)
    {
        $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();
        $wallet = AccountWallet::where('AccountId', auth()->user()->Id)->where('SubAccountId', $subaccount->Id)->first();

        if (!$subaccount) {
            return self::error('Subaccount not found or you do not have permission to access it', 404, new Exception("Subaccount not found or you do not have permission to access it"));
        }

        $this->validate($request, [
            'layout' => 'required|file|mimes:xlsx,xls'
        ]);

        try {

            $spreadsheet = IOFactory::load($request->file('layout')->getPathname());
            $sheet = $spreadsheet->getSheet(0);

            $rows = $sheet->toArray();

            $actions = [];
            $total = 0;

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                $card = Card::where('UUID', $row[0])->where('SubAccountId', $subaccount->Id)->first();
                if (!$card) {
                    return self::error('Card ' . $row[0] . ' not found or you do not have permission to access it', 404, new Exception("Card " . $row[0] . " not found or you do not have permission to access it"));
                }

                $actions[] = [
                    'card' => $card,
                    'balance' => self::decrypt($card->Balance),
                    'amount' => floatval($row[3]),
                    'new_balance' => self::decrypt($card->Balance) + floatval($row[3])
                ];

                $total += floatval($row[3]);
            }

            if ($total > self::decrypt($wallet->Balance)) {
                return self::error('Insufficient funds in the subaccount', 400, new Exception("Insufficient funds in the subaccount"));
            }

            $resultActions = $this->callActions($wallet, $actions);

            if ($resultActions['result']) {
                return response()->json(SubaccountController::subaccountObject($subaccount->Id), 200);
            } else {
                return self::error('Error funding cards. ' . $resultActions['message'], 400, new Exception('Error funding cards. ' . $resultActions['message']));
            }
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error loading layout', 400, $e);
        }
    }

    /**
     *  @OA\Post(
     *      path="/api/v1/subaccounts/{uuid}/fund",
     *      tags={"Subaccount Cards"},
     *      summary="Fund cards for the subaccount specified",
     *      description="Funds cards for the subaccount specified",
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
     *      ),
     * 
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="cards", type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="card_id", type="string", example="123456", description="Card UUID"),
     *                      @OA\Property(property="amount", type="string", example="100.00", description="Amount to fund"),
     *                      @OA\Property(property="description", type="string", example="Funding", description="Description")
     *                  )
     *              )
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Subaccount retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="subaccount_id", type="string", example="123456", description="Subaccount UUID"),
     *              @OA\Property(property="external_id", type="string", example="123456", description="Subaccount ExternalId"),
     *              @OA\Property(property="description", type="string", example="My subaccount", description="Subaccount Description"),
     *              @OA\Property(property="wallet", type="object",
     *                  @OA\Property(property="wallet_id", type="string", example="123456", description="Wallet UUID"),
     *                  @OA\Property(property="balance", type="string", example="0.00", description="Wallet Balance"),
     *                  @OA\Property(property="clabe", type="string", example="123456", description="Wallet CLABE"),
     *                  @OA\Property(property="last_movements", type="array", description="Last movements",  
     *                      @OA\Items(
     *                          @OA\Property(property="movement_id", type="string", example="123456", description="Movement UUID"),    
     *                          @OA\Property(property="card", type="object",
     *                          @OA\Property(property="card_id", type="string", example="123456", description="Card UUID"),     
     *                          @OA\Property(property="masked_pan", type="string", example="123456", description="Card Masked PAN"),
     *                       ),
     *                       @OA\Property(property="type", type="string", example="deposit", description="Movement Type"),
     *                       @OA\Property(property="description", type="string", example="Deposit", description="Movement Description"),
     *                       @OA\Property(property="amount", type="string", example="100.00", description="Movement Amount"),
     *                       @OA\Property(property="balance", type="string", example="100.00", description="Movement Balance"),
     *                       @OA\Property(property="date", type="string", example="1716611739", description="Movement Date / Unix Timestamp"),
     *                  )
     *                )
     *          )
     * 
     *      )
     *      ),
     * 
     *      @OA\Response(
     *          response=400,
     *          description="Error funding cards | Insufficient funds in the subaccount",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Error funding cards. Error message", description="Message")
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
     *              @OA\Property(property="message", type="string", example="Subaccount not found or you do not have permission to access it", description="Message")
     *         )
     *      ),
     *  )
     * 
     */

    public function fund(Request $request, $uuid)
    {
        $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();
        $wallet = AccountWallet::where('AccountId', auth()->user()->Id)->where('SubAccountId', $subaccount->Id)->first();

        if (!$subaccount) {
            return self::error('Subaccount not found or you do not have permission to access it', 404, new Exception("Subaccount not found or you do not have permission to access it"));
        }

        // Log::info('Funding cards for subaccount ' . $subaccount->Id);
        // Log::info($request->cards);

        $this->validate($request, [
            'cards' => 'required|array',
        ]);

        try {
            $actions = [];
            $total = 0;

            foreach ($request->cards as $card) {
                $cardO = Card::where('UUID', $card['card_id'])->where('SubAccountId', $subaccount->Id)->first();
                if (!$cardO) {
                    return self::error('Card ' . $card['card_id'] . ' not found or you do not have permission to access it', 404, new Exception("Card " . $card['card_id'] . " not found or you do not have permission to access it"));
                }

                $actions[] = [
                    'card' => $cardO,
                    'balance' => self::decrypt($cardO->Balance),
                    'amount' => floatval($card['amount']),
                    'new_balance' => self::decrypt($cardO->Balance) + floatval($card['amount']),
                    'description' => $card['description'] ?? 'Funding'
                ];

                $total += floatval($card['amount']);
            }

            if ($total > self::decrypt($wallet->Balance)) {
                return self::error('Insufficient funds in the subaccount', 400, new Exception("Insufficient funds in the subaccount"));
            }

            $resultActions = $this->callActions($wallet, $actions);

            if ($resultActions['result']) {
                return response()->json(SubaccountController::subaccountObject($subaccount->Id), 200);
            } else {
                return self::error('Error funding cards. ' . $resultActions['message'], 400, new Exception('Error funding cards. ' . $resultActions['message']));
            }
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error funding cards', 400, $e);
        }
    }

    private function callActions($wallet, $actions)
    {
        try {
            DB::beginTransaction();

            $walletBalance = self::decrypt($wallet->Balance);

            foreach ($actions as $action) {

                $walletBalance = floatval($walletBalance) - floatval($action['amount']);

                WalletMovement::create([
                    'UUID' => Uuid::uuid7()->toString(),
                    'WalletId' => $wallet->Id,
                    'ApprovedBy' => auth()->user()->Id,
                    'CardId' => $action['card']->Id,
                    'Type' => 'Transfer Out',
                    'Description' => $action['description'] ?? 'Transfer to Card. Layout Movement',
                    'Amount' => floatval($action['amount'] * -1),
                    'Balance' => floatval($walletBalance),
                    'Reference' => null
                ]);

                $cardBalance = self::decrypt($action['card']->Balance);

                $newBalance = floatval($cardBalance) + floatval($action['amount']);

                $action['card']->Balance = self::encrypt($newBalance);
                $action['card']->save();

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
                    'CardExternalId' => $action['card']->ExternalId
                ]);

                CardMovements::create([
                    'UUID' => Uuid::uuid7()->toString(),
                    'CardId' => $action['card']->Id,
                    'AuthorizationRequestId' => $authorization->Id,
                    'Type' => 'TRANSFER',
                    'Description' => 'Transfer from Subaccount. ' . $action['description'] ?? 'Layout Movement',
                    'Amount' => floatval($action['amount']),
                    'Balance' => floatval($newBalance)
                ]);
            }

            $wallet->Balance = self::encrypt($walletBalance);
            $wallet->save();

            DB::commit();
            return [
                'result' => true
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'result' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
