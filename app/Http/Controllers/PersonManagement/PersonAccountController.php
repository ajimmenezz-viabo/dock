<?php

namespace App\Http\Controllers\PersonManagement;

use App\Http\Controllers\Controller;
use App\Services\DockApiService;
use App\Models\Person\PersonAccount;

class PersonAccountController extends Controller
{
    public static function store($person, $external_id)
    {
        $dockRaw = self::personAccountDockRaw($external_id);

        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'account-services/management/v1/accounts',
            'POST',
            [],
            [],
            'bearer',
            $dockRaw
        );

        $person_account = PersonAccount::create([
            'PersonId' => $person->Id,
            'ExternalId' => $response->id,
            'ClientId' => $response->client_id,
            'BookId' => $response->book_id
        ]);

        return $person_account;
    }

    private static function personAccountDockRaw($external_id)
    {
        return [
            'person_id' => $external_id,
            'product_id' => env('DOCK_PRODUCT_ID')
        ];
    }
}
