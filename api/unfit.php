<?php
/*
 * GET /api/unfit   부적합농산물공시 목록
 *
 * 조회 조건: CURDATE() BETWEEN ban_start_date AND ban_end_date
 * 응답 데이터에 management_info, note 절대 포함 안 함.
 */
function getUnfitList(): void {
    $db = DB::getInstance();

    $stmt = $db->prepare(
        "SELECT id, item_name, producer_name, origin_area, detected_component,
                ban_start_date, ban_end_date
         FROM unfit_product_notices
         WHERE CURDATE() BETWEEN ban_start_date AND ban_end_date
         ORDER BY ban_start_date DESC, id DESC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['items' => $rows, 'total' => count($rows)]);
}
