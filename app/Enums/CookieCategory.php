<?php

namespace App\Enums;

enum CookieCategory: string
{
    case Necessary = 'necessary';
    case Preferences = 'preferences';
    case Statistics = 'statistics';
    case Marketing = 'marketing';
    case Unclassified = 'unclassified';

    /** Categories an owner may assign as a manual override (excludes Unclassified). */
    public static function assignable(): array
    {
        return [self::Necessary, self::Preferences, self::Statistics, self::Marketing];
    }

    public function isNecessary(): bool
    {
        return $this === self::Necessary;
    }
}
