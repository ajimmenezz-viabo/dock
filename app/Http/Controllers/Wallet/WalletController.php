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
            $movements_array[] = self::movement_object($movement);
        }

        return $movements_array;
    }

    public static function movements($wallet_id, $from, $to)
    {
        $movements = WalletMovement::where('WalletId', $wallet_id)
            ->whereBetween('created_at', [$from, $to])
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
        $card = Card::leftJoin('card_pan', 'card_pan.CardId', '=', 'cards.Id')
            ->select('cards.UUID', 'card_pan.Pan', 'cards.MaskedPan')
            ->where('cards.Id', $movement->CardId)->first();

        return [
            'movement_id' => $movement->UUID,
            'type' => $movement->Type,
            'description' => $movement->Description,
            'reference' => $movement->Reference ?? '',
            'amount' => $movement->Amount,
            'balance' => $movement->Balance,
            'date' => self::toUnixTime($movement->created_at),
            'card' => [
                'card_id' => $card->UUID ?? null,
                'masked_pan' => $card->MaskedPan ?? null,
                'bin' => isset($card->Pan) ?  substr($card->Pan, -8) : null
            ]
        ];
    }

    public function account(Request $request)
    {
        $this->validate($request, [
            'account_id' => 'required'
        ]);
    }
}
