<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Shared\WebhookRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DockWebhookController extends Controller
{
    public function store(Request $request)
    {
        try {
            $decryptedText = "Error";

            $decrypted = $this->decrypt($request->getContent());
            if ($decrypted === false) {
                Log::error('Failed to decrypt message');
            } else {
                $decryptedText = $decrypted;
            }

            WebhookRequest::create([
                'Url' => $request->fullUrl(),
                'Method' => $request->method(),
                'Headers' => json_encode($request->header()),
                'QueryParams' => json_encode($request->query()),
                'Body' => $request->getContent(),
                'DecryptedBody' => $decryptedText
            ]);

            $this->tryDecryptErrors();

            return response()->json(['message' => 'Request registered'], 200);
        } catch (\Exception $e) {
            return self::error('Error registering request', 500, $e);
        }
    }

    public function tryDecryptErrors()
    {
        try {
            $errors = WebhookRequest::where('DecryptedBody', 'Error')->get();
            foreach ($errors as $error) {
                $decrypted = $this->decrypt($error->Body);
                if ($decrypted === false) {
                    $decryptedText = "Error";
                } else {
                    $decryptedText = $decrypted;
                }

                WebhookRequest::where('Id', $error->Id)->update(['DecryptedBody' => $decryptedText]);
            }
        } catch (\Exception $e) {
        }
    }

    private function decrypt($encryptedMessage)
    {
        $aesKey = env("WEBHOOK_AES_KEY");
        $key = base64_decode($aesKey);
        $messageBytes = base64_decode($encryptedMessage);
        $nonce = substr($messageBytes, 0, 12);
        $ciphertext = substr($messageBytes, 12, -16);
        $tag = substr($messageBytes, -16);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        return $decrypted;
    }
}
