<?php
// Reusable messenger-style chat widget (polling) snippet.
// Includes: button, overlay, popup modal + JS.
// Expectations:
// - FontAwesome is loaded on the page.
// - api endpoint exists: api.php?type=chat (GET/POST returning JSON {success, messages}).
// - The page is for an authenticated user (user/admin).
//
// NOTE: This snippet intentionally uses fixed positioning and keeps the button always visible.
//
?>

<!-- Messenger-style Chat Widget (Dashboard Popup) -->
<button id="chatWidgetBtn" type="button" class="fixed" style="right: 24px; bottom: 24px; z-index: 9999; width: 56px; height: 56px; border-radius: 9999px; background: linear-gradient(135deg, #1a472a, #2d6a4f); display:flex; align-items:center; justify-content:center; box-shadow: 0 16px 48px rgba(27,67,50,0.25); cursor:pointer; border:none;">
    <i class="fas fa-comments" style="color: white; font-size: 20px;"></i>
</button>

<div id="chatWidgetOverlay" class="fixed inset-0" style="z-index: 10000; display:none; background: rgba(15,23,42,0.35); backdrop-filter: blur(8px);"></div>

<div id="chatWidgetModal" class="fixed" style="z-index: 10001; display:none; right: 24px; bottom: 92px; width: 360px; max-width: calc(100vw - 48px); height: 520px; background:#fff; border-radius: 22px; box-shadow: 0 25px 50px rgba(0,0,0,0.25); overflow:hidden; border: 1px solid rgba(163,177,138,0.25);">
    <div style="padding: 14px 16px; background: linear-gradient(135deg, #3d5a40, #2d6a4f); color:white; display:flex; align-items:center; justify-content:space-between;">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:36px; height:36px; border-radius: 14px; background: rgba(220,252,231,0.25); display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-headset" style="color:#dcfce7;"></i>
            </div>
            <div>
                <div style="font-weight:900; line-height:1.1;">CAVENDIA Support</div>
                <div style="font-size:12px; opacity:0.9;">Online</div>
            </div>
        </div>
        <button id="chatWidgetClose" type="button" style="background: rgba(255,255,255,0.15); border:none; color:white; width:36px; height:36px; border-radius: 14px; cursor:pointer;">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div id="chatWidgetMessageList" style="height: calc(100% - 140px); overflow-y:auto; padding: 12px 12px; background:#faf9f6;">
        <div id="chatWidgetEmpty" style="height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#8b9a7a; gap:8px;">
            <i class="fas fa-comments text-4xl" style="color:#c5d8bf;"></i>

            <div style="font-weight:700;">No messages yet</div>
            <div style="font-size:12px; text-align:center;">Start a conversation with our support team</div>
        </div>
        <div id="chatWidgetBubbles"></div>
    </div>

    <form id="chatWidgetForm" style="padding: 10px 12px; border-top: 1px solid #e8ebe3; background: white;" autocomplete="off">
        <div style="position:relative;">
            <input id="chatWidgetInput" type="text" name="message" placeholder="Type your message..." required style="width:100%; padding: 12px 44px 12px 14px; border: 2px solid #e3ebe0; border-radius: 24px; outline:none; font-size: 14px;" />
            <button type="submit" style="position:absolute; right: 8px; top:50%; transform: translateY(-50%); width: 34px; height:34px; border-radius: 50%; background:#1a472a; color:white; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </form>
</div>

<script>
// Messenger-style dashboard chat widget (real-time via polling)
(function () {
    const btn = document.getElementById('chatWidgetBtn');
    const overlay = document.getElementById('chatWidgetOverlay');
    const modal = document.getElementById('chatWidgetModal');
    const closeBtn = document.getElementById('chatWidgetClose');
    const messageList = document.getElementById('chatWidgetBubbles');
    const emptyState = document.getElementById('chatWidgetEmpty');
    const form = document.getElementById('chatWidgetForm');
    const input = document.getElementById('chatWidgetInput');

    if (!btn || !overlay || !modal || !messageList || !emptyState || !form || !input) return;

    // Hard-guarantee button visibility (prevents “disappear after clicking X”)
    btn.style.setProperty('display', 'flex', 'important');
    btn.style.setProperty('opacity', '1', 'important');
    btn.style.setProperty('visibility', 'visible', 'important');
    btn.style.setProperty('z-index', '9999', 'important');

    // Prevent any other overlay from visually covering it
    overlay.style.zIndex = '10000';
    modal.style.zIndex = '10001';


    let pollingTimer = null;

    // Dedupe to prevent duplicate rendering after polling.
    // Key = (sender_type + created_at + message). This is stable for the same DB row.
    const renderedKeys = new Set();


    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/'/g, '&#039;');
    }

    function renderMessages(messages) {
        messageList.innerHTML = '';

        const bubbles = (messages || []).map(msg => {
            const senderType = msg.sender_type === 'user' ? 'user' : 'admin';
            const safeMsg = escapeHtml(msg.message);
            const senderName = msg.user_name ? String(msg.user_name) : (senderType === 'user' ? 'User' : 'Admin');
            const createdAt = msg.created_at ? String(msg.created_at) : '';

            return `
                <div class="message-bubble ${senderType}" style="max-width:75%; padding:10px 14px; border-radius:18px; margin-bottom:10px; ${senderType === 'user' ? 'margin-left:auto; background: linear-gradient(135deg, #1a472a, #2d6a4f); color:#fff;' : 'background:#fff; border:1px solid #e8ebe3; color:#353f2d;'}">
                    <div style="word-break:break-word;">${safeMsg}</div>
                    <div style="font-size:12px; opacity:.7; margin-top:4px;">${escapeHtml(senderName)} &bull; ${escapeHtml(createdAt)}</div>
                </div>
            `;
        });

        if (!bubbles.length) {
            emptyState.style.display = 'flex';
            return;
        }

        emptyState.style.display = 'none';
        bubbles.forEach(html => {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            messageList.appendChild(wrapper.firstElementChild);
        });

        const container = document.getElementById('chatWidgetMessageList');
        if (container) container.scrollTop = container.scrollHeight;
    }

    async function fetchMessages() {
        const res = await fetch('api.php?type=chat', { method: 'GET' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.success || !Array.isArray(data.messages)) return;

        const messages = data.messages.slice().reverse();
        const sig = messages.length ? (messages[messages.length - 1].sender_type + '|' + messages[messages.length - 1].message + '|' + messages[messages.length - 1].created_at) : null;
        if (sig && sig === lastMsgSig) return;
        lastMsgSig = sig;

        renderMessages(messages);
    }

    function startPolling() {
        if (pollingTimer) return;
        fetchMessages().catch(() => {});
        pollingTimer = setInterval(() => fetchMessages().catch(() => {}), 2000);
    }

    function stopPolling() {
        if (pollingTimer) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }
    }

    function openWidget() {
        overlay.style.display = 'block';
        modal.style.display = 'block';
        overlay.classList.add('active');
        startPolling();
    }

    function closeWidget() {
        // Hide modal/overlay but keep button + icon always visible.
        overlay.style.display = 'none';
        modal.style.display = 'none';
        overlay.classList.remove('active');
        stopPolling();

        // Force button visibility immediately (user reported icon disappears after clicking X)
        forceKeepChatButtonVisible();
    }


    // Ensure the icon button never disappears (even if other scripts/styles toggle it)
    function forceKeepChatButtonVisible() {
        if (!btn) return;
        btn.style.display = 'flex';
        btn.style.opacity = '1';
        btn.style.visibility = 'visible';
        btn.style.zIndex = '9999';
        btn.style.pointerEvents = 'auto';
    }

    forceKeepChatButtonVisible();

    // Re-apply periodically to guard against accidental hide
    setInterval(forceKeepChatButtonVisible, 1000);


    // Guard close button (optional)
    if (!closeBtn) return;

    btn.addEventListener('click', (e) => {

        e.preventDefault();
        openWidget();
    });

    closeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        closeWidget();
    });

    overlay.addEventListener('click', closeWidget);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = input.value.trim();
        if (!msg) return;

        await fetch('api.php?type=chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg })
        }).catch(() => {});

        input.value = '';
        fetchMessages().catch(() => {});
    });

    closeWidget();
})();
</script>

