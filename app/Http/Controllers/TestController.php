<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Caradhras\Auth\TokenController;

class TestController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function test()
    {
        return TokenController::get();
    }

    //
}
