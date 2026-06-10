<?php
/*
 * GET    /api/company_news          회사소식 목록 (페이지네이션 + 검색)
 * GET    /api/company_news/{id}     회사소식 상세 + 조회수 증가
 * POST   /api/company_news          등록  (Authorization: Bearer <ADMIN_API_TOKEN>)
 * PUT    /api/company_news/{id}     수정  (Authorization: Bearer <ADMIN_API_TOKEN>)
 * DELETE /api/company_news/{id}     삭제  (Authorization: Bearer <ADMIN_API_TOKEN>)
 *
 * ※ notice 테이블과 완전히 독립된 company_news 테이블 사용
 */

/* ─── 관리자 토큰 인증 헬퍼 ────────────────────────────────────────────────
 * 요청 헤더: Authorization: Bearer <token>
 * 토큰값: config.php 의 ADMIN_API_TOKEN 상수
 */
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

/* ─── 입력 필드 공통 검증 & 정규화 ─────────────────────────────────────────
 * @return array  ['title', 'writer', 'reg_date', 'source', 'url', 'content']
 */
function parseNewsBody(): array {
    $raw = [];
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($ct, 'application/json') !== false) {
        $raw = (array)(json_decode(file_get_contents('php://input'), true) ?? []);
    } else {
        // application/x-www-form-urlencoded 또는 multipart/form-data
        $raw = $_POST;
        // PUT/DELETE 는 php://input 으로만 옴
        if (empty($raw)) {
            parse_str(file_get_contents('php://input'), $raw);
        }
    }

    $title   = trim($raw['title']    ?? '');
    $writer  = trim($raw['writer']   ?? '관리자');
    $regDate = trim($raw['reg_date'] ?? date('Y-m-d'));
    $source  = trim($raw['source']   ?? '');
    $url     = trim($raw['url']      ?? '');
    $content = trim($raw['content']  ?? '');

    $errors = [];
    if ($title === '')   $errors[] = '제목은 필수입니다.';
    if ($content === '') $errors[] = '본문은 필수입니다.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $regDate)) $errors[] = '등록일 형식이 올바르지 않습니다.';
    if (mb_strlen($title) > 300)  $errors[] = '제목은 300자 이하입니다.';
    if (mb_strlen($writer) > 80)  $errors[] = '글쓴이는 80자 이하입니다.';
    if (mb_strlen($source) > 200) $errors[] = '출처는 200자 이하입니다.';
    if (mb_strlen($url) > 500)    $errors[] = 'URL은 500자 이하입니다.';

    if (!empty($errors)) {
        Response::error(implode(' ', $errors), 422);
    }

    return compact('title', 'writer', 'regDate', 'source', 'url', 'content');
}

/* ─── GET /api/company_news ───────────────────────────────────────────────
 *  Query params:
 *    page      int     페이지 번호 (기본 1)
 *    per_page  int     페이지당 건수 (기본 15, 최대 50)
 *    q         string  검색 키워드
 *    type      string  검색 대상: title(기본) | writer
 */
function getCompanyNewsList(): void {
    $db      = DB::getInstance();
    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 15)));
    $offset  = ($page - 1) * $perPage;
    $keyword = trim($_GET['q'] ?? '');
    $type    = ($_GET['type'] ?? '') === 'writer' ? 'writer' : 'title';

    $where  = '';
    $params = [];
    if ($keyword !== '') {
        $where         = "WHERE {$type} LIKE :kw";
        $params[':kw'] = '%' . $keyword . '%';
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM company_news {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT id, title, writer, reg_date, views
            FROM company_news
            {$where}
            ORDER BY reg_date DESC, id DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id']    = (int)$row['id'];
        $row['views'] = (int)$row['views'];
    }
    unset($row);

    Response::success($rows, [
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'last_page' => (int)ceil($total / max(1, $perPage)),
    ]);
}

/* ─── GET /api/company_news/{id} ──────────────────────────────────────────
 */
function getCompanyNewsDetail(int $id): void {
    if ($id <= 0) {
        Response::error('잘못된 요청입니다.', 400);
    }

    $db = DB::getInstance();

    // 조회수 증가
    $db->prepare('UPDATE company_news SET views = views + 1 WHERE id = ?')
       ->execute([$id]);

    $stmt = $db->prepare(
        'SELECT id, title, writer, reg_date, views, source, url, content, created_at, updated_at
         FROM company_news WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        Response::error('소식을 찾을 수 없습니다.', 404);
    }

    $row['id']    = (int)$row['id'];
    $row['views'] = (int)$row['views'];

    // 이전글: 현재보다 id 작은 것 중 가장 큰 것
    $prevStmt = $db->prepare(
        'SELECT id, title FROM company_news WHERE id < ? ORDER BY id DESC LIMIT 1'
    );
    $prevStmt->execute([$id]);
    $prev = $prevStmt->fetch() ?: null;

    // 다음글: 현재보다 id 큰 것 중 가장 작은 것
    $nextStmt = $db->prepare(
        'SELECT id, title FROM company_news WHERE id > ? ORDER BY id ASC LIMIT 1'
    );
    $nextStmt->execute([$id]);
    $next = $nextStmt->fetch() ?: null;

    if ($prev) { $prev['id'] = (int)$prev['id']; }
    if ($next) { $next['id'] = (int)$next['id']; }

    $row['prev'] = $prev;
    $row['next'] = $next;

    Response::success($row);
}

/* ─── POST /api/company_news ──────────────────────────────────────────────
 *  등록. Authorization: Bearer <ADMIN_API_TOKEN> 필요.
 */
function postCompanyNews(): void {
    verifyAdminToken();
    $f  = parseNewsBody();
    $db = DB::getInstance();

    $db->prepare(
        'INSERT INTO company_news (title, writer, reg_date, source, url, content, views)
         VALUES (?, ?, ?, ?, ?, ?, 0)'
    )->execute([$f['title'], $f['writer'], $f['regDate'], $f['source'], $f['url'], $f['content']]);

    $newId = (int)$db->lastInsertId();
    Response::success(['id' => $newId], [], 201);
}

/* ─── PUT /api/company_news/{id} ──────────────────────────────────────────
 *  수정. Authorization: Bearer <ADMIN_API_TOKEN> 필요.
 */
function putCompanyNews(int $id): void {
    verifyAdminToken();
    if ($id <= 0) Response::error('잘못된 요청입니다.', 400);

    $db   = DB::getInstance();
    $stmt = $db->prepare('SELECT id FROM company_news WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) Response::error('소식을 찾을 수 없습니다.', 404);

    $f = parseNewsBody();
    $db->prepare(
        'UPDATE company_news
         SET title=?, writer=?, reg_date=?, source=?, url=?, content=?, updated_at=NOW()
         WHERE id=?'
    )->execute([$f['title'], $f['writer'], $f['regDate'], $f['source'], $f['url'], $f['content'], $id]);

    Response::success(['id' => $id]);
}

/* ─── DELETE /api/company_news/{id} ───────────────────────────────────────
 *  삭제. Authorization: Bearer <ADMIN_API_TOKEN> 필요.
 */
function deleteCompanyNews(int $id): void {
    verifyAdminToken();
    if ($id <= 0) Response::error('잘못된 요청입니다.', 400);

    $db   = DB::getInstance();
    $stmt = $db->prepare('SELECT id FROM company_news WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) Response::error('소식을 찾을 수 없습니다.', 404);

    $db->prepare('DELETE FROM company_news WHERE id = ?')->execute([$id]);
    Response::success(['deleted' => true]);
}
