<?php

namespace App\Http\Controllers\CardMovements;

use App\Http\Controllers\Controller;
use App\Models\CardMovements\CardMovements;
use App\Models\Shared\AuthorizationRequest;

class CardMovementController extends Controller
{
    public static function movements($card_id, $from, $to)
    {
        $movements = CardMovements::where('CardId', $card_id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'desc')
            ->get();

        $movements_array = [];

        foreach ($movements as $movement) {
            $movements_array[] = self::movement_object($movement);
        }

        return $movements_array;
    }

    public static function movement_object($movement)
    {
        $authorization = AuthorizationRequest::where('Id', $movement->AuthorizationRequestId)->first();
        $code = null;
        if ($authorization) {
            $code = substr($authorization->AuthorizationCode, -6);
        }

        return [
            'movement_id' => $movement->UUID,
            'date' => strtotime($movement->created_at),
            'type' => $movement->Type,
            'amount' => $movement->Amount,
            'authorization_code' => $code,
            'description' => $movement->Description,
        ];
    }
}
