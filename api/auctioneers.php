<?php
/*
 * GET /api/auctioneers   경매사 목록 (과일부/채소부 구분)
 * organization_members 테이블에서 is_auctioneer=1 인 행을 dept 기준으로 분류
 */
function getAuctioneers(): void {
    $stmt = DB::getInstance()->prepare(
        'SELECT id, dept, name, position, charge, phone, email, photo
         FROM organization_members
         WHERE is_auctioneer = 1 AND is_active = 1
         ORDER BY dept, sort_order, id'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $data = ['fruit' => [], 'veg' => []];
    foreach ($rows as $r) {
        $key = ($r['dept'] === '과일부') ? 'fruit' : 'veg';
        $data[$key][] = $r;
    }
    Response::success($data);
}
