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
     *     path="/api/v1/subaccounts",
     *    tags={"Subaccounts"},
     *    summary="Get all subaccounts for the authenticated user",
     *    description="Get all subaccounts for the authenticated user",
     *    @OA\Response(response="200", description="An example endpoint")
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
     *     path="/api/v1/subaccounts/{uuid}",
     *    tags={"Subaccounts"},
     *    summary="Get all subaccounts for the authenticated user",
     *    description="Get all subaccounts for the authenticated user",
     *    @OA\Response(response="200", description="An example endpoint")
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
