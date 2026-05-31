<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\InteractionController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MessageBundleController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\SupportRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('check-username', [AuthController::class, 'checkUsername']);
    Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);
});

Route::prefix('otp')->group(function () {
    Route::post('generate-login', [OtpController::class, 'generateLogin']);
    Route::post('login', [OtpController::class, 'login']);
});

Route::get('profiles/discover', [ProfileController::class, 'discover']);
Route::get('profiles/{user}', [ProfileController::class, 'show']);
Route::get('stories', [StoryController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [ProfileController::class, 'me']);
    Route::patch('me', [ProfileController::class, 'update']);
    Route::get('profiles/liked-me', [ProfileController::class, 'likedMe']);
    Route::post('me/profile-photo', [ProfileController::class, 'uploadPhoto']);

    Route::get('me/stories', [StoryController::class, 'mine']);
    Route::post('me/stories', [StoryController::class, 'store']);

    Route::post('likes', [InteractionController::class, 'like']);
    Route::post('pops', [InteractionController::class, 'pop']);

    Route::get('matches', [ConversationController::class, 'matches']);
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);

    Route::get('message-bundles', [MessageBundleController::class, 'index']);
    Route::post('message-bundle-requests', [MessageBundleController::class, 'requestBundle']);

    Route::get('marketplace-items', [MarketplaceController::class, 'index']);
    Route::post('support-requests', [SupportRequestController::class, 'store']);
});
