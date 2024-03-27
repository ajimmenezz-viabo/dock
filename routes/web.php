<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->group(['prefix' => 'api'], function () use ($router) {

    $router->group(['prefix' => 'auth'], function () use ($router) {
        $router->post('login', 'Authentication\AuthController@login');
        $router->post('logout', 'Authentication\AuthController@logout');
        $router->post('refresh', 'Authentication\AuthController@refresh');
        $router->post('me', 'Authentication\AuthController@me');
        // $router->post('register', 'Authentication\AuthController@register');
    });

    $router->group(["middleware" => "auth:api", 'prefix' => 'v1'], function () use ($router) {

        $router->group(['prefix' => 'person_management'], function () use ($router) {
            $router->get('/', 'PersonManagement\PersonController@index');
            $router->get('/{id}', 'PersonManagement\PersonController@show');
            $router->post('/', 'PersonManagement\PersonController@store');
            $router->put('/{id}', 'PersonManagement\PersonController@update');
            $router->delete('/{id}', 'PersonManagement\PersonController@destroy');


            $router->group(['prefix' => 'setup'], function () use ($router) {
                $router->post('/update_all', 'PersonManagement\SetupController@updateAll');
                $router->get('/status', 'PersonManagement\SetupController@status');
                $router->get('/gender', 'PersonManagement\SetupController@gender');
                $router->get('/address', 'PersonManagement\SetupController@address');
                $router->get('/phone', 'PersonManagement\SetupController@phone');
                $router->get('/marital_status', 'PersonManagement\SetupController@maritalStatus');
                $router->get('/email', 'PersonManagement\SetupController@email');
                $router->get('/document', 'PersonManagement\SetupController@document');
            });
        });

        $router->group(['prefix' => 'cards_management'], function () use ($router) {
            $router->get('/', 'CardsManagement\CardsController@index');
            $router->get('/{id}', 'CardsManagement\CardsController@show');
            $router->post('/', 'CardsManagement\CardsController@store');
            $router->put('/{id}', 'CardsManagement\CardsController@update');
            $router->delete('/{id}', 'CardsManagement\CardsController@destroy');
        });

        $router->group(['prefix' => 'card_profiles'], function () use ($router) {
            $router->post('/update_all', 'CardsManagement\ProfileController@updateAll');
            $router->get('/', 'CardsManagement\ProfileController@index');
            $router->get('/{id}', 'CardsManagement\ProfileController@show');
        });

        $router->group(['prefix' => 'embossing'], function () use ($router) {
            $router->group(['prefix' => 'setup'], function () use ($router) {
                $router->post('/update_all', 'EmbossingManagement\EmbossingController@updateAll');
                $router->get('/', 'EmbossingManagement\EmbossingController@index');
                $router->get('/{id}', 'EmbossingManagement\EmbossingController@show');
            });
        });

        $router->group(['prefix' => 'rsa'], function () use ($router) {
            $router->post('/generate', 'Caradhras\Security\RsaController@generate');
            $router->post('/upload', 'Caradhras\Security\RsaController@upload');
            $router->post('/update', 'Caradhras\Security\RsaController@update');
        });

        $router->group(['prefix' => 'aes'], function () use ($router) {
            $router->post('/generate', 'Caradhras\Security\AesController@generate');
            $router->get('/', 'Caradhras\Security\AesController@find');
        });

    });
});
