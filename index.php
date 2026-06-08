<?php
/* PHP warning/notice가 JSON 앞에 출력되면 파싱 오류 발생 → 응답 중 출력 억제 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/includes/DB.php';
require_once __DIR__ . '/includes/Response.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = '/' . ltrim($uri, '/');
$parts  = array_values(array_filter(explode('/', trim($uri, '/'))));
$method = $_SERVER['REQUEST_METHOD'];

/*
 * URL 구조: /api/{resource}[/{sub_or_id}]
 * parts[0] = 'api'
 * parts[1] = resource  (prices | auction | receipt | notice | contact)
 * parts[2] = sub/id    (trend | live | {notice_id})
 */
if (empty($parts) || $parts[0] !== 'api') {
    Response::error('Not Found', 404);
}

$resource = $parts[1] ?? '';
$sub      = $parts[2] ?? '';

try {

    switch ($resource) {

        // ─── 시세현황 & 품목별동향 ───────────────────────────────────────
        case 'prices':
            require_once __DIR__ . '/api/prices.php';
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            if ($sub === 'trend') {
                getPriceTrend();
            } else {
                getPriceList();
            }
            break;

        // ─── 실시간경매현황 ─────────────────────────────────────────────
        case 'auction':
            require_once __DIR__ . '/api/auction.php';
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            if ($sub === 'live') {
                getLiveAuction();
            } else {
                Response::error('Not Found', 404);
            }
            break;

        // ─── 반입물량 ────────────────────────────────────────────────────
        case 'receipt':
            require_once __DIR__ . '/api/receipt.php';
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            getReceiptVolume();
            break;

        // ─── 조직도 담당자 ──────────────────────────────────────────────
        case 'organization':
            require_once __DIR__ . '/api/organization.php';
            if ($method !== 'GET') Response::methodNotAllowed();
            getOrganizationMembers();
            break;

        // ─── 공지사항 목록 & 상세 ────────────────────────────────────────
        case 'notice':
            require_once __DIR__ . '/api/notice.php';
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            if ($sub !== '' && ctype_digit($sub)) {
                getNoticeDetail((int)$sub);
            } else {
                getNoticeList();
            }
            break;

        // ─── 문의하기 ────────────────────────────────────────────────────
        case 'contact':
            require_once __DIR__ . '/api/contact.php';
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            submitContact();
            break;

        default:
            Response::error('Not Found', 404);
    }

} catch (PDOException $e) {
    error_log('[DJCG API] PDO Error: ' . $e->getMessage());
    $msg = (APP_ENV === 'development') ? $e->getMessage() : 'Database error';
    Response::error($msg, 500);
} catch (Throwable $e) {
    error_log('[DJCG API] Error: ' . $e->getMessage());
    $msg = (APP_ENV === 'development') ? $e->getMessage() : 'Internal Server Error';
    Response::error($msg, 500);
}
