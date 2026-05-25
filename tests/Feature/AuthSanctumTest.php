<?php

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('login issues a sanctum token', function () {
    $user = User::factory()->create([
        'email' => 'amina@example.com',
        'username' => 'amina',
        'password' => Hash::make('password-secret'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'identifier' => 'amina',
        'password' => 'password-secret',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('code', 'auth-ok')
        ->assertJsonStructure(['token', 'expire_in', 'user' => ['id', 'username']]);

    expect($response->json('token'))->toContain('|')
        ->and($user->tokens()->count())->toBe(1);
});

test('protected api routes require sanctum authentication', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();

    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/notifications')
        ->assertOk();
});

test('authenticated user can update their profile', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/me', [
            'first_name' => 'Amina',
            'last_name' => 'Mbala',
            'city' => 'Kinshasa',
            'country' => 'RDC',
            'bio' => 'Une bio complete pour le profil.',
            'interests' => ['Musique', 'Voyage', 'Cuisine'],
        ])
        ->assertOk()
        ->assertJsonPath('firstName', 'Amina')
        ->assertJsonPath('city', 'Kinshasa');

    expect($response->json('interests'))->toContain('Musique', 'Voyage', 'Cuisine')
        ->and($user->refresh()->interests()->count())->toBe(3);
});

test('authenticated user can send a message in their conversation', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $conversation = Conversation::query()->create([
        'user_one_id' => $sender->id,
        'user_two_id' => $receiver->id,
    ]);
    $token = $sender->createToken('mobile')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Salut, ravi de te parler.',
        ])
        ->assertCreated()
        ->assertJsonPath('text', 'Salut, ravi de te parler.')
        ->assertJsonPath('mine', true);

    expect($conversation->messages()->count())->toBe(1)
        ->and($conversation->refresh()->last_message_at)->not->toBeNull();
});
