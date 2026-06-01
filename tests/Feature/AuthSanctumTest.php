<?php

use App\Models\Conversation;
use App\Models\Story;
use App\Models\StoryMedia;
use App\Models\User;
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

test('stories are grouped by user', function () {
    $user = User::factory()->create();
    $firstStory = Story::query()->create([
        'user_id' => $user->id,
        'expires_at' => now()->addDay(),
    ]);
    $secondStory = Story::query()->create([
        'user_id' => $user->id,
        'expires_at' => now()->addDay(),
    ]);

    StoryMedia::query()->create([
        'story_id' => $firstStory->id,
        'path' => 'storage/stories/first.jpg',
        'url' => '/storage/stories/first.jpg',
    ]);
    StoryMedia::query()->create([
        'story_id' => $secondStory->id,
        'path' => 'storage/stories/second.jpg',
        'url' => '/storage/stories/second.jpg',
    ]);

    $this
        ->getJson('/api/stories')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.profileId', (string) $user->id)
        ->assertJsonCount(2, '0.images');
});
