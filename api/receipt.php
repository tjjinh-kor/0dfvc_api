<?php
/*
 * GET /api/receipt   반입물량
 *
 * Query params:
 *   date_from  string  시작일 YYYY-MM-DD (기본: 오늘)
 *   date_to    string  종료일 YYYY-MM-DD (기본: 오늘)
 *   date       string  단일 날짜 (하위 호환)
 */
function getReceiptVolume(): void {
    $db = DB::getInstance();

    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo   = trim($_GET['date_to']   ?? '');

    /* 기존 단일 date 파라미터 하위 호환 */
    if ($dateFrom === '' && $dateTo === '') {
        $single   = trim($_GET['date'] ?? '');
        $dateFrom = $dateTo = ($single !== '') ? $single : '';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-d');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = $dateFrom;
    }

    /* 종료일 < 시작일이면 교환 */
    if ($dateTo < $dateFrom) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    /* 최대 31일 제한 */
    $diff = (int)(new DateTime($dateFrom))->diff(new DateTime($dateTo))->days;
    if ($diff > 31) {
        Response::error('최대 조회 기간(1개월)을 초과했습니다.', 400);
    }

    $sql = 'SELECT arrival_date, category, item_name, grade,
                   box_count, quantity, weight
            FROM receipt_volume
            WHERE arrival_date BETWEEN :date_from AND :date_to
            ORDER BY arrival_date DESC, item_name, grade';

    $params = [':date_from' => $dateFrom, ':date_to' => $dateTo];

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['box_count'] = (int)$row['box_count'];
        $row['quantity']  = (int)$row['quantity'];
        $row['weight']    = (float)$row['weight'];
    }
    unset($row);

    $meta = [
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'count'     => count($rows),
    ];
    if (empty($rows)) {
        $meta['message'] = '조회 결과가 없습니다.';
    }
    Response::success($rows, $meta);
}
