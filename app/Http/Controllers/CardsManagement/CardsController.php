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
use App\Http\Controllers\PersonManagement\PersonController;
use App\Models\CardSetups\CardSetups;
use App\Models\Person\PersonAccount;
use App\Models\Person\PersonAccountAlias;
use Exception;

class CardsController extends Controller
{
    private $dock_encrypter;

    public function __construct()
    {
        parent::__construct();
        $this->dock_encrypter = new Encryption();
    }

    public function index(Request $request)
    {
        try {
            $page = $request['page'] ?? 1;
            $limit = 100;
            $offset = ($page - 1) * $limit;

            $cards = Card::where('CreatorId', auth()->user()->Id)
                ->orWhere('PersonId', auth()->user()->Id)
                ->offset($offset)
                ->limit($limit)
                ->get();

            $count = Card::where('CreatorId', auth()->user()->Id)->orWhere('PersonId', auth()->user()->Id)->count();

            $cards_array = [];

            foreach ($cards as $card) {
                array_push($cards_array, $this->cardObject($card->Id));
            }

            return response()->json([
                'cards' => $cards_array,
                'page' => $page,
                'total_pages' => ceil($count / $limit),
                'total_records' => $count
            ], 200);
        } catch (Exception $e) {
            return self::error('Error getting cards', 400, $e);
        }
    }

    public function show($id)
    {
        try {
            $card = $this->validateCardPermission($id);
            if (!$card) return response()->json(['message' => 'Card not found or you do not have permission to access it'], 404);

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

    public function block($id)
    {
        try {
            $card = $this->validateCardPermission($id);
            if (!$card) return response()->json(['message' => 'Card not found or you do not have permission to access it'], 404);

            $dockRaw = [
                'status' => 'BLOCKED',
                'status_reason' => 'OTHER'
            ];

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/status',
                'PUT',
                [],
                [],
                'bearer',
                $dockRaw
            );

            if (isset($response->response->description))
                return response()->json(['message' => $response->response->description], 400);

            return response()->json(['message' => 'Card blocked successfully', 'card' => $this->cardObject($id)], 200);
        } catch (Exception $e) {
            return self::error('Error blocking card', 400, $e);
        }
    }

    public function unblock($id)
    {
        try {
            $card = $this->validateCardPermission($id);
            if (!$card) return response()->json(['message' => 'Card not found or you do not have permission to access it'], 404);

            $dockRaw = [
                'status' => 'BLOCKED',
                'status_reason' => 'OTHER'
            ];

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/status',
                'PUT',
                [],
                [],
                'bearer',
                $dockRaw
            );

            if (isset($response->response->description))
                return response()->json(['message' => $response->response->description], 400);

            return response()->json(['message' => 'Card blocked successfully', 'card' => $this->cardObject($id)], 200);
        } catch (Exception $e) {
            return self::error('Error blocking card', 400, $e);
        }
    }

    private function validateCardPermission($id)
    {
        return Card::where('Id', $id)
            ->where(function ($query) {
                $query->where('CreatorId', auth()->user()->Id)
                    ->orWhere('PersonId', auth()->user()->Id);
            })->first();
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

    private function cardObject($id)
    {
        $card = $this->fillSensitiveData(Card::where('Id', $id)->first());
        $person = PersonController::getPersonObjectShort($card->PersonId);
        $alias = PersonAccountAlias::where('CardId', $card->Id)->first();
        if (!$alias) {
            $alias = $this->fixNonAlias($card, $person['person_account']);
        }

        if ($card->Balance == null) {
            $card->Balance = $this->encrypter->encrypt("0.00");
            $card->save();
        }

        $pin = $this->getPin($card->ExternalId);

        $object = [
            'card_id' => $card->Id,
            'card_type' => $card->Type,
            'active_function' => $card->ActiveFunction,
            'brand' => $card->Brand,
            'masked_pan' => $card->MaskedPan,
            'balance' => $this->encrypter->decrypt($card->Balance),
            'sensitive_data' => $this->sensitiveData($card, $pin),
        ];


        if (env('DEV_MODE') ===  true) {

            $object['person'] = $person;
            $object['alias_account'] = $alias;
            $object['external'] = $this->fillSetups($card);
            $object['sensitive_data_raw'] = [
                'pan' => $this->encrypter->decrypt($card->Pan),
                'cvv' => $this->encrypter->decrypt($card->CVV),
                'expiration_date' => $this->encrypter->decrypt($card->ExpirationDate),
                'pin' => $pin
            ];
        }

        return $object;
    }

    private function sensitiveData($card, $pin)
    {
        $sensitive = [
            'pan' => $this->encrypter->decrypt($card->Pan),
            'cvv' => $this->encrypter->decrypt($card->CVV),
            'expiration_date' => $this->encrypter->decrypt($card->ExpirationDate),
            'pin' => $pin
        ];

        return $this->encrypter->encrypt(json_encode($sensitive));
    }

    private function fillSetups($card)
    {
        try {
            $setups = CardSetups::where('CardId', $card->Id)->first();
            if ($setups) return $setups;

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId,
                'GET',
                [],
                [],
                'bearer',
                null
            );

            $setups = CardSetups::create([
                'CardId' => $card->Id,
                'Status' => $response->status,
                'StatusReason' => $response->status_reason,
                'Ecommerce' => $response->settings->transaction->ecommerce,
                'International' => $response->settings->transaction->international,
                'Stripe' => $response->settings->transaction->stripe,
                'Wallet' => $response->settings->transaction->wallet,
                'Withdrawal' => $response->settings->transaction->withdrawal,
                'Contactless' => $response->settings->transaction->contactless,
                'PinOffline' => $response->settings->security->pin_offline,
                'PinOnUs' => $response->settings->security->pin_on_us
            ]);

            return $setups;
        } catch (Exception $e) {
            return null;
        }
    }

    private function fixNonAlias($card, $person_account)
    {
        $dockRaw = [
            'account_id' => $person_account['external_id'],
            'alias_provider_id' => env('DOCK_ALIAS_PROVIDER_ID'),
            'alias' => [
                'card_id' => $card->ExternalId,
                'card_rail' => $card->ActiveFunction,
                'card_issuer' => "CARDS"
            ],
        ];

        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'account-services/alias-core/v1/alias/ecosystems/MASTERCARD/schemas/CARDS',
            'POST',
            [],
            ['trace_id' => ""],
            'bearer',
            $dockRaw
        );

        return PersonAccountAlias::create([
            'CardId' => $card->Id,
            'PersonAccountId' => PersonAccount::where('ExternalId', $person_account['external_id'])->first()->Id,
            'ExternalId' => $response->id,
            'ClientId' => $response->client_id,
            'BookId' => $response->book_id
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

    private function fillSensitiveData(Card $card)
    {
        try {
            if ($card->Pan == null || $card->Pan == "") {
                $response = DockApiService::request(
                    ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card->ExternalId . '/data',
                    'GET',
                    [],
                    [],
                    'bearer',
                    null
                );

                Card::where('Id', $card->Id)->update([
                    'Pan' => $this->encrypter->encrypt($this->dock_encrypter->decrypt($response->aes, $response->iv, $response->pan)),
                    'CVV' => $this->encrypter->encrypt($this->dock_encrypter->decrypt($response->aes, $response->iv, $response->cvv)),
                    'ExpirationDate' => $this->encrypter->encrypt($this->dock_encrypter->decrypt($response->aes, $response->iv, $response->expiration_date))
                ]);

                $card = Card::where('Id', $card->Id)->first();
            }
            return $card;
        } catch (Exception $e) {
            return $card;
        }
    }

    private function getPin($card_id)
    {
        try {
            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/cards/' . $card_id . '/pin',
                'GET',
                [],
                [],
                'bearer',
                null
            );

            return $this->dock_encrypter->decrypt($response->aes, $response->iv, $response->pin);
        } catch (Exception $e) {
            return null;
        }
    }
}
