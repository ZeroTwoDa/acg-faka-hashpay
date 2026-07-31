<?php
declare(strict_types=1);
namespace Kernel\Util;
final class Context { private static array $data=[]; public static function set(string $key, mixed $value): void { self::$data[$key]=$value; } public static function get(string $key): mixed { return self::$data[$key]??null; } }
