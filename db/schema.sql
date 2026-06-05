-- ============================================================
--  대전청과(주) API DB 스키마
--  대상 DB: MySQL 5.7+ / MariaDB 10.3+
--  문자셋: utf8mb4 (이모지·특수문자 포함)
--  실행: mysql -u djcg_user -p djcg_db < schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+09:00';

-- ──────────────────────────────────────────────────────────
-- 1. 시세현황 (prices)
--    GET /api/prices
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS prices (
    id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    category         VARCHAR(30)      NOT NULL COMMENT '대분류 (과일/채소/기타)',
    item_name        VARCHAR(50)      NOT NULL COMMENT '품목명',
    unit             VARCHAR(20)      NOT NULL DEFAULT 'kg' COMMENT '단위',
    today_price      INT UNSIGNED     NOT NULL DEFAULT 0    COMMENT '금일 평균가 (원/단위)',
    yesterday_price  INT UNSIGNED     NOT NULL DEFAULT 0    COMMENT '전일 평균가 (원/단위)',
    week_ago_price   INT UNSIGNED     NOT NULL DEFAULT 0    COMMENT '전주 평균가 (원/단위)',
    recorded_at      DATE             NOT NULL              COMMENT '시세 기준일',
    created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_item_date (item_name, recorded_at),
    KEY idx_recorded_at (recorded_at),
    KEY idx_category    (category)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='시세현황';

-- ──────────────────────────────────────────────────────────
-- 2. 품목별 가격 동향 (price_trend)
--    GET /api/prices/trend
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS price_trend (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    item_name   VARCHAR(50)   NOT NULL COMMENT '품목명',
    category    VARCHAR(30)   NOT NULL COMMENT '대분류',
    price       INT UNSIGNED  NOT NULL DEFAULT 0   COMMENT '해당일 평균가 (원)',
    unit        VARCHAR(20)   NOT NULL DEFAULT 'kg',
    trend_date  DATE          NOT NULL              COMMENT '가격 기준일',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_item_trend_date (item_name, trend_date),
    KEY idx_item_date  (item_name, trend_date),
    KEY idx_trend_date (trend_date)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='품목별 가격 동향';

-- ──────────────────────────────────────────────────────────
-- 3. 실시간 경매현황 (auction_status)
--    GET /api/auction/live
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS auction_status (
    id             INT UNSIGNED         NOT NULL AUTO_INCREMENT,
    lot_no         VARCHAR(20)          NOT NULL              COMMENT '경매 번호',
    item_name      VARCHAR(50)          NOT NULL              COMMENT '품목명',
    category       VARCHAR(30)          NOT NULL              COMMENT '대분류',
    origin         VARCHAR(50)          NOT NULL DEFAULT ''   COMMENT '산지',
    grade          VARCHAR(10)          NOT NULL DEFAULT ''   COMMENT '등급 (특/상/보통)',
    quantity       DECIMAL(10, 2)       NOT NULL DEFAULT 0.00 COMMENT '반입 수량',
    unit           VARCHAR(20)          NOT NULL DEFAULT 'kg' COMMENT '단위',
    starting_price INT UNSIGNED         NOT NULL DEFAULT 0    COMMENT '시작가 (원)',
    final_price    INT UNSIGNED         NOT NULL DEFAULT 0    COMMENT '낙찰가 (원, 0=미낙찰)',
    status         ENUM(
                     'waiting',         -- 경매 대기
                     'ongoing',         -- 진행 중
                     'completed',       -- 낙찰 완료
                     'cancelled'        -- 유찰
                   )                    NOT NULL DEFAULT 'waiting',
    auction_at     DATETIME             NOT NULL              COMMENT '경매 시작 시각',
    created_at     DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auction_at (auction_at),
    KEY idx_status     (status),
    KEY idx_lot_no     (lot_no)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='실시간 경매현황';

-- ──────────────────────────────────────────────────────────
-- 4. 반입물량 (receipt_volume)
--    GET /api/receipt
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS receipt_volume (
    id           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    category     VARCHAR(30)    NOT NULL              COMMENT '대분류',
    item_name    VARCHAR(50)    NOT NULL              COMMENT '품목명',
    origin       VARCHAR(50)    NOT NULL DEFAULT ''   COMMENT '산지',
    quantity     DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '반입 수량',
    unit         VARCHAR(20)    NOT NULL DEFAULT 'kg' COMMENT '단위',
    arrival_date DATE           NOT NULL              COMMENT '반입 날짜',
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_arrival_date (arrival_date),
    KEY idx_category     (category)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='반입물량';

-- ──────────────────────────────────────────────────────────
-- 5. 공지사항 (notice)
--    GET /api/notice
--    GET /api/notice/{id}
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notice (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(200) NOT NULL              COMMENT '제목',
    content     TEXT         NOT NULL              COMMENT '본문 (HTML 허용)',
    author      VARCHAR(50)  NOT NULL DEFAULT '관리자',
    is_pinned   TINYINT(1)   NOT NULL DEFAULT 0    COMMENT '상단 고정 여부',
    view_count  INT UNSIGNED NOT NULL DEFAULT 0    COMMENT '조회수',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_created_at (created_at),
    KEY idx_is_pinned  (is_pinned)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='공지사항';

-- ──────────────────────────────────────────────────────────
-- 6. 문의 접수 (contact_inquiry)
--    POST /api/contact
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contact_inquiry (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(50)  NOT NULL              COMMENT '문의자 이름',
    email       VARCHAR(100) NOT NULL              COMMENT '이메일',
    phone       VARCHAR(20)  NOT NULL DEFAULT ''   COMMENT '연락처',
    subject     VARCHAR(200) NOT NULL              COMMENT '제목',
    content     TEXT         NOT NULL              COMMENT '내용',
    is_answered TINYINT(1)   NOT NULL DEFAULT 0    COMMENT '답변 완료 여부',
    answered_at DATETIME          NULL DEFAULT NULL COMMENT '답변 시각',
    ip_address  VARCHAR(45)  NOT NULL DEFAULT ''   COMMENT '요청 IP (IPv4/v6)',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_created_at  (created_at),
    KEY idx_is_answered (is_answered)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='문의 접수';

-- ──────────────────────────────────────────────────────────
-- 7. 미수령상품대공시 (unclaimed_products)
--    GET /api/v1/support/unclaimed  (추후 구현)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS unclaimed_products (
    id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    lot_no         VARCHAR(20)    NOT NULL               COMMENT '경매 번호',
    item_name      VARCHAR(50)    NOT NULL               COMMENT '품목명',
    quantity       DECIMAL(10, 2) NOT NULL DEFAULT 0.00  COMMENT '수량',
    unit           VARCHAR(20)    NOT NULL DEFAULT 'kg',
    auction_date   DATE           NOT NULL               COMMENT '경매 실시일',
    claim_deadline DATE           NOT NULL               COMMENT '수령 마감일',
    status         ENUM(
                     'unclaimed',  -- 미수령
                     'claimed',    -- 수령 완료
                     'disposed'    -- 처분 완료
                   )              NOT NULL DEFAULT 'unclaimed',
    note           VARCHAR(500)   NOT NULL DEFAULT ''    COMMENT '비고',
    published_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '공시 일시',
    created_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auction_date  (auction_date),
    KEY idx_status        (status),
    KEY idx_published_at  (published_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='미수령상품대공시';

-- ──────────────────────────────────────────────────────────
-- 8. 부적합농산물공시 (unfit_products)
--    GET /api/v1/support/unfit-produce  (추후 구현)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS unfit_products (
    id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    item_name      VARCHAR(50)    NOT NULL               COMMENT '품목명',
    origin         VARCHAR(50)    NOT NULL               COMMENT '산지',
    reason         VARCHAR(200)   NOT NULL               COMMENT '부적합 사유 (잔류농약 초과 등)',
    quantity       DECIMAL(10, 2) NOT NULL DEFAULT 0.00  COMMENT '해당 수량',
    unit           VARCHAR(20)    NOT NULL DEFAULT 'kg',
    disposal_date  DATE           NOT NULL               COMMENT '처리(폐기) 완료일',
    published_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '공시 일시',
    created_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_published_at  (published_at),
    KEY idx_disposal_date (disposal_date)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='부적합농산물공시';

-- ──────────────────────────────────────────────────────────
-- 초기 데이터 (개발/테스트용)
-- ──────────────────────────────────────────────────────────

INSERT INTO notice (title, content, author, is_pinned) VALUES
('시스템 오픈 안내', '<p>대전청과(주) 공식 홈페이지가 오픈되었습니다.</p>', '관리자', 1),
('2024년 추석 연휴 경매 일정 안내', '<p>추석 연휴 기간 경매 일정을 안내해 드립니다.</p>', '관리자', 0),
('전자송품장 서비스 이용 안내', '<p>전자송품장 서비스 이용 방법을 안내해 드립니다.</p>', '관리자', 0);

INSERT INTO prices (category, item_name, unit, today_price, yesterday_price, week_ago_price, recorded_at) VALUES
('과일', '사과 (후지)',  '10kg', 42000, 41000, 40000, CURDATE()),
('과일', '배 (원황)',    '15kg', 55000, 54000, 53000, CURDATE()),
('과일', '감귤',         '5kg',  18000, 18500, 17000, CURDATE()),
('채소', '배추',         '포기', 4500,  4200,  4000,  CURDATE()),
('채소', '무',           '개',   1800,  1900,  1700,  CURDATE()),
('채소', '대파',         'kg',   3200,  3100,  3000,  CURDATE()),
('기타', '고추 (청양)',  'kg',   12000, 11500, 11000, CURDATE()),
('기타', '마늘',         'kg',   8500,  8300,  8000,  CURDATE());

SET FOREIGN_KEY_CHECKS = 1;
