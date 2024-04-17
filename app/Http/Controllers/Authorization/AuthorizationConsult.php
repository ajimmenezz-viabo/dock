<?php

namespace App\Http\Controllers\Authorization;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

use App\Models\Shared\AuthorizationRequest;
use Exception;

class AuthorizationConsult extends AuthorizationController
{
    public function consult(Request $request)
    {
        // $exists = AuthorizationRequest::where('ExternalId', $request->headers->all()['uuid'][0])->first();
        // if ($exists) {
        //     $error = $this->dock_error($exists->UUID, 'Request already exists', 400);
        //     return response()->json($error, 400);
        // }

        $authorization = AuthorizationRequest::create([
            'UUID' => Uuid::uuid7()->toString(),
            'ExternalId' => $request->headers->all()['uuid'][0] ?? '',
            'AuthorizationCode' => $this->getAuthorizationCode('AC'),
            'Endpoint' => $request->getRequestUri(),
            'Headers' => json_encode($request->headers->all()),
            'Body' => json_encode($request->all()),
            'Response' => '',
            'Error' => '',
            'Code' => 400
        ]);

        try {
            $this->validateHeaders($request->headers->all());
            $this->validateBodyConsult($request->all()['request']);
            $card = $this->validateCard($request->all()['request']['card_id']);
            $response = $this->dock_response('APPROVED', 'Card is active and ready to use', $this->encrypter->decrypt($card->Balance));
            $this->save_response($authorization, $request, $response);

            return response()->json($response, 200);
        } catch (Exception $e) {
            $error = $this->save_error($authorization, $e->getMessage());

            return response()->json($error, 400);
        }
    }

    private function validateBodyConsult($body)
    {
        if (!isset($body['card_id'])) {
            throw new Exception('CardId not found');
        }
    }
}
