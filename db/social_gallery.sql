-- ============================================================
--  사회공헌 활동사진 테이블
--  대상 DB: MySQL 5.7+ / MariaDB 10.3+
--  실행: mysql -u djcg_user -p djcg_db < social_gallery.sql
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+09:00';

CREATE TABLE IF NOT EXISTS social_gallery (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    category    ENUM('volunteer','sharing')
                              NOT NULL COMMENT 'volunteer=복지관후원, sharing=나눔행사',
    event_type  VARCHAR(50)       NULL DEFAULT NULL
                              COMMENT 'sharing 전용 필터: kimchi | jjajang | NULL',
    title       VARCHAR(200)  NOT NULL COMMENT '사진 제목',
    event_date  DATE          NOT NULL COMMENT '활동 날짜 (연도 필터 기준)',
    caption     TEXT              NULL DEFAULT NULL COMMENT '설명 (라이트박스 표시)',
    image_name  VARCHAR(300)      NULL DEFAULT NULL COMMENT '서버 저장 파일명 (랜덤+확장자)',
    sort_order  INT           NOT NULL DEFAULT 0    COMMENT '동일 날짜 내 순서',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_category   (category),
    KEY idx_event_date (event_date),
    KEY idx_cat_date   (category, event_date)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='사회공헌 활동사진';
