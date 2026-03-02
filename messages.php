<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

check_login();

$current_user_id = (int)$_SESSION['user_id'];
$base_url = BASE_URL;

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

// ── Load conversations ────────────────────────────────────────────────────────
$sql = "SELECT 
            other_user,
            u.username AS other_name,
            u.role     AS other_role,
            last_message,
            last_time,
            unread_count
        FROM (
            SELECT
                CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_user,
                message     AS last_message,
                created_at  AS last_time,
                SUM(CASE WHEN receiver_id = ? AND is_read = 0 THEN 1 ELSE 0 END)
                    OVER (PARTITION BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END) AS unread_count,
                ROW_NUMBER() OVER (
                    PARTITION BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
                    ORDER BY created_at DESC, id DESC
                ) AS rn
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
        ) t
        JOIN users u ON u.id = t.other_user
        WHERE t.rn = 1
        ORDER BY t.last_time DESC";
$stmt = $conn->prepare($sql);
$p = $current_user_id;
$stmt->bind_param("iiiiii", $p, $p, $p, $p, $p, $p);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Messages – Farmers' Market";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php require_once 'includes/nav.php'; ?>
    <style>
        body {
            background: #f0fdf4;
            min-height: 100vh;
        }

        .msg-wrap {
            max-width: 760px;
            margin: 40px auto;
            padding: 0 16px 60px;
        }

        .msg-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .msg-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #065f46;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .msg-title i {
            color: #059669;
        }

        .msg-new-btn {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 18px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: opacity 0.15s, transform 0.15s;
            text-decoration: none;
        }

        .msg-new-btn:hover {
            opacity: 0.88;
            transform: translateY(-1px);
            color: #fff;
            text-decoration: none;
        }

        /* Conversation list */
        .conv-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .conv-card {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: box-shadow 0.15s, transform 0.15s;
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
            box-shadow: 0 1px 6px rgba(5, 150, 105, 0.07);
        }

        .conv-card:hover {
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.14);
            transform: translateY(-1px);
            border-color: #d1fae5;
            text-decoration: none;
            color: inherit;
        }

        .conv-card.unread {
            border-color: #a7f3d0;
            background: #f0fdf4;
        }

        .conv-avatar {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .conv-body {
            flex: 1;
            min-width: 0;
        }

        .conv-name-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .conv-name {
            font-weight: 700;
            font-size: 0.97rem;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-time {
            font-size: 0.72rem;
            color: #94a3b8;
            flex-shrink: 0;
        }

        .conv-preview {
            font-size: 0.84rem;
            color: #64748b;
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-card.unread .conv-preview {
            color: #374151;
            font-weight: 600;
        }

        .conv-badge {
            background: #059669;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            flex-shrink: 0;
        }

        .conv-role-tag {
            font-size: 0.65rem;
            background: #dcfce7;
            color: #059669;
            padding: 2px 7px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: capitalize;
            margin-left: 6px;
            flex-shrink: 0;
        }

        /* Empty state */
        .msg-empty {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 6px rgba(5, 150, 105, 0.07);
        }

        .msg-empty i {
            font-size: 3rem;
            color: #d1fae5;
            margin-bottom: 16px;
        }

        .msg-empty h3 {
            color: #374151;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .msg-empty p {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        /* New message modal */
        .msg-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
        }

        .msg-modal-backdrop.open {
            display: flex;
        }

        .msg-modal {
            background: #fff;
            border-radius: 18px;
            width: 94%;
            max-width: 460px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
            overflow: hidden;
            animation: modalIn 0.22s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.93) translateY(-16px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .msg-modal-head {
            background: linear-gradient(135deg, #065f46, #059669);
            color: #fff;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .msg-modal-head h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .msg-modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #fff;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .msg-modal-body {
            padding: 22px;
        }

        .msg-form-group {
            margin-bottom: 16px;
        }

        .msg-form-group label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 6px;
            display: block;
            letter-spacing: 0.5px;
        }

        .msg-form-group input,
        .msg-form-group textarea,
        .msg-form-group select {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 13px;
            font-size: 0.9rem;
            color: #1e293b;
            background: #f8fafc;
            transition: border-color 0.15s;
            outline: none;
            font-family: inherit;
        }

        .msg-form-group input:focus,
        .msg-form-group textarea:focus,
        .msg-form-group select:focus {
            border-color: #059669;
            background: #fff;
        }

        .msg-form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .msg-send-btn {
            width: 100%;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.15s;
        }

        .msg-send-btn:hover {
            opacity: 0.88;
        }

        #userSearchResults {
            position: absolute;
            z-index: 999;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .user-result-item {
            padding: 10px 14px;
            cursor: pointer;
            font-size: 0.875rem;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.12s;
        }

        .user-result-item:hover {
            background: #f0fdf4;
        }

        .user-result-avatar {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .search-wrap {
            position: relative;
        }
    </style>
</head>

<body>

    <div class="msg-wrap">
        <div class="msg-header">
            <div class="msg-title">
                <i class="fas fa-comments"></i>
                Messages
            </div>
            <button class="msg-new-btn" id="openNewMsg">
                <i class="fas fa-pen-to-square"></i> New Message
            </button>
        </div>

        <?php if (empty($conversations)): ?>
            <div class="msg-empty">
                <i class="fas fa-comment-slash"></i>
                <h3>No conversations yet</h3>
                <p>Start a conversation by clicking "New Message" or message a farmer from their product listing.</p>
            </div>
        <?php else: ?>
            <div class="conv-list">
                <?php foreach ($conversations as $conv):
                    $initials = strtoupper(substr($conv['other_name'], 0, 2));
                    $time_str = '';
                    $ts = strtotime($conv['last_time']);
                    $now = time();
                    $diff = $now - $ts;
                    if ($diff < 60)         $time_str = 'just now';
                    elseif ($diff < 3600)   $time_str = floor($diff / 60) . 'm ago';
                    elseif ($diff < 86400)  $time_str = floor($diff / 3600) . 'h ago';
                    else                    $time_str = date('d M', $ts);
                    $role_label = $conv['other_role'] === 'user' ? 'Buyer' : ucfirst($conv['other_role']);
                ?>
                    <a href="messages_chat.php?user=<?php echo (int)$conv['other_user']; ?>"
                        class="conv-card <?php echo $conv['unread_count'] > 0 ? 'unread' : ''; ?>">
                        <div class="conv-avatar"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="conv-body">
                            <div class="conv-name-row">
                                <span class="conv-name">
                                    <?php echo htmlspecialchars($conv['other_name']); ?>
                                    <span class="conv-role-tag"><?php echo $role_label; ?></span>
                                </span>
                                <span class="conv-time"><?php echo $time_str; ?></span>
                            </div>
                            <div class="conv-preview">
                                <?php echo htmlspecialchars(mb_strimwidth($conv['last_message'], 0, 70, '…')); ?>
                            </div>
                        </div>
                        <?php if ($conv['unread_count'] > 0): ?>
                            <span class="conv-badge"><?php echo (int)$conv['unread_count']; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── New Message Modal ───────────────────────────────────────────────────── -->
    <div class="msg-modal-backdrop" id="newMsgModal">
        <div class="msg-modal">
            <div class="msg-modal-head">
                <h5><i class="fas fa-pen-to-square"></i> New Message</h5>
                <button class="msg-modal-close" id="closeNewMsg"><i class="fas fa-times"></i></button>
            </div>
            <div class="msg-modal-body">
                <div class="msg-form-group">
                    <label>To</label>
                    <div class="search-wrap">
                        <input type="text" id="userSearchInput" placeholder="Search by username…" autocomplete="off">
                        <div id="userSearchResults"></div>
                    </div>
                    <input type="hidden" id="selectedReceiverId">
                    <input type="hidden" id="selectedReceiverName">
                </div>
                <div class="msg-form-group">
                    <label>Message</label>
                    <textarea id="newMsgText" placeholder="Type your message…"></textarea>
                </div>
                <button class="msg-send-btn" id="sendNewMsg"><i class="fas fa-paper-plane"></i> Send</button>
                <div id="newMsgFeedback" style="margin-top:10px;font-size:0.85rem;text-align:center;"></div>
            </div>
        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>

    <script>
        const BASE = '<?php echo $base_url; ?>';

        // ── Modal ─────────────────────────────────────────────────────────────────────
        document.getElementById('openNewMsg').addEventListener('click', () => {
            document.getElementById('newMsgModal').classList.add('open');
            document.getElementById('userSearchInput').focus();
        });
        document.getElementById('closeNewMsg').addEventListener('click', () => {
            document.getElementById('newMsgModal').classList.remove('open');
        });
        document.getElementById('newMsgModal').addEventListener('click', e => {
            if (e.target === document.getElementById('newMsgModal'))
                document.getElementById('newMsgModal').classList.remove('open');
        });

        // ── User search ───────────────────────────────────────────────────────────────
        let searchTimer;
        const searchInput = document.getElementById('userSearchInput');
        const searchRes = document.getElementById('userSearchResults');
        const receiverIdEl = document.getElementById('selectedReceiverId');
        const receiverNmEl = document.getElementById('selectedReceiverName');

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length < 2) {
                searchRes.style.display = 'none';
                return;
            }
            searchTimer = setTimeout(() => {
                fetch(BASE + 'messages_handler.php?action=search_users&q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(resp => {
                        const data = resp.users || [];
                        searchRes.innerHTML = '';
                        if (!data.length) {
                            searchRes.innerHTML = '<div class="user-result-item" style="color:#94a3b8;">No users found</div>';
                        } else {
                            data.forEach(u => {
                                const div = document.createElement('div');
                                div.className = 'user-result-item';
                                div.innerHTML = `<div class="user-result-avatar">${u.username.substring(0,2).toUpperCase()}</div>
                            <span>${u.username} <small style="color:#94a3b8">(${u.role === 'user' ? 'Buyer' : u.role})</small></span>`;
                                div.addEventListener('click', () => {
                                    receiverIdEl.value = u.id;
                                    receiverNmEl.value = u.username;
                                    searchInput.value = u.username;
                                    searchRes.style.display = 'none';
                                });
                                searchRes.appendChild(div);
                            });
                        }
                        searchRes.style.display = 'block';
                    }).catch(() => {});
            }, 300);
        });

        document.addEventListener('click', e => {
            if (!searchInput.contains(e.target) && !searchRes.contains(e.target))
                searchRes.style.display = 'none';
        });

        // ── Send new message ──────────────────────────────────────────────────────────
        document.getElementById('sendNewMsg').addEventListener('click', function() {
            const rid = receiverIdEl.value;
            const msg = document.getElementById('newMsgText').value.trim();
            const fb = document.getElementById('newMsgFeedback');

            if (!rid) {
                fb.style.color = '#ef4444';
                fb.textContent = 'Please select a recipient.';
                return;
            }
            if (!msg) {
                fb.style.color = '#ef4444';
                fb.textContent = 'Please enter a message.';
                return;
            }

            const fd = new FormData();
            fd.append('action', 'send');
            fd.append('receiver_id', rid);
            fd.append('message', msg);

            fetch(BASE + 'messages_handler.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = BASE + 'messages_chat.php?user=' + rid;
                    } else {
                        fb.style.color = '#ef4444';
                        fb.textContent = data.error || 'Failed to send.';
                    }
                });
        });
    </script>
</body>

</html>