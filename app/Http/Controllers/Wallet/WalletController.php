<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Card\Card;
use Illuminate\Http\Request;
use App\Models\Wallet\WalletMovement;
use Ramsey\Uuid\Uuid;

use Exception;

class WalletController extends Controller
{
    public static function last_movements($wallet_id, $limit = 10)
    {
        $movements = WalletMovement::where('WalletId', $wallet_id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();


        $movements_array = [];

        foreach ($movements as $movement) {
            $movements_array = self::movement_object($movement);
        }

        return $movements_array;
    }

    public static function movements($wallet_id, $from, $to)
    {
        // $movements = WalletMovement::where('WalletId', $wallet_id)
        //     ->where('created_at', '>=', $from)
        //     ->where('created_at', '<=', $to)
        //     ->orderBy('created_at', 'desc')
        //     ->get();

        $movements = WalletMovement::where('WalletId', $wallet_id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderBy('created_at', 'desc')
            ->get();

        $movements_array = [];

        foreach ($movements as $movement) {
            $movements_array[] = self::movement_object($movement);
        }

        return [
            'count' => count($movements_array),
            'movements' => $movements_array
        ];
    }

    public static function movement_object($movement)
    {
        $m = [
            'movement_id' => $movement->UUID,
            'type' => $movement->Type,
            'description' => $movement->Description,
            'amount' => $movement->Amount,
            'balance' => $movement->Balance,
            'date' => $movement->created_at
        ];

        $card = Card::where('Id', $movement->CardId)->first();
        if ($card) {
            $m['card'] = [
                'card_id' => $card->UUID,
                'masked_pan' => $card->MaskedPan
            ];
        }

        return $m;
    }

    public function register_deposit_by_uuid(Request $request, $uuid)
    {
        try {
        } catch (Exception $e) {
            return self::error('Error registering deposit', 400, $e);
        }
    }
}
