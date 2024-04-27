<?php

namespace App\Http\Controllers\Authorization;

use Illuminate\Http\Request;
use App\Exceptions\AuthorizationException;

class AuthorizationReversal extends AuthorizationController
{
    public function reversal(Request $request)
    {
        $authorization = $this->validateRepeatedRequest($request, "RE");

        try {
            $this->validateHeaders($request->headers->all());
            $this->validateBodyReversal($request->all());
            $card = $this->validateCard($request->all()['card_id'], $request->all()['card_number']);
            $balance = $this->encrypter->decrypt($card->Balance);
            $newBalance = $balance + $request->all()['values']['billing_value'];

            $card->Balance = $this->encrypter->encrypt($newBalance);
            $card->save();

            $this->registerMovement($card->Id, $request->all()['values']['billing_value'], $newBalance, 'REVERSAL');

            $response = $this->dock_response('APPROVED', 'Transaction approved', $newBalance);

            $this->save_response($authorization, $request, $response);

            return response()->json($response, 200);
        } catch (AuthorizationException $e) {
            $error = $this->save_error($authorization, $e->getMessage());
            return response()->json($error, 200);
        }
    }

    private function validateBodyReversal($body)
    {
        if (!isset($body['card_id'])) {
            if (!isset($body['card_number'])) {
                throw new AuthorizationException('Card not found', 200, 400);
            }
        }

        if (!isset($body['card_expiration_date'])) {
            throw new AuthorizationException('Card expiration date is required', 200, 400);
        }

        if (!isset($body['values']['billing_value'])) {
            throw new AuthorizationException('Transaction amount must be greater than zero', 200, 400);
        }
    }
}
