<?php
declare(strict_types=1);
namespace App\Pay;
interface Signature { public function verification(array $data, array $config): bool; }
