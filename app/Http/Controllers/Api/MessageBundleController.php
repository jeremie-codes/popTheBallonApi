<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\MessageBundle;
use App\Models\MessageBundleRequest;
use App\Models\MessageCredit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\ExpoNotificationService;
use App\Services\FlexpaieService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\json;

class MessageBundleController extends Controller
{
    public function index()
    {
        return response()->json(
            MessageBundle::query()
                ->where('active', true)
                ->orderBy('price')
                ->get()
                ->map(fn(MessageBundle $bundle) => [
                    'id' => (string) $bundle->id,
                    'title' => $bundle->title,
                    'messages' => $bundle->messages,
                    'price' => rtrim(rtrim((string) $bundle->price, '0'), '.') . ' ' . $this->currencySymbol($bundle->currency),
                    'popular' => (bool) $bundle->popular,
                    'description' => $bundle->description ?? '',
                ])
        );
    }

    public function requestBundle(Request $request, ExpoNotificationService $expo)
    {
        try {
            $data = $request->validate([
                'requester_id' => ['nullable', 'exists:users,id'],
                'requested_user_id' => ['required', 'exists:users,id'],
            ]);

            $requester = $request->user();
            $requested = User::query()->findOrFail($data['requested_user_id']);

            $actor = $request->user('sanctum');
            $targetId = (int) $data['requested_user_id'];
            $target = User::query()->with('devices')->find($targetId);

            MessageBundleRequest::query()->create([
                'requester_id' => $data['requester_id'] ?? $requester->id,
                'requested_user_id' => $requested->id,
            ]);

            AppNotification::query()->create([
                'user_id' => $requested->id,
                'title' => 'Demande de forfait',
                'message' => $requester->displayName() . ' vous demande de lui acheter un forfait messages pour discuter.',
                'kind' => 'bundle_request',
                'profile_id' => $requester->id,
            ]);

            foreach ($target->devices as $device) {
                $expo->send(
                    $device->expo_token,
                    'PopTheBallon - Nouvelle notification',
                    $actor->displayName() . ' aime votre profil, cliquez pour voir.',
                    [
                        'type' => 'like',
                        'user_id' => $actor->id,
                    ]
                );
            }

            return response()->json(['success' => true, 'message' => 'Demande de forfait envoyee.'], 201);
        } catch (\Throwable $e) {
            logger()->error('MessageBundleController.requestBundle error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    public function purchaseBundle(MessageBundle $MessageBundle, Request $request, FlexpaieService $fxp)
    {

        try {
            $data = $request->validate([
                'profile_id' => ['nullable', 'exists:users,id'],
            ]);

            $user = $request->user('sanctum');

            if (!$MessageBundle) {
                return response()->json(['message' => 'Forfait introuvable'], 404);
            }

            //$fxp->purchase($user, $MessageBundle->price, $MessageBundle->currency);

            // Logique d'achat du forfait pour l'utilisateur ciblé (à implémenter selon votre logique métier)
            // si profile_id est fourni, c'est une demande d'achat pour un autre utilisateur, sinon c'est pour soi-même

            AppNotification::query()->create([
                'user_id' => $data['profile_id'] ?? $user->id,
                'title' => 'Demande de forfait',
                'message' => $data['profile_id'] ? $user->displayName() . ' vous a acheté un forfait messages ' . $MessageBundle->title . ' pour discuter avec lui.'
                    : 'Vous avez acheté un forfait messages ' . $MessageBundle->title . ' pour discuter avec vos matchs.',
                'kind' => 'bundle_purchase',
                'profile_id' => $user->id,
            ]);

            return response()->json(['success' => true, 'message' => 'Demande de forfait envoyee.'], 201);
        } catch (\Throwable $e) {
            logger()->error('MessageBundleController.purchaseBundle error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    private function currencySymbol(string $currency): string
    {
        return strtoupper($currency) === 'USD' ? '$' : $currency;
    }

    public function initiate(
        Request $request,
        FlexpaieService $flexpay
    ) {
        try {
            $rules = [
                'user_id' => ['required', 'exists:users,id'],
                'bundle_id' => ['required', 'exists:message_bundles,id'],
                'currency' => ['required', 'in:USD,CDF'],
                'method' => ['required', 'in:mobile,card'],
            ];

            if ($request->method === 'mobile') {
                $rules['phone'] = ['required', 'string', 'size:12', 'regex:/^243[0-9]{9}$/',];
            }

            $data = $request->validate($rules);
            $user = User::findOrFail($data['user_id']);
            $bundle = MessageBundle::findOrFail($data['bundle_id']);
            $reference = 'MB-' . uniqid();

            if ($data['method'] === 'mobile') {

                $response = $flexpay->mobilePayment(
                    reference: $reference,
                    amount: $bundle->price,
                    phone: $data['phone'],
                    currency: $data['currency'],
                    callbackUrl: route('payments.callback', ['reference' => $reference]),
                );
            } else {

                $response = $flexpay->cardPayment(
                    reference: $reference,
                    amount: $bundle->price,
                    currency: $data['currency'],
                    callbackUrl: route('payments.callback', ['reference' => $reference]),
                    approveUrl: route('payments.success', ['reference' => $reference]),
                    cancelUrl: route('payments.canceled', ['reference' => $reference]),
                    declineUrl: route('payments.declined',  ['reference' => $reference]),
                );
            }

            Transaction::create([
                'user_id' => $user->id,
                'bundle_id' => $bundle->id,
                'reference' => $reference,
                'amount' => $bundle->price,
                'currency' => $data['currency'],
                'phone' => $data['phone'] ?? null,
                'payment_method' => $data['method'],
                'order_number' => $response['orderNumber'] ?? null,
                'status' => 'pending',
            ]);

            return response()->json([
                'code' => $response['code'],
                'message' => $response['message'] ?? 'Paiement initialisé',
                'redirect' => !empty($response['url']),
                'orderNumber' => $response['orderNumber'] ?? null,
                'url' => $response['url'] ?? null,
            ]);
        } catch (\Throwable $e) {

            logger()->error('Payment init error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 1,
                'message' => 'Erreur interne du serveur',
            ], 500);
        }
    }

    public function status(
        Request $request,
        FlexpaieService $flexpay
    ) {
        try {
            $data = $request->validate([
                'order_number' => ['required']
            ]);

            $transaction = Transaction::where('order_number', $data['order_number'])->first();
            if (!$transaction) {
                return response()->json([
                    'code' => 1,
                    'message' => 'Transaction introuvable',
                ], 404);
            }

            $response = $flexpay->getPaymentStatus(
                $transaction->order_number
            );

            $flexStatus = $response['transaction']['status'] ?? 2;

            // 0 -> paiement effectué
            // 1 -> paiement échoué
            // 2 -> en attente
            // 4 -> annulé

            switch ($flexStatus) {
                case 0:
                    if ($transaction->status !== 'success') {
                        DB::transaction(function () use (
                            $transaction
                        ) {

                            $transaction->update([
                                'status' => 'success'
                            ]);

                            $credit = MessageCredit::firstOrCreate(
                                [
                                    'user_id' => $transaction->user_id
                                ],
                                [
                                    'total_messages' => 0,
                                    'available_messages' => 0
                                ]
                            );

                            $messages = $transaction
                                ->bundle
                                ->messages;

                            $credit->increment(
                                'total_messages',
                                $messages
                            );

                            $credit->increment(
                                'available_messages',
                                $messages
                            );
                        });
                    }
                    break;
                case 1:
                    $transaction->update([
                        'status' => 'failed'
                    ]);
                    break;
                case 2:
                    $transaction->update([
                        'status' => 'pending'
                    ]);
                    break;
                case 4:
                    $transaction->update([
                        'status' => 'cancelled'
                    ]);
                    break;
                default:
                    logger()->warning('Statut FlexPay inconnu', [
                        'order_number' => $transaction->order_number,
                        'status' => $flexStatus,
                        'response' => $response,
                    ]);
                    break;
            }

            return response()->json([
                'code' => 0,
                'transaction' => [
                    'status' => $flexStatus
                ]
            ]);
        } catch (\Throwable $e) {

            logger()->error('Payment status error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 1,
                'message' => 'Erreur interne du serveur',
            ], 500);
        }
    }

    public function callback(Request $request, $reference)
    {
        try {
            $content = json_decode($request->getContent(), true);
            $transaction = Transaction::where('reference', $reference)->first();
            $flexStatus = $content['status'] ?? 2;

            if (!$transaction) {
                Log::warning(
                    "Transaction introuvable : $reference"
                );

                return response()->json([
                    'success' => false
                ],404);
            }

            if ($transaction) {
                switch ($flexStatus) {
                    case 0:
                        if ($transaction->status === 'success') {
                            break;
                        }

                        DB::transaction(function () use ($transaction) {

                            $transaction->update([
                                'status' => 'success'
                            ]);

                            $credit = MessageCredit::firstOrCreate(
                                [
                                    'user_id' => $transaction->user_id
                                ],
                                [
                                    'total_messages' => 0,
                                    'available_messages' => 0
                                ]
                            );

                            $messages = $transaction
                                ->bundle
                                ->messages;

                            $credit->increment(
                                'total_messages',
                                $messages
                            );

                            $credit->increment(
                                'available_messages',
                                $messages
                            );
                        });

                        break;
                    case 1:
                        $transaction->update([
                            'status' => 'failed'
                        ]);
                        break;
                    case 2:
                        $transaction->update([
                            'status' => 'pending'
                        ]);
                        break;
                    case 4:
                        $transaction->update([
                            'status' => 'cancelled'
                        ]);
                        break;
                    default:
                        logger()->warning('Statut FlexPay inconnu', [
                            'order_number' => $transaction->order_number,
                            'status' => $flexStatus,
                            'response' => $content,
                        ]);
                        break;
                }
            }

            Log::error('Callback received: ' . $reference . ' - ' . json_encode($request->all()));
            return response()->json([
                'success' => true
            ]);
        } catch (\Throwable $e) {
            Log::error('Callback error: ' . $e->getMessage());
        }
    }

    public function success($reference)
    {
        $transaction = Transaction::where('reference', $reference)->first();

        if ($transaction && $transaction->status == 'pending') {
            DB::transaction(function () use (
                $transaction
            ) {
                $transaction->update([
                    'status' => 'success'
                ]);

                $credit = MessageCredit::firstOrCreate(
                    [
                        'user_id' => $transaction->user_id
                    ],
                    [
                        'total_messages' => 0,
                        'available_messages' => 0
                    ]
                );

                $messages = $transaction
                    ->bundle
                    ->messages;

                $credit->increment(
                    'total_messages',
                    $messages
                );

                $credit->increment(
                    'available_messages',
                    $messages
                );
            });
            return response()->json([
                'status' => 'success',
                'message' => 'Paiement effectué',
            ]);
        }

        return response()->json([
            'message' => 'Paiement déjà clôturé',
        ]);
    }

    public function cancel($reference)
    {
        $transaction = Transaction::where('reference', $reference)->first();

        if ($transaction && $transaction->status == 'pending') {
            $transaction->update(['status' => 'failed']);
            return response()->json([
                'status' => 'cancelled',
                'message' => 'Paiement annulé',
            ]);
        }

        return response()->json([
            'message' => 'Paiement déjà clôturé',
        ]);
    }

    public function decline($reference)
    {
        $transaction = Transaction::where('reference', $reference)->first();

        if ($transaction && $transaction->status == 'pending') {
            $transaction->update(['status' => 'failed']);
            return response()->json([
                'status' => 'declined',
                'message' => 'Paiement échoué',
            ]);
        }

        return response()->json([
            'message' => 'Paiement déjà clôturé',
        ]);
    }
}
