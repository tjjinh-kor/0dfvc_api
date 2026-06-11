<?php
/*
 * POST /api/contact   문의 접수
 *
 * Request body (JSON 또는 form-urlencoded):
 *   name     string  이름 (필수, 50자 이내)
 *   email    string  이메일 (필수)
 *   phone    string  연락처 (선택)
 *   subject  string  제목 (필수, 200자 이내)
 *   content  string  내용 (필수, 10~3000자)
 */
function submitContact(): void {
    // JSON body 우선, fallback → form-encoded
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $name    = trim($body['name']    ?? '');
    $email   = trim($body['email']   ?? '');
    $phone   = trim($body['phone']   ?? '');
    $subject = trim($body['subject'] ?? '');
    $content = trim($body['content'] ?? '');

    // ── 유효성 검사 ──────────────────────────────────────────────────────
    $errors = [];

    if ($name === '') {
        $errors[] = '이름을 입력해주세요.';
    } elseif (mb_strlen($name) > 50) {
        $errors[] = '이름은 50자 이내로 입력해주세요.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '유효한 이메일 주소를 입력해주세요.';
    }

    if ($subject === '') {
        $errors[] = '제목을 입력해주세요.';
    } elseif (mb_strlen($subject) > 200) {
        $errors[] = '제목은 200자 이내로 입력해주세요.';
    }

    $contentLen = mb_strlen($content);
    if ($contentLen < 10) {
        $errors[] = '내용을 10자 이상 입력해주세요.';
    } elseif ($contentLen > 3000) {
        $errors[] = '내용은 3,000자 이내로 입력해주세요.';
    }

    if (!empty($errors)) {
        Response::error(implode(' ', $errors), 422, ['errors' => $errors]);
    }

    // ── 저장 ─────────────────────────────────────────────────────────────
    $name    = mb_substr($name,    0,  50);
    $email   = mb_substr($email,   0, 100);
    $phone   = mb_substr(preg_replace('/[^0-9\-\+\(\) ]/', '', $phone), 0, 20);
    $subject = mb_substr($subject, 0, 200);
    $content = mb_substr($content, 0, 3000);

    // 클라이언트 IP (로드밸런서 환경 고려)
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = mb_substr(explode(',', $ip)[0], 0, 45); // 첫 번째 IP만 사용

    $db   = DB::getInstance();
    $stmt = $db->prepare(
        'INSERT INTO contact_inquiry (name, email, phone, subject, content, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $phone, $subject, $content, $ip]);

    $insertedId = (int)$db->lastInsertId();
    if ($insertedId < 1) {
        Response::error('문의 저장에 실패했습니다. 잠시 후 다시 시도해 주세요.', 500);
    }

    Response::success(
        ['id' => $insertedId, 'message' => '문의가 접수되었습니다. 빠른 시일 내에 답변드리겠습니다.'],
        [],
        201
    );
}
