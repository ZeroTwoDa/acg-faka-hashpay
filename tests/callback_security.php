<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Pay\HashPay\Impl\Signature;

$private = file_get_contents(__DIR__ . '/fixtures/test_private.pem');
$config = ['private_key' => $private, 'callback_window' => '300'];

$valid = hpEnvelope(hpPayload(), time());
$result = Signature::decryptAndValidate($valid, $config);
hpAssert($result['merchantNo'] === 'ACG-TEST-10001', 'valid callback rejected');

$duplicateA = Signature::decryptAndValidate($valid, $config);
$duplicateB = Signature::decryptAndValidate($valid, $config);
hpAssert($duplicateA === $duplicateB, 'duplicate callback is not deterministic');

$tampered = $valid;
$raw = base64_decode($tampered['data'], true);
$raw[0] = $raw[0] ^ "\1";
$tampered['data'] = base64_encode($raw);
try { Signature::decryptAndValidate($tampered, $config); throw new RuntimeException('tampered callback accepted'); } catch (Throwable $e) { hpAssert($e->getMessage() !== 'tampered callback accepted', $e->getMessage()); }

$expired = hpEnvelope(hpPayload(), time() - 301);
try { Signature::decryptAndValidate($expired, $config); throw new RuntimeException('expired callback accepted'); } catch (Throwable $e) { hpAssert($e->getMessage() !== 'expired callback accepted', $e->getMessage()); }

$pending = hpEnvelope(hpPayload(['status' => 'pending']), time());
try { Signature::decryptAndValidate($pending, $config); throw new RuntimeException('pending callback accepted'); } catch (Throwable $e) { hpAssert($e->getMessage() !== 'pending callback accepted', $e->getMessage()); }

$wrongAmount = hpEnvelope(hpPayload(['amount' => 2.00]), time());
$out = Signature::decryptAndValidate($wrongAmount, $config);
hpAssert((float)$out['amount'] === 2.0, 'payload amount changed before core comparison');

$wrongCurrency = hpEnvelope(hpPayload(['currency' => 'USD']), time());
try { Signature::decryptAndValidate($wrongCurrency, $config); throw new RuntimeException('wrong currency accepted'); } catch (Throwable $e) { hpAssert($e->getMessage() !== 'wrong currency accepted', $e->getMessage()); }

$otherKey = file_get_contents(__DIR__ . '/fixtures/other_private.pem');
try { Signature::decryptAndValidate($valid, ['private_key' => $otherKey, 'callback_window' => '300']); throw new RuntimeException('wrong key accepted'); } catch (Throwable $e) { hpAssert($e->getMessage() !== 'wrong key accepted', $e->getMessage()); }

echo "PASS callback security: valid, duplicate, tamper, expiry, status, amount, currency, key rotation\n";
