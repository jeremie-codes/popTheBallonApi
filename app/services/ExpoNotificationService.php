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
            logger()->info('Expo push sending', [
                'token' => substr($token, 0, 10) . '...',
                'title' => $title,
                'data' => $data,
            ]);

            $response = $this->httpClient->post(
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

            logger()->info('Expo push sent successfully', [
                'token' => substr($token, 0, 10) . '...',
                'status' => $response->getStatusCode(),
            ]);
        } catch (\Throwable $e) {
            // Ne pas faire échouer la requête principale — journaliser l'erreur
            logger()->error('Expo push send failed', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
