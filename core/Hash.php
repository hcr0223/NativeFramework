<?php

namespace Core;

class Hash {

    public static function make(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }

    public static function check(string $password, string $hashedPassword): bool {
        return password_verify($password, $hashedPassword);
    }

    public static function needRehash(string $hashedPassword): bool {
        return password_needs_hash($hashedPassword, PASSWORD_DEFAULT, ['cost' => 12]);
    }
}