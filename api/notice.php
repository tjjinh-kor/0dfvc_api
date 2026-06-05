<?php
/*
 * GET /api/notice       공지사항 목록 (페이지네이션)
 * GET /api/notice/{id}  공지사항 상세 + 조회수 증가
 */

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
        $where            = 'WHERE (title LIKE :kw1 OR content LIKE :kw2)';
        $params[':kw1']   = '%' . $keyword . '%';
        $params[':kw2']   = '%' . $keyword . '%';
    }

    // 전체 건수
    $countStmt = $db->prepare("SELECT COUNT(*) FROM notice {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // 목록 조회 — LIMIT/OFFSET 은 int 이므로 직접 보간
    $sql  = "SELECT id, title, author, is_pinned, view_count, created_at
             FROM notice
             {$where}
             ORDER BY is_pinned DESC, created_at DESC
             LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
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

    // 조회수 증가
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

    $row['is_pinned']  = (bool)$row['is_pinned'];
    $row['view_count'] = (int)$row['view_count'];

    Response::success($row);
}
