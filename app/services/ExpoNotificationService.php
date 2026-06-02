<?php

namespace App\Services;

use GuzzleHttp\Client;

class ExpoNotificationService
{
    private $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client();
    }

    public function send(
        string $token,
        string $title,
        string $body,
        array $data = []
    ): void {

        try {
            logger()->error('Expo push send done before post', [
                'token' => $token,
            ]);
            $this->httpClient->post(
                'https://exp.host/--/api/v2/push/send',
                [
                    'json' => [
                        'to' => $token,
                        'sound' => 'default',
                        'title' => $title,
                        'body' => $body,
                        'data' => $data,
                    ],
                    'timeout' => 5,
                ]
            );

            logger()->error('Expo push send done post', [
                'token' => $token,
            ]);
        } catch (\Throwable $e) {
            // Ne pas faire échouer la requête principale — journaliser l'erreur
            logger()->error('Expo push send failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
