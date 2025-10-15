<?php
include '../functions.php';
$pdo = dbConnect();

$code = trim($_GET['coupon_code'] ?? '');

header('Content-Type: application/json');

if(!$code){
    echo json_encode(['success'=>false, 'message'=>'コードが入力されていません']);
    exit;
}

$stmt = $pdo->prepare("SELECT nebiki FROM coupon WHERE coupon_code = :code LIMIT 1");
$stmt->execute([':code'=>$code]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if($coupon){
    echo json_encode(['success'=>true, 'name'=>$coupon['nebiki']]);
    
} else {
    echo json_encode(['success'=>false, 'message'=>'該当するcouponがありませんでした']);
}

