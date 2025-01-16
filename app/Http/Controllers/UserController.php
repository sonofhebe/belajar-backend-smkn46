<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'bio' => 'required|string|max:100',
            'username' => 'required|string|min:3|max:100|unique:users,username|regex:/^[a-zA-Z0-9._]+$/',
            'password' => 'required|string|min:6|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'massage' => 'Invalid Field',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'full_name' => $request->full_name,
            'bio' => $request->bio,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'is_private' => $request->is_private,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Register success',
            'token' => $token,
            'user' => [
                'full_name' => $user->full_name,
                'bio' => $user->bio,
                'username' => $user->username,
                'is_private' => $user->is_private,
                'id' => $user->id
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        // Validate the incoming data
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }


        $user = User::where('username', $request->username)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login success',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                    'bio' => $user->bio,
                    'is_private' => $user->is_private,
                ]
            ]);
        }

        // Authentication failed
        return response()->json([
            'message' => 'Wrong username or password'
        ], 401);
    }

    public function logout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // logout function
        $auth = Auth::user();
        if ($auth) {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logout success'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }
    }

    public function getUsers()
    {
        $users = User::select('id', 'full_name', 'username', 'bio', 'is_private', 'created_at', 'updated_at')->get();

        return response()->json(['users' => $users], 200);
    }

    public function getUserDetail(Request $request, $username)
    {
        // Cari pengguna berdasarkan username
        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Periksa apakah ini akun milik pengguna yang sedang login
        $isYourAccount = $request->user()->id === $user->id;

        // Ambil daftar postingan jika akun tidak privat atau milik pengguna sendiri
        $posts = [];
        $postsCount = 0;

        if (!$user->is_private || $isYourAccount) {
            $posts = Post::where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->get(['id', 'caption', 'created_at', 'deleted_at'])
                ->map(function ($post) {
                    $post->attachments = $post->attachments()->get(['id', 'storage_path']);
                    return $post;
                });

            $postsCount = $posts->count();
        }

        // Return data pengguna
        return response()->json([
            'id' => $user->id,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'bio' => $user->bio,
            'is_private' => $user->is_private,
            'created_at' => $user->created_at,
            'is_your_account' => $isYourAccount,
            'posts_count' => $postsCount,
            'posts' => $posts
        ], 200);
    }
}
