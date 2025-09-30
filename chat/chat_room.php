<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: index.php');
    exit;
}

$g_login_id = $_GET['g_login_id'] ?? null;
$room_id = $_GET['room_id'] ?? null;

if (!$g_login_id) {
    header('Location: chat_list.php');
    exit;
}

// 女の子の情報を取得
try {
    $pdo = dbConnect();
    $sql = "SELECT * FROM girl WHERE g_login_id = :g_login_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['g_login_id' => $g_login_id]);
    $girl = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$girl) {
        header('Location: chat_list.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: chat_list.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チャット - <?= htmlspecialchars($girl['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', sans-serif;
            background-color: #f5f5f5;
            overflow: hidden;
        }
        
        #chat-container {
            max-width: 800px;
            margin: 0 auto;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: white;
        }
        
        #chat-header {
            padding: 15px 20px;
            background-color: #4a90e2;
            color: white;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        #back-button {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            margin-right: 15px;
            padding: 0;
        }
        
        #chat-title {
            font-weight: bold;
            font-size: 18px;
        }
        
        #chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: #fafafa;
        }
        
        .message {
    margin-bottom: 15px;
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    max-width: 70%;
    animation: fadeIn 0.3s ease-in;
}
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-user {
    align-self: flex-end;
    flex-direction: row-reverse;
    margin-left: auto; /* 追加 */
    margin-right: 0; /* 追加 */
}
        
        .message-girl {
            align-self: flex-start;
        }
        
        
        
        
        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
            padding: 0 5px;
        }
        
        .message-user .message-time {
            text-align: right;
        }
        
        .chat-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 12px;
            cursor: pointer;
            display: block;
        }
        
        .chat-video {
            max-width: 100%;
            border-radius: 12px;
            display: block;
        }
        
        .chat-audio {
            width: 100%;
            max-width: 300px;
        }
        
        #chat-input-area {
            padding: 15px 20px;
            background-color: white;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        #message-input-wrapper {
            flex: 1;
            position: relative;
        }
        
        #message-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 14px;
            outline: none;
            resize: none;
            max-height: 100px;
            overflow-y: auto;
        }
        
        #message-input:focus {
            border-color: #4a90e2;
        }
        
        #char-counter {
            position: absolute;
            bottom: -20px;
            right: 10px;
            font-size: 11px;
            color: #999;
        }
        
        button {
            padding: 10px 20px;
            background-color: #4a90e2;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
        }
        
        button:hover {
            background-color: #357abd;
        }
        
        button:active {
            transform: scale(0.95);
        }
        
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .file-button {
            background-color: #6c757d;
            padding: 10px 15px;
            font-size: 18px;
        }
        
        .file-button:hover {
            background-color: #5a6268;
        }
        
        #file-input {
            display: none;
        }
        
        /* スクロールバー */
        #chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        
        #chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        #chat-messages::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }
        
        #chat-messages::-webkit-scrollbar-thumb:hover {
            background: #999;
        }
        
        /* 読み込み中表示 */
        .loading {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .message {
    margin-bottom: 15px;
    display: flex;
    flex-direction: row; /* column から row に変更 */
    align-items: flex-start; /* 上揃え */
    max-width: 70%;
    animation: fadeIn 0.3s ease-in;
}

.message-girl {
    align-self: flex-start;
    flex-direction: row; /* 左にアイコン */
}

.message-user {
    align-self: flex-end;
    flex-direction: row-reverse; /* 右寄せ */
}

.message-user .message-content p {
    background-color: #4a90e2;
    color: white;
    border-bottom-right-radius: 4px;
}

.message-user .message-time {
    text-align: right;
}

.message-content {
    display: flex;
    flex-direction: column;
}

.message-content p {
    padding: 10px 15px;
    border-radius: 18px;
    word-wrap: break-word;
    line-height: 1.4;
    white-space: pre-wrap;
    margin: 0;
}

.message-girl .message-content p {
    background-color: #e8e8e8;
    color: #333;
    border-bottom-left-radius: 4px;
}

.message-user .message-content p {
    background-color: #4a90e2;
    color: white;
    border-bottom-right-radius: 4px;
}
    </style>
</head>
<body>
    <div id="chat-container" data-room-id="<?= htmlspecialchars($room_id ?? '') ?>" data-g-login-id="<?= htmlspecialchars($g_login_id) ?>" data-girl-img="<?= htmlspecialchars($girl['img'] ?? '') ?>">
        <div id="chat-header">
            <button id="back-button" onclick="location.href='chat_list.php'">←</button>
            <div id="chat-title"><?= htmlspecialchars($girl['name']) ?></div>
        </div>
        
        <div id="chat-messages">
            <div class="loading">読み込み中...</div>
        </div>
        
        <div id="chat-input-area">
    <label for="file-input" class="file-button" title="ファイルを添付">
        📎
    </label>
    <input type="file" id="file-input" accept="image/*,video/*,audio/*">
    
    <form id="message-form" style="flex: 1; display: flex; gap: 10px; align-items: flex-end;">
        <div id="message-input-wrapper" style="flex: 1; position: relative;">
            <textarea 
                id="message-input" 
                placeholder="メッセージを入力..."
                rows="1"
                autocomplete="off"
                style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 20px; font-size: 14px; outline: none; resize: none; max-height: 100px; overflow-y: auto;"
            ></textarea>
            <span id="char-counter" style="position: absolute; bottom: -20px; right: 10px; font-size: 11px; color: #999;">0/500</span>
        </div>
        
        <button type="submit">送信</button>
    </form>
</div>
    </div>
    
    <script src="./chat.js"></script>
    <script>
        // ルーム初期化処理
        document.addEventListener('DOMContentLoaded', async function() {
            const container = document.getElementById('chat-container');
            let roomId = container.dataset.roomId;
            const gLoginId = container.dataset.gLoginId;
            
            // ルームIDがない場合は作成
            if (!roomId) {
                try {
                    const formData = new FormData();
                    formData.append('g_login_id', gLoginId);
                    
                    const response = await fetch('chat_api.php?action=get_or_create_room', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        roomId = data.room.id;
                        container.dataset.roomId = roomId;
                        
                        // URLを更新（履歴は残さない）
                        const url = new URL(window.location);
                        url.searchParams.set('room_id', roomId);
                        window.history.replaceState({}, '', url);
                    } else {
                        alert(data.error || 'チャットルームの作成に失敗しました');
                        location.href = 'chat_list.php';
                        return;
                    }
                } catch (error) {
                    console.error('ルーム作成エラー:', error);
                    alert('エラーが発生しました');
                    location.href = 'chat_list.php';
                    return;
                }
            }
            
            // チャットクライアント開始
            if (roomId && chatClient) {
                chatClient.roomId = roomId;
                chatClient.startPolling();
            }
            
            // テキストエリアの自動リサイズ
            const messageInput = document.getElementById('message-input');
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
            
           // Enterキーで送信（Shift+Enterで改行）
messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('message-form').requestSubmit();
    }
});
            
            
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>