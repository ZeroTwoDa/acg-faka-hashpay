<?php
declare(strict_types=1);

namespace App\Pay\HashPay\Impl;

use GuzzleHttp\Client;
use RuntimeException;

final class HashPayClient
{
    private string $baseUrl;
    private string $merchantId;
    private $privateKey;
    private Client $http;

    public function __construct(array $config, ?Client $http = null)
    {
        $this->baseUrl = rtrim(trim((string)($config['api_url'] ?? '')), '/');
        $this->merchantId = trim((string)($config['merchant_id'] ?? ''));
        $pem = self::normalizePem((string)($config['private_key'] ?? ''));
        if ($this->baseUrl === '' || !preg_match('~^https://~i', $this->baseUrl)) {
            throw new RuntimeException('HashPay API 地址必须使用 HTTPS');
        }
        if ($this->merchantId === '' || $pem === '') {
            throw new RuntimeException('HashPay 商户 ID 或 RSA 私钥未配置');
        }
        $this->privateKey = openssl_pkey_get_private($pem);
        if ($this->privateKey === false) {
            throw new RuntimeException('HashPay RSA 私钥格式无效');
        }
        $this->http = $http ?? new Client(['verify' => true, 'connect_timeout' => 10, 'timeout' => 20]);
    }

    public function createOrder(string $merchantNo, float $amount, string $currency, string $description, string $returnUrl): array
    {
        $data = [
            'merchantNo' => $merchantNo,
            'amount' => round($amount, 2),
            'currency' => strtoupper($currency),
            'description' => $description,
            'return_url' => $returnUrl,
        ];
        return $this->request('POST', '/api/merchant/new', $data);
    }

    public function queryOrder(string $orderId): array
    {
        $path = '/api/order/' . rawurlencode($orderId);
        return $this->request('GET', $path);
    }

    public function request(string $method, string $path, ?array $data = null): array
    {
        $method = strtoupper($method);
        $body = $data === null ? '' : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string)time();
        $message = $method . "\n" . $path . "\n" . $timestamp . "\n" . $body;
        if (!openssl_sign($message, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('HashPay 请求签名失败');
        }
        try {
            $response = $this->http->request($method, $this->baseUrl . $path, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Merchant-Id' => $this->merchantId,
                    'X-Timestamp' => $timestamp,
                    'X-Signature' => base64_encode($signature),
                ],
                'body' => $body,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('HashPay 网络请求失败: ' . $e->getMessage(), 0, $e);
        }
        $raw = (string)$response->getBody();
        $json = json_decode($raw, true);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || !is_array($json)) {
            $key = is_array($json) ? (string)($json['error']['key'] ?? '') : '';
            throw new RuntimeException('HashPay API 请求失败 (' . $response->getStatusCode() . ')' . ($key !== '' ? ': ' . $key : ''));
        }
        return $json;
    }

    public static function normalizePem(string $pem): string
    {
        return trim(str_replace(['\\n', "\r\n", "\r"], ["\n", "\n", "\n"], $pem));
    }
}
