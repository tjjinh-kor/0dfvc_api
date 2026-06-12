<?php
/*
 * GET /api/unclaimed   미수령상품대공시 목록
 *
 * Query params:
 *   year   int  출하일자 연도 (기본: 현재 연도)
 *   month  int  출하일자 월  (1-12, 생략 시 전체)
 *
 * 응답 데이터에 management_info 절대 포함 안 함.
 */
function getUnclaimedList(): void {
    $year  = isset($_GET['year'])  ? (int)$_GET['year']  : 0;
    $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

    if ($year !== 0 && ($year < 2000 || $year > 2100)) {
        Response::error('유효하지 않은 연도입니다.', 422);
    }

    $db = DB::getInstance();

    $where  = ['is_published = 1'];
    $params = [];

    if ($year > 0) {
        $where[]  = 'YEAR(shipment_date) = ?';
        $params[] = $year;
    }
    if ($month >= 1 && $month <= 12) {
        $where[]  = 'MONTH(shipment_date) = ?';
        $params[] = $month;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $db->prepare(
        "SELECT id, shipment_date, category, producer_name, product_name, note
         FROM unclaimed_product_notices
         {$whereSql}
         ORDER BY shipment_date DESC, id DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['items' => $rows, 'total' => count($rows)]);
}
