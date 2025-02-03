<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //
    /**
     * Register a new user
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['password'] = Hash::make($data['password']);
        $data['username'] = strstr($data['email'], '@', true);

        $user = User::create($data);
        $token = $user->createToken(User::USER_TOKEN);

        return $this->success([
            'user' => $user,
            'token' => $token->plainTextToken,
        ], 'User registered successfully');
    }

    /**
     * Login a user
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $isValid = $this->isValidCredential($request);

        if (!$isValid['success']) {
            return $this->error($isValid['message'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $isValid['user'];

        $token = $user->createToken(User::USER_TOKEN);

        return $this->success([
            'user' => $user,
            'token' => $user->createToken(User::USER_TOKEN)->plainTextToken,
        ], 'User logged in successfully');
    }

    /**
     * Check if the credential is valid
     *
     * @param LoginRequest $request
     * @return array
     */
    public function isValidCredential(LoginRequest $request): array
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if ($user === null) {
            return [
                'success' => false,
                'message' => 'Invalid credentials',
            ];
        }

        if (Hash::check($data['password'], $user->password)) {
            return [
                'success' => true,
                'user' => $user,
            ];
        }
        // if password not match
        return [
            'success' => false,
            'message' => 'Password is not matched',
        ];
    }

    /**
     * Login a user with token
     *
     * @return JsonResponse
     */
    public function loginWithToken(): JsonResponse
    {
        return $this->success(auth()->user(), 'User logged in successfully');
    }

    /**
     * Logout a user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'User logged out successfully!');
    }
}
