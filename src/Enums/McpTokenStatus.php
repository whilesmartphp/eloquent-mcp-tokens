<?php

namespace Whilesmart\McpTokens\Enums;

enum McpTokenStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
