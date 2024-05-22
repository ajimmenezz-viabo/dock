<?php

namespace App\Http\Controllers\Authentication;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'refresh', 'logout']]);
    }

    /**
     * Get a JWT via given credentials.
     * 
     * @param Request $request
     * @return Response
     */

    /**
     *  @OA\Post(
     *      path="/api/auth/login",
     *      tags={"Authentication"},
     *      summary="Get a JWT via given credentials",
     *      description="Get a JWT via given credentials",
     * 
     *      @OA\Response(
     *          response="200", 
     *          description="Credentials are valid",
     *          @OA\JsonContent(
     *              @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV...", description="JWT token"),
     *              @OA\Property(property="token_type", type="string", example="bearer", description="Token type"),
     *              @OA\Property(property="expires_in", type="integer", example="3600", description="Token expiration time in seconds")
     *          )
     *     ),
     * 
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email","password"},
     *              @OA\Property(property="email", type="string", format="email", example="", description="User email"),
     *              @OA\Property(property="password", type="string", format="password", example="", description="User password")
     *          )
     *      ),
     *  )
     * 
     */
    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $credentials = $request->only(['email', 'password']);

        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->jsonResponse($token);
    }

    /**
     * Get the authenticated User.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * @OA\Post(
     *      path="/api/auth/logout",
     *      tags={"Authentication"},
     *      summary="Log the user out (Invalidate the token)",
     *      description="Log the user out (Invalidate the token)",
     * 
     *     security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response="200", 
     *          description="Successfully logged out",
     *          @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully logged out", description="Message")
     *          )
     *     ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *             @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *         )
     *      )
     *  )
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }


    /**
     * @OA\Post(
     *      path="/api/auth/refresh",
     *      tags={"Authentication"},
     *      summary="Refresh a token",
     *      description="Refresh a token",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response="200", 
     *          description="Token refreshed",
     *          @OA\JsonContent(
     *              @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV...", description="JWT token"),
     *              @OA\Property(property="token_type", type="string", example="bearer", description="Token type"),
     *              @OA\Property(property="expires_in", type="integer", example="3600", description="Token expiration time in seconds")
     *          )
     *     ),
     *     @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *             @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *         )
     *      )
     * 
     *  )
     */
    public function refresh()
    {
        return $this->jsonResponse(auth()->refresh());
    }

    /**
     * Get the token array structure.
     * 
     * @param string $token
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    protected function jsonResponse($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }
}
