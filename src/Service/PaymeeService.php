<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymeeService
{
    public function __construct(
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(PAYMEE_ACCOUNT_NUMBER)%')]
        private readonly string $accountNumber,
        #[Autowire('%env(PAYMEE_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(PAYMEE_BASE_URL)%')]
        private readonly string $liveBaseUrl,
        #[Autowire('%env(PAYMEE_SANDBOX_URL)%')]
        private readonly string $sandboxBaseUrl,
        #[Autowire('%env(bool:PAYMEE_USE_SANDBOX)%')]
        private readonly bool $useSandbox,
        #[Autowire('%env(PAYMEE_PUBLIC_BASE_URL)%')]
        private readonly ?string $publicBaseUrl = null,
    ) {}

    public function createCheckout(array $buyer, float $amount, string $note, string $orderId): array
    {
        if ($amount <= 0) {
            throw new BadRequestHttpException('Le montant Paymee doit être supérieur à zéro.');
        }

        $base = $this->getCallbackBaseUrl();
        $returnUrl = $base . '/cart/paymee/return';
        $cancelUrl = $base . '/cart/paymee/cancel';
        $webhookUrl = $base . '/cart/paymee/webhook';

        $firstName = $this->normalizeName((string) ($buyer['first_name'] ?? $buyer['prenom'] ?? ''), 'Test');
        $lastName = $this->normalizeName((string) ($buyer['last_name'] ?? $buyer['nom'] ?? ''), 'User');
        $email = $this->normalizeEmail((string) ($buyer['email'] ?? ''));
        $phone = $this->normalizePhone((string) ($buyer['phone'] ?? $buyer['num_tel'] ?? ''));

        $payload = [
            'amount' => round($amount, 2),
            'note' => trim($note) !== '' ? trim($note) : ('Commande ' . $orderId),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'webhook_url' => $webhookUrl,
            'order_id' => $orderId,
        ];

        $result = $this->curlRequest('POST', $this->getBaseUrl() . '/api/v2/payments/create', $payload, true);

        if (($result['ok'] ?? false) && !empty($result['data']['data']['token'])) {
            $data = $result['data']['data'];
            $token = (string) $data['token'];
            $paymentUrl = (string) ($data['payment_url'] ?? '');

            if ($paymentUrl === '') {
                $gatewayBase = $this->useSandbox
                    ? 'https://sandbox.paymee.tn/gateway/'
                    : 'https://app.paymee.tn/gateway/';
                $paymentUrl = rtrim($gatewayBase, '/') . '/' . $token;
            }

            return [
                'payment_url' => $paymentUrl,
                'token' => $token,
                'order_id' => (string) ($data['order_id'] ?? $orderId),
                'payload' => $payload,
            ];
        }

        $message = (string) ($result['message'] ?? 'La création du paiement Paymee a échoué.');
        throw new BadRequestHttpException($message);
    }

    public function checkPayment(string $token): array
    {
        if (trim($token) === '') {
            return [];
        }

        foreach (['/api/v2/payments/', '/api/v1/payments/'] as $prefix) {
            $result = $this->curlRequest('GET', $this->getBaseUrl() . $prefix . rawurlencode($token) . '/check');
            if (($result['ok'] ?? false) && is_array($result['data'] ?? null)) {
                return $result['data'];
            }
        }

        return [];
    }

    public function validateChecksum(string $token, bool $paymentStatus, string $checksum): bool
    {
        $checksum = trim($checksum);
        if ($checksum === '') {
            return true;
        }

        $expected = md5($token . ($paymentStatus ? '1' : '0') . $this->apiKey);
        return hash_equals($expected, $checksum);
    }

    public function extractCallbackPayload(Request $request): array
    {
        $payload = $request->request->all();
        if ($payload === []) {
            $payload = $request->query->all();
        }
        if ($payload === []) {
            $raw = json_decode($request->getContent(), true);
            if (is_array($raw)) {
                $payload = $raw;
            }
        }

        return $payload;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function isSandboxEnabled(): bool
    {
        return $this->useSandbox;
    }

    private function getCallbackBaseUrl(): string
    {
        $public = trim((string) $this->publicBaseUrl);
        if ($public !== '' && !str_contains($public, 'your-ngrok-url') && !str_contains($public, 'abc123.ngrok')) {
            return rtrim($public, '/');
        }

        return rtrim($this->defaultUri, '/');
    }

    private function normalizeName(string $value, string $fallback): string
    {
        $value = trim($value);
        $value = preg_replace("/[^\\p{L}\\p{N} .'\\-]+/u", ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($value === '') {
            return $fallback;
        }

        return mb_substr($value, 0, 60);
    }

    private function normalizeEmail(string $email): string
    {
        $email = trim($email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return 'test@gmail.com';
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';

        if ($digits === '') {
            return '+21611111111';
        }

        if (str_starts_with($digits, '216')) {
            return '+216' . substr($digits, -8);
        }

        if (strlen($digits) >= 8) {
            return '+216' . substr($digits, -8);
        }

        return '+21611111111';
    }

    private function getBaseUrl(): string
    {
        return rtrim($this->useSandbox ? $this->sandboxBaseUrl : $this->liveBaseUrl, '/');
    }

    private function curlRequest(string $method, string $url, ?array $payload = null, bool $asJson = true): array
    {
        if (!function_exists('curl_init')) {
            throw new BadRequestHttpException('cURL doit être activé dans PHP pour utiliser Paymee.');
        }

        $headers = [
            'Authorization: Token ' . $this->apiKey,
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($this->useSandbox) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        if ($payload !== null && strtoupper($method) !== 'GET') {
            if ($asJson) {
                $headers[] = 'Content-Type: application/json';
                $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $options[CURLOPT_HTTPHEADER] = $headers;
            } else {
                $options[CURLOPT_POSTFIELDS] = http_build_query($payload);
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'ok' => false,
                'message' => $error !== '' ? $error : 'Erreur cURL Paymee inconnue.',
                'status' => $status,
                'raw' => $body,
            ];
        }

        $decoded = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Réponse Paymee invalide.',
                'status' => $status,
                'raw' => $body,
            ];
        }

        return [
            'ok' => $status >= 200 && $status < 300 && (($decoded['status'] ?? false) === true),
            'message' => (string) ($decoded['message'] ?? ''),
            'status' => $status,
            'data' => $decoded,
            'raw' => $body,
        ];
    }
}
