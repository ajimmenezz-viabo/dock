<?php

namespace App\Http\Controllers\Authorization;

use App\Http\Controllers\Controller;
use App\Models\Authorization\ProfileAuthorization;
use App\Models\Authorization\ProfileCard;
use App\Models\Card\Card;
use App\Models\CardMovements\CardMovements;
use App\Models\CardSetups\CardSetups;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Log;

use App\Models\Shared\AuthorizationRequest;

use App\Exceptions\AuthorizationException;

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
        $authorization->Error = '';
        $authorization->Code = 200;
        $authorization->save();

        return $authorization->Id;
    }

    public function dock_response($response, $reason, $limit = null, $additional = null)
    {
        $response = [
            'response' => $response,
            'reason' => $reason
        ];

        $response = !is_null($limit) ? array_merge($response, ['available_limit' => number_format($limit, 2, '.', '')]) : $response;

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
                    throw new AuthorizationException('Card not found', 200, 400);
            } else {
                throw new AuthorizationException('Card not found', 200, 400);
            }
        }

        return $card;
    }

    public function validateHeaders($headers)
    {
        // if (!isset($headers['client-id']) || $headers['client-id'][0] != env('AUTHORIZATION_CLIENT_ID')) {
        //     throw new AuthorizationException('Client-Id header not found or invalid value');
        // }

        if (!isset($headers['uuid'])) {
            throw new AuthorizationException('UUID header not found', 200, 400);
        }

        // if (!isset($headers['x-apigw-api-id'])) {
        //     throw new Exception('X-Apigw-Api-Id header not found');
        // }
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

    public function registerMovement($cardId, $amount, $balance, $type, $authorization = null, $description = null)
    {
        do {
            $uuid = Uuid::uuid7()->toString();
        } while (CardMovements::where('UUID', $uuid)->exists());

        CardMovements::create([
            'UUID' => $uuid,
            'CardId' => $cardId,
            'Amount' => str_replace(',', '', number_format($amount, 2)),
            'Balance' => $balance,
            'Type' => $type,
            'AuthorizationRequestId' => $authorization,
            'Description' => $description
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
                return $this->validateWithdrawalRules($card, $auth_profile, $amount);
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

        if (abs($total) + $amount > $profile->MaxAmountMonthlyTPV) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Transaction amount exceeds the maximum monthly allowed'
            ];
        }

        if ($cont + 1 > $profile->MaxOperationsMonthlyTPV) {
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

        if (abs($total) + $amount > $profile->MaxAmountMonthlyATM) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Transaction amount exceeds the maximum monthly allowed'
            ];
        }

        return [
            'response' => 'APPROVED',
            'reason' => 'Transaction approved'
        ];
    }

    public function validateCardStatus($card)
    {
        $setup = CardSetups::where('CardId', $card->Id)->first();
        if (!$setup) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Card setup not found'
            ];
        }

        if ($setup->Status != 'NORMAL') {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Card is blocked or cancelled'
            ];
        }

        return [
            'response' => 'APPROVED',
            'reason' => 'Card is active'
        ];
    }

    public function validateCardSetup($card, $transaction)
    {
        $setup = CardSetups::where('CardId', $card->Id)->first();
        if (!$setup) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Card setup not found'
            ];
        }

        if (!in_array($transaction['processing']['type'], ["PURCHASE", "WITHDRAWAL"])) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Invalid transaction type'
            ];
        }

        if ($transaction['transaction_indicators']['is_international'] === true && $setup->International == 0) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'International transactions are not allowed'
            ];
        }

        if ($transaction['processing']['type'] == 'PURCHASE') {
            if ($transaction['transaction_indicators']['is_ecommerce'] === true && $setup->Ecommerce == 0) {
                return [
                    'response' => 'INVALID_TRANSACTION',
                    'reason' => 'Ecommerce transactions are not allowed'
                ];
            }

            if (in_array($transaction['card_entry']['mode'], ['PAN_AUTO_ENTRY_VIA_CONTACTLESS_M_CHIP', 'PAN_AUTO_ENTRY_VIA_CONTACTLESS_MAGNETIC_STRIPE']) && $setup->Contactless == 0) {
                return [
                    'response' => 'INVALID_TRANSACTION',
                    'reason' => 'Contactless transactions are not allowed'
                ];
            }
        }

        if ($transaction['processing']['type'] == 'WITHDRAWAL' && $setup->Withdrawal == 0) {
            return [
                'response' => 'INVALID_TRANSACTION',
                'reason' => 'Withdrawal transactions are not allowed'
            ];
        }


        return [
            'response' => 'APPROVED',
            'reason' => 'Card pass all setup validations'
        ];
    }

    public function validateRepeatedRequest(Request $request, $type = 'AC')
    {
        Log::info('Validating New Request');
        Log::info(['Headers' => $request->headers->all(), 'Body' => $request->all()]);

        $exists = AuthorizationRequest::where('ExternalId', $request->headers->all()['uuid'][0] ?? 'X')->first();
        if ($exists && $exists->Code == 200)
            throw new AuthorizationException('Request already exists', 400);
        else if ($exists && $exists->Code != 200)
            return $exists;

        return AuthorizationRequest::create([
            'UUID' => Uuid::uuid7()->toString(),
            'ExternalId' => $request->headers->all()['uuid'][0] ?? '',
            'AuthorizationCode' => $this->getAuthorizationCode($type),
            'Endpoint' => $request->getRequestUri(),
            'Headers' => json_encode($request->headers->all()),
            'Body' => json_encode($request->all()),
            'Response' => '',
            'Error' => '',
            'Code' => 400
        ]);
    }
}
