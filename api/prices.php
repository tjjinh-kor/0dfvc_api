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

    $sql    = 'SELECT id, category, item_name, unit,
                      today_price, yesterday_price, week_ago_price, recorded_at
               FROM prices
               WHERE recorded_at = :date';
    $params = [':date' => $date];

    if ($category !== '') {
        $sql .= ' AND category = :category';
        $params[':category'] = $category;
    }
    $sql .= ' ORDER BY category, item_name';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['today_price']      = (int)$row['today_price'];
        $row['yesterday_price']  = (int)$row['yesterday_price'];
        $row['week_ago_price']   = (int)$row['week_ago_price'];

        $prev                    = $row['yesterday_price'];
        $today                   = $row['today_price'];
        $row['change_pct']       = $prev > 0 ? round(($today - $prev) / $prev * 100, 1) : 0.0;
        $row['change_direction'] = $row['change_pct'] > 0 ? 'up' : ($row['change_pct'] < 0 ? 'down' : 'same');
    }
    unset($row);

    Response::success($rows, ['date' => $date, 'count' => count($rows)]);
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
