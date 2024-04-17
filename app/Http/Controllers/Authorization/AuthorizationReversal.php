<?php

namespace App\Http\Controllers\Authorization;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

use App\Models\Shared\AuthorizationRequest;
use Exception;

class AuthorizationReversal extends AuthorizationController
{
    public function reversal(Request $request)
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
            $this->validateBodyReversal($request->all());
            $card = $this->validateCard($request->all()['card_id'], $request->all()['card_number']);
            $balance = $this->encrypter->decrypt($card->Balance);
            $newBalance = $balance + $request->all()['values']['billing_value'];

            $card->Balance = $this->encrypter->encrypt($newBalance);
            $card->save();

            $this->registerMovement($card->Id, $request->all()['values']['billing_value'], $newBalance, 'REVERSAL');

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

    private function validateBodyReversal($body)
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
