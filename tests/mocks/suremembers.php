<?php
namespace SureMembers\Inc;

if (!class_exists('Access')) {
    class Access {
        public static array $granted = [];
        public static array $revoked = [];

        public static function grant(int $user_id, array $group): void {
            self::$granted[] = ['user_id' => $user_id, 'groups' => $group];
        }

        public static function revoke(int $user_id, array $group): void {
            self::$revoked[] = ['user_id' => $user_id, 'groups' => $group];
        }

        public static function reset(): void {
            self::$granted = [];
            self::$revoked = [];
        }
    }
}

if (!class_exists('Access_Groups')) {
    class Access_Groups {
        public static function get_active(): array {
            return [
                10 => 'Test Group',
                20 => 'VIP Group',
            ];
        }
    }
}