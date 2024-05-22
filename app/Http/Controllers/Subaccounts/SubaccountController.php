<?php

namespace App\Http\Controllers\Subaccounts;

use App\Http\Controllers\Controller;
use App\Models\Account\Subaccount;
use App\Models\Shared\AvailableSTPAccount;
use App\Models\Wallet\AccountWallet;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;

use Exception;

/**
 * @OA\Info(title="API Documentation", version="1.0")
 */

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
     *                      @OA\Property(property="UUID", type="string", example="123456", description="Subaccount UUID"),
     *                      @OA\Property(property="ExternalId", type="string", example="123456", description="Subaccount ExternalId"),
     *                      @OA\Property(property="Description", type="string", example="My subaccount", description="Subaccount Description"),
     *                      @OA\Property(property="Balance", type="string", example="0.00", description="Subaccount Balance"),
     *                      @OA\Property(property="STPAccount", type="string", example="123456", description="STPAccount associated with the subaccount")
     *                  ),
     *                  @OA\Property(property="page", type="integer", example="1", description="Current page"),
     *                  @OA\Property(property="total_pages", type="integer", example="1", description="Total pages"),
     *                  @OA\Property(property="total_records", type="integer", example="1", description="Total records")
     *              )
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
                array_push($subaccounts_array, $this->subaccountObject($subaccount->Id));
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
     *              @OA\Property(property="UUID", type="string", example="123456", description="Subaccount UUID"),
     *              @OA\Property(property="ExternalId", type="string", example="123456", description="Subaccount ExternalId"),
     *              @OA\Property(property="Description", type="string", example="My subaccount", description="Subaccount Description"),
     *              @OA\Property(property="Balance", type="string", example="0.00", description="Subaccount Balance"),
     *              @OA\Property(property="STPAccount", type="string", example="123456", description="STPAccount associated with the subaccount")
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

            $this->createWallet($subaccount->Id);

            DB::commit();

            return response()->json($this->subaccountObject($subaccount->Id), 200);
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

            return response()->json($this->subaccountObject($subaccount->Id), 200);
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
     *         description="Subaccount UUID",
     *         @OA\Schema( 
     *          type="string",
     *          example="123456"
     *          )
     *     ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Subaccount retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="UUID", type="string", example="123456", description="Subaccount UUID"),
     *              @OA\Property(property="ExternalId", type="string", example="123456", description="Subaccount ExternalId"),
     *              @OA\Property(property="Description", type="string", example="My subaccount", description="Subaccount Description"), 
     *              @OA\Property(property="Balance", type="string", example="0.00", description="Subaccount Balance"), 
     *              @OA\Property(property="STPAccount", type="string", example="123456", description="STPAccount associated with the subaccount")
     *         )
     *     ),
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

            return response()->json($this->subaccountObject($subaccount->Id), 200);
        } catch (Exception $e) {
            return self::error('Error getting subaccount', 400, $e);
        }
    }

    private function createWallet($subaccount_id)
    {
        $this->fixNonAccountWallet();

        while (true) {
            $uuid = Uuid::uuid7()->toString();
            $exists = AccountWallet::where('UUID', $uuid)->first();
            if (!$exists) break;
        }

        $accountWallet = AccountWallet::create([
            'UUID' => $uuid,
            'AccountId' => auth()->user()->Id,
            'SubAccountId' => $subaccount_id,
            'Balance' => $this->encrypter->encrypt('0.00')
        ]);

        $this->fixNonSTPAccountWallet($accountWallet);
    }

    private function fixNonSTPAccountWallet($accountWallet)
    {
        $availableSTPAccount = AvailableSTPAccount::where('Available', true)->first();

        if ($availableSTPAccount && $accountWallet->STPAccount == null) {
            AccountWallet::where('Id', $accountWallet->Id)->update(['STPAccount' => $availableSTPAccount->STPAccount]);
            AvailableSTPAccount::where('STPAccount', $availableSTPAccount->STPAccount)->update(['Available' => false]);
        }

        return AccountWallet::where('Id', $accountWallet->Id)->first();
    }

    private function fixNonAccountWallet()
    {
        $accountWallet = AccountWallet::where('AccountId', auth()->user()->Id)
            ->where('SubAccountId', null)
            ->first();

        if (!$accountWallet) {
            while (true) {
                $uuid = Uuid::uuid7()->toString();
                $exists = AccountWallet::where('UUID', $uuid)->first();
                if (!$exists) break;
            }

            $accountWallet = AccountWallet::create([
                'UUID' => $uuid,
                'AccountId' => auth()->user()->Id,
                'Balance' => $this->encrypter->encrypt('0.00')
            ]);
        }

        $this->fixNonSTPAccountWallet($accountWallet);
    }

    public function subaccountObject($subaccount_id)
    {
        $subaccount = Subaccount::where('Id', $subaccount_id)->first();
        $wallet = AccountWallet::where('SubAccountId', $subaccount_id)->first();
        $wallet = $this->fixNonSTPAccountWallet($wallet);

        return [
            'UUID' => $subaccount->UUID,
            'ExternalId' => $subaccount->ExternalId,
            'Description' => $subaccount->Description,
            'Balance' => $this->encrypter->decrypt($wallet->Balance),
            'STPAccount' => $wallet->STPAccount
        ];
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
}
