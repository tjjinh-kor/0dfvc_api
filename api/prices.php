<?php
/*
 * GET /api/prices         시세현황 목록
 * GET /api/prices/trend   품목별동향
 */

/* ─── GET /api/prices ─────────────────────────────────────────────────────
 *  Query params:
 *    category  string  대분류 필터 (과일 | 채소 | 기타)
 *    date      string  기준일 YYYY-MM-DD (기본: 오늘)
 */
function getPriceList(): void {
    $db       = DB::getInstance();
    $category = trim($_GET['category'] ?? '');
    $date     = trim($_GET['date'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    /* DATE() 래핑: recorded_at 이 DATE/DATETIME/TIMESTAMP 어느 타입이어도 안전하게 비교 */
    $sql    = 'SELECT category, divi_name, sect_name, weight,
                      max_price, min_price, avg_price, kg_price,
                      qty, amt, recorded_at
               FROM prices
               WHERE DATE(recorded_at) = :date';
    $params = [':date' => $date];

    if ($category !== '') {
        $sql .= ' AND category = :category';
        $params[':category'] = $category;
    }
    $sql .= ' ORDER BY category, divi_name, sect_name, weight';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['max_price'] = (int)$row['max_price'];
        $row['min_price'] = (int)$row['min_price'];
        $row['avg_price'] = (int)$row['avg_price'];
        $row['kg_price']  = (int)$row['kg_price'];
        $row['weight']    = (float)$row['weight'];
        $row['qty']       = (int)$row['qty'];
        $row['amt']       = (int)$row['amt'];
    }
    unset($row);

    /* 빈 결과도 success:true로 반환 (JS에서 "조회 결과 없음" 처리) */
    $meta = ['date' => $date, 'count' => count($rows)];
    if (empty($rows)) {
        $meta['message'] = '조회 결과가 없습니다.';
    }
    Response::success($rows, $meta);
}

/* ─── GET /api/prices/trend ───────────────────────────────────────────────
 *  Query params:
 *    item  string  품목명 필터
 *    days  int     조회 기간 (1~30, 기본 7)
 */
function getPriceTrend(): void {
    $db   = DB::getInstance();
    $item = trim($_GET['item'] ?? '');
    $days = max(1, min(30, (int)($_GET['days'] ?? 7)));

    // $days 는 int 이므로 직접 보간 (SQL injection 불가)
    $sql    = "SELECT item_name, category, price, unit, trend_date
               FROM price_trend
               WHERE trend_date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
    $params = [];

    if ($item !== '') {
        $sql .= ' AND item_name = :item';
        $params[':item'] = $item;
    }
    $sql .= ' ORDER BY item_name, trend_date';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['price'] = (int)$row['price'];
    }
    unset($row);

    Response::success($rows, ['days' => $days, 'count' => count($rows)]);
}
