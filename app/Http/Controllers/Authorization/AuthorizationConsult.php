<?php

namespace App\Http\Controllers\Authorization;

use App\Exceptions\AuthorizationException;
use Illuminate\Http\Request;

class AuthorizationConsult extends AuthorizationController
{
    public function consult(Request $request)
    {
        $authorization = $this->validateRepeatedRequest($request);

        try {
            $this->validateHeaders($request->headers->all());
            $this->validateBodyConsult($request->all());
            $card = $this->validateCard($request->all()['card_id']);
            $balance = $this->encrypter->decrypt($card->Balance);

            $status_validation = $this->validateCardStatus($card);
            if ($status_validation['response'] != 'APPROVED') {
                $error = $this->save_error($authorization, $status_validation['reason']);
                $response = $this->dock_response($status_validation['response'], $status_validation['reason'], $balance);
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

            $response = $this->dock_response('APPROVED', 'Card is active and ready to use', $this->encrypter->decrypt($card->Balance), [
                'authorization_code' => substr($authorization->AuthorizationCode, -6)
            ]);

            $authorizationRequestId = $this->save_response($authorization, $request, $response);

            $this->registerMovement($card->Id, "-" . $request->all()['values']['billing_value'], $newBalance, 'CONSULT', $authorizationRequestId, $request->all()['establishment'] ?? 'Unknown establishment');

            return response()->json($response, 200);
        } catch (AuthorizationException $e) {
            $error = $this->save_error($authorization, $e->getMessage());
            return response()->json($error, 200);
        }
    }

    private function validateBodyConsult($body)
    {
        if (!isset($body['card_id'])) {
            throw new AuthorizationException('CardId not found');
        }
    }
}
