<?php
/**
 * フリーコース予約時の女の子自動アサイン関数
 */
function assignGirlToReservation($pdo, $reserveData, $baseDate) {
    $start_time = $reserveData['start_time'];
    $end_time = $reserveData['end_time'];
    $course_time = (int)$reserveData['course_time'];
    $user_tel = $reserveData['tel'];
    
    // === デバッグコード ===
    error_log("=== assignGirlToReservation START ===");
    error_log("start_time: $start_time");
    error_log("end_time: $end_time");
    error_log("course_time: $course_time");
    error_log("user_tel: $user_tel");
    error_log("baseDate: $baseDate");
    
    // 1. 空いている女の子を取得
    $availableGirls = getAvailableGirls($pdo, $baseDate, $start_time, $end_time, $course_time);
    
    error_log("Available girls count: " . count($availableGirls));
    if (empty($availableGirls)) {
        error_log("NO AVAILABLE GIRLS FOUND");
        return null; // 誰も空いていない
    }
    
    // 2. ユーザーの好み設定を取得
    $userPreferences = getUserPreferences($pdo, $user_tel);
    error_log("User preferences: " . json_encode($userPreferences));
    
    // 3. 各女の子のマッチング率を計算
    $candidates = [];
    foreach ($availableGirls as $girl) {
        $matchRate = calculateMatchRate($pdo, $girl['g_login_id'], $userPreferences);
        $reservationCount = getTodayReservationCount($pdo, $girl['g_login_id'], $baseDate);
        
        $candidates[] = [
            'g_login_id' => $girl['g_login_id'],
            'g_name' => $girl['name'],
            'match_rate' => $matchRate,
            'reservation_count' => $reservationCount
        ];
        
        error_log("Girl: {$girl['name']}, Match: $matchRate%, Reservations: $reservationCount");
    }
    
    // 4. 優先順位でソート・選択
    $selected = selectBestCandidate($candidates);
    
    error_log("Selected: " . ($selected ? json_encode($selected) : 'NULL'));
    error_log("=== assignGirlToReservation END ===");
    
    return $selected;
}

/**
 * 空いている女の子を取得
 */
function getAvailableGirls($pdo, $baseDate, $start_time, $end_time, $course_time) {
    // === デバッグコード ===
    error_log("=== getAvailableGirls START ===");
    error_log("baseDate: $baseDate, start_time: $start_time, course_time: $course_time");
    
    // 基準日のシフトに入っている女の子を取得
    $stmt = $pdo->prepare("
        SELECT s.g_login_id, s.in_time, s.out_time, s.LO, g.name
        FROM shift s 
        JOIN girl g ON s.g_login_id = g.g_login_id 
        WHERE s.date = :base_date
    ");
    $stmt->execute([':base_date' => $baseDate]);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Shifts found: " . count($shifts));
    
    $available = [];
    $prepTime = 10; // 前後10分
    
    // free_check.phpと同じロジックで判定
    $slot = new DateTime($start_time);
    $y = $course_time + 2 * $prepTime; // コース時間 + 前後準備20分
    $slotEndTime = clone $slot;
    $slotEndTime->modify("+{$y} minutes");
    
    error_log("Slot: " . $slot->format('Y-m-d H:i:s') . " ~ " . $slotEndTime->format('Y-m-d H:i:s'));
    
    foreach ($shifts as $shift) {
        error_log("Checking shift for: {$shift['name']} ({$shift['g_login_id']})");
        error_log("Shift time: {$shift['in_time']} ~ {$shift['out_time']}, LO: {$shift['LO']}");
        
        $shiftIn = new DateTime($shift['in_time']);
        $shiftOut = new DateTime($shift['out_time']);
        $LO = $shift['LO'];

        // 前準備10分考慮
        $shiftInPrep = clone $shiftIn;
        $shiftInPrep->modify('-10 minutes');

        // 1. 始業前準備チェック
        if ($slot < $shiftInPrep) {
            error_log("SKIP: Slot before shift start (prep time)");
            continue;
        }

        // 2. 終了時間シフトチェック（LO=1は無視）
        if ($LO == 0 && $slotEndTime > $shiftOut) {
            error_log("SKIP: Slot end after shift end (LO=0)");
            continue;
        }

        // 3. 予約チェック（重複しないか）
        if (!checkReservationConflict($pdo, $shift['g_login_id'], $slot, $slotEndTime, $baseDate)) {
            error_log("AVAILABLE: {$shift['name']}");
            $available[] = [
                'g_login_id' => $shift['g_login_id'],
                'name' => $shift['name']
            ];
        } else {
            error_log("SKIP: Reservation conflict");
        }
    }
    
    error_log("Available girls: " . count($available));
    error_log("=== getAvailableGirls END ===");
    
    return $available;
}

/**
 * 予約重複チェック
 */
function checkReservationConflict($pdo, $g_login_id, $slot, $slotEndTime, $baseDate) {
    // 基準日ベースで予約を取得（深夜の予約も含む）
    $stmt = $pdo->prepare("
    SELECT start_time, end_time 
    FROM reserve 
    WHERE g_login_id = :g_login_id 
    AND date = :base_date
    AND done != 5
");
    $stmt->execute([
        ':g_login_id' => $g_login_id,
        ':base_date' => $baseDate
    ]);
    
    $reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($reserves as $reserve) {
        $resStart = new DateTime($reserve['start_time']);
        $resEnd = new DateTime($reserve['end_time']);
        
        // free_check.phpと同じ重複チェック条件
        if (!($slotEndTime <= $resStart || $slot >= $resEnd)) {
            return true; // 重複あり
        }
    }
    
    return false; // 重複なし
}

/**
 * ユーザーの好み設定を取得
 */
function getUserPreferences($pdo, $tel) {
    $stmt = $pdo->prepare("SELECT preference_value FROM user_preferences WHERE tel = :tel");
    $stmt->execute([':tel' => $tel]);
    
    $preferences = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $preferences[] = $row['preference_value'];
    }
    
    return $preferences;
}

/**
 * マッチング率を計算
 */
function calculateMatchRate($pdo, $g_login_id, $userPreferences) {
    if (empty($userPreferences)) {
        return 0; // 好み設定なしは0%
    }
    
    // 女の子の属性を取得
    $stmt = $pdo->prepare("SELECT attribute_value FROM girl_attributes WHERE g_login_id = :g_login_id");
    $stmt->execute([':g_login_id' => $g_login_id]);
    
    $girlAttributes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $girlAttributes[] = $row['attribute_value'];
    }
    
    if (empty($girlAttributes)) {
        return 0; // 属性設定なしは0%
    }
    
    // マッチング計算
    $matches = array_intersect($userPreferences, $girlAttributes);
    $matchRate = (count($matches) / count($userPreferences)) * 100;
    
    return $matchRate;
}

/**
 * 当日の予約数を取得
 */
function getTodayReservationCount($pdo, $g_login_id, $baseDate) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reserve 
        WHERE g_login_id = :g_login_id 
        AND date = :base_date
        AND done IN (1, 2, 3, 4)
    ");
    $stmt->execute([
        ':g_login_id' => $g_login_id,
        ':base_date' => $baseDate
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$result['count'];
}
/**
 * 最適な候補者を選択
 */
function selectBestCandidate($candidates) {
    if (empty($candidates)) {
        return null;
    }
    
    // 1. 最高マッチング率で絞り込み
    $maxMatchRate = max(array_column($candidates, 'match_rate'));
    $topMatches = array_filter($candidates, function($c) use ($maxMatchRate) {
        return $c['match_rate'] == $maxMatchRate;
    });
    
    if (count($topMatches) == 1) {
        return reset($topMatches);
    }
    
    // 2. 予約数最少で絞り込み
    $minReservations = min(array_column($topMatches, 'reservation_count'));
    $leastBusy = array_filter($topMatches, function($c) use ($minReservations) {
        return $c['reservation_count'] == $minReservations;
    });
    
    if (count($leastBusy) == 1) {
        return reset($leastBusy);
    }
    
    // 3. ランダム選択
    $randomKey = array_rand($leastBusy);
    return $leastBusy[$randomKey];

  }
/**
 * 最終予約重複チェック関数
 */
function checkFinalReservationConflict($pdo, $reserveData) {
    $g_login_id = $reserveData[':g_login_id'];
    $start_time = $reserveData[':start_time'];
    $end_time = $reserveData[':end_time'];
    $date = $reserveData[':date'];
    
    if (empty($g_login_id) || empty($start_time) || empty($end_time)) {
        return false; // データ不備は重複とみなす
    }
    
    // 該当女の子の既存予約をチェック
    $stmt = $pdo->prepare("
    SELECT start_time, end_time 
    FROM reserve 
    WHERE g_login_id = :g_login_id 
    AND date = :date
    AND done IN (1, 2, 3, 4)
");
    $stmt->execute([
        ':g_login_id' => $g_login_id,
        ':date' => $date
    ]);
    
    $existingReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $newStart = new DateTime($start_time);
    $newEnd = new DateTime($end_time);
    
    foreach ($existingReservations as $existing) {
        $existingStart = new DateTime($existing['start_time']);
        $existingEnd = new DateTime($existing['end_time']);
        
        // 重複チェック（free_check.phpと同じロジック）
        if (!($newEnd <= $existingStart || $newStart >= $existingEnd)) {
            return false; // 重複あり
        }
    }
    
    return true; // 重複なし
}
/**
 * 予約時間から基準日を計算（6時切り替え）
 */

?>