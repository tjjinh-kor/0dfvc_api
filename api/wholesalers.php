<?php
/*
 * GET /api/wholesalers   중도매인 목록 (과일/채소 구분)
 */
function getWholesalers(): void {
    $stmt = DB::getInstance()->prepare(
        'SELECT brokcode, category, company_name, representative, items, phone, website_url, note
         FROM wholesalers
         WHERE is_active = 1
         ORDER BY category, sort_order, brokcode'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $data = ['fruit' => [], 'veg' => []];
    foreach ($rows as $r) {
        $key = ($r['category'] === '과일') ? 'fruit' : 'veg';
        $data[$key][] = $r;
    }
    Response::success($data);
}
