<?php
include 'header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: index.php');
    exit;
}

$pdo = dbConnect();

// 店舗情報を取得
$sql = "SELECT tel, start_time, end_time FROM shop LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$shop = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shop) {
    // デフォルト値
    $shop_tel = '0120-XXX-XXXX';
    $business_hours = '17:00 ~ 27:00';
} else {
    $shop_tel = $shop['tel'];
    
    // 営業時間のフォーマット (TIME型から取得)
    $start_time = substr($shop['start_time'], 0, 5); // "17:00:00" → "17:00"
    $end_time = substr($shop['end_time'], 0, 5); // "03:00:00" → "03:00"
    
    // 終了時間が開始時間より小さい場合は翌日扱い(27:00表記に変換)
    $start_hour = (int)substr($start_time, 0, 2);
    $end_hour = (int)substr($end_time, 0, 2);
    
    if ($end_hour < $start_hour) {
        // 翌日なので24時間足す
        $end_hour += 24;
        $end_time = $end_hour . substr($end_time, 2);
    }
    
    $business_hours = $start_time . ' ~ ' . $end_time;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="./css/contact.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <h1><i class="bi bi-headset"></i> お問い合わせ</h1>
    
    <p class="intro-text">
        ご不明な点やお困りのことがございましたら、<br>
        お気軽にお問い合わせください。
    </p>

    <!-- 電話でのお問い合わせ -->
    <div class="contact-card">
        <div class="contact-icon phone-icon">
            <i class="bi bi-telephone-fill"></i>
        </div>
        <h2>電話でのお問い合わせ</h2>
        <div class="contact-info">
            <div class="info-row">
                <span class="info-label"><i class="bi bi-clock"></i> 営業時間</span>
                <span class="info-value"><?= htmlspecialchars($business_hours) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="bi bi-telephone"></i> 電話番号</span>
                <span class="info-value tel-number"><?= htmlspecialchars($shop_tel) ?></span>
            </div>
        </div>
        <a href="tel:<?= htmlspecialchars(str_replace('-', '', $shop_tel)) ?>" class="btn btn-phone">
            <i class="bi bi-telephone-fill"></i> 電話をかける
        </a>
    </div>

    <!-- チャットでのお問い合わせ -->
    <div class="contact-card">
        <div class="contact-icon chat-icon">
            <i class="bi bi-chat-dots-fill"></i>
        </div>
        <h2>チャットでのお問い合わせ</h2>
        <p class="contact-description">
            スタッフがチャットでご対応いたします。<br>
            お気軽にご相談ください。
        </p>
        <button class="btn btn-chat" id="start-support-chat">
            <i class="bi bi-chat-dots-fill"></i> チャットを開始
        </button>
    </div>

    <!-- よくある質問 -->
    <div class="faq-section">
        <h2><i class="bi bi-question-circle"></i> よくある質問</h2>
        
        <div class="accordion" id="faqAccordion">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                        予約のキャンセルはできますか?
                    </button>
                </h3>
                <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        予約リストから予約をキャンセルすることができます。キャンセルポリシーについては予約詳細ページをご確認ください。
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                        ポイントの有効期限はありますか?
                    </button>
                </h3>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        ポイントの有効期限は取得から1年間です。有効期限を過ぎたポイントは自動的に失効いたします。
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                        支払い方法を変更できますか?
                    </button>
                </h3>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        現金払いとクレジットカード払いをお選びいただけます。予約時に選択した支払い方法の変更については、お問い合わせください。
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                        ランクアップの条件は?
                    </button>
                </h3>
                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        累計ご利用回数に応じて自動的にランクアップします。詳細はポイント・ランクページをご確認ください。
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const startChatBtn = document.getElementById('start-support-chat');
    
    if (startChatBtn) {
        startChatBtn.addEventListener('click', async function() {
            // ローディング表示
            startChatBtn.disabled = true;
            startChatBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 準備中...';
            
            try {
                // サポートチャットルームを取得または作成
                const formData = new FormData();
                formData.append('g_login_id', 'support');
                
                const response = await fetch('../chat/chat_api.php?action=get_or_create_room', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // チャットルームへ遷移
                    window.location.href = `../chat/chat_room.php?g_login_id=support&room_id=${data.room.id}`;
                } else {
                    alert(data.error || 'チャットの開始に失敗しました');
                    startChatBtn.disabled = false;
                    startChatBtn.innerHTML = '<i class="bi bi-chat-dots-fill"></i> チャットを開始';
                }
            } catch (error) {
                console.error('エラー:', error);
                alert('エラーが発生しました');
                startChatBtn.disabled = false;
                startChatBtn.innerHTML = '<i class="bi bi-chat-dots-fill"></i> チャットを開始';
            }
        });
    }
});
</script>

</body>
</html>