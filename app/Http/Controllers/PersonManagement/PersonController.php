<?php

namespace App\Http\Controllers\PersonManagement;

use App\Http\Controllers\Controller;
use App\Services\DockApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

use App\Models\Shared\Country;

use App\Models\Address\Suffix;
use App\Models\Person\Person;
use App\Models\Person\PersonDocument;
use App\Models\Person\PersonPhone;
use App\Models\Person\PersonAddress;
use App\Models\Person\PersonEmail;
use App\Models\Person\PersonsSetup;
use App\Models\Person\PersonAccount;
use Exception;

class PersonController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = $request['page'] ?? 1;
            $limit = 100;
            $offset = ($page - 1) * $limit;

            $persons = Person::where('UserId', auth()->user()->Id)
                ->offset($offset)
                ->limit($limit)
                ->get();

            $object = [];

            foreach ($persons as $person) {
                array_push($object, $this->personObject($person->Id));
            }

            return response()->json([
                'persons' => $object,
                'page' => $page,
                'total_pages' => ceil(Person::where('UserId', auth()->user()->Id)->count() / $limit),
                'total_records' => Person::where('UserId', auth()->user()->Id)->count()
            ], 200);
        } catch (Exception $e) {
            return self::error('Error getting legal persons', 400, $e);
        }
    }

    public function show($id)
    {
        try {
            $person = Person::where('Id', $id)->where('UserId', auth()->user()->Id)->first();
            if (!$person) return response()->json(['message' => 'Person not found or you do not have permission to access it'], 404);

            return response()->json($this->personObject($person->Id), 200);
        } catch (Exception $e) {
            return self::error('Error getting legal person. Try again latter', 400, $e);
        }
    }

    public function store(Request $request)
    {
        if ($request['type'] == 'natural') {
            $this->validateNaturalPersonRequest($request);
        } else {
            $this->validateLegalPersonRequest($request);
        }

        try {
            DB::beginTransaction();

            if ($request['type'] == 'natural') {
                $person = Person::create([
                    'UUID' => Uuid::uuid7()->toString(),
                    'UserId' => auth()->user()->Id,
                    'PersonType' => '2', // '1' => 'legal', '2' => 'natural
                    'CountryId' => Country::where('Alpha2Code', $request['country_code'])->first()->Id ?? '140',
                    'FullName' => $request['full_name'],
                    'PreferredName' => $request['preferred_name'],
                    'MotherName' => $request['mother_name'] ?? null,
                    'FatherName' => $request['father_name'] ?? null,
                    'BirthDate' => $request['birth_date'],
                    'IsEmancipated' => $request['is_emancipated'],
                    'GenderId' => $request['gender_id'],
                    'MaritalStatusId' => $request['marital_status_id'],
                    'NationalityId' => Country::where('Alpha2Code', $request['nationality_code'])->first()->Id ?? '140'
                ]);
            } else {
                $person = Person::create([
                    'UUID' => Uuid::uuid7()->toString(),
                    'UserId' => auth()->user()->Id,
                    'PersonType' => '1', // '1' => 'legal', '2' => 'natural
                    'CountryId' => Country::where('Alpha2Code', $request['country_code'])->first()->Id ?? '140',
                    'LegalName' => $request['legal_name'],
                    'TradeName' => $request['trade_name'],
                    'RFC' => $request['rfc']
                ]);
            }

            $person_document = PersonDocument::create([
                'PersonId' => $person->Id,
                'CountryId' => Country::where('Alpha2Code', $request['document_country_code'])->first()->Id ?? '140',
                'TypeId' => $request['document_type_id'],
                'DocumentNumber' => $request['document_number'],
                'Main' => true
            ]);

            $person_phone = PersonPhone::create([
                'PersonId' => $person->Id,
                'CountryId' => Country::where('Alpha2Code', $request['phone_country_code'])->first()->Id ?? '140',
                'TypeId' => $request['phone_type_id'],
                'DialingCode' => $request['phone_dialing_code'],
                'AreaCode' => $request['phone_area_code'],
                'PhoneNumber' => $request['phone'],
                'Main' => true
            ]);

            $person_address = PersonAddress::create([
                'PersonId' => $person->Id,
                'CountryId' => Country::where('Alpha2Code', $request['address_country_code'])->first()->Id ?? '140',
                'TypeId' => $request['address_type_id'],
                'SuffixId' => $request['address_suffix_id'],
                'Street' => $request['address_street'],
                'Number' => $request['address_number'],
                'ZipCode' => $request['address_postal_code'],
                'City' => $request['address_city'],
                'Main' => true
            ]);

            $person_email = PersonEmail::create([
                'PersonId' => $person->Id,
                'TypeId' => $request['email_type_id'],
                'Email' => $request['email'],
                'Main' => true
            ]);

            $request['external_id'] = $person->UUID;


            $dockRaw = $this->registerDockRaw($request);

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'person/v1/' . ($request['type'] == 'natural' ? 'natural' : 'legal') . '-persons',
                'POST',
                [],
                [],
                'bearer',
                $dockRaw
            );

            Person::where('Id', $person->Id)->update(['ExternalId' => $response->person_id]);
            PersonDocument::where('Id', $person_document->Id)->update(['ExternalId' => $response->documents_ids[0]]);
            PersonPhone::where('Id', $person_phone->Id)->update(['ExternalId' => $response->phones_ids[0]]);
            PersonAddress::where('Id', $person_address->Id)->update(['ExternalId' => $response->addresses_ids[0]]);
            PersonEmail::where('Id', $person_email->Id)->update(['ExternalId' => $response->emails_ids[0]]);

            PersonAccountController::store($person, $response->person_id);

            DB::commit();

            return response()->json(['message' => 'Person created successfully', 'person' => $this->personObject($person->Id)], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error creating legal person', 400, $e);
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

    private function validateLegalPersonRequest($request)
    {
        $this->validate($request, [
            'country_code' => 'required|string',
            'legal_name' => 'required|string',
            'trade_name' => 'required|string',
            'rfc' => 'required|string',
            'document_type_id' => 'required|integer',
            'document_country_code' => 'required|string',
            'document_number' => 'required|string',
            'phone_type_id' => 'required|integer',
            'phone_country_code' => 'required|string',
            'phone_dialing_code' => 'required|string',
            'phone_area_code' => 'required|string',
            'phone' => 'required|string',
            'address_type_id' => 'required|integer',
            'address_suffix_id' => 'required|integer',
            'address_street' => 'required|string',
            'address_number' => 'required|string',
            'address_postal_code' => 'required|string',
            'address_city' => 'required|string',
            'address_country_code' => 'required|string',
            'email_type_id' => 'required|integer',
            'email' => 'required|string|email'
        ]);
    }

    private function validateNaturalPersonRequest($request)
    {
        $this->validate($request, [
            'country_code' => 'required|string',
            'full_name' => 'required|string',
            'preferred_name' => 'required|string',
            'birth_date' => 'required|date',
            'gender_id' => 'required|integer',
            'marital_status_id' => 'required|integer',
            'is_emancipated' => 'required|boolean',
            'nationality_code' => 'required|string',
            'document_type_id' => 'required|integer',
            'document_country_code' => 'required|string',
            'document_number' => 'required|string',
            'phone_type_id' => 'required|integer',
            'phone_country_code' => 'required|string',
            'phone_dialing_code' => 'required|string',
            'phone_area_code' => 'required|string',
            'phone' => 'required|string',
            'address_type_id' => 'required|integer',
            'address_suffix_id' => 'required|integer',
            'address_street' => 'required|string',
            'address_number' => 'required|string',
            'address_postal_code' => 'required|string',
            'address_city' => 'required|string',
            'address_country_code' => 'required|string',
            'email_type_id' => 'required|integer',
            'email' => 'required|string|email'
        ]);
    }


    private function registerDockRaw($request)
    {
        if ($request['type'] == 'natural') {
            $array_part = [
                'person_full_name' => $request['full_name'],
                'preferred_name' => $request['preferred_name'],
                'mother_name' => $request['mother_name'],
                'father_name' => $request['father_name'],
                'gender_id' => $request['gender_id'],
                'marital_status_id' => $request['marital_status_id'],
                'birth_date' => $request['birth_date'],
                'is_emancipated_minor' => $request['is_emancipated'],
                'nationality' => $request['nationality_code']
            ];
        } else {
            $array_part = [
                'legal_name' => $request['legal_name'],
                'trade_name' => $request['trade_name'],
                "metadata" => [
                    "RFC" => $request['rfc']
                ]
            ];
        }

        $base = [
            'external_id' => $request['external_id'],
            'country_code' => $request['country_code'],
            'status_id' => 1,

            'documents' => [
                [
                    'country_code' => $request['document_country_code'],
                    'type_id' => $request['document_type_id'],
                    'number' => $request['document_number'],
                    'is_main' => true
                ]
            ],
            'phones' => [
                [
                    'country_code' => $request['phone_country_code'],
                    'type_id' => $request['phone_type_id'],
                    'is_main' => true,
                    'dialing_code' => $request['phone_dialing_code'],
                    'area_code' => $request['phone_area_code'],
                    'number' => $request['phone']
                ]
            ],
            'addresses' => [
                [
                    'type_id' => $request['address_type_id'],
                    'is_main' => true,
                    'postal_code' => $request['address_postal_code'],
                    'suffix' => Suffix::find($request['address_suffix_id'])->Suffix,
                    'street' => $request['address_street'],
                    'number' => $request['address_number'],
                    'city' => $request['address_city'],
                    'country_code' => $request['address_country_code']
                ]
            ],
            'emails' => [
                [
                    'type_id' => $request['email_type_id'],
                    'is_main' => true,
                    'email' => $request['email']
                ]
            ]
        ];

        return array_merge($array_part, $base);
    }

    private function personObject($id)
    {
        $person = Person::where('Id', $id)->first();
        $person_documents = PersonDocument::where('PersonId', $id)->get();
        $person_phones = PersonPhone::where('PersonId', $id)->get();
        $person_addresses = PersonAddress::where('PersonId', $id)->get();
        $person_emails = PersonEmail::where('PersonId', $id)->get();
        $person_account = PersonAccount::where('PersonId', $id)->first();

        $object = [
            'person_id' => $person->Id,
            'person_type' => $person->PersonType == 1 ? 'legal' : 'natural',
            'status' => $person->Active == 1 ? 'active' : 'inactive',
            'person_account' => [
                'account_id' => $person_account->Id ?? null,
                'external_id' => $person_account->ExternalId ?? null,
                'client_id' => $person_account->ClientId ?? null,
                'book_id' => $person_account->BookId ?? null
            ],
            'legal_person_data' => [
                'legal_name' => $person->LegalName,
                'trade_name' => $person->TradeName,
                'rfc' => $person->RFC,
            ],
            'natural_person_data' => [
                'full_name' => $person->FullName,
                'preferred_name' => $person->PreferredName,
                'mother_name' => $person->MotherName,
                'father_name' => $person->FatherName,
                'birth_date' => $person->BirthDate,
                'is_emancipated' => $person->IsEmancipated == 1 ? true : false,
                'gender' => [
                    'id' => $person->GenderId,
                    'name' => $person->GenderId != "" ? PersonsSetup::where('ExternalId', $person->GenderId)->where('Category', 'GENDER')->first()->Description : null,
                ],
                'marital_status' => [
                    'id' => $person->MaritalStatusId,
                    'name' => $person->MaritalStatusId != "" ? PersonsSetup::where('ExternalId', $person->MaritalStatusId)->where('Category', 'MARITAL_STATUS')->first()->Description : null,
                ],
                'nationality' => [
                    'id' => $person->NationalityId,
                    'name' => $person->NationalityId != "" ? Country::where('Id', $person->NationalityId)->first()->Name : null
                ],
            ],
            'country' => [
                'code' => Country::where('Id', $person->CountryId)->first()->Alpha2Code,
                'name' => Country::where('Id', $person->CountryId)->first()->Name
            ],

            'documents' => [],
            'phones' => [],
            'addresses' => [],
            'emails' => []
        ];

        foreach ($person_documents as $document) {
            array_push($object['documents'], [
                'document_id' => $document->Id,
                'type' => [
                    'id' => $document->TypeId,
                    'description' => PersonsSetup::where('ExternalId', $document->TypeId)->where('Category', 'DOCUMENT')->first()->Description
                ],
                'country' => [
                    'code' => Country::where('Id', $document->CountryId)->first()->Alpha2Code,
                    'name' => Country::where('Id', $document->CountryId)->first()->Name
                ],
                'number' => $document->DocumentNumber,
                'main' => $document->Main == 1 ? true : false,
                'status' => $document->Active == 1 ? 'active' : 'inactive'
            ]);
        }

        foreach ($person_phones as $phone) {
            array_push($object['phones'], [
                'phone_id' => $phone->Id,
                'type' => [
                    'id' => $phone->TypeId,
                    'description' => PersonsSetup::where('ExternalId', $phone->TypeId)->where('Category', 'PHONE')->first()->Description
                ],
                'country' => [
                    'code' => Country::where('Id', $phone->CountryId)->first()->Alpha2Code,
                    'name' => Country::where('Id', $phone->CountryId)->first()->Name
                ],
                'dialing_code' => $phone->DialingCode,
                'area_code' => $phone->AreaCode,
                'number' => $phone->PhoneNumber,
                'main' => $phone->Main == 1 ? true : false,
                'status' => $phone->Active == 1 ? 'active' : 'inactive'
            ]);
        }

        foreach ($person_addresses as $address) {
            array_push($object['addresses'], [
                'address_id' => $address->Id,
                'type' => [
                    'id' => $address->TypeId,
                    'description' => PersonsSetup::where('ExternalId', $address->TypeId)->where('Category', 'ADDRESS')->first()->Description
                ],
                'suffix' => [
                    'id' => $address->SuffixId,
                    'name' => Suffix::where('Id', $address->SuffixId)->first()->Name
                ],
                'street' => $address->Street,
                'number' => $address->Number,
                'postal_code' => $address->ZipCode,
                'city' => $address->City,
                'country' => [
                    'code' => Country::where('Id', $address->CountryId)->first()->Alpha2Code,
                    'name' => Country::where('Id', $address->CountryId)->first()->Name
                ],
                'main' => $address->Main == 1 ? true : false,
                'status' => $address->Active == 1 ? 'active' : 'inactive'
            ]);
        }

        foreach ($person_emails as $email) {
            array_push($object['emails'], [
                'email_id' => $email->Id,
                'type' => [
                    'id' => $email->TypeId,
                    'description' => PersonsSetup::where('ExternalId', $email->TypeId)->where('Category', 'EMAIL')->first()->Description
                ],
                'email' => $email->Email,
                'main' => $email->Main == 1 ? true : false,
                'status' => $email->Active == 1 ? 'active' : 'inactive'
            ]);
        }

        return $object;
    }

    private function personObjectShort($id)
    {
        $person = Person::where('Id', $id)->first();
        $person_account = PersonAccount::where('PersonId', $id)->first();

        $object = [
            'person_id' => $person->Id,
            'person_external_id' => $person->ExternalId,
            'person_type' => $person->PersonType == 1 ? 'legal' : 'natural',
            'status' => $person->Active == 1 ? 'active' : 'inactive',
            'person_account' => [
                'account_id' => $person_account->Id ?? null,
                'external_id' => $person_account->ExternalId ?? null,
                'client_id' => $person_account->ClientId ?? null,
                'book_id' => $person_account->BookId ?? null
            ]
        ];

        if ($person->PersonType == 1) {
            $object['legal_person_data'] = [
                'legal_name' => $person->LegalName,
                'trade_name' => $person->TradeName,
                'rfc' => $person->RFC,
            ];
        } else if ($person->PersonType == 2) {
            $object['natural_person_data'] = [
                'full_name' => $person->FullName,
                'preferred_name' => $person->PreferredName
            ];
        }

        return $object;
    }

    public static function getPersonObject($id)
    {
        return (new self)->personObject($id);
    }

    public static function getPersonObjectShort($id)
    {
        return (new self)->personObjectShort($id);
    }
}
