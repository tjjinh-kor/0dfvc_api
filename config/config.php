<?php
// ── 환경 자동 감지 (HTTP_HOST에 포트가 포함될 수 있으므로 포트 제거 후 비교)
$_api_host    = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost')[0]);
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
    'http://localhost:8000',
    'http://localhost:8001',
]);

// 관리자 API 토큰 (POST/PUT/DELETE 보호용 — 배포 전 반드시 변경)
define('ADMIN_API_TOKEN', 'djcg_admin_2026_change_me');

// Notice 첨부파일 저장 경로 (0dfvc_api 와 0dfvc 가 같은 서버에 있음을 전제)
define('NOTICE_UPLOAD_DIR', dirname(dirname(__DIR__)) . '/0dfvc/uploads/notice');

// DB 접속 정보 로드 (상수 정의)
require_once __DIR__ . '/db.php';
