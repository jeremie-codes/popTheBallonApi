<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('username')->nullable()->unique()->after('email');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->unique()->after('username');
            $table->date('birth_date')->nullable()->after('password');
            $table->string('gender')->nullable()->after('birth_date');
            $table->string('city')->nullable()->after('gender');
            $table->string('country')->nullable()->after('city');
            $table->string('intention')->nullable()->after('country');
            $table->text('bio')->nullable()->after('intention');
            $table->boolean('verified')->default(false)->after('bio');
            $table->timestamp('last_seen_at')->nullable()->after('verified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'first_name',
                'last_name',
                'username',
                'phone',
                'birth_date',
                'gender',
                'city',
                'country',
                'intention',
                'bio',
                'verified',
                'last_seen_at',
            ]);
        });
    }
};
