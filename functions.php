<?php
// 元々の config.php 読み込みを維持
require_once(dirname(__FILE__) . '/config/config.php');

/**
 * DB接続用関数
 */
function dbConnect() {
    try {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER,
            DB_PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // エラーを例外で投げる
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // fetch() の結果を連想配列にする
                PDO::ATTR_EMULATE_PREPARES => false // SQLインジェクション対策
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        exit('DB接続エラー: ' . $e->getMessage());
    }
}

/**
 * 営業時間 17:00〜27:00 を扱うユーティリティ
 */

// "27:00" 形式 → DateTime（翌日 3:00 に変換）
function normalizeTime($timeStr, $date='today') {
    $parts = explode(':', $timeStr);
    $h = intval($parts[0]);
    $m = isset($parts[1]) ? intval($parts[1]) : 0;

    if ($h >= 24) {
        $h -= 24;
        $date = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
    }

    return new DateTime("$date $h:$m:00");
}

// DateTime → "27:00" 形式で表示
function displayTime($dt) {
    $h = intval($dt->format('H'));
    $m = $dt->format('i');
    $base = new DateTime($dt->format('Y-m-d 00:00:00'));
    $diffHours = ($dt->getTimestamp() - $base->getTimestamp()) / 3600;
    if ($diffHours >= 24) {
        $h += 24;
    }
    return sprintf("%d:%02d", $h, $m);
}

// 30分刻みスロット生成（17:00〜27:00対応）
function generateTimeSlots($start="17:00", $end="27:00", $date='today') {
    $slots = [];
    $startDT = normalizeTime($start, $date);
    $endDT   = normalizeTime($end, $date);
    $cur = clone $startDT;

    while ($cur < $endDT) {
        $slots[] = displayTime($cur);
        $cur->modify('+30 minutes');
    }

    return $slots;
}

/**
 * DBアクセス系関数
 */

// k_check = 'y' の女の子を取得
function k_checkgirls($pdo) {
    $sql = "SELECT * FROM girl WHERE k_check = :k_check";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':k_check', 'y', PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 今日出勤の女の子を取得
function girls_today($pdo) {
    $target_date = date('Y-m-d');

    $sql = "SELECT g.* 
            FROM girl g
            JOIN shift s ON g.g_login_id = s.g_login_id
            WHERE s.date = :date";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':date', $target_date, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 指定日出勤の女の子を取得
function girls_targetday($pdo, $target_date = null) {
    // 日付が渡されなければ今日を使う
    if (!$target_date) {
        $target_date = date('Y-m-d');
    }

    $sql = "SELECT g.* 
            FROM girl g
            JOIN shift s ON g.g_login_id = s.g_login_id
            WHERE s.date = :date";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':date', $target_date, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// 指定テーブルのカラムをプルダウン用配列として取得
function getDropdownArray(PDO $pdo, string $table, string $column, string $search_keyword = '', string $search_column = ''): array {
    // カラムの存在チェック
    $columns = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);

    $order = '';
    if (in_array('sort_order', $columns, true)) {
        $order = " ORDER BY sort_order ASC";
    } elseif (in_array('id', $columns, true)) {
        $order = " ORDER BY id ASC";
    } else {
        $order = " ORDER BY {$column} ASC"; // あいうえお順
    }

    $sql = "SELECT {$column} FROM {$table}";
    $params = [];

    if ($search_keyword !== '' && $search_column !== '') {
        $sql .= " WHERE {$search_column} LIKE :keyword";
        $params[':keyword'] = '%' . $search_keyword . '%';
    }

    $sql .= $order;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $result = [];
    foreach ($rows as $row) {
        $result[$row] = $row; // keyもvalueも同じ
    }

    return $result;
}

// 配列からプルダウン用の value=>label 配列を作る
function buildDropdownArray(array $data, string $valueKey, string $labelKey, bool $appendToday = false, string $todayLabel = '本日出勤中', array $todayFilter = []): array {
    $result = [];
    foreach ($data as $row) {
        $value = $row[$valueKey] ?? '';
        $label = $row[$labelKey] ?? '';

        if ($appendToday && in_array($value, $todayFilter)) {
            $label .= $todayLabel;
        }

        $result[$value] = $label;
    }
    return $result;
}
/**
 * 現在時刻から見た基準日を取得
 * 基準日は明朝6時が切り替えタイミング
 *
 * @param DateTime|null $now 現在時刻。指定がなければ現在時刻
 * @return string 基準日 'Y-m-d' 形式
 */
function getBaseDate(?DateTime $now = null): string {
    if (!$now) $now = new DateTime();

    // 0:00〜5:59は前日を基準日とする
    $hour = (int)$now->format('H');
    $baseDate = clone $now;
    if ($hour < 6) {
        $baseDate->modify('-1 day');
    }

    return $baseDate->format('Y-m-d');
}



/**
 * 基準日を取得する関数
 * 営業日は前日17:00から当日6:00まで
 * 例：9/29 17:00 ～ 9/30 06:00 → 基準日は9/29
 */
function get_BaseDate($datetime = null) {
    // 引数がない場合は現在時刻を使用
    if ($datetime === null) {
        $datetime = new DateTime();
    } elseif (is_string($datetime)) {
        $datetime = new DateTime($datetime);
    }
    
    $hour = (int)$datetime->format('H');
    
    // 6:00未満（深夜～早朝）の場合は前日が基準日
    if ($hour < 6) {
        $baseDate = clone $datetime;
        $baseDate->modify('-1 day');
        return $baseDate->format('Y-m-d');
    } else {
        // 6:00以降は当日が基準日
        return $datetime->format('Y-m-d');
    }
}

/**
 * 指定した予約時間から基準日を取得
 */
function get_BaseDateFromReservation($reservationDateTime) {
    $dt = new DateTime($reservationDateTime);
    return get_BaseDate($dt);
}

// ランクのクラス名を取得する関数
function getRankClass($rank_name) {
    $classes = [
        'ビギナー' => 'beginner',
        'ブロンズ' => 'bronze',
        'シルバー' => 'silver',
        'ゴールド' => 'gold',
        'ダイヤモンド' => 'diamond'
    ];
    return $classes[$rank_name] ?? 'beginner';
}