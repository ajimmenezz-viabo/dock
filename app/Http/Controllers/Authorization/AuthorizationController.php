<?php

namespace App\Http\Controllers\Authorization;

use App\Http\Controllers\Controller;
use App\Models\Authorization\ProfileAuthorization;
use App\Models\Authorization\ProfileCard;
use App\Models\Card\Card;
use App\Models\CardMovements\CardMovements;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

use App\Models\Shared\AuthorizationRequest;
use Exception;

class AuthorizationController extends Controller
{

    public function save_response(AuthorizationRequest $authorization, Request $request, $response)
    {
        $authorization->Response = json_encode($response);
        if (isset($request->all()['request']['card_id']))
            $authorization->CardExternalId = $request->all()['request']['card_id'];
        else
            $authorization->CardExternalId = $request->all()['card_id'];

        $authorization->ExternalId = $request->headers->all()['uuid'][0];
        $authorization->Code = 200;
        $authorization->save();
    }

    public function dock_response($response, $reason, $limit = null, $additional = null)
    {
        $response = [
            'response' => $response,
            'reason' => $reason
        ];

        $response = !is_null($limit) ? array_merge($response, ['available_limit' => $limit]) : $response;

        if ($additional) {
            $response = array_merge($response, $additional);
        }

        return $response;
    }

    public function save_error(AuthorizationRequest $authorization, $message)
    {
        $error = $this->dock_error($authorization->UUID, $message, 400);

        $authorization->Error = $message;
        $authorization->Code = 400;
        $authorization->Response = json_encode($error);
        $authorization->save();

        return $error;
    }

    public function dock_error($id, $description, $code)
    {
        return [
            'error' => [
                'id' => $id,
                'description' => $description,
                'code' => $code
            ]
        ];
    }

    public function validateCard($cardId, $pan = null)
    {
        $card = Card::where('ExternalId', $cardId)->first();
        if (!$card) {
            if ($pan) {
                $card = Card::where('MaskedPan', $pan)->first();
                if (!$card)
                    throw new Exception('Card not found');
            }

            throw new Exception('Card not found');
        }

        return $card;
    }

    public function validateHeaders($headers)
    {
        if (!isset($headers['client-id']) || $headers['client-id'][0] != env('DOCK_API_CLIENT_U')) {
            throw new Exception('Client-Id header not found or invalid value');
        }

        if (!isset($headers['uuid'])) {
            throw new Exception('UUID header not found');
        }

        if (!isset($headers['x-apigw-api-id'])) {
            throw new Exception('X-Apigw-Api-Id header not found');
        }
    }

    public function getAuthorizationCode($prefix)
    {
        do {

            $number = '';

            for ($i = 0; $i < 7; $i++) {
                $number .= rand(0, 9);
            }

            $code = $prefix . $number;
        } while (AuthorizationRequest::where('AuthorizationCode', $code)->exists());

        return $code;
    }

    public function registerMovement($cardId, $amount, $balance, $type)
    {
        CardMovements::create([
            'CardId' => $cardId,
            'Amount' => str_replace(',', '', number_format($amount, 2)),
            'Balance' => $balance,
            'Type' => $type
        ]);
    }

    public function validateProfileRules($card, $amount, $type)
    {
        $profile = ProfileCard::where('CardId', $card->Id)->first();
        if (!$profile) {
            ProfileCard::create([
                'CardId' => $card->Id,
                'AuthorizationProfileId' => 1
            ]);
            $profile = ProfileCard::where('CardId', $card->Id)->first();
        }

        $auth_profile = ProfileAuthorization::where('Id', $profile->AuthorizationProfileId)->first();

        switch ($type) {
            case 'PURCHASE':
                return $this->validatePurchaseRules($card, $auth_profile, $amount);
                break;
            case 'WITHDRAWAL':
                $this->validateWithdrawalRules($card, $auth_profile, $amount);
                break;
            default:
                return [
                    'response' => 'INVALID_TRANSACTION',
                    'reason' => 'Invalid transaction type'
                ];
        }
    }

    private function validatePurchaseRules($card, $profile, $amount)
    {
        if ($amount > $profile->MaxAmountTPV) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Transaction amount exceeds the maximum allowed'
            ];
        }

        $date = date('Y-m-d');
        $movementsToday = CardMovements::where('CardId', $card->Id)
            ->where('Type', 'PURCHASE')
            ->where('created_at', '>=', $date . ' 00:00:00')
            ->where('created_at', '<=', $date . ' 23:59:59')
            ->get();
        $total = 0;
        $cont = 0;
        foreach ($movementsToday as $movement) {
            $total += $movement->Amount;
            $cont++;
        }

        if (abs($total) + $amount > $profile->MaxDailyAmountTPV) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Transaction amount exceeds the maximum daily allowed'
            ];
        }

        if ($cont + 1 > $profile->MaxDailyOperationsTPV) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Number of transactions exceeds the maximum daily allowed'
            ];
        }


        $month = date('m');
        $movementsMonth = CardMovements::where('CardId', $card->Id)
            ->where('Type', 'PURCHASE')
            ->whereMonth('created_at', $month)
            ->get();

        $total = 0;
        $cont = 0;
        foreach ($movementsMonth as $movement) {
            $total += $movement->Amount;
            $cont++;
        }

        if (abs($total) + $amount > $profile->MaxMonthlyAmountTPV) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Transaction amount exceeds the maximum monthly allowed'
            ];
        }

        if ($cont + 1 > $profile->MaxMonthlyOperationsTPV) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Number of transactions exceeds the maximum monthly allowed'
            ];
        }

        return [
            'response' => 'APPROVED',
            'reason' => 'Transaction approved'
        ];
    }

    private function validateWithdrawalRules($card, $profile, $amount)
    {
        if ($amount > $profile->MaxAmountATM) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Transaction amount exceeds the maximum allowed'
            ];
        }

        $date = date('Y-m-d');
        $movementsToday = CardMovements::where('CardId', $card->Id)
            ->where('Type', 'WITHDRAWAL')
            ->where('created_at', '>=', $date . ' 00:00:00')
            ->where('created_at', '<=', $date . ' 23:59:59')
            ->get();
        $total = 0;
        $cont = 0;
        foreach ($movementsToday as $movement) {
            $total += $movement->Amount;
            $cont++;
        }

        if (abs($total) + $amount > $profile->MaxDailyAmountATM) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Transaction amount exceeds the maximum daily allowed'
            ];
        }

        if ($cont + 1 > $profile->MaxDailyOperationsATM) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Number of transactions exceeds the maximum daily allowed'
            ];
        }


        $month = date('m');
        $movementsMonth = CardMovements::where('CardId', $card->Id)
            ->where('Type', 'WITHDRAWAL')
            ->whereMonth('created_at', $month)
            ->get();

        $total = 0;
        $cont = 0;
        foreach ($movementsMonth as $movement) {
            $total += $movement->Amount;
            $cont++;
        }

        if (abs($total) + $amount > $profile->MaxMonthlyAmountATM) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Transaction amount exceeds the maximum monthly allowed'
            ];
        }

        if ($cont + 1 > $profile->MaxMonthlyOperationsATM) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Number of transactions exceeds the maximum monthly allowed'
            ];
        }

        return [
            'response' => 'APPROVED',
            'reason' => 'Transaction approved'
        ];
    }
}
