<?php

declare(strict_types=1);

namespace Colossal\Routing\Utilities;

class Utilities
{
    public static function strRemovePrefix(string $str, string $pre)
    {
        if (!str_starts_with($str, $pre)) {
            throw new \InvalidArgumentException("Argument 'str' ($str) does not start with argument 'pre' ($pre).");
        }
        return substr($str, strlen($pre));
    }
}