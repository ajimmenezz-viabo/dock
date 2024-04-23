<?php

namespace App\Http\Controllers\Authorization;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

use App\Models\Shared\AuthorizationRequest;
use Exception;

class AuthorizationPurchase extends AuthorizationController
{
    public function purchase(Request $request)
    {
        $exists = AuthorizationRequest::where('ExternalId', $request->headers->all()['uuid'][0])->first();
        if ($exists) {
            // $error = $this->dock_error($exists->UUID, 'Request already exists', 400);
            // return response()->json($error, 400);
        }

        $authorization = AuthorizationRequest::create([
            'UUID' => Uuid::uuid7()->toString(),
            'ExternalId' => $request->headers->all()['uuid'][0] ?? '',
            'AuthorizationCode' => $this->getAuthorizationCode('PU'),
            'Endpoint' => $request->getRequestUri(),
            'Headers' => json_encode($request->headers->all()),
            'Body' => json_encode($request->all()),
            'Response' => '',
            'Error' => '',
            'Code' => 400
        ]);
        try {

            $this->validateHeaders($request->headers->all());
            $this->validateBodyPurchase($request->all());
            $card = $this->validateCard($request->all()['card_id']);
            $balance = $this->encrypter->decrypt($card->Balance);


            $status_validation = $this->validateCardStatus($card);
            if ($status_validation['response'] != 'APPROVED') {
                $error = $this->save_error($authorization, $status_validation['reason']);
                $response = $this->dock_response($status_validation['response'], $status_validation['reason'], $balance);
                return response()->json($error, 200);
            }

            $setup_validation = $this->validateCardSetup($card, $request->all());
            if ($setup_validation['response'] != 'APPROVED') {
                $error = $this->save_error($authorization, $setup_validation['reason']);
                $response = $this->dock_response($setup_validation['response'], $setup_validation['reason'], $balance);
                return response()->json($error, 200);
            }

            $profile_validation = $this->validateProfileRules($card, $request->all()['values']['billing_value'], 'PURCHASE');
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

            $this->registerMovement($card->Id, "-" . $request->all()['values']['billing_value'], $newBalance, 'PURCHASE');

            $response = $this->dock_response('APPROVED', 'Transaction approved', $newBalance, [
                'authorization_code' => $authorization->AuthorizationCode
            ]);

            $this->save_response($authorization, $request, $response);

            return response()->json($response, 200);
        } catch (Exception $e) {
            $error = $this->save_error($authorization, $e->getMessage());
            var_dump($e);
            return response()->json($error, 500);
        }
    }

    private function validateBodyPurchase($body)
    {
        if (!isset($body['card_id'])) {
            throw new Exception('CardId not found');
        }

        if (!isset($body['card_expiration_date'])) {
            throw new Exception('Card expiration date is required');
        }

        if (!isset($body['values']['billing_value'])) {
            throw new Exception('Transaction amount must be greater than zero');
        }
    }
}
