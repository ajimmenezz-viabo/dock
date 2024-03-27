<?php

namespace App\Http\Controllers\CardsManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

use App\Services\DockApiService;
use App\Models\Card\Card;
use App\Models\Card\Profile;
use App\Models\Embossing\Embossing;
use App\Models\Person\Person;
use App\Models\Person\PersonAddress;
use App\Models\Shared\Country;
use App\Http\Controllers\Caradhras\Security\Encryption;

use Exception;

class CardsController extends Controller
{
    public function index(Request $request)
    {
    }

    public function show($id)
    {
        try {
            $card = Card::where('Id', $id)
                ->where(function ($query) {
                    $query->where('CreatorId', auth()->user()->Id)
                        ->orWhere('PersonId', auth()->user()->Id);
                })->first();
            if (!$card) return response()->json(['message' => 'Card not found or you don\'t have permission to access it'], 404);

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/data',
                'GET',
                [],
                [],
                'bearer',
                null
            );

            var_dump($response);

            $decrypted = Encryption::decrypt($response->aes, $response->iv, $response->pan);


            return response()->json(['card' => $this->cardObject($id)], 200);
        } catch (Exception $e) {
            return self::error('Error getting card', 400, $e);
        }
    }

    public function store(Request $request)
    {
        $this->validateCardData($request);

        $request['data'] = $this->validateCardDataExist($request);

        try {
            DB::beginTransaction();

            while (true) {
                $uuid = Uuid::uuid7()->toString();
                $exists = Card::where('UUID', $uuid)->first();
                if (!$exists) break;
            }

            $card = Card::create([
                'UUID' => $uuid,
                'CreatorId' => auth()->user()->Id,
                'PersonId' => $request['person_id'],
                'Type' => $request['type'],
                'ActiveFunction' => $request['data']['profile']->ProductType,
                'ExternalId' => null,
                'Brand' => null,
                'MaskedPan' => null
            ]);

            $dockRaw = $this->cardDockRaw($request);

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards',
                'POST',
                [],
                [],
                'bearer',
                $dockRaw
            );

            Card::where('Id', $card->Id)->update([
                'ExternalId' => $response->id,
                'Brand' => $response->brand,
                'MaskedPan' => $response->masked_pan
            ]);

            DB::commit();

            return response()->json(['message' => 'Card created successfully', 'card' => $this->cardObject($card->Id)], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error creating card', 400, $e);
        }
    }

    public function update($id)
    {
        return 'LegalPersonController@update';
    }

    public function destroy($id)
    {
        return 'LegalPersonController@destroy';
    }

    private function validateCardData($request)
    {
        $this->validate($request, [
            'type' => 'required|string',
            'person_id' => 'required|int',
            'embossing_setup_id' => 'required|int',
            'card_profile_id' => 'required|int'
        ]);
    }

    private function validateCardDataExist($request)
    {

        $person = Person::where('Id', $request['person_id'])->where('UserId', auth()->user()->Id)->first();
        $card_profile = Profile::where('Id', $request['card_profile_id'])->first();
        $embossing_setup = Embossing::where('Id', $request['embossing_setup_id'])->first();

        if (!$person)
            abort(404, 'Person not found or you do not have permission to access it');

        if (!in_array($request['type'], ['physical', 'virtual']))
            abort(400, 'The card type must be physical or virtual');

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


    private function cardDockRaw($request)
    {
        $person_address = PersonAddress::where('PersonId', $request['data']['person']->Id)->where('Main', 1)->first();
        $array_part = [];

        if ($request['type'] == 'physical') {
            $array_part = [
                'embossing_setup_id' => $request['data']['embossing']->ExternalId,
                'address' => [
                    'city' => $person_address->City,
                    'complement' => "Number " . $person_address->Number,
                    'country_code' => Country::where('Id', $person_address->CountryId)->first()->Alpha2Code,
                    'number' => "0",
                    'street' => $person_address->Street,
                    'postal_code' => $person_address->ZipCode,
                    'administrative_area_code' => "DIF"
                ]
            ];
        }

        $base = [
            'type' => strtoupper($request['type']),
            'active_function' => $request['data']['profile']->ProductType,
            'cardholder_name' => $request['data']['person']->PersonType == 1 ? $request['data']['person']->LegalName : $request['data']['person']->FullName,
            'pin' => str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'profile_id' => $request['data']['profile']->ExternalId,
            'settings' => [
                'transaction' => [
                    'ecommerce' => true,
                    'international' => false,
                    'stripe' => true,
                    'wallet' => true,
                    'withdrawal' => true,
                    'contactless' => true
                ]
            ]
        ];

        return array_merge($array_part, $base);
    }

    private function cardObject($id, $external_data = null)
    {
        $card = Card::where('Id', $id)->first();

        $object = [
            'card_id' => $card->UUID,
            'card_type' => $card->Type,
            'active_function' => $card->ActiveFunction,
            'brand' => $card->Brand,
            'masked_pan' => $card->MaskedPan
        ];

        if ($external_data) {
            $object['external_data'] = $external_data;
        }

        return $object;
    }
}
