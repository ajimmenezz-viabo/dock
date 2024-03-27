<?php

namespace App\Http\Controllers\CardsManagement;

use App\Http\Controllers\Controller;
use App\Services\DockApiService;
use App\Models\Card\Profile as CardsProfile;
use Exception;

class ProfileController extends Controller
{
    public function index()
    {
        $profile = $this->baseQuery();
        return $profile->get();
    }

    public function show($id)
    {
        $profile = $this->baseQuery()->where('Id', $id)->first();
        if (!$profile) return response()->json(['error' => 'Profile not found'], 404);

        return $profile;
    }

    public function updateAll()
    {
        try {
            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/profiles',
                'GET',
                [],
                [],
                'bearer'
            );

            $profiles = $response->content;


            foreach ($profiles as $p) {
                $date = new \DateTime($p->creation_date);

                $profile = CardsProfile::where('ExternalId', $p->id)->first();
                if (!$profile) {
                    CardsProfile::create([
                        'ExternalId' => $p->id,
                        'Profile' => $p->name,
                        'Brand' => $p->brand,
                        'ProductType' => $p->product_type
                    ]);
                } else if ($date > $profile->updated_at) {
                    $profile->Profile = $p->name;
                    $profile->Brand = $p->brand;
                    $profile->ProductType = $p->product_type;
                    $profile->save();
                }
            }

            return response()->json(['message' => 'Profiles updated successfully'], 200);
        } catch (Exception $e) {
            return self::error("Error updating profiles", 500, $e);
        }
    }

    private function baseQuery()
    {
        return CardsProfile::select(
            'Id',
            'Profile',
            'Brand',
            'ProductType'
        );
    }
}
