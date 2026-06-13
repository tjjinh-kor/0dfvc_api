<?php
/*
 * GET /api/social-gallery
 *
 * Query params:
 *   category    string  필수: volunteer | sharing
 *   year        int     선택: 연도 필터 (e.g. 2024)
 *   event_type  string  선택: sharing 페이지 행사 구분 (kimchi | jjajang)
 */

function getSocialGallery(): void {
    $category  = trim($_GET['category']   ?? '');
    $year      = trim($_GET['year']       ?? '');
    $eventType = trim($_GET['event_type'] ?? '');

    if (!in_array($category, ['volunteer', 'sharing'], true)) {
        Response::error('category 파라미터가 필요합니다. (volunteer | sharing)', 422);
    }

    $db     = DB::getInstance();
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
