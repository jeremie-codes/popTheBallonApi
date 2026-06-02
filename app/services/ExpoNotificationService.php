<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ExpoNotificationService
{
    public function send(
        string $token,
        string $title,
        string $body,
        array $data = []
    ): void {

        Http::post(
            'https://exp.host/--/api/v2/push/send',
            [
                'to' => $token,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]
        );
    }
}
