<?php
require_once(dirname(__FILE__) . '/../functions.php');
require_once('assign_functions.php');

header('Content-Type: application/json');

// パラメータ取得
$date = $_GET['date'] ?? '';
$start_time = $_GET['start_time'] ?? '';
$end_time = $_GET['end_time'] ?? '';
$course_time = (int)($_GET['course_time'] ?? 0);

if (!$date || !$start_time || !$end_time || !$course_time) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = dbConnect();
    
    // 基準日を計算
    $baseDate = get_BaseDateFromReservation($start_time);
    
    // その時間帯に空いている女の子を取得
    $availableGirls = getAvailableGirls($pdo, $baseDate, $start_time, $end_time, $course_time);
    
    error_log("Available girls count: " . count($availableGirls));
    
    if (empty($availableGirls)) {
        echo json_encode([]);
        exit;
    }
    
    // 各女の子のオプションを取得
    $allGirlOptions = [];
    foreach ($availableGirls as $girl) {
        $stmt = $pdo->prepare("SELECT option FROM girl WHERE g_login_id = :g_login_id");
        $stmt->execute([':g_login_id' => $girl['g_login_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $options = [];
        if ($row && !empty($row['option'])) {
            $options = json_decode($row['option'], true) ?: [];
        }
        $allGirlOptions[] = $options;
        
        error_log("Girl: {$girl['name']}, Options: " . json_encode($options));
    }
    
    // 全員が対応可能なオプション（積集合）を計算
    if (empty($allGirlOptions)) {
        error_log("No girl options found");
        echo json_encode([]);
        exit;
    }
    
    // 最初の女の子のオプションから開始
    $commonOptions = $allGirlOptions[0];
    error_log("Starting with options: " . json_encode($commonOptions));
    
    // 他の女の子のオプションと順次比較
    for ($i = 1; $i < count($allGirlOptions); $i++) {
        $girlOptions = $allGirlOptions[$i];
        error_log("Comparing with girl $i options: " . json_encode($girlOptions));
        
        // 手動で積集合を計算（順番に依存しない）
        $newCommon = [];
        foreach ($commonOptions as $option) {
            if (in_array($option, $girlOptions, true)) {
                $newCommon[] = $option;
            }
        }
        $commonOptions = $newCommon;
        error_log("Common after comparison $i: " . json_encode($commonOptions));
    }
    
    error_log("Final common options: " . json_encode($commonOptions));
    
    // 結果を返す
    echo json_encode(array_values($commonOptions));
    
} catch (Exception $e) {
    error_log("get_free_options error: " . $e->getMessage());
    echo json_encode([]);
}
?>