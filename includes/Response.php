<?php
class Response {

    /**
     * JSON 응답 출력 후 종료
     */
    public static function json(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 성공 응답: {"success":true,"data":...}
     */
    public static function success($data, array $meta = [], int $status = 200): void {
        $body = ['success' => true, 'data' => $data];
        if (!empty($meta)) {
            $body['meta'] = $meta;
        }
        self::json($body, $status);
    }

    /**
     * 에러 응답: {"success":false,"message":...}
     */
    public static function error(string $message, int $status = 400, array $extra = []): void {
        $body = array_merge(['success' => false, 'message' => $message], $extra);
        self::json($body, $status);
    }

    /**
     * 허용되지 않은 메서드
     */
    public static function methodNotAllowed(): void {
        self::error('Method Not Allowed', 405);
    }
}
