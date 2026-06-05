<?php
/*
 * GET /api/receipt   반입물량
 */

/* ─── GET /api/receipt ────────────────────────────────────────────────────
 *  Query params:
 *    date      string  기준일 YYYY-MM-DD (기본: 오늘)
 *    category  string  대분류 필터
 */
function getReceiptVolume(): void {
    $db       = DB::getInstance();
    $date     = trim($_GET['date'] ?? '');
    $category = trim($_GET['category'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $sql    = 'SELECT id, category, item_name, origin, quantity, unit, arrival_date
               FROM receipt_volume
               WHERE arrival_date = :date';
    $params = [':date' => $date];

    if ($category !== '') {
        $sql .= ' AND category = :category';
        $params[':category'] = $category;
    }
    $sql .= ' ORDER BY category, quantity DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['quantity'] = (float)$row['quantity'];
    }
    unset($row);

    Response::success($rows, ['date' => $date, 'count' => count($rows)]);
}
