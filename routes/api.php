<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [UserController::class, 'register']);
        Route::post('login', [UserController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [UserController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('posts', [PostController::class, 'createPost']);
        Route::delete('posts/{id}', [PostController::class, 'deletePost']);
        Route::get('posts', [PostController::class, 'getPosts']);

        Route::get('users', [UserController::class, 'getUsers']);
        Route::get('users/{username}', [UserController::class, 'getUserDetail']);

        Route::get('image/posts/{path}', [PostController::class, 'getImage']);
    });
});
