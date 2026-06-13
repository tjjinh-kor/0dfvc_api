<?php
/*
 * GET    /api/wholesalers        공개 목록 (과일/채소 구분, 인증 불필요)
 * POST   /api/wholesalers        신규 등록 (인증 필요)
 * PUT    /api/wholesalers/{id}   수정     (인증 필요)
 * DELETE /api/wholesalers/{id}   삭제     (인증 필요)
 */

function whs_verify_token(): void {
    $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
        $token = $m[1];
    }
    if (!defined('ADMIN_API_TOKEN') || !hash_equals(ADMIN_API_TOKEN, $token)) {
        Response::error('인증이 필요합니다.', 401);
    }
}

function whs_parse_body(): array {
    $raw = file_get_contents('php://input');
    $body = ($raw !== '') ? (json_decode($raw, true) ?? []) : [];
    return array_merge($_POST, $body);
}

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

function postWholesaler(): void {
    whs_verify_token();
    $body = whs_parse_body();

    $brokcode    = trim($body['brokcode']       ?? '');
    $category    = trim($body['category']       ?? '');
    $companyName = trim($body['company_name']   ?? '');
    $rep         = trim($body['representative'] ?? '') ?: null;
    $items       = trim($body['items']          ?? '') ?: null;
    $phone       = trim($body['phone']          ?? '') ?: null;
    $websiteUrl  = trim($body['website_url']    ?? '') ?: null;
    $note        = trim($body['note']           ?? '') ?: null;
    $isActive    = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;
    $sortOrder   = (int)($body['sort_order'] ?? 0);

    if ($brokcode === '')                              Response::error('중도매인번호는 필수입니다.', 422);
    if (strlen($brokcode) > 5)                        Response::error('중도매인번호는 5자 이내여야 합니다.', 422);
    if (!in_array($category, ['과일', '채소'], true)) Response::error('부류는 과일 또는 채소여야 합니다.', 422);
    if ($companyName === '')                           Response::error('상호명은 필수입니다.', 422);

    $db = DB::getInstance();
    $ck = $db->prepare('SELECT COUNT(*) FROM wholesalers WHERE brokcode = ?');
    $ck->execute([$brokcode]);
    if ((int)$ck->fetchColumn() > 0) {
        Response::error("중도매인번호 '{$brokcode}'는 이미 사용 중입니다.", 409);
    }

    $db->prepare(
        'INSERT INTO wholesalers (brokcode, category, company_name, representative,
         items, phone, website_url, note, is_active, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$brokcode, $category, $companyName, $rep, $items, $phone, $websiteUrl, $note, $isActive, $sortOrder]);

    Response::success(['id' => (int)$db->lastInsertId(), 'brokcode' => $brokcode], [], 201);
}

function putWholesaler(int $id): void {
    whs_verify_token();
    $body = whs_parse_body();

    $db  = DB::getInstance();
    $chk = $db->prepare('SELECT id FROM wholesalers WHERE id = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) Response::error('존재하지 않는 중도매인입니다.', 404);

    $brokcode    = trim($body['brokcode']       ?? '');
    $category    = trim($body['category']       ?? '');
    $companyName = trim($body['company_name']   ?? '');

    if ($brokcode !== '') {
        if (strlen($brokcode) > 5) Response::error('중도매인번호는 5자 이내여야 합니다.', 422);
        $ck = $db->prepare('SELECT COUNT(*) FROM wholesalers WHERE brokcode = ? AND id != ?');
        $ck->execute([$brokcode, $id]);
        if ((int)$ck->fetchColumn() > 0) {
            Response::error("중도매인번호 '{$brokcode}'는 이미 사용 중입니다.", 409);
        }
    }
    if ($category !== '' && !in_array($category, ['과일', '채소'], true)) {
        Response::error('부류는 과일 또는 채소여야 합니다.', 422);
    }

    $sets = [];
    $params = [];
    if ($brokcode !== '')    { $sets[] = 'brokcode = ?';      $params[] = $brokcode; }
    if ($category !== '')    { $sets[] = 'category = ?';      $params[] = $category; }
    if ($companyName !== '') { $sets[] = 'company_name = ?';  $params[] = $companyName; }
    foreach (['representative', 'items', 'phone', 'website_url', 'note'] as $col) {
        if (array_key_exists($col, $body)) {
            $sets[]   = "{$col} = ?";
            $params[] = trim($body[$col]) ?: null;
        }
    }
    if (array_key_exists('is_active', $body))  { $sets[] = 'is_active = ?';  $params[] = (int)(bool)$body['is_active']; }
    if (array_key_exists('sort_order', $body)) { $sets[] = 'sort_order = ?'; $params[] = (int)$body['sort_order']; }

    if (empty($sets)) Response::error('변경할 항목이 없습니다.', 422);

    $sets[]   = 'updated_at = NOW()';
    $params[] = $id;
    $db->prepare('UPDATE wholesalers SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

    Response::success(['id' => $id]);
}

function deleteWholesaler(int $id): void {
    whs_verify_token();
    $db  = DB::getInstance();
    $chk = $db->prepare('SELECT id FROM wholesalers WHERE id = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) Response::error('존재하지 않는 중도매인입니다.', 404);

    $db->prepare('DELETE FROM wholesalers WHERE id = ?')->execute([$id]);
    Response::success(['deleted' => true]);
}
