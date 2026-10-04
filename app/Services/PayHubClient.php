<?php

declare(strict_types=1);

namespace Yuc\Services;

use RuntimeException;

final class PayHubClient
{
    public const API_BASE = 'https://merchant.payhub.com.ng/api';
    public const CHECKOUT_HOST = 'merchant.payhub.com.ng';

    private string $secretKey;

    /** @param array<string,mixed> $config */
    public function __construct(array $config)
    {
        $payments = is_array($config['payments'] ?? null) ? $config['payments'] : [];
        $secretKey = $payments['secret_key'] ?? '';
        $this->secretKey = is_scalar($secretKey) ? trim((string) $secretKey) : '';
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    /** @param array{email:string,amount_kobo:int,name:string,phone:string} $customer @return array{authorization_url:string,reference:string} */
    public function initialize(array $customer): array
    {
        $this->requireConfigured();
        $response = $this->request('POST', '/transaction/initialize', [
            'email' => $customer['email'],
            'amount' => (string) $customer['amount_kobo'],
            'name' => $customer['name'],
            'phone' => $customer['phone'],
        ]);
        $data = $response['data'] ?? null;
        if (($response['status'] ?? false) !== true || !is_array($data)) {
            throw new RuntimeException('PayHub did not initialize the payment.');
        }

        $authorizationUrl = is_scalar($data['authorization_url'] ?? null) ? (string) $data['authorization_url'] : '';
        $reference = is_scalar($data['reference'] ?? null) ? trim((string) $data['reference']) : '';
        if (!self::isTrustedCheckoutUrl($authorizationUrl)
            || preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $reference) !== 1) {
            throw new RuntimeException('PayHub returned invalid checkout details.');
        }

        return ['authorization_url' => $authorizationUrl, 'reference' => $reference];
    }

    /** @return array<string,mixed> */
    public function verify(string $reference): array
    {
        $this->requireConfigured();
        if (preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $reference) !== 1) {
            throw new RuntimeException('The PayHub reference is not valid.');
        }

        return $this->request('GET', '/transaction/verify/' . rawurlencode($reference));
    }

    public function hasValidWebhookSignature(string $rawBody, string $signature): bool
    {
        if (!$this->isConfigured() || preg_match('/^[a-f0-9]{64}$/iD', $signature) !== 1) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $this->secretKey), strtolower($signature));
    }

    public static function isTrustedCheckoutUrl(string $url): bool
    {
        if (strlen($url) > 2048) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== self::CHECKOUT_HOST
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return false;
        }

        return str_starts_with((string) ($parts['path'] ?? ''), '/checkout');
    }

    private function requireConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('PayHub is not configured.');
        }
    }

    /** @param array<string,string>|null $form @return array<string,mixed> */
    private function request(string $method, string $path, ?array $form = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for PayHub payments.');
        }

        $handle = curl_init(self::API_BASE . $path);
        if ($handle === false) {
            throw new RuntimeException('The PayHub connection could not be started.');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->secretKey,
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($method === 'POST' && $form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errorNumber = curl_errno($handle);
        curl_close($handle);

        if ($body === false || $errorNumber !== 0 || $status < 200 || $status >= 300) {
            throw new RuntimeException('The PayHub request could not be completed.');
        }
        try {
            $decoded = json_decode((string) $body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('PayHub returned an unreadable response.');
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('PayHub returned an unreadable response.');
        }

        return $decoded;
    }
}
