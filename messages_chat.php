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
            background: #f0fdf4;
        }

        /* ── Page wrapper ── */
        .chat-page {
            max-width: 760px;
            margin: 30px auto 60px;
            padding: 0 16px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 120px);
        }

        /* ── Chat window card ── */
        .chat-card {
            flex: 1;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(5, 150, 105, 0.11);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        /* ── Header ── */
        .chat-head {
            background: linear-gradient(135deg, #065f46, #059669);
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }

        .chat-back {
            background: rgba(255, 255, 255, 0.18);
            border: none;
            color: #fff;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            cursor: pointer;
            flex-shrink: 0;
            text-decoration: none;
            transition: background 0.15s;
        }

        .chat-back:hover {
            background: rgba(255, 255, 255, 0.28);
            color: #fff;
        }

        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            background: rgba(255, 255, 255, 0.22);
            color: #fff;
            font-size: 0.92rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .chat-head-info {
            flex: 1;
            min-width: 0;
        }

        .chat-head-name {
            font-weight: 700;
            color: #fff;
            font-size: 0.97rem;
        }

        .chat-head-role {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.75);
            margin-top: 1px;
        }

        .chat-head-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.85);
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #a7f3d0;
        }

        /* ── Messages area ── */
        .chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 20px 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            scroll-behavior: smooth;
        }

        .msg-day-divider {
            text-align: center;
            font-size: 0.68rem;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin: 10px 0 4px;
        }

        /* ── Bubble ── */
        .bubble-row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }

        .bubble-row.mine {
            justify-content: flex-end;
        }

        .bubble-row.theirs {
            justify-content: flex-start;
        }

        .bubble-mini-avatar {
            width: 26px;
            height: 26px;
            border-radius: 8px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-bottom: 2px;
        }

        .bubble {
            max-width: 68%;
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 0.875rem;
            line-height: 1.5;
            word-break: break-word;
        }

        .bubble-row.mine .bubble {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .bubble-row.theirs .bubble {
            background: #f1f5f9;
            color: #1e293b;
            border-bottom-left-radius: 4px;
        }

        .bubble-time {
            font-size: 0.65rem;
            margin-top: 4px;
            display: block;
            text-align: right;
        }

        .bubble-row.mine .bubble-time {
            color: rgba(255, 255, 255, 0.7);
        }

        .bubble-row.theirs .bubble-time {
            color: #94a3b8;
        }

        .bubble-read-tick {
            font-size: 0.65rem;
            margin-left: 4px;
        }

        /* ── Input area ── */
        .chat-footer {
            padding: 14px 16px;
            border-top: 1px solid #f0fdf4;
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-shrink: 0;
        }

        .chat-input-wrap {
            flex: 1;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 10px 14px;
            display: flex;
            align-items: flex-end;
            gap: 8px;
            transition: border-color 0.15s;
        }

        .chat-input-wrap:focus-within {
            border-color: #059669;
            background: #fff;
        }

        #chatInput {
            flex: 1;
            border: none;
            background: transparent;
            resize: none;
            font-size: 0.9rem;
            color: #1e293b;
            line-height: 1.5;
            max-height: 120px;
            overflow-y: auto;
            outline: none;
            font-family: inherit;
        }

        #chatInput::placeholder {
            color: #94a3b8;
        }

        .chat-send-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            border: none;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: opacity 0.15s, transform 0.15s;
        }

        .chat-send-btn:hover {
            opacity: 0.88;
            transform: scale(1.05);
        }

        .chat-send-btn:disabled {
            opacity: 0.45;
            transform: none;
            cursor: not-allowed;
        }

        /* ── Typing indicator ── */
        .typing-indicator {
            display: none;
            padding: 0 20px 6px;
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
            gap: 12px;
            color: #94a3b8;
        }

        .chat-empty i {
            font-size: 2.5rem;
            color: #d1fae5;
        }

        .chat-empty p {
            font-size: 0.875rem;
            text-align: center;
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
                    <a href="<?php echo $base_url; ?>farmer/profile.php?id=<?php echo $other_id; ?>"
                        style="background:rgba(255,255,255,0.18);border:none;color:#fff;padding:6px 12px;border-radius:8px;font-size:0.78rem;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:5px;transition:background 0.15s;"
                        onmouseover="this.style.background='rgba(255,255,255,0.28)'"
                        onmouseout="this.style.background='rgba(255,255,255,0.18)'">
                        <i class="fas fa-user"></i> Profile
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