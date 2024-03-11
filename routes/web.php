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

    $router->group(['prefix' => 'test'], function () use ($router) {
        $router->post('/', 'TestController@test');
    });

    $router->group(["middleware" => "auth:api"], function () use ($router) {
        $router->group(['prefix' => 'person_management'], function () use ($router) {
            $router->group(['prefix' => 'legal'], function () use ($router) {
                $router->get('/', 'PersonManagement\LegalPersonController@index');
                $router->get('/{id}', 'PersonManagement\LegalPersonController@show');
                $router->post('/', 'PersonManagement\LegalPersonController@store');
                $router->put('/{id}', 'PersonManagement\LegalPersonController@update');
                $router->delete('/{id}', 'PersonManagement\LegalPersonController@destroy');
            });


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
    });
});
