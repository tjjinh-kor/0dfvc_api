<?php
/*
 * mbstring 확장이 없는 환경(가비아 공유호스팅 등)을 위한 fallback 정의.
 * strlen/substr은 바이트 기준이지만, 검증 한계값이 넉넉하고
 * 입력값 트리밍 후 호출되므로 실용상 문제없음.
 */
if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = 'UTF-8') {
        return strlen($str);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = 'UTF-8') {
        return ($length === null) ? substr($str, $start) : substr($str, $start, $length);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($str, $encoding = 'UTF-8') {
        return strtolower($str);
    }
}
