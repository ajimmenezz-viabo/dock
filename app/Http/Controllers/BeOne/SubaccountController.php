<?php

namespace App\Http\Controllers\BeOne;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Subaccounts\SubaccountController as MainSubaccountController;
use App\Models\Account\Subaccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Carbon;
use App\Models\Wallet\AccountWallet;
use App\Http\Controllers\Wallet\WalletController;
use Exception;

class SubaccountController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/b1/subaccounts",
     *      tags={"BeOne Subaccounts"},
     *      summary="Get all BeOne subaccounts",
     *      description="Get all BeOne subaccounts",
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
     *                  )
     *              )
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
    public function index()
    {
        try {

            $subaccounts = Subaccount::where('AccountId', auth()->user()->Id)->get();

            $subaccounts_array = [];

            foreach ($subaccounts as $subaccount) {
                array_push($subaccounts_array, MainSubaccountController::subaccountObject($subaccount->Id, true));
            }

            return response()->json($subaccounts_array, 200);
        } catch (Exception $e) {
            return self::error('Error getting subaccounts', 400, $e);
        }
    }

    /**
     * @OA\Post(
     *      path="/api/b1/subaccount",
     *      tags={"BeOne Subaccounts"},
     *      summary="Create a new BeOne subaccount",
     *      description="Create a new BeOne subaccount",
     * 
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"Description"},
     *              @OA\Property(property="description", type="string", example="My subaccount")
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
     *     )
     * )
     * 
     */
    public function store(Request $request)
    {
        if (!isset($request['description']) || $request['description'] == "") return response()->json(['message' => 'Description is required'], 400);

        try {
            DB::beginTransaction();

            while (true) {
                $uuid = Uuid::uuid7()->toString();
                $exists = Subaccount::where('UUID', $uuid)->first();
                if (!$exists) break;
            }

            if (Subaccount::where('Description', $request['description'])->first()) return response()->json(['message' => 'Subaccount with this Description already exists'], 400);

            $subaccount = Subaccount::create([
                'UUID' => $uuid,
                'ExternalId' => "",
                'Description' => $request['description'],
                'AccountId' => auth()->user()->Id
            ]);

            MainSubaccountController::createWallet(auth()->user()->Id, $subaccount->Id);

            DB::commit();

            return response()->json(MainSubaccountController::subaccountObject($subaccount->Id, true), 200);
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error creating subaccount', 400, $e);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/b1/subaccount/{uuid}",
     *      tags={"BeOne Subaccounts"},
     *      summary="Get a specific BeOne subaccount",
     *      description="Get a specific BeOne subaccount by UUID",
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
     *         )
     *      ),
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
            $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();
            if (!$subaccount) return response()->json(['message' => 'Subaccount not found or you do not have permission to access it'], 404);

            return response()->json(MainSubaccountController::subaccountObject($subaccount->Id, true), 200);
        } catch (Exception $e) {
            return self::error('Error getting subaccount', 400, $e);
        }
    }

    /**
     *  @OA\Get(
     *      path="/api/b1/subaccount/{uuid}/balance",
     *      tags={"BeOne Subaccounts"},
     *      summary="Get the balance of a specific BeOne subaccount",
     *      description="Get the balance of a specific BeOne subaccount by UUID",
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
     *      ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Balance retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="balance", type="number", example="100.00", description="Subaccount balance")
     *          )
     *      )
     *   )
     */
    public function balance($uuid)
    {
        try {
            $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();
            if (!$subaccount) return response()->json(['message' => 'Subaccount not found or you do not have permission to access it'], 404);

            $subaccount_object = MainSubaccountController::subaccountObject($subaccount->Id);

            return response()->json(['balance' => $subaccount_object['wallet']['balance']], 200);
        } catch (Exception $e) {
            return self::error('Error getting subaccount balance', 400, $e);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/b1/subaccount/{uuid}/movements",
     *      tags={"BeOne Subaccounts"},
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
            $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();
            if (!$subaccount) return response()->json(['message' => 'Subaccount not found or you do not have permission to access it'], 404);

            $from = isset($request['from']) ? Carbon::createFromTimestamp($request->from) : Carbon::now()->subMonth();
            $to = isset($request['to']) ? Carbon::createFromTimestamp($request->to) : Carbon::now();

            if ($from > $to) return response()->json(['message' => 'Invalid date range'], 400);

            if (strtotime($to) - strtotime($from) > (2592000 * 3)) return response()->json(['message' => 'Date range exceeds 90 days'], 400);

            $wallet = AccountWallet::where('SubAccountId', $subaccount->Id)->first();
            $wallet = MainSubaccountController::fixNonSTPAccountWallet($wallet);

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
