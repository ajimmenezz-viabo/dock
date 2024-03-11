<?php

namespace App\Http\Controllers\PersonManagement;

use App\Http\Controllers\Controller;
use App\Services\DockApiService;
use App\Models\Person\PersonSetup;
use Exception;

class SetupController extends Controller
{

    public function updateAll()
    {
        try {
            $this->updateStatus();
            $this->updateGender();
            $this->updateAddress();
            $this->updatePhone();
            $this->updateMaritalStatus();
            $this->updateEmail();
            $this->updateDocument();
            return response()->json(['message' => 'Setup updated successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error updating setup', 'error' => $e->getMessage()], 500);
        }
    }

    public function status()
    {
        return $this->baseQuery('STATUS');
    }

    public function gender()
    {
        return $this->baseQuery('GENDER');
    }

    public function address()
    {
        return $this->baseQuery('ADDRESS');
    }

    public function phone()
    {
        return $this->baseQuery('PHONE');
    }

    public function maritalStatus()
    {
        return $this->baseQuery('MARITAL_STATUS');
    }

    public function email()
    {
        return $this->baseQuery('EMAIL');
    }

    public function document()
    {
        return $this->baseQuery('DOCUMENT');
    }

    private function baseQuery($setup_type)
    {
        return PersonSetup::where('Category', $setup_type)
            ->select(
                'ExternalId as Id',
                'Description as Label'
            )->get();
    }

    private function updateStatus()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'person/v1/setup/types',
            'GET',
            [
                'category' => 'STATUS'
            ],
            [],
            'bearer'
        );

        $status = json_decode($response)->content;
        foreach ($status as $s) {
            $setup = PersonSetup::where('Category', 'STATUS')->where('ExternalId', $s->id)->first();
            if (!$setup) {
                $date = new \DateTime($s->creation_date);

                $setup = new PersonSetup();
                $setup->ExternalId = $s->id;
                $setup->Category = $s->category;
                $setup->Description = $s->description;
                $setup->ExternalCreatedAt = $date->format('Y-m-d H:i:s');
                $setup->save();
            }
        }
    }

    private function updateGender()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'person/v1/setup/types',
            'GET',
            [
                'category' => 'GENDER'
            ],
            [],
            'bearer'
        );

        $status = json_decode($response)->content;
        foreach ($status as $s) {
            $setup = PersonSetup::where('Category', 'GENDER')->where('ExternalId', $s->id)->first();
            if (!$setup) {
                $date = new \DateTime($s->creation_date);

                $setup = new PersonSetup();
                $setup->ExternalId = $s->id;
                $setup->Category = $s->category;
                $setup->Description = $s->description;
                $setup->ExternalCreatedAt = $date->format('Y-m-d H:i:s');
                $setup->save();
            }
        }
    }

    private function updateAddress()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'person/v1/setup/types',
            'GET',
            [
                'category' => 'ADDRESS'
            ],
            [],
            'bearer'
        );

        $status = json_decode($response)->content;
        foreach ($status as $s) {
            $setup = PersonSetup::where('Category', 'ADDRESS')->where('ExternalId', $s->id)->first();
            if (!$setup) {
                $date = new \DateTime($s->creation_date);

                $setup = new PersonSetup();
                $setup->ExternalId = $s->id;
                $setup->Category = $s->category;
                $setup->Description = $s->description;
                $setup->ExternalCreatedAt = $date->format('Y-m-d H:i:s');
                $setup->save();
            }
        }
    }

    private function updatePhone()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'person/v1/setup/types',
            'GET',
            [
                'category' => 'PHONE'
            ],
            [],
            'bearer'
        );

        $status = json_decode($response)->content;
        foreach ($status as $s) {
            $setup = PersonSetup::where('Category', 'PHONE')->where('ExternalId', $s->id)->first();
            if (!$setup) {
                $date = new \DateTime($s->creation_date);

                $setup = new PersonSetup();
                $setup->ExternalId = $s->id;
                $setup->Category = $s->category;
                $setup->Description = $s->description;
                $setup->ExternalCreatedAt = $date->format('Y-m-d H:i:s');
                $setup->save();
            }
        }
    }

    private function updateMaritalStatus()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'person/v1/setup/types',
            'GET',
            [
                'category' => 'MARITAL_STATUS'
            ],
            [],
            'bearer'
        );

        $status = json_decode($response)->content;
        foreach ($status as $s) {
            $setup = PersonSetup::where('Category', 'MARITAL_STATUS')->where('ExternalId', $s->id)->first();
            if (!$setup) {
                $date = new \DateTime($s->creation_date);

                $setup = new PersonSetup();
                $setup->ExternalId = $s->id;
                $setup->Category = $s->category;
                $setup->Description = $s->description;
                $setup->ExternalCreatedAt = $date->format('Y-m-d H:i:s');
                $setup->save();
            }
        }
    }

    private function updateEmail()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'person/v1/setup/types',
            'GET',
            [
                'category' => 'EMAIL'
            ],
            [],
            'bearer'
        );

        $status = json_decode($response)->content;
        foreach ($status as $s) {
            $setup = PersonSetup::where('Category', 'EMAIL')->where('ExternalId', $s->id)->first();
            if (!$setup) {
                $date = new \DateTime($s->creation_date);

                $setup = new PersonSetup();
                $setup->ExternalId = $s->id;
                $setup->Category = $s->category;
                $setup->Description = $s->description;
                $setup->ExternalCreatedAt = $date->format('Y-m-d H:i:s');
                $setup->save();
            }
        }
    }

    private function updateDocument()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'person/v1/setup/types',
            'GET',
            [
                'category' => 'DOCUMENT'
            ],
            [],
            'bearer'
        );

        $status = json_decode($response)->content;
        foreach ($status as $s) {
            $setup = PersonSetup::where('Category', 'DOCUMENT')->where('ExternalId', $s->id)->first();
            if (!$setup) {
                $date = new \DateTime($s->creation_date);

                $setup = new PersonSetup();
                $setup->ExternalId = $s->id;
                $setup->Category = $s->category;
                $setup->Description = $s->description;
                $setup->ExternalCreatedAt = $date->format('Y-m-d H:i:s');
                $setup->save();
            }
        }
    }
}
