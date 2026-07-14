<?php

namespace App\Support;

class DeviceTokenPlatform
{
    public const FCM_ANDROID = 'fcm_android';

    public const APNS_VOIP = 'apns_voip';

    public const WEB_PUSH = 'web_push';

    public const LEGACY_ANDROID = 'android';

    public const LEGACY_IOS = 'ios';

    public static function normalize(string $platform): string
    {
        return match (strtolower($platform)) {
            self::LEGACY_ANDROID => self::FCM_ANDROID,
            default => strtolower($platform),
        };
    }

    public static function fcmPlatforms(): array
    {
        return [self::FCM_ANDROID, self::LEGACY_ANDROID];
    }

    public static function isAllowed(string $platform): bool
    {
        return in_array(self::normalize($platform), [
            self::FCM_ANDROID,
            self::APNS_VOIP,
            self::WEB_PUSH,
            self::LEGACY_IOS,
        ], true);
    }
}
