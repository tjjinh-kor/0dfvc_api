<?php
// ── 환경 자동 감지
$_api_host    = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$_api_isLocal = in_array($_api_host, ['localhost', '127.0.0.1', '::1'], true)
             || strpos($_api_host, '.local') !== false
             || strpos($_api_host, '.test')  !== false;

define('APP_ENV', $_api_isLocal ? 'development' : 'production');

unset($_api_host, $_api_isLocal);

define('FRONTEND_ORIGIN', 'https://www.djcg.co.kr');
define('API_VERSION',     'v1');

// CORS 허용 출처
define('ALLOWED_ORIGINS', [
    'https://www.djcg.co.kr',
    'https://djcg.co.kr',
    'http://localhost',
    'http://127.0.0.1',
]);

// DB 접속 정보 로드 (상수 정의)
require_once __DIR__ . '/db.php';
