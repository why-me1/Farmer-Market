<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

check_login();

$current_user_id   = (int)$_SESSION['user_id'];
$current_username  = $_SESSION['username'] ?? 'Me';
$base_url          = BASE_URL;

$other_id = (int)($_GET['user'] ?? 0);
if (!$other_id || $other_id === $current_user_id) {
    header("Location: {$base_url}messages.php");
    exit();
}

// Ensure messages table exists
$conn->query("CREATE TABLE IF NOT EXISTS `messages` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id`   INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `message`     TEXT NOT NULL,
    `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sender`   (`sender_id`),
    INDEX `idx_receiver` (`receiver_id`),
    INDEX `idx_created`  (`created_at`),
    FOREIGN KEY (`sender_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch other user info
$us = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
$us->bind_param("i", $other_id);
$us->execute();
$other_user = $us->get_result()->fetch_assoc();
$us->close();

if (!$other_user) {
    header("Location: {$base_url}messages.php");
    exit();
}

$other_name  = $other_user['username'];
$other_role  = $other_user['role'] === 'user' ? 'Buyer' : ucfirst($other_user['role']);
$other_init  = strtoupper(substr($other_name, 0, 2));

// Load initial batch of messages & mark as read
$conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = {$other_id} AND receiver_id = {$current_user_id} AND is_read = 0");

$msgQ = $conn->prepare(
    "SELECT m.id, m.sender_id, m.message, m.is_read, m.created_at
     FROM messages m
     WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
     ORDER BY m.created_at ASC, m.id ASC
     LIMIT 200"
);
$msgQ->bind_param("iiii", $current_user_id, $other_id, $other_id, $current_user_id);
$msgQ->execute();
$initial_messages = $msgQ->get_result()->fetch_all(MYSQLI_ASSOC);
$msgQ->close();

$last_msg_id = !empty($initial_messages) ? end($initial_messages)['id'] : 0;

$page_title = "Chat with " . htmlspecialchars($other_name);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> – Farmers' Market</title>
    <?php require_once 'includes/nav.php'; ?>
    <style>
        body {
            background: #f1f5f9;
        }

        /* ── Page wrapper ── */
        .chat-page {
            max-width: 900px;
            margin: 20px auto 40px;
            padding: 0 16px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 100px);
            font-family: inherit;
        }

        /* ── Chat window card ── */
        .chat-card {
            flex: 1;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        /* ── Header ── */
        .chat-head {
            background: #fff;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
            border-bottom: 1px solid #f1f5f9;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
            z-index: 10;
        }

        .chat-back {
            background: transparent;
            border: none;
            color: #64748b;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            flex-shrink: 0;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .chat-back:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .chat-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
            position: relative;
        }

        .chat-avatar::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #22c55e;
            border: 2.5px solid #fff;
        }

        .chat-head-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .chat-head-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.1rem;
            line-height: 1.2;
        }

        .chat-head-role {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 4px;
            font-weight: 500;
        }

        .btn-profile {
            background: #f1f5f9;
            border: none;
            color: #475569;
            padding: 8px 16px;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-profile:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* ── Messages area ── */
        .chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            scroll-behavior: smooth;
            background: #fafbfc url('data:image/svg+xml;utf8,<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg"><circle cx="2" cy="2" r="1.5" fill="%23e2e8f0"/></svg>') repeat;
        }

        .msg-day-divider {
            text-align: center;
            margin: 20px 0 12px;
            position: relative;
            z-index: 1;
        }

        .msg-day-divider span {
            background: #fff;
            padding: 6px 14px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
        }

        /* ── Bubble ── */
        .bubble-row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            margin-bottom: 4px;
        }

        .bubble-row.mine {
            justify-content: flex-end;
        }

        .bubble-row.theirs {
            justify-content: flex-start;
        }

        .bubble-mini-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-bottom: 18px;
        }

        .bubble {
            max-width: 65%;
            padding: 12px 16px;
            border-radius: 20px;
            font-size: 0.95rem;
            line-height: 1.5;
            word-break: break-word;
            position: relative;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .bubble-row.mine .bubble {
            background: #10b981;
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .bubble-row.theirs .bubble {
            background: #fff;
            color: #1e293b;
            border-bottom-left-radius: 4px;
            border: 1px solid #f1f5f9;
        }

        .bubble-time {
            font-size: 0.7rem;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            position: absolute;
            bottom: -20px;
            right: 4px;
            white-space: nowrap;
        }

        .bubble-row.mine .bubble-time {
            color: #94a3b8;
        }

        .bubble-row.theirs .bubble-time {
            color: #94a3b8;
            left: 4px;
            right: auto;
            justify-content: flex-start;
        }

        .bubble-row.mine {
            margin-bottom: 22px;
        }

        .bubble-row.theirs {
            margin-bottom: 22px;
        }

        .bubble-read-tick {
            font-size: 0.8rem;
            color: #10b981;
        }

        /* ── Input area ── */
        .chat-footer {
            padding: 16px 24px;
            background: #fff;
            border-top: 1px solid #f1f5f9;
            display: flex;
            gap: 14px;
            align-items: flex-end;
            flex-shrink: 0;
            z-index: 10;
        }

        .chat-input-wrap {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .chat-input-wrap:focus-within {
            border-color: #10b981;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        #chatInput {
            flex: 1;
            border: none;
            background: transparent;
            resize: none;
            font-size: 0.95rem;
            color: #1e293b;
            line-height: 1.5;
            max-height: 120px;
            padding: 8px 0;
            overflow-y: auto;
            outline: none;
            font-family: inherit;
        }

        #chatInput::placeholder {
            color: #94a3b8;
        }

        .chat-send-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #10b981;
            color: #fff;
            border: none;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s ease;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
        }

        .chat-send-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .chat-send-btn:disabled {
            background: #cbd5e1;
            box-shadow: none;
            transform: none;
            cursor: not-allowed;
            color: #fff;
        }

        /* ── Typing indicator ── */
        .typing-indicator {
            display: none;
            padding: 8px 24px;
            background: #fff;
            border-top: 1px solid #f1f5f9;
        }

        .typing-indicator span {
            font-size: 0.75rem;
            color: #94a3b8;
            font-style: italic;
        }

        /* ── Empty state ── */
        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            color: #64748b;
        }

        .chat-empty i {
            font-size: 3.5rem;
            color: #cbd5e1;
        }

        .chat-empty p {
            font-size: 1rem;
            text-align: center;
            line-height: 1.6;
        }

        @media (max-width: 640px) {
            .chat-page {
                height: calc(100vh - 70px);
                margin: 10px auto 20px;
            }

            .bubble {
                max-width: 85%;
            }

            .chat-head {
                padding: 12px 16px;
            }

            .chat-body {
                padding: 16px;
            }

            .chat-footer {
                padding: 12px 16px;
            }
        }
    </style>
</head>

<body>

    <div class="chat-page">
        <div class="chat-card">

            <!-- Header -->
            <div class="chat-head">
                <a href="<?php echo $base_url; ?>messages.php" class="chat-back" title="Back to inbox">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="chat-avatar"><?php echo $other_init; ?></div>
                <div class="chat-head-info">
                    <div class="chat-head-name"><?php echo htmlspecialchars($other_name); ?></div>
                    <div class="chat-head-role"><?php echo $other_role; ?></div>
                </div>
                <?php if ($other_user['role'] === 'farmer'): ?>
                    <a href="<?php echo $base_url; ?>farmer/profile.php?id=<?php echo $other_id; ?>" class="btn-profile">
                        <i class="fas fa-user-circle"></i> Profile
                    </a>
                <?php endif; ?>
            </div>

            <!-- Messages -->
            <div class="chat-body" id="chatBody">
                <?php if (empty($initial_messages)): ?>
                    <div class="chat-empty">
                        <i class="fas fa-comments"></i>
                        <p>No messages yet.<br>Say hello to <strong><?php echo htmlspecialchars($other_name); ?></strong>!</p>
                    </div>
                <?php else: ?>
                    <?php
                    $prev_date = '';
                    foreach ($initial_messages as $msg):
                        $is_mine = ((int)$msg['sender_id'] === $current_user_id);
                        $ts      = strtotime($msg['created_at']);
                        $day     = date('d M Y', $ts);
                        $time    = date('h:i A', $ts);
                    ?>
                        <?php if ($day !== $prev_date): $prev_date = $day; ?>
                            <div class="msg-day-divider"><?php echo $day; ?></div>
                        <?php endif; ?>
                        <div class="bubble-row <?php echo $is_mine ? 'mine' : 'theirs'; ?>" data-msg-id="<?php echo $msg['id']; ?>">
                            <?php if (!$is_mine): ?>
                                <div class="bubble-mini-avatar"><?php echo $other_init; ?></div>
                            <?php endif; ?>
                            <div class="bubble">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                <span class="bubble-time">
                                    <?php echo $time; ?>
                                    <?php if ($is_mine): ?>
                                        <span class="bubble-read-tick"><?php echo $msg['is_read'] ? '✓✓' : '✓'; ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Typing / status -->
            <div class="typing-indicator" id="typingIndicator">
                <span><?php echo htmlspecialchars($other_name); ?> is typing…</span>
            </div>

            <!-- Input -->
            <div class="chat-footer">
                <div class="chat-input-wrap">
                    <textarea id="chatInput" rows="1" placeholder="Type a message…"></textarea>
                </div>
                <button class="chat-send-btn" id="sendBtn" title="Send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>

        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>

    <script>
        const BASE = '<?php echo $base_url; ?>';
        const OTHER_ID = <?php echo $other_id; ?>;
        const MY_INIT = '<?php echo strtoupper(substr($current_username, 0, 2)); ?>';
        const OTHER_INIT = '<?php echo $other_init; ?>';
        let lastMsgId = <?php echo $last_msg_id; ?>;
        let polling;

        const chatBody = document.getElementById('chatBody');
        const chatInput = document.getElementById('chatInput');
        const sendBtn = document.getElementById('sendBtn');

        // ── Auto-resize textarea ──────────────────────────────────────────────────────
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // ── Scroll to bottom ──────────────────────────────────────────────────────────
        function scrollBottom() {
            chatBody.scrollTop = chatBody.scrollHeight;
        }
        scrollBottom();

        // ── Format time ──────────────────────────────────────────────────────────────
        function fmtTime(dateStr) {
            const d = new Date(dateStr);
            let h = d.getHours(),
                m = d.getMinutes(),
                ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return h + ':' + String(m).padStart(2, '0') + ' ' + ampm;
        }

        function fmtDay(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        // ── Build bubble HTML ─────────────────────────────────────────────────────────
        let lastRenderedDay = '';

        // Track the last day rendered from PHP-side
        <?php if (!empty($initial_messages)): ?>
            lastRenderedDay = '<?php echo date('d M Y', strtotime(end($initial_messages)['created_at'])); ?>';
        <?php endif; ?>

        function buildBubble(msg) {
            const isMine = (msg.sender_id == <?php echo $current_user_id; ?>);
            const day = fmtDay(msg.created_at);
            let html = '';

            if (day !== lastRenderedDay) {
                lastRenderedDay = day;
                html += `<div class="msg-day-divider">${day}</div>`;
            }

            const text = msg.message.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
            const tick = isMine ? `<span class="bubble-read-tick">${msg.is_read == 1 ? '✓✓' : '✓'}</span>` : '';

            html += `<div class="bubble-row ${isMine ? 'mine' : 'theirs'}" data-msg-id="${msg.id}">
        ${!isMine ? `<div class="bubble-mini-avatar">${OTHER_INIT}</div>` : ''}
        <div class="bubble">
            ${text}
            <span class="bubble-time">${fmtTime(msg.created_at)} ${tick}</span>
        </div>
    </div>`;
            return html;
        }

        // ── Remove empty state ────────────────────────────────────────────────────────
        function clearEmptyState() {
            const empty = chatBody.querySelector('.chat-empty');
            if (empty) empty.remove();
        }

        // ── Append new messages ───────────────────────────────────────────────────────
        function appendMessages(msgs) {
            if (!msgs.length) return;
            clearEmptyState();
            const atBottom = chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight < 60;
            msgs.forEach(m => {
                const div = document.createElement('div');
                div.innerHTML = buildBubble(m);
                while (div.firstChild) chatBody.appendChild(div.firstChild);
                lastMsgId = Math.max(lastMsgId, m.id);
            });
            if (atBottom) scrollBottom();
        }

        // ── Send message ──────────────────────────────────────────────────────────────
        function sendMessage() {
            const text = chatInput.value.trim();
            if (!text) return;

            sendBtn.disabled = true;
            const fd = new FormData();
            fd.append('action', 'send');
            fd.append('receiver_id', OTHER_ID);
            fd.append('message', text);

            fetch(BASE + 'messages_handler.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        chatInput.value = '';
                        chatInput.style.height = 'auto';
                        appendMessages([data.message]);
                        scrollBottom();
                    }
                })
                .finally(() => {
                    sendBtn.disabled = false;
                    chatInput.focus();
                });
        }

        sendBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // ── Polling for new messages ──────────────────────────────────────────────────
        function pollMessages() {
            fetch(`${BASE}messages_handler.php?action=get_messages&other_id=${OTHER_ID}&since_id=${lastMsgId}`, {
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.messages.length) {
                        appendMessages(data.messages);
                        // Update read ticks on own sent messages
                        data.messages.forEach(m => {
                            if (m.sender_id != <?php echo $current_user_id; ?>) return;
                            // nothing for now – they already show ✓
                        });
                    }
                })
                .catch(() => {});
        }

        pollMessages(); // immediate check
        polling = setInterval(pollMessages, 3000);

        // Stop polling when page is hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) clearInterval(polling);
            else {
                pollMessages();
                polling = setInterval(pollMessages, 3000);
            }
        });
    </script>
</body>

</html>