<?php

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('SGECESESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== ''
                && strtolower((string) $_SERVER['HTTPS']) !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }
}
