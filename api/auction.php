<?php
/*
 * GET /api/auction/live   실시간경매현황
 */

/* ─── GET /api/auction/live ───────────────────────────────────────────────
 *  Query params:
 *    status    string  경매 상태 필터 (waiting | ongoing | completed | cancelled)
 *    category  string  품목 대분류 필터
 */
function getLiveAuction(): void {
    $db       = DB::getInstance();
    $status   = trim($_GET['status']   ?? '');
    $category = trim($_GET['category'] ?? '');

    $validStatuses = ['waiting', 'ongoing', 'completed', 'cancelled'];

    $sql    = 'SELECT id, lot_no, item_name, category, origin, grade,
                      quantity, unit, starting_price, final_price, status, auction_at
               FROM auction_status
               WHERE DATE(auction_at) = CURDATE()';
    $params = [];

    if ($status !== '' && in_array($status, $validStatuses, true)) {
        $sql .= ' AND status = :status';
        $params[':status'] = $status;
    }
    if ($category !== '') {
        $sql .= ' AND category = :category';
        $params[':category'] = $category;
    }
    $sql .= ' ORDER BY auction_at DESC LIMIT 300';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['starting_price'] = (int)$row['starting_price'];
        $row['final_price']    = (int)$row['final_price'];
        $row['quantity']       = (float)$row['quantity'];
    }
    unset($row);

    Response::success($rows, [
        'date'         => date('Y-m-d'),
        'count'        => count($rows),
        'generated_at' => date('Y-m-d H:i:s'),
    ]);
}
