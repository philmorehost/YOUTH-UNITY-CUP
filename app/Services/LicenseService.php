<?php

declare(strict_types=1);

namespace Yuc\Services;

use RuntimeException;

final class LicenseService
{
    public const API_URL = 'https://manager.pmhserver.name.ng/api.php';
    public const DOCS_URL = 'https://manager.pmhserver.name.ng/api-docs.php';

    public function requestDomain(): string
    {
        $rawHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($rawHost === '' || str_contains($rawHost, '/') || str_contains($rawHost, "\n")) {
            throw new RuntimeException('The current website domain could not be read safely.');
        }

        if (str_starts_with($rawHost, '[')) {
            $closingBracket = strpos($rawHost, ']');
            $host = $closingBracket === false ? '' : substr($rawHost, 1, $closingBracket - 1);
        } else {
            $host = preg_replace('/:\d+$/', '', $rawHost) ?? '';
        }
        $host = strtolower(rtrim($host, '.'));

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isHostname = preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/i', $host) === 1;
        if (!$isIp && !$isHostname) {
            throw new RuntimeException('The current website domain is not a valid hostname.');
        }

        return $host;
    }

    /** @return array{valid:bool,message:string} */
    public function verify(string $licenseKey, string $domain): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required to contact the license verification service.');
        }

        $handle = curl_init(self::API_URL);
        if ($handle === false) {
            throw new RuntimeException('The license verification request could not be started.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['key' => $licenseKey, 'domain' => $domain]),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($handle) !== 0;
        curl_close($handle);

        if ($response === false || $curlError) {
            throw new RuntimeException('The license service could not be reached. Check the server network and try again.');
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('The license service returned an unexpected response. Try again later.');
        }

        try {
            $result = json_decode((string) $response, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('The license service returned an unreadable response. Try again later.');
        }

        if (!is_array($result) || (int) ($result['status'] ?? 0) !== 1) {
            $message = is_array($result) ? trim((string) ($result['message'] ?? 'The key or domain was not accepted.')) : 'The key or domain was not accepted.';
            return ['valid' => false, 'message' => $message !== '' ? $message : 'The key or domain was not accepted.'];
        }

        return ['valid' => true, 'message' => 'The key is active for this domain.'];
    }
}
