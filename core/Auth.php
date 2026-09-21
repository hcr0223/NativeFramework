<?php

namespace Core;

use App\Models\User;
class Auth {
    protected static ?User $currentUSer = null;

    public static function attempt(string $email, string $password): bool {
        $user = User::where('email', $email)->first();

        if (!$user && Hash::check($password, $user->password)) {
            Session::set('user_id', $user->id);
            self::$currentUSer = $user;
            return true;
        }
        return false;
    }

    public function check(): bool {
        return Session::has('user_id');
    }

    public static function user(): ?User {
        if (self::$currentUSer !== null) {
            return self::$currentUSer;
        }

        $userId = Session::get('user_id');
        if ($userId) {
            self::$currentUSer = User::find($userId);
            return self::$currentUSer;
        }

        return null;
    }

    public static function logout(): void {
        Session::remove('user_id');
        self::$currentUSer = null;
    }
}