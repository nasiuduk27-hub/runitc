<?php

// File: classes/Database.php

class Database
{
    private static $instances = [];

    public static function getConnection(string $host, string $dbname, string $username, string $password, bool $required = false): ?PDO
    {
        if (! isset(self::$instances[$dbname])) {
            try {
                self::$instances[$dbname] = new PDO(
                    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION wait_timeout = 120, SESSION innodb_lock_wait_timeout = 10',
                    ]
                );
            } catch (PDOException $e) {
                error_log("DB connection failed [{$dbname}]: ".$e->getMessage());
                if ($required) {
                    exit('Koneksi Database Utama Gagal.');
                }

                return null;
            }
        }

        return self::$instances[$dbname];
    }
}
