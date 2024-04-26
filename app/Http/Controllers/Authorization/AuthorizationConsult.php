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
            $response = $this->dock_response('APPROVED', 'Card is active and ready to use', $this->encrypter->decrypt($card->Balance));
            $this->save_response($authorization, $request, $response);

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
