<?php
declare(strict_types=1);

namespace App\Pay\HashPay\Impl;

use App\Consts\Pay as PayConst;
use Kernel\Context\Interface\Request;
use Kernel\Util\Context;

final class Signature implements \App\Pay\Signature
{
    private const ALGORITHM = 'RSA-OAEP-256+A256GCM';

    public function verification(array $data, array $config): bool
    {
        try {
            $payload = self::decryptAndValidate($data, $config);
            Context::set(PayConst::DAFA, $payload);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function callbackTradeNo(array $data, array $config): ?string
    {
        try {
            $payload = self::decryptAndValidate($data, $config);
            return isset($payload['merchantNo']) ? trim((string)$payload['merchantNo']) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function decryptAndValidate(array $envelope, array $config): array
    {
        self::validateHeaders($config);
        if (($envelope['alg'] ?? null) !== self::ALGORITHM) {
            throw new \RuntimeException('Unsupported HashPay callback algorithm');
        }
        $key = self::decode((string)($envelope['key'] ?? ''), 'key');
        $iv = self::decode((string)($envelope['iv'] ?? ''), 'iv');
        $encrypted = self::decode((string)($envelope['data'] ?? ''), 'data');
        if (strlen($iv) !== 12 || strlen($encrypted) < 17) {
            throw new \RuntimeException('Invalid HashPay encrypted envelope');
        }
        $privateKeyPem = HashPayClient::normalizePem((string)($config['private_key'] ?? ''));
        if ($privateKeyPem === '') {
            throw new \RuntimeException('Invalid HashPay private key');
        }
        if (!self::rsaOaepSha256Decrypt($key, $privateKeyPem, $contentKey) || strlen($contentKey) !== 32) {
            throw new \RuntimeException('HashPay content key decryption failed');
        }
        $tag = substr($encrypted, -16);
        $ciphertext = substr($encrypted, 0, -16);
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $contentKey, OPENSSL_RAW_DATA, $iv, $tag, '');
        if ($plain === false) {
            throw new \RuntimeException('HashPay payload authentication failed');
        }
        $message = json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
        $timestamp = $message['timestamp'] ?? null;
        $payload = $message['payload'] ?? null;
        $window = max(60, min(1800, (int)($config['callback_window'] ?? 300)));
        if (!is_int($timestamp) && !(is_string($timestamp) && ctype_digit($timestamp))) {
            throw new \RuntimeException('Invalid HashPay callback timestamp');
        }
        if (abs(time() - (int)$timestamp) > $window || !is_array($payload)) {
            throw new \RuntimeException('Expired HashPay callback');
        }
        foreach (['orderId', 'merchantNo', 'amount', 'currency', 'status'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new \RuntimeException('Incomplete HashPay callback payload');
            }
        }
        $merchantNo = trim((string)$payload['merchantNo']);
        $currency = strtoupper(trim((string)$payload['currency']));
        if ($merchantNo === '' || !is_numeric($payload['amount']) || (float)$payload['amount'] <= 0 || $currency !== 'CNY') {
            throw new \RuntimeException('Invalid HashPay callback business data');
        }
        return $payload;
    }

    private static function validateHeaders(array $config): void
    {
        $request = Context::get(Request::class);
        if (!$request instanceof Request) {
            return;
        }
        $merchant = trim((string)$request->header('XHashpayMerchant'));
        $timestamp = trim((string)$request->header('XHashpayTimestamp'));
        $algorithm = trim((string)$request->header('XHashpayEncryption'));
        $expectedMerchant = trim((string)($config['merchant_id'] ?? ''));
        $window = max(60, min(1800, (int)($config['callback_window'] ?? 300)));
        if ($merchant === '' || !hash_equals($expectedMerchant, $merchant)) {
            throw new \RuntimeException('HashPay callback merchant mismatch');
        }
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > $window || $algorithm !== self::ALGORITHM) {
            throw new \RuntimeException('Invalid HashPay callback headers');
        }
    }

    private static function decode(string $value, string $field): string
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false || $decoded === '') {
            throw new \RuntimeException('Invalid HashPay ' . $field);
        }
        return $decoded;
    }

    private static function rsaOaepSha256Decrypt(string $ciphertext, string $privateKeyPem, ?string &$plaintext): bool
    {
        $autoload = dirname(__DIR__) . '/Vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new \RuntimeException('HashPay RSA dependency is missing');
        }
        require_once $autoload;
        try {
            \phpseclib3\Math\BigInteger::setEngine(PHP_INT_SIZE >= 8 ? 'PHP64' : 'PHP32', ['DefaultEngine']);
            \phpseclib3\Crypt\RSA::forceEngine('PHP');
            $rsa = \phpseclib3\Crypt\PublicKeyLoader::loadPrivateKey($privateKeyPem)
                ->withPadding(\phpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');
            $result = $rsa->decrypt($ciphertext);
        } catch (\Throwable $e) {
            throw new \RuntimeException('HashPay RSA-OAEP-SHA256 decryption failed: ' . $e->getMessage(), 0, $e);
        }
        if (!is_string($result) || $result === '') {
            return false;
        }
        $plaintext = $result;
        return true;
    }
}
