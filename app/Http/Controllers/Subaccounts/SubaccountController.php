<?php

namespace App\Http\Controllers\Subaccounts;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Wallet\WalletController;
use App\Models\Account\Subaccount;
use App\Models\Shared\AvailableSTPAccount;
use App\Models\Wallet\AccountWallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;

use Exception;

class SubaccountController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/subaccounts",
     *      tags={"Subaccounts"},
     *      summary="Get all subaccounts for the authenticated account user",
     *      description="Get all subaccounts for the authenticated account user",
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Subaccounts retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="subaccounts", type="array", 
     *                  @OA\Items(
     *                      @OA\Property(property="subaccount_id", type="string", example="123456", description="Subaccount UUID"),
     *                      @OA\Property(property="external_id", type="string", example="123456", description="Subaccount ExternalId"),
     *                      @OA\Property(property="description", type="string", example="My subaccount", description="Subaccount Description"),
     *                      @OA\Property(property="wallet", type="object",
     *                          @OA\Property(property="wallet_id", type="string", example="123456", description="Wallet UUID"),
     *                          @OA\Property(property="balance", type="string", example="0.00", description="Wallet Balance"),
     *                          @OA\Property(property="clabe", type="string", example="123456", description="Wallet CLABE"),
     *                          @OA\Property(property="last_movements", type="array", description="Last movements",  
     *                              @OA\Items(
     *                                  @OA\Property(property="movement_id", type="string", example="123456", description="Movement UUID"),    
     *                                  @OA\Property(property="card", type="object",
     *                                      @OA\Property(property="card_id", type="string", example="123456", description="Card UUID"),     
     *                                      @OA\Property(property="masked_pan", type="string", example="123456", description="Card Masked PAN"),
     *                                  ),
     *                                  @OA\Property(property="type", type="string", example="deposit", description="Movement Type"),
     *                                  @OA\Property(property="description", type="string", example="Deposit", description="Movement Description"),
     *                                  @OA\Property(property="amount", type="string", example="100.00", description="Movement Amount"),
     *                                  @OA\Property(property="balance", type="string", example="100.00", description="Movement Balance"),
     *                                  @OA\Property(property="date", type="string", example="1716611739", description="Movement Date / Unix Timestamp"),
     *                             )
     *                        )
     *                  ),
     *                  @OA\Property(property="page", type="integer", example="1", description="Current page"),
     *                  @OA\Property(property="total_pages", type="integer", example="1", description="Total pages"),
     *                  @OA\Property(property="total_records", type="integer", example="1", description="Total records")
     *              )
     *         )
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
            $page = $request['page'] ?? 1;
            $limit = 100;
            $offset = ($page - 1) * $limit;

            $subaccounts = Subaccount::join('users', 'users.Id', '=', 'subaccounts.AccountId')
                ->select('subaccounts.Id')
                ->where('users.Id', auth()->user()->Id)
                ->offset($offset)
                ->limit($limit)
                ->get();

            $count = Subaccount::join('users', 'users.Id', '=', 'subaccounts.AccountId')->where('users.Id', auth()->user()->Id)->count();

            $subaccounts_array = [];

            foreach ($subaccounts as $subaccount) {
                array_push($subaccounts_array, self::subaccountObject($subaccount->Id));
            }

            return response()->json([
                'subaccounts' => $subaccounts_array,
                'page' => $page,
                'total_pages' => ceil($count / $limit),
                'total_records' => $count
            ], 200);
        } catch (Exception $e) {
            return self::error('Error getting subaccounts', 400, $e);
        }
    }

    /**
     * @OA\Post(
     *      path="/api/v1/subaccounts",
     *      tags={"Subaccounts"},
     *      summary="Create a new subaccount",
     *      description="Create a new subaccount",
     * 
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"ExternalId", "Description"},
     *              @OA\Property(property="ExternalId", type="string", example="123456"),
     *              @OA\Property(property="Description", type="string", example="My subaccount")
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response="200", 
     *          description="Subaccount created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="subaccount_id", type="string", example="123456", description="Subaccount UUID"),
     *              @OA\Property(property="external_id", type="string", example="123456", description="Subaccount ExternalId"),
     *              @OA\Property(property="description", type="string", example="My subaccount", description="Subaccount Description"),
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *         )
     *     ),
     *     @OA\Response(
     *          response=400,
     *          description="Subaccount with this ExternalId already exists | Subaccount with this Description already exists | Error creating subaccount",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Subaccount with this ExternalId already exists | Subaccount with this Description already exists | Error creating subaccount", description="Message")
     *          )
     *      )
     * )
     * 
     */
    public function store(Request $request)
    {
        $this->validateSubaccountData($request);

        try {
            DB::beginTransaction();

            while (true) {
                $uuid = Uuid::uuid7()->toString();
                $exists = Subaccount::where('UUID', $uuid)->first();
                if (!$exists) break;
            }

            if (Subaccount::where('ExternalId', $request['ExternalId'])->first()) return response()->json(['message' => 'Subaccount with this ExternalId already exists'], 400);

            if (Subaccount::where('Description', $request['Description'])->first()) return response()->json(['message' => 'Subaccount with this Description already exists'], 400);

            $subaccount = Subaccount::create([
                'UUID' => $uuid,
                'ExternalId' => $request['ExternalId'],
                'Description' => $request['Description'],
                'AccountId' => auth()->user()->Id
            ]);

            self::createWallet(auth()->user()->Id, $subaccount->Id);

            DB::commit();

            return response()->json(self::subaccountObject($subaccount->Id, true), 200);
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error creating subaccount', 400, $e);
        }
    }

    public function update(Request $request, $uuid)
    {
        $this->validateSubaccountData($request);

        try {
            DB::beginTransaction();

            $subaccount = $this->validateSubaccountPermission($uuid);
            if (!$subaccount) return response()->json(['message' => 'Subaccount not found or you do not have permission to access it'], 404);

            if (Subaccount::where('ExternalId', $request['ExternalId'])->where('Id', '!=', $subaccount->Id)->first()) return response()->json(['message' => 'Subaccount with this ExternalId already exists'], 400);

            if (Subaccount::where('Description', $request['Description'])->where('Id', '!=', $subaccount->Id)->first()) return response()->json(['message' => 'Subaccount with this Description already exists'], 400);

            Subaccount::where('Id', $subaccount->Id)->update([
                'ExternalId' => $request['ExternalId'],
                'Description' => $request['Description']
            ]);

            DB::commit();

            return response()->json(self::subaccountObject($subaccount->Id), 200);
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error updating subaccount', 400, $e);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/v1/subaccounts/{uuid}",
     *      tags={"Subaccounts"},
     *      summary="Get a specific subaccount",
     *      description="Get a specific subaccount",
     * 
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          required=true,
     *          description="Subaccount UUID",
     *              @OA\Schema( 
     *                  type="string",
     *                  example="123456"
     *              )
     *       ),
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
     *    ),
     * 
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *          )
     *     ),
     *      
     *     @OA\Response(
     *          response=404,
     *          description="Subaccount not found or you do not have permission to access it",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Subaccount not found or you do not have permission to access it", description="Message")
     *          )
     *      )
     * )
     *    
     * )
     */
    public function show($uuid)
    {
        try {
            $subaccount = $this->validateSubaccountPermission($uuid);
            if (!$subaccount) return response()->json(['message' => 'Subaccount not found or you do not have permission to access it'], 404);

            return response()->json(self::subaccountObject($subaccount->Id), 200);
        } catch (Exception $e) {
            return self::error('Error getting subaccount', 400, $e);
        }
    }

    public static function createWallet($account_id, $subaccount_id)
    {
        while (true) {
            $uuid = Uuid::uuid7()->toString();
            $exists = AccountWallet::where('UUID', $uuid)->first();
            if (!$exists) break;
        }

        $accountWallet = AccountWallet::create([
            'UUID' => $uuid,
            'AccountId' => $account_id,
            'SubAccountId' => $subaccount_id,
            'Balance' => self::encrypt('0.00')
        ]);

        self::fixNonSTPAccountWallet($accountWallet);
    }

    public static function fixNonSTPAccountWallet($accountWallet)
    {
        $availableSTPAccount = AvailableSTPAccount::where('Available', true)->first();

        if ($availableSTPAccount && $accountWallet->STPAccount == null) {
            AccountWallet::where('Id', $accountWallet->Id)->update(['STPAccount' => $availableSTPAccount->STPAccount]);
            AvailableSTPAccount::where('STPAccount', $availableSTPAccount->STPAccount)->update(['Available' => false]);
        }

        return AccountWallet::where('Id', $accountWallet->Id)->first();
    }

    public static function fixNonSubaccountWallet($account_id, $subaccount_id)
    {
        $subaccountWallet = AccountWallet::where('AccountId', $account_id)
            ->where('SubAccountId', $subaccount_id)
            ->first();

        if (!$subaccountWallet) {
            while (true) {
                $uuid = Uuid::uuid7()->toString();
                $exists = AccountWallet::where('UUID', $uuid)->first();
                if (!$exists) break;
            }

            $subaccountWallet = AccountWallet::create([
                'UUID' => $uuid,
                'AccountId' => $account_id,
                'SubAccountId' => $subaccount_id,
                'Balance' => self::encrypt('0.00')
            ]);
        }

        return self::fixNonSTPAccountWallet($subaccountWallet);
    }

    public static function subaccountObject($subaccount_id, $lite = false)
    {
        $subaccount = Subaccount::where('Id', $subaccount_id)->first();
        $wallet = self::fixNonSubaccountWallet($subaccount->AccountId, $subaccount->Id);

        $object = [
            'subaccount_id' => $subaccount->UUID,
            'external_id' => $subaccount->ExternalId,
            'description' => $subaccount->Description,

        ];

        if (!$lite) {
            $object['wallet'] = [
                'wallet_id' => $wallet->UUID,
                'balance' => self::decrypt($wallet->Balance),
                'clabe' => $wallet->STPAccount,
                'last_movements' => WalletController::last_movements($wallet->Id)
            ];
        }

        return $object;
    }

    private function validateSubaccountData($request)
    {
        $this->validate($request, [
            'ExternalId' => 'required|string',
            'Description' => 'required|string'
        ]);
    }

    private function validateSubaccountPermission($uuid)
    {
        return Subaccount::where('UUID', $uuid)
            ->where('AccountId', auth()->user()->Id)
            ->first();
    }


    /**
     * @OA\Get(
     *      path="/api/v1/subaccounts/{uuid}/movements",
     *      tags={"Subaccounts"},
     *      summary="Get all movements for a specific subaccount",
     *      description="Get all movements for a specific subaccount",
     *      
     *      security={{"bearerAuth":{}}},
     * 
     *     @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          required=true,
     *          description="Subaccount UUID",
     *          @OA\Schema(
     *              type="string",
     *              example="123456"
     *          )
     *      ),
     * 
     *     @OA\RequestBody(
     *         required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="from", type="string", example="1234567890", description="From date (Unix Timestamp)"),
     *              @OA\Property(property="to", type="string", example="1234567890", description="To date (Unix Timestamp)")
     *          )
     *     ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Movements retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="movements", type="array", description="Movements",
     *                  @OA\Items(
     *                      @OA\Property(property="movement_id", type="string", example="123456", description="Movement UUID"),
     *                      @OA\Property(property="date", type="string", example="1716611739", description="Movement Date / Unix Timestamp"),
     *                      @OA\Property(property="type", type="string", example="deposit", description="Movement Type"),
     *                      @OA\Property(property="amount", type="string", example="100.00", description="Movement Amount"),
     *                      @OA\Property(property="authorization_code", type="string", example="123456", description="Authorization Code"),
     *                      @OA\Property(property="description", type="string", example="Deposit", description="Movement Description"),
     *                 )
     *              ),
     *              @OA\Property(property="total_records", type="integer", example="1", description="Total records"),
     *              @OA\Property(property="from", type="string", example="1234567890", description="From date (Unix Timestamp)"),
     *              @OA\Property(property="to", type="string", example="1234567890", description="To date (Unix Timestamp)")
     *          )
     *     ),
     *  )
     */
    public function movements($uuid, Request $request)
    {
        try {
            $subaccount = $this->validateSubaccountPermission($uuid);

            if (!$subaccount) return response()->json(['message' => 'Subaccount not found or you do not have permission to access it'], 404);

            $from = isset($request['from']) ? Carbon::createFromTimestamp($request->from) : Carbon::now()->subMonth();
            $to = isset($request['to']) ? Carbon::createFromTimestamp($request->to) : Carbon::now();

            if ($from > $to) return response()->json(['message' => 'Invalid date range'], 400);

            if (strtotime($to) - strtotime($from) > (2592000 * 3)) return response()->json(['message' => 'Date range exceeds 90 days'], 400);

            $wallet = AccountWallet::where('SubAccountId', $subaccount->Id)->first();
            $wallet = $this->fixNonSTPAccountWallet($wallet);

            $movements = WalletController::movements($wallet->Id, $from, $to);

            return response()->json([
                'movements' => $movements['movements'],
                'total_records' => $movements['count'],
                'from' => $from->timestamp,
                'to' => $to->timestamp
            ], 200);
        } catch (Exception $e) {
            return self::error('Error getting subaccount movements', 400, $e);
        }
    }
}
