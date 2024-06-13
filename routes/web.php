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

$router->group(['prefix' => 'wh'], function () use ($router) {
    $router->group(['prefix' => 'v1', 'middleware' => 'auth_basic'], function () use ($router) {
        $router->post('dock_events', 'Webhooks\DockWebhookController@store');
    });
});

$router->group(['prefix' => 'dev'], function () use ($router) {
    $router->get('/decrypt', 'Security\Decrypt@decrypt');
    $router->post('/create_usr', 'Security\Dev@create_user');
    $router->post('/encrypt', 'Security\Dev@encrypt_data');
});

$router->group(['prefix' => 'authorizations'], function () use ($router) {
    $router->post('consult',  'Authorization\AuthorizationConsult@consult');
    $router->post('purchase', 'Authorization\AuthorizationPurchase@purchase');
    $router->post('deposit',  'Authorization\AuthorizationDeposit@deposit');
    $router->post('reversal', 'Authorization\AuthorizationReversal@reversal');
    $router->post('withdrawal', 'Authorization\AuthorizationWithdraw@withdraw');
    $router->post('payment',  'Authorization\AuthorizationPayment@payment');
    $router->post('advice',  'Authorization\AuthorizationAdvice@advice');

    $router->group(['prefix' => 'purchase'], function () use ($router) {
        $router->post('/', 'Authorization\AuthorizationPurchase@purchase');
    });

    $router->group(['prefix' => 'deposit'], function () use ($router) {
        $router->post('/', 'Authorization\AuthorizationDeposit@deposit');
    });

    $router->group(['prefix' => 'reversal'], function () use ($router) {
        $router->post('/', 'Authorization\AuthorizationReversal@reversal');
    });

    $router->group(['prefix' => 'withdrawal'], function () use ($router) {
        $router->post('/', 'Authorization\AuthorizationWithdraw@withdraw');
    });

    $router->group(['prefix' => 'payment'], function () use ($router) {
        $router->post('/', 'Authorization\AuthorizationPayment@payment');
    });

    $router->group(['prefix' => 'advice'], function () use ($router) {
        $router->post('/', 'Authorization\AuthorizationAdvice@advice');
    });

    $router->group(['prefix' => 'consult'], function () use ($router) {
        $router->post('/', 'Authorization\AuthorizationConsult@consult');
    });
});

$router->group(['prefix' => 'api'], function () use ($router) {

    $router->get('/documentation', function () use ($router) {
        return response()->json(['message' => 'Welcome to Caradhras API']);
    });

    $router->group(['prefix' => 'auth'], function () use ($router) {
        $router->post('login', 'Authentication\AuthController@login');
        $router->post('logout', 'Authentication\AuthController@logout');
        $router->post('refresh', 'Authentication\AuthController@refresh');
        $router->post('me', 'Authentication\AuthController@me');
    });

    $router->group(["middleware" => "auth:api", 'prefix' => 'v1'], function () use ($router) {

        $router->group(['prefix' => 'dock_webhook_register'], function () use ($router) {
            $router->get('/retrieve_key', 'Webhooks\DockRegisterController@retrieve_key');
            $router->post('/upload_aes_key', 'Webhooks\DockRegisterController@upload_aes_key');
            $router->post('/', 'Webhooks\DockRegisterController@store');
        });

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
            $router->get('/{uuid}', 'CardsManagement\CardsController@show');
            $router->post('/', 'CardsManagement\CardsController@store');

            $router->post('/{uuid}/block', 'CardsManagement\CardsController@block');
            $router->post('/{uuid}/unblock', 'CardsManagement\CardsController@unblock');

            $router->group(['prefix' => '{uuid}/sensitive'], function () use ($router) {
                $router->get('/', 'CardsManagement\CardsController@sensitive');
            });

            $router->group(['prefix' => '{uuid}/cvv'], function () use ($router) {
                $router->get('/', 'CardsManagement\CvvController@show');
            });

            $router->group(['prefix' => '{uuid}/setup'], function () use ($router) {
                $router->group(['prefix' => '{setup_name}'], function () use ($router) {
                    $router->post('/{action}', 'CardsManagement\CardsController@setSetup');
                });
            });
        });

        $router->group(['prefix' => 'account'], function () use ($router) {
            $router->get('/', 'Accounts\AccountController@index');
            $router->get('/movements', 'Accounts\AccountController@movements');
            $router->get('/cards', 'Accounts\AccountCardController@index');
            $router->post('/cards/assign', 'Accounts\AccountCardController@assign');
        });

        $router->group(['prefix' => 'subaccounts'], function () use ($router) {
            $router->get('/', 'Subaccounts\SubaccountController@index');
            $router->get('/{uuid}', 'Subaccounts\SubaccountController@show');
            $router->post('/', 'Subaccounts\SubaccountController@store');
            $router->put('/{uuid}', 'Subaccounts\SubaccountController@update');
            $router->get('/{uuid}/movements', 'Subaccounts\SubaccountController@movements');

            $router->get('/{uuid}/cards', 'Subaccounts\SubaccountCardController@index');
        });

        $router->group(['prefix' => 'card'], function () use ($router) {
            $router->get('/{uuid}', 'Card\MainCardController@show');
            $router->get('/{uuid}/movements', 'Card\MainCardController@movements');
            $router->get('/{uuid}/sensitive', 'CardsManagement\CardsController@sensitive');
            $router->get('/{uuid}/cvv', 'CardsManagement\CvvController@show');
            $router->post('/{uuid}/block', 'CardsManagement\CardsController@block');
            $router->post('/{uuid}/unblock', 'CardsManagement\CardsController@unblock');
        });

        $router->group(['prefix' => 'embossing_batches'], function () use ($router) {
            $router->get('/key', 'EmbossingManagement\EmbossingBatchController@getKey');
            $router->post('/key', 'EmbossingManagement\EmbossingBatchController@generateKey');

            $router->get('/', 'EmbossingManagement\EmbossingBatchController@index');
            $router->post('/', 'EmbossingManagement\EmbossingBatchController@batchEmbossing');
            $router->get('/{uuid}', 'EmbossingManagement\EmbossingBatchController@show');
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

        $router->group(['prefix' => 'transfer'], function () use ($router) {
            $router->post('/', 'Transfer\TransferController@transfer');
        });
    });

    $router->group(["middleware" => "auth_admin", 'prefix' => 'admin/v1'], function () use ($router) {
        $router->group(['prefix' => 'deposit'], function () use ($router) {
            $router->post('/account', 'Wallet\DepositController@to_account');
            $router->post('/subaccount', 'Wallet\DepositController@to_subaccount');
        });

        $router->group(['prefix' => 'reversal'], function () use ($router) {
            $router->post('/account', 'Wallet\ReversalController@from_account');
            $router->post('/subaccount', 'Wallet\ReversalController@from_subaccount');
        });

        $router->group(['prefix' => 'account'], function () use ($router) {
            $router->get('/{id}', 'Accounts\AccountAdminController@index');
            $router->get('/{account_id}/subaccounts', 'Subaccounts\SubaccountAdminController@index');
        });

        $router->group(['prefix' => 'subaccount'], function () use ($router) {
            $router->get('/{id}', 'Subaccounts\SubaccountAdminController@index');
        });
    });

    $router->group(["middleware" => "auth_beone", "prefix" => "b1"], function () use ($router) {
        $router->group(['prefix' => 'subaccount'], function () use ($router) {
            $router->get('/', 'BeOne\SubaccountController@index');
            $router->post('/', 'BeOne\SubaccountController@store');
            $router->get('/{uuid}', 'BeOne\SubaccountController@show');
            $router->get('/{uuid}/balance', 'BeOne\SubaccountController@balance');
            $router->get('/{uuid}/cards', 'BeOne\CardController@index');
            $router->get('/{uuid}/movements', 'BeOne\SubaccountController@movements');
        });

        $router->group(['prefix' => 'card'], function () use ($router) {
            $router->get('/{bin}', 'BeOne\CardController@show');
            $router->get('/{bin}/balance', 'BeOne\CardController@balance');
            $router->get('/{bin}/movements', 'BeOne\CardController@movements');
            $router->post('/{bin}/deposit', 'BeOne\CardController@deposit');
            $router->post('/{bin}/reverse', 'BeOne\CardController@reverse');
            $router->post('/{bin}/block', 'BeOne\CardController@block');
            $router->post('/{bin}/unblock', 'BeOne\CardController@unblock');
        });
    });
});
