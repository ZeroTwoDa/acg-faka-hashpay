<?php
declare(strict_types=1);
namespace Kernel\Context\Interface;
interface Request { public function header(?string $key = null): mixed; }
