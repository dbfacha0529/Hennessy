<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (empty($_SESSION['USER']['login_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = dbConnect();
$err = [];
$reserveData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POSTデータ取得
    $girl_name = trim($_POST['girl_name'] ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $reserve_date=new DateTime($_POST['reserve_date']);
    $reserve_dates =$_POST['reserve_date'];
   // $reserve_date = clone $reserve_date_k;


    $reserve_stime = trim($_POST['reserve_time'] ?? '');
        

        // 時間と分を取得
        list($hour, $minute) = explode(':', $reserve_stime);

        $hour   = (int)$hour;
        $minute = (int)$minute;

        // DateTime 作成（date 部分をセット）
        $reserveDateTime = clone $reserve_date; 

        // time 部分をセット
        $reserveDateTime->setTime($hour, $minute, 0);

        // 00:00～08:00なら翌日にする
        if ((int)$hour >= 0 && (int)$hour < 8) {
            $reserveDateTime->modify('+1 day');
        }

        // DATETIME 形式文字列に変換
    $reserve_time = $reserveDateTime->format('Y-m-d H:i:s');

    $place = trim($_POST['place'] ?? '');
    $place_other = trim($_POST['place_other'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $area_other = trim($_POST['area_other'] ?? '');
    $area_outside = isset($_POST['area_outside']) ? 1 : 0;
    $options = $_POST['options'] ?? [];
    $payment_method = (int)($_POST['payment_method'] ?? 1);
    $coupon_code = trim($_POST['coupon_code'] ?? '');
    $use_point = (int)($_POST['use_point'] ?? 0);
    $contact_tel = trim($_POST['contact_tel'] ?? '');
    $other = trim($_POST['comment'] ?? '');
    // バリデーション開始
    
    // 1. コース必須チェック
    if (empty($course_name)) {
        $err['course'][] = 'コースを選択してください';
    } else {
        // コース情報取得
        $sql = "SELECT * FROM course WHERE c_name = :c_name";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':c_name', $course_name, PDO::PARAM_STR);
        $stmt->execute();
        $courseInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$courseInfo) {
            $err['course'][] = '選択されたコースが存在しません';
        } else {
            $reserveData['course_info'] = $courseInfo;
            
            // free_checkが0の場合、女の子選択必須
            if ((int)$courseInfo['free_check'] === 0 && empty($girl_name)) {
                $err['girl'][] = 'このコースでは女の子の選択が必要です';
            }
        }
    }
    
    // 2. 女の子チェック（指定がある場合）
    if (!empty($girl_name)) {
        $sql = "SELECT * FROM girl WHERE name = :name";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':name', $girl_name, PDO::PARAM_STR);
        $stmt->execute();
        $girlInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$girlInfo) {
            $err['girl'][] = '選択された女の子が存在しません';
        } else {
            $reserveData['girl_info'] = $girlInfo;
        }
    }
    
    // 3. 日付チェック
    if (empty($reserve_dates)) {
        $err['date'][] = '日付を選択してください';
    } else {
        $dateObj = new DateTime($reserve_dates);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $reserve_dates) {
            $err['date'][] = '日付の形式が正しくありません';
        } else {
            $today = new DateTime();
            if ($dateObj < $today->setTime(0, 0, 0)) {
                $err['date'][] = '過去の日付は選択できません';
            }
        }
    }
    
    // 4. 時間チェック
    if (empty($reserve_stime)) {
        $err['time'][] = '時間を選択してください';
    } else {
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $reserve_stime)) {
            $err['time'][] = '時間の形式が正しくありません';
        }
    }
    
    // 5. place（形態）チェック
if (empty($place)) {
    $err['place'][] = '形態を選択してください';
} else {
    if ($place === 'その他' && empty($place_other)) {
        $err['place'][] = 'その他を選択した場合は詳細を入力してください';
    } else {
        // placeがDBに存在するかチェック
        if ($place !== 'その他') {
            $sql = "SELECT COUNT(*) FROM place WHERE place_name = :place_name";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':place_name', $place, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $err['place'][] = '選択された形態が無効です';
            }
        }
    }
    
    // ★ ご自宅選択時の特別バリデーション
    if ($place === 'ご自宅') {
        if ($area !== 'その他') {
            $err['area'][] = 'ご自宅の場合はエリアをその他にしてください';
        }
        if (empty($area_other)) {
            $err['area'][] = '住所を記入してください';
        }
    }
}
if (!empty($place_other) && mb_strlen($place_other) > 300) {
    $err['place'][] = '300文字以内でご記入ください';
}

// 6. area（エリア）チェック
if (empty($area)) {
    $err['area'][] = 'エリアを選択してください';
} else {
    if ($area === 'その他' && $place !== 'ご自宅' && empty($area_other)) {
        $err['area'][] = 'その他を選択した場合はエリア詳細を入力してください';
    } else {
        // areaがDBに存在するかチェック
        if ($area !== 'その他') {
            $sql = "SELECT COUNT(*) FROM area WHERE area_name = :area_name";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':area_name', $area, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $err['area'][] = '選択されたエリアが無効です';
            }
        }
    }
}
// ★ area_other の文字数チェック
if (!empty($area_other) && mb_strlen($area_other) > 300) {
    $err['area'][] = '300文字以内でご記入ください';
}
    // 7. オプションチェック（女の子が選択されている場合のみ）
    if (!empty($girl_name) && !empty($options)) {
        // 女の子のオプション取得
        $girlOptions = [];
        if (!empty($reserveData['girl_info']['option'])) {
            $girlOptions = json_decode($reserveData['girl_info']['option'], true) ?: [];
        }
        
        foreach ($options as $option) {
            if (!in_array($option, $girlOptions)) {
                $err['option'][] = '選択されたオプション「' . htmlspecialchars($option) . '」は利用できません';
            }
        }
    }
    
    // 8. 支払い方法チェック
    if (!in_array($payment_method, [1, 2])) {
        $err['pay'][] = '支払い方法が無効です';
    }
    
    // 9. クーポンチェック（入力がある場合）
    $couponDiscount = 0;
    if (!empty($coupon_code)) {
        // クーポン存在チェック
        $sql = "SELECT nebiki FROM coupon WHERE coupon_code = :coupon_code";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':coupon_code', $coupon_code, PDO::PARAM_STR);
        $stmt->execute();
        $couponDiscount = $stmt->fetchColumn();
        
        if ($couponDiscount === false) {
            $err['coupon'][] = 'クーポンコードが無効です';
        } else {
            $couponDiscount = (int)$couponDiscount;
            $reserveData['coupon_discount'] = $couponDiscount;
        }
    }
    
    // 10. ポイント利用チェック
    if ($use_point > 0) {
        // 利用可能ポイント取得
        $sql = "SELECT balance_after FROM point WHERE tel = :tel ORDER BY created DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tel', $_SESSION['USER']['tel'], PDO::PARAM_STR);
        $stmt->execute();
        $availablePoint = (int)$stmt->fetchColumn();
        
        if ($use_point > $availablePoint) {
            $err['point'][] = 'ご利用ポイントが利用可能ポイントを超えています';
        }
    }
    
    // 11. 連絡先チェック
    if (empty($contact_tel)) {
        $err['tel'][]  = '連絡先を入力してください';
    } else {
        if (!preg_match('/^[0-9]{11}$/', $contact_tel)) {
            $err['tel'][]  = '連絡先は11桁の数字で入力してください';
        }
    }
    //12.備考欄チェック
        if (!empty($other) && mb_strlen($other) > 300) {
        $err['comment'][] = '300文字以内でご記入ください';
    }
    // 12. 予約重複チェック（エラーがここまでない場合のみ）
    if (empty($err)) {
        // 時間重複チェック用の開始・終了時間計算
        $startDateTime = clone $reserveDateTime;
        $courseTime = (int)$courseInfo['time']; // 分
        $totalTime = $courseTime + 20; // 前後10分ずつ
        $endDateTime = clone $startDateTime;
        $endDateTime->modify("+{$totalTime} minutes");
        $endDateTime->modify("+10 minutes");
        $startDateTime->modify("-10 minutes");
      
        if (!empty($girl_name)) {
            // 特定の女の子の予約重複チェック
            $sql = "SELECT COUNT(*) FROM reserve 
                    WHERE g_login_id = :g_login_id 
                    AND date = :date 
                    AND NOT (end_time <= :start_time OR start_time >= :end_time)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':g_login_id', $reserveData['girl_info']['g_login_id'], PDO::PARAM_STR);
            $stmt->bindValue(':date', $reserve_dates, PDO::PARAM_STR);
            $stmt->bindValue(':start_time', $startDateTime->format('H:i:s'), PDO::PARAM_STR);
            $stmt->bindValue(':end_time', $endDateTime->format('H:i:s'), PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                $err['time'] [] = '選択された時間は既に埋まっています';
            }
        }
        
        // シフトチェック（女の子指定がある場合）
        if (!empty($girl_name)) {
            $sql = "SELECT * FROM shift WHERE g_login_id = :g_login_id AND date = :date";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':g_login_id', $reserveData['girl_info']['g_login_id'], PDO::PARAM_STR);
            $stmt->bindValue(':date', $reserve_dates, PDO::PARAM_STR);
            $stmt->execute();
            $shiftInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shiftInfo) {
                $err['girl'][]  = '選択された女の子は指定日に選択できません';
            } else {
                $shiftStart = new DateTime($shiftInfo['in_time']);
                $shiftEnd = new DateTime( $shiftInfo['out_time']);
                
                // 準備時間考慮
                $requiredStart = clone $startDateTime;
                $shiftStart->modify('-10 minutes');

                
                if ($requiredStart < $shiftStart || ($shiftInfo['LO'] == 0 && $endDateTime > $shiftEnd)) {
                    $err['time'][] = '指定された時間は選択できません';
                    
                }
            }
        }
        
        $reserveData['start_time'] = $startDateTime;
        $reserveData['end_time'] = $endDateTime;
    }
   
    
    // バリデーション結果に応じて処理分岐
    if (empty($err)) {
        // 料金計算
$courseCost = (int)$courseInfo['cost'];
$nominationFee = ((int)$courseInfo['free_check'] === 0) ? 2000 : 0;

// 派遣料・タオル代（修正版：DBから取得）
$hakenFee = 0;
$towelFee = 0;
$sql = "SELECT haken_fee, towel_fee FROM place_fees WHERE place_name = :place_name";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':place_name', $place, PDO::PARAM_STR);
$stmt->execute();
$placeFeeData = $stmt->fetch(PDO::FETCH_ASSOC);
if ($placeFeeData) {
    $hakenFee = (int)$placeFeeData['haken_fee'];
    $towelFee = (int)$placeFeeData['towel_fee'];
}
        
        // オプション料金
        $optionsCost = 0;
        if (!empty($options)) {
            $optionsList = "'" . implode("','", array_map('addslashes', $options)) . "'";
            $sql = "SELECT SUM(option_cost) FROM options WHERE option_name IN ({$optionsList})";
            $stmt = $pdo->query($sql);
            $optionsCost = (int)$stmt->fetchColumn();
        }
        
        $totalAmount = $courseCost + $nominationFee + $hakenFee + $towelFee + $optionsCost - $couponDiscount - $use_point;
        $totalAmount = max(0, $totalAmount);
        
        $reserveData['pricing'] = [
            'course_cost' => $courseCost,
            'nomination_fee' => $nominationFee,
            'haken_fee' => $hakenFee,
            'towel_fee' => $towelFee,
            'options_cost' => $optionsCost,
            'coupon_discount' => $couponDiscount,
            'use_point' => $use_point,
            'total_amount' => $totalAmount
        ];
        
        // ここで確認画面に遷移 or DB登録
        // 今回は確認画面表示用のセッションに保存
        $_SESSION['RESERVE_DATA'] = [
            'girl_name' => $girl_name,
            'course_name' => $course_name,
            'reserve_date' => $reserve_dates,
            'reserve_times' => $reserve_time,
            'place' => $place,
            'place_other' => $place_other,
            'area' => $area,
            'area_other' => $area_other,
            'area_outside' => $area_outside,
            'options' => $options,
            'payment_method' => $payment_method,
            'coupon_code' => $coupon_code,
            'use_point' => $use_point,
            'contact_tel' => $contact_tel,
            'pricing' => $reserveData['pricing'],
            'comment' => $other
        ];
        
        // 確認画面にリダイレクト
        $_SESSION['RESERVE_INPUT'] = $_POST;
        header('Location: reserve_confirm.php');
        
        exit;
        
    } else {
        // エラーがある場合はreserve.phpに戻る
        $_SESSION['RESERVE_ERRORS'] = $err;
        $_SESSION['RESERVE_INPUT'] = $_POST;
        header('Location: reserve.php');
        exit;
    }
}

// GETアクセスの場合はreserve.phpにリダイレクト
header('Location: reserve.php');
exit;
?>