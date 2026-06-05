<?php
class DB {
    private static $instance = null; // PHP 7.4: ?PDO 쓰면 되지만 nullable 초기값 null은 PHP 7.4+OK

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            // config/db.php 의 상수 사용 (config.php 가 먼저 로드되어 있어야 함)
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }
}
