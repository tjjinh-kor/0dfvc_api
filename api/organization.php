<?php
/*
 * GET /api/organization   조직도 담당자 목록
 */
function getOrganizationMembers(): void {
    $stmt = DB::getInstance()->prepare(
        'SELECT id, dept, name, position, charge, phone, photo, sort_order
         FROM organization_members
         WHERE is_active = 1
         ORDER BY dept, sort_order, id'
    );
    $stmt->execute();
    Response::success($stmt->fetchAll());
}
