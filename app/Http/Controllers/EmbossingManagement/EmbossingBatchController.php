<?php

namespace App\Http\Controllers\EmbossingManagement;

use App\Http\Controllers\Card\MainCardController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PersonManagement\PersonController;
use App\Models\Card\Card;
use App\Services\DockApiService;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

use App\Models\Card\Profile;
use App\Models\Embossing\Embossing;
use App\Models\Person\Person;
use App\Models\Embossing\EmbossingBatch;
use App\Models\Person\PersonAddress;
use App\Models\Shared\Country;
use App\Models\User;
use Exception;

class EmbossingBatchController extends Controller
{
    public function index()
    {
        $embossing_batches = EmbossingBatch::where('UserId', auth()->user()->Id)
            ->get();

        $batches = [];

        foreach ($embossing_batches as $batch) {
            $batches[] = $this->batchEmbossingObject($batch->ExternalId);
            $this->fillBatchCards($batch);
        }

        return response()->json($batches, 200);
    }

    public function show($uuid)
    {
        try {
            $batch = EmbossingBatch::where('UserId', auth()->user()->Id)->where('ExternalId', $uuid)->first();
            if (!$batch) return response()->json(['message' => 'Batch not found or you do not have permission to access it'], 404);

            return $this->batchEmbossingObject($uuid);
        } catch (Exception $e) {
            return self::error('Error getting batch', 500, $e);
        }
    }

    public function batchEmbossing(Request $request)
    {
        $this->validateCardBatchData($request);

        $request['data'] = $this->validateCardDataExist($request);

        try {
            $last_clientid = Card::where('CustomerPrefix', auth()->user()->prefix)->orderBy('CustomerId', 'desc')->first();
            if ($last_clientid) {
                $next_clientid = $last_clientid->CustomerId + 1;
            } else {
                $next_clientid = 1;
            }

            for ($i = 0; $i < $request['quantity']; $i++) {
                $request['metadata'] = [
                    'key' => 'text1',
                    'value' => auth()->user()->prefix . str_pad($next_clientid, 7, '0', STR_PAD_LEFT)
                ];

                $dockRaw = $this->cardBatchDockRaw($request);

                $response = DockApiService::request(
                    ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/batches',
                    'POST',
                    [],
                    [],
                    'bearer',
                    $dockRaw
                );

                EmbossingBatch::create([
                    'UserId' => auth()->user()->Id,
                    'PersonId' => $request['data']['person']->Id,
                    'ExternalId' => $response->id,
                    // 'TotalCards' => $response->quantity,
                    'TotalCards' => 1,
                    'Status' => $response->status
                ]);
            }

            return response()->json(['message' => 'Batch created successfully'], 200);
        } catch (Exception $e) {
            return self::error('Error creating batch', 500, $e);
        }
    }

    private function batchEmbossingObject($uuid)
    {
        try {

            $batch = EmbossingBatch::where('ExternalId', $uuid)->first();
            $batch_external_data = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/batches/' . $batch->ExternalId,
                'GET',
                [],
                [],
                'bearer',
                []
            );

            $object = [
                'id' => $batch->ExternalId,
                'total_cards' => $batch->TotalCards,
                'status' => $batch->Status,
                'status_reason' => '',
                'update_date' => $batch->updated_at,
                'person' => PersonController::getPersonObjectShort($batch->PersonId),
                'external_data' => [
                    'status' => $batch_external_data->status,
                    'status_reason' => $batch_external_data->status_reason,
                    'update_date' => $batch_external_data->update_date
                ]
            ];


            $update_date = new \DateTime($batch_external_data->update_date);

            if ($update_date > $batch->updated_at) {
                EmbossingBatch::where('Id', $batch->Id)->update([
                    'Status' => $batch_external_data->status
                ]);
            }

            if ($batch_external_data->status == 'PROCESSED') {
                $limit = 150;
                $page = 1;
                for ($i = 0; $i < $batch->TotalCards; $i += $limit) {
                    $this->fillBatchCards($batch, $page, $limit);
                    $page++;
                }
            }
        } catch (Exception $e) {
        }

        return $object;
    }

    private function fillBatchCards($batch, $page = 1, $limit = 150)
    {
        try {
            $batch_external_data = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/batches/' . $batch->ExternalId . '/cards',
                'GET',
                [
                    'limit' => $limit,
                    'page' => $page
                ],
                [],
                'bearer',
                []
            );

            $prefix = User::where('Id', $batch->UserId)->first()->prefix;

            foreach ($batch_external_data->content as $card) {
                $cardExist = Card::where('ExternalId', $card->id)->first();

                if ($cardExist) {
                    MainCardController::fixNonCustomerId($cardExist, $card);
                    continue;
                }

                $cardExist = Card::create([
                    'BatchId' => $batch->Id,
                    'UUID' => Uuid::uuid7()->toString(),
                    'CreatorId' => $batch->UserId,
                    'PersonId' => $batch->PersonId,
                    'Type' => 'physical',
                    'ActiveFunction' => "CREDIT",
                    'ExternalId' => $card->id,
                    'Brand' => "MASTER",
                    'MaskedPan' => $card->masked_pan,
                    'Pan' => null,
                    'ExpirationDate' => null,
                    'CVV' => null,
                    'Balance' => $this->encrypter->encrypt('0.00')
                ]);

                MainCardController::fixNonCustomerId($cardExist, $card);
            }
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
    }

    private function validateCardBatchData($request)
    {
        $this->validate($request, [
            'person_id' => 'required|int',
            'embossing_setup_id' => 'required|int',
            'card_profile_id' => 'required|int',
            'quantity' => 'required|int'
        ]);
    }

    private function validateCardDataExist($request)
    {

        $person = Person::where('Id', $request['person_id'])->where('UserId', auth()->user()->Id)->first();
        $card_profile = Profile::where('Id', $request['card_profile_id'])->first();
        $embossing_setup = Embossing::where('Id', $request['embossing_setup_id'])->first();

        if (!$person)
            abort(404, 'Person not found or you do not have permission to access it');

        if (!$card_profile)
            abort(404, 'The card profile does not exist or you do not have permission to access it');

        if (!$embossing_setup)
            abort(404, 'The embossing setup does not exist or you do not have permission to access it');

        return [
            'person' => $person,
            'profile' => $card_profile,
            'embossing' => $embossing_setup
        ];
    }

    private function cardBatchDockRaw($request)
    {
        $person_address = PersonAddress::where('PersonId', $request['data']['person']->Id)->where('Main', 1)->first();

        return [
            // 'quantity' => $request['quantity'],
            'quantity' => 1,
            'profile_id' => $request['data']['profile']->ExternalId,
            'embossing_setup_id' => $request['data']['embossing']->ExternalId,
            'type' => "PHYSICAL",
            'active_function' => $request['data']['profile']->ProductType,
            'settings' => [
                'transaction' => [
                    'ecommerce' => true,
                    'international' => false,
                    'stripe' => true,
                    'wallet' => true,
                    'withdrawal' => true,
                    'contactless' => true
                ]
            ],
            'address' => [
                'city' => $person_address->City,
                'complement' => "Number " . $person_address->Number,
                'country_code' => Country::where('Id', $person_address->CountryId)->first()->Alpha2Code,
                'number' => "0",
                'street' => $person_address->Street,
                'postal_code' => $person_address->ZipCode,
                'administrative_area_code' => "NLE"
            ],
            'metadata' => $request['metadata']
        ];
    }
}
