<?php

namespace App\Http\Controllers\Authorization;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

use App\Models\Shared\AuthorizationRequest;
use Exception;

class AuthorizationWithdraw extends AuthorizationController
{
    public function withdraw(Request $request)
    {
        $exists = AuthorizationRequest::where('ExternalId', $request->headers->all()['uuid'][0])->first();
        if ($exists) {
            $error = $this->dock_error($exists->UUID, 'Request already exists', 400);
            return response()->json($error, 400);
        }

        $authorization = AuthorizationRequest::create([
            'UUID' => Uuid::uuid7()->toString(),
            'ExternalId' => $request->headers->all()['uuid'][0] ?? '',
            'AuthorizationCode' => $this->getAuthorizationCode('DE'),
            'Endpoint' => $request->getRequestUri(),
            'Headers' => json_encode($request->headers->all()),
            'Body' => json_encode($request->all()),
            'Response' => '',
            'Error' => '',
            'Code' => 400
        ]);
        try {

            $this->validateHeaders($request->headers->all());
            $this->validateBodyWithdraw($request->all());
            $card = $this->validateCard($request->all()['card_id'], $request->all()['card_number']);
            $balance = $this->encrypter->decrypt($card->Balance);

            $profile_validation = $this->validateProfileRules($card, $request->all()['values']['billing_value'], 'WITHDRAWAL');
            if ($profile_validation['response'] != 'APPROVED') {
                $error = $this->save_error($authorization, $profile_validation['reason']);
                $response = $this->dock_response($profile_validation['response'], $profile_validation['reason'], $balance);
                return response()->json($error, 200);
            }

            if ($balance < $request->all()['values']['billing_value']) {
                $error = $this->save_error($authorization, 'Insufficient funds');
                $response = $this->dock_response('INSUFFICIENT_FUNDS_OVER_CREDIT_LIMIT', 'Insufficient funds', $balance);
                return response()->json($response, 200);
            }

            $newBalance = $balance - $request->all()['values']['billing_value'];

            $card->Balance = $this->encrypter->encrypt($newBalance);
            $card->save();

            $this->registerMovement($card->Id, "-" . $request->all()['values']['billing_value'], $newBalance, 'WITHDRAWAL');

            $response = $this->dock_response('APPROVED', 'Transaction approved', $newBalance, [
                'authorization_code' => $authorization->AuthorizationCode
            ]);

            $this->save_response($authorization, $request, $response);

            return response()->json($response, 200);
        } catch (Exception $e) {
            $error = $this->save_error($authorization, $e->getMessage());
            return response()->json($error, 500);
        }
    }

    private function validateBodyWithdraw($body)
    {
        if (!isset($body['card_id'])) {
            if (!isset($body['card_number'])) {
                throw new Exception('Card not found');
            }
        }

        if (!isset($body['card_expiration_date'])) {
            throw new Exception('Card expiration date is required');
        }

        if (!isset($body['values']['billing_value'])) {
            throw new Exception('Transaction amount must be greater than zero');
        }
    }
}
