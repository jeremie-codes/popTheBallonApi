<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FlexpaieService
{
    const BASE_URL = "https://corporateapi.flexpay.cd/api/rest/v1/paymentService";
    const BASE_URL_CHECK = "https://apicheck.flexpaie.com/api/rest/v1/check/";

    const SUCCESS = 0;

    public function mobilePayment(
        $reference,
        $amount,
        $phone,
        $currency,
        $callbackUrl,
        $sender
    ): array {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.flexpay.token'),
            'Content-Type' => 'application/json',
        ])->post(self::BASE_URL, [
            'merchant' => 'ORACLEZAPP',
            'type' => '1',
            'reference' => $reference,
            'description' => "Don payé par $sender",
            'phone' => $phone,
            'amount' => $amount,
            'currency' => $currency,
            'callback_url' => $callbackUrl,
        ]);

        logger()->info('FlexPay mobile', [
            'status' => $response->status(),
            'body' => $response->body(),
            'json' => $response->json(),
        ]);
        return $response->json() ?? [];
    }

    public function cardPayment($reference, $amount, $currency, $callbackUrl, $approveUrl, $cancelUrl, $declineUrl, $sender): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.flexpay.token'),
            'Content-Type' => 'application/json',
        ])->post(self::BASE_URL, [
            'merchant' => 'ORACLEZAPP',
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'type' => "2",
            'description' => "Don payé par $sender",
            'callback_url' => $callbackUrl,
            'approve_url' => $approveUrl,
            'cancel_url' => $cancelUrl,
            'decline_url' => $declineUrl,
        ]);

        logger()->info('FlexPay mobile', [
            'status' => $response->status(),
            'body' => $response->body(),
            'json' => $response->json(),
        ]);
        return $response->json();
    }

    public function getPaymentStatus(string $ordernumber): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.flexpay.token'),
        ])->get(self::BASE_URL_CHECK . $ordernumber);

        return $response->json();
    }
}
