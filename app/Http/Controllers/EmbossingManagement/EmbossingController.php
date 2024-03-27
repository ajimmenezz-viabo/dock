<?php

namespace App\Http\Controllers\EmbossingManagement;

use App\Http\Controllers\Controller;
use App\Services\DockApiService;
use App\Models\Embossing\Embossing;
use Exception;

class EmbossingController extends Controller
{
    public function index()
    {
        $embossing = $this->baseQuery();
        return $embossing->get();
    }

    public function show($id)
    {
        $embossing = $this->baseQuery()->where('Id', $id)->first();
        if (!$embossing) return response()->json(['error' => 'Embossing Setup Not Found'], 404);

        return $embossing;
    }

    public function updateAll()
    {
        try {
            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'embossing/v1/embossing/setup',
                'GET',
                [],
                [],
                'bearer'
            );

            $embossings = $response->content;


            foreach ($embossings as $em) {
                $date = new \DateTime($em->creation_date);

                $embossing = Embossing::where('ExternalId', $em->id)->first();
                if (!$embossing) {
                    Embossing::create([
                        'ExternalId' => $em->id,
                        'Embossing' => $em->name,
                        'Description' => $em->description
                    ]);
                } else if ($date > $embossing->update_date) {
                    $embossing->Embossing = $em->name;
                    $embossing->Description = $em->description;
                    $embossing->save();
                }
            }

            return response()->json(['message' => 'Embossing Setups was updated successfully'], 200);
        } catch (Exception $e) {
            return self::error("Error updating embossing setups", 500, $e);
        }
    }

    private function baseQuery()
    {
        return Embossing::select(
            'Id',
            'Embossing',
            'Description'
        );
    }
}
