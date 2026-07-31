<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/Pay/HashPay/Vendor/autoload.php';

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'App\\Pay\\HashPay\\' => $root . '/app/Pay/HashPay/',
        'Kernel\\Context\\Interface\\' => $root . '/tests/stubs/Kernel/Context/Interface/',
        'Kernel\\Util\\' => $root . '/tests/stubs/Kernel/Util/',
        'App\\Pay\\' => $root . '/tests/stubs/App/Pay/',
        'App\\Consts\\' => $root . '/tests/stubs/App/Consts/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) require $path;
            return;
        }
    }
});

function hpAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function hpEnvelope(array $payload, int $timestamp): array
{
    $aes = file_get_contents(__DIR__ . '/fixtures/content_key.bin');
    $wrapped = file_get_contents(__DIR__ . '/fixtures/wrapped_key.bin');
    $iv = random_bytes(12);
    $plain = json_encode(['timestamp' => $timestamp, 'payload' => $payload], JSON_THROW_ON_ERROR);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $aes, OPENSSL_RAW_DATA, $iv, $tag, '');
    return [
        'alg' => 'RSA-OAEP-256+A256GCM',
        'key' => base64_encode($wrapped),
        'iv' => base64_encode($iv),
        'data' => base64_encode($cipher . $tag),
    ];
}

function hpPayload(array $override = []): array
{
    return array_replace([
        'orderId' => 'hashpay-test-order',
        'merchantNo' => 'ACG-TEST-10001',
        'amount' => 1.00,
        'currency' => 'CNY',
        'status' => 'paid',
        'payment' => [],
    ], $override);
}
