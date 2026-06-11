<?php
/*
 * GET    /api/notice       공지사항 목록 (페이지네이션)
 * GET    /api/notice/{id}  공지사항 상세 + 조회수 증가
 * POST   /api/notice       등록  (Authorization: Bearer <ADMIN_API_TOKEN>)
 * PUT    /api/notice/{id}  수정  (Authorization: Bearer <ADMIN_API_TOKEN>)
 * DELETE /api/notice/{id}  삭제  (Authorization: Bearer <ADMIN_API_TOKEN>)
 */

if (!function_exists('verifyAdminToken')) {
    function verifyAdminToken(): void {
        $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        if (preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
            $token = $m[1];
        }
        if (!defined('ADMIN_API_TOKEN') || !hash_equals(ADMIN_API_TOKEN, $token)) {
            Response::error('인증이 필요합니다.', 401);
        }
    }
}

function parseNoticeBody(): array {
    $raw = [];
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($ct, 'application/json') !== false) {
        $raw = (array)(json_decode(file_get_contents('php://input'), true) ?? []);
    } else {
        $raw = $_POST;
        if (empty($raw)) {
            parse_str(file_get_contents('php://input'), $raw);
        }
    }

    $title    = trim($raw['title']    ?? '');
    $content  = trim($raw['content']  ?? '');
    $author   = trim($raw['author']   ?? '관리자');
    $isPinned = isset($raw['is_pinned']) ? (int)(bool)$raw['is_pinned'] : 0;

    $errors = [];
    if ($title === '')   $errors[] = '제목은 필수입니다.';
    if ($content === '') $errors[] = '내용은 필수입니다.';
    if (mb_strlen($title)  > 300) $errors[] = '제목은 300자 이하입니다.';
    if (mb_strlen($author) > 100) $errors[] = '작성자는 100자 이하입니다.';

    if (!empty($errors)) {
        Response::error(implode(' ', $errors), 422);
    }

    return compact('title', 'content', 'author', 'isPinned');
}

/* ─── GET /api/notice ─────────────────────────────────────────────────────
 *  Query params:
 *    page      int     페이지 번호 (기본 1)
 *    per_page  int     페이지당 건수 (기본 20, 최대 50)
 *    q         string  제목 키워드 검색
 */
function getNoticeList(): void {
    $db      = DB::getInstance();
    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset  = ($page - 1) * $perPage;
    $keyword = trim($_GET['q'] ?? '');

    $where  = '';
    $params = [];
    if ($keyword !== '') {
        $where          = 'WHERE (title LIKE :kw1 OR content LIKE :kw2)';
        $params[':kw1'] = '%' . $keyword . '%';
        $params[':kw2'] = '%' . $keyword . '%';
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM notice {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT id, title, author, is_pinned, view_count, created_at
            FROM notice
            {$where}
            ORDER BY is_pinned DESC, created_at DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id']         = (int)$row['id'];
        $row['is_pinned']  = (bool)$row['is_pinned'];
        $row['view_count'] = (int)$row['view_count'];
    }
    unset($row);

    Response::success($rows, [
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'last_page' => (int)ceil($total / max(1, $perPage)),
    ]);
}

/* ─── GET /api/notice/{id} ────────────────────────────────────────────────
 */
function getNoticeDetail(int $id): void {
    if ($id <= 0) {
        Response::error('잘못된 요청입니다.', 400);
    }

    $db = DB::getInstance();

    $db->prepare('UPDATE notice SET view_count = view_count + 1 WHERE id = ?')
       ->execute([$id]);

    $stmt = $db->prepare(
        'SELECT id, title, content, author, is_pinned, view_count, created_at, updated_at
         FROM notice WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        Response::error('공지사항을 찾을 수 없습니다.', 404);
    }

    $row['id']         = (int)$row['id'];
    $row['is_pinned']  = (bool)$row['is_pinned'];
    $row['view_count'] = (int)$row['view_count'];

    // 첨부파일 목록
    $fileStmt = $db->prepare(
        'SELECT id, original_name, file_size, created_at FROM notice_files WHERE notice_id = ? ORDER BY id ASC'
    );
    $fileStmt->execute([$id]);
    $files = $fileStmt->fetchAll();
    foreach ($files as &$f) {
        $f['id']           = (int)$f['id'];
        $f['file_size']    = (int)$f['file_size'];
        $f['download_url'] = '/support/download.php?id=' . $f['id'];
    }
    unset($f);
    $row['files'] = $files;

    // 이전글
    $prevStmt = $db->prepare(
        'SELECT id, title FROM notice WHERE id < ? ORDER BY id DESC LIMIT 1'
    );
    $prevStmt->execute([$id]);
    $prev = $prevStmt->fetch() ?: null;

    // 다음글
    $nextStmt = $db->prepare(
        'SELECT id, title FROM notice WHERE id > ? ORDER BY id ASC LIMIT 1'
    );
    $nextStmt->execute([$id]);
    $next = $nextStmt->fetch() ?: null;

    if ($prev) { $prev['id'] = (int)$prev['id']; }
    if ($next) { $next['id'] = (int)$next['id']; }

    $row['prev'] = $prev;
    $row['next'] = $next;

    Response::success($row);
}

/* ─── POST /api/notice ────────────────────────────────────────────────────
 */
function postNotice(): void {
    verifyAdminToken();
    $f  = parseNoticeBody();
    $db = DB::getInstance();

    $db->prepare(
        'INSERT INTO notice (title, content, author, is_pinned, view_count)
         VALUES (?, ?, ?, ?, 0)'
    )->execute([$f['title'], $f['content'], $f['author'], $f['isPinned']]);

    $newId = (int)$db->lastInsertId();
    Response::success(['id' => $newId], [], 201);
}

/* ─── PUT /api/notice/{id} ────────────────────────────────────────────────
 */
function putNotice(int $id): void {
    verifyAdminToken();
    if ($id <= 0) Response::error('잘못된 요청입니다.', 400);

    $db   = DB::getInstance();
    $stmt = $db->prepare('SELECT id FROM notice WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) Response::error('공지사항을 찾을 수 없습니다.', 404);

    $f = parseNoticeBody();
    $db->prepare(
        'UPDATE notice SET title=?, content=?, author=?, is_pinned=?, updated_at=NOW()
         WHERE id=?'
    )->execute([$f['title'], $f['content'], $f['author'], $f['isPinned'], $id]);

    Response::success(['id' => $id]);
}

/* ─── DELETE /api/notice/{id} ─────────────────────────────────────────────
 */
function deleteNotice(int $id): void {
    verifyAdminToken();
    if ($id <= 0) Response::error('잘못된 요청입니다.', 400);

    $db   = DB::getInstance();
    $stmt = $db->prepare('SELECT id FROM notice WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) Response::error('공지사항을 찾을 수 없습니다.', 404);

    // 첨부파일 물리 삭제
    $fileStmt = $db->prepare('SELECT stored_name FROM notice_files WHERE notice_id = ?');
    $fileStmt->execute([$id]);
    foreach ($fileStmt->fetchAll() as $f) {
        $path = NOTICE_UPLOAD_DIR . '/' . $f['stored_name'];
        if (file_exists($path)) @unlink($path);
    }
    $db->prepare('DELETE FROM notice_files WHERE notice_id = ?')->execute([$id]);

    $db->prepare('DELETE FROM notice WHERE id = ?')->execute([$id]);
    Response::success(['deleted' => true]);
}
