<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * APIログイン
     * 
     * メールアドレスとパスワードを検証し、
     * SanctumのBearerトークンを発行する。
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (
            !$user ||
            !Hash::check($credentials['password'], $user->password)
        ) {
            return response()->json([
                'message' => 'メールアドレスまたはパスワードが正しくありません。',
            ], 401);
        }

        $token = $user->createToken('bookshelf-api')->plainTextToken;

        return response()->json([
            'message' => 'ログインしました。',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * APIログアウト
     * 
     * リクエストで使用したBearerトークンのみを失効する。
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if (!$token instanceof PersonalAccessToken) {
            return response()->json([
                'message' => '有効なBearerトークンが必要です。',
            ], 401);
        }

        $token->delete();

        return response()->json([
            'message' => 'ログアウトしました。',
        ]);
    }
}