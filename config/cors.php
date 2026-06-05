<?php
/*
 * CORS 헤더 설정
 * 이 파일은 index.php 최상단에서 require 되어야 합니다.
 */

$_allowedOrigins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : ['https://www.djcg.co.kr'];

$_origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($_origin, $_allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $_origin);
} else {
    // 허용되지 않은 출처는 운영 도메인 고정
    header('Access-Control-Allow-Origin: https://www.djcg.co.kr');
}

header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Preflight 요청 즉시 응답
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

unset($_allowedOrigins, $_origin);
