<?php
/*
 * GET /api/social-gallery
 *
 * Query params:
 *   mode        string  선택: 'main' → 카테고리별 최신 1건 반환 (메인페이지용)
 *   category    string  mode 미사용 시 필수: volunteer | sharing
 *   year        int     선택: 연도 필터 (e.g. 2024)
 *   event_type  string  선택: sharing 행사 구분 (kimchi | jjajang)
 *   per_page    int     선택: 반환 건수 제한 (기본 전체)
 */

function getSocialGallery(): void {
    $mode      = trim($_GET['mode']       ?? '');
    $category  = trim($_GET['category']   ?? '');
    $year      = trim($_GET['year']       ?? '');
    $eventType = trim($_GET['event_type'] ?? '');
    $perPage   = (int)($_GET['per_page']  ?? 0);

    $db = DB::getInstance();

    /* ── mode=main: 카테고리별 최신 1건 통합 반환 (메인 페이지용) ── */
    if ($mode === 'main') {
        $result = [];
        foreach (['volunteer', 'sharing'] as $cat) {
            $stmt = $db->prepare(
                'SELECT id, title, event_date, caption, image_name
                 FROM social_gallery
                 WHERE category = ?
                 ORDER BY event_date DESC, sort_order ASC, id DESC
                 LIMIT 1'
            );
            $stmt->execute([$cat]);
            $row = $stmt->fetch();
            $result[$cat] = $row ? [
                'id'         => (int)$row['id'],
                'title'      => $row['title'],
                'event_date' => $row['event_date'],
                'caption'    => $row['caption'] ?? '',
                'image_url'  => $row['image_name']
                                ? '/uploads/social/' . $row['image_name']
                                : '',
            ] : null;
        }
        Response::success($result);
        return;
    }

    /* ── 일반 목록 조회 ── */
    if (!in_array($category, ['volunteer', 'sharing'], true)) {
        Response::error('category 파라미터가 필요합니다. (volunteer | sharing)', 422);
    }

    $where  = ['category = ?'];
    $params = [$category];

    if ($year !== '' && ctype_digit($year)) {
        $where[]  = 'YEAR(event_date) = ?';
        $params[] = (int)$year;
    }

    if ($eventType !== '') {
        if ($eventType === 'null') {
            $where[] = 'event_type IS NULL';
        } else {
            $where[]  = 'event_type = ?';
            $params[] = $eventType;
        }
    }

    $sql = 'SELECT id, category, event_type, title, event_date, caption, image_name
            FROM social_gallery
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY event_date DESC, sort_order ASC, id DESC';

    if ($perPage > 0) {
        $sql     .= ' LIMIT ?';
        $params[] = $perPage;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $data = array_map(function (array $row): array {
        return [
            'id'         => (int)$row['id'],
            'category'   => $row['category'],
            'event_type' => $row['event_type'],
            'title'      => $row['title'],
            'event_date' => $row['event_date'],
            'caption'    => $row['caption'] ?? '',
            'image_url'  => $row['image_name']
                            ? '/uploads/social/' . $row['image_name']
                            : '',
        ];
    }, $rows);

    Response::success($data);
}
