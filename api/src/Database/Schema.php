<?php

declare(strict_types=1);

namespace App\Database;

use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use PDO;

final class Schema
{
    public const SEED_EMAIL = 'tech@example.test';
    public const SEED_PASSWORD = 'ChangeMe_now-1';
    public const SEED_ROLE = 'tech';

    public static function migrate(PDO $pdo, PasswordHasher $hasher, UserRepository $users): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT \'tech\' CHECK (role IN (\'tech\', \'admin\'))
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS refresh_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip TEXT NOT NULL,
                attempted_at TEXT NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS login_attempts_ip_at ON login_attempts (ip, attempted_at)'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS work_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT \'\',
                status TEXT NOT NULL DEFAULT \'open\' CHECK (status IN (\'open\', \'in_progress\', \'done\')),
                assigned_email TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        if ($users->count() === 0) {
            $users->insert(self::SEED_EMAIL, $hasher->hash(self::SEED_PASSWORD), self::SEED_ROLE);
        }
    }
}
