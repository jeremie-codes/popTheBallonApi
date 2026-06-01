<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('messages');
            $table->decimal('price', 10, 2);
            $table->string('currency', 8)->default('USD');
            $table->text('description')->nullable();
            $table->boolean('popular')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('message_bundle_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'read'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_bundle_requests');
        Schema::dropIfExists('message_bundles');
    }
};
