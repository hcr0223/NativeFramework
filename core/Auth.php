<?php

namespace Core;

use App\Models\User;
class Auth {
    protected static ?User $currentUSer = null;
    protected static int $maxAttempts = 3;
    protected static int $lockoutMinutes = 15;

    public static function attempt(string $email, string $password): bool {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        if (!empty($user->locked_until) && strtotime($user->locked_until) > time()) {
            Session::flash('auth_error', 'Too much failed attempts, your account has been block temporarly.');
            return false;
        }

        if ($user && Hash::check($password, $user->password)) {
            $user->locked_until = null;
            $user->failed_attempts = 0;
            $user->save();
            Session::set('user_id', $user->id);
            self::$currentUSer = $user;
            return true;
        }

        $attempts = ($user->failed_attempts ?? 0) + 1;
        $user->failed_attempts = $attempts;

        if ($attempts >= self::$maxAttempts) {
            $user->locked_until = date('Y-m-d H:i:s', strtotime("+" . self::$lockoutMinutes . " minutes"));
            Session::set('auth_error', 'Too much failed attempts, your account has been block temporarly.');
        } else {
            Session::set('auth_error', 'Credenciales inválidas.');
        }

        $user->save();

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