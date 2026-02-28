<?php
// Ensure CSRF token exists and base URL is available
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$_auth_handler_url = (defined('BASE_URL') ? BASE_URL : (isset($base_url) ? $base_url : 'http://localhost/demo/')) . 'auth_handler.php';
?>
<!-- ═══════════════════════════════════ AUTH MODAL ═══════════════════════════════════ -->
<div class="am-overlay" id="authModal" role="dialog" aria-modal="true" aria-label="Sign in or create account">
    <div class="am-card">
        <!-- Close -->
        <button class="am-close" id="amClose" aria-label="Close">&times;</button>

        <!-- Branded Banner -->
        <div class="am-banner">
            <div class="am-banner-logo"><i class="fas fa-leaf"></i></div>
            <p class="am-banner-title">Farmers' Marketplace</p>
            <p class="am-banner-sub">Fresh produce, directly from farmers</p>
        </div>

        <!-- Pill Tab Switcher -->
        <div class="am-tabs">
            <button class="am-tab active" id="amTabLogin" data-tab="login">Login</button>
            <button class="am-tab" id="amTabSignup" data-tab="signup">Sign Up</button>
        </div>

        <!-- ── LOGIN PANEL ── -->
        <div class="am-panel" id="amPanelLogin">
            <div class="am-panel-header">
                <div class="am-icon"><i class="fas fa-leaf"></i></div>
                <h2>Welcome Back</h2>
                <p>Sign in to your account</p>
            </div>
            <div class="am-alert" id="amLoginAlert"></div>
            <form id="amLoginForm" novalidate>
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" id="amLoginCsrf" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="redirect" id="amLoginRedirect" value="">

                <div class="am-field">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" placeholder="Enter your username" required autocomplete="username">
                </div>
                <div class="am-field">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="am-pw-wrap">
                        <input type="password" name="password" id="amLoginPw" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="am-pw-toggle" title="Show/hide password"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="am-submit-btn" id="amLoginBtn">
                    <span>Sign In</span><i class="fas fa-arrow-right"></i>
                </button>
            </form>
            <p class="am-switch-text">Don't have an account? <button class="am-switch-link" data-target="signup">Create one</button></p>
        </div>

        <!-- ── SIGN UP PANEL ── -->
        <div class="am-panel am-panel-hidden" id="amPanelSignup">
            <div class="am-panel-header">
                <div class="am-icon"><i class="fas fa-user-plus"></i></div>
                <h2>Create Account</h2>
                <p>Join the Farmers' Marketplace</p>
            </div>
            <div class="am-alert" id="amSignupAlert"></div>
            <form id="amSignupForm" novalidate>
                <input type="hidden" name="action" value="register">

                <div class="am-field">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" placeholder="Choose a username" required autocomplete="username">
                </div>
                <div class="am-field">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="am-pw-wrap">
                        <input type="password" name="password" id="amSignupPw" placeholder="Create a password" required autocomplete="new-password">
                        <button type="button" class="am-pw-toggle" title="Show/hide password"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="am-field">
                    <label><i class="fas fa-user-tag"></i> Register As</label>
                    <select name="role" required>
                        <option value="">Select your role</option>
                        <option value="user">Buyer</option>
                        <option value="farmer">Farmer</option>
                    </select>
                </div>
                <button type="submit" class="am-submit-btn" id="amSignupBtn">
                    <span>Create Account</span><i class="fas fa-arrow-right"></i>
                </button>
            </form>
            <p class="am-switch-text">Already have an account? <button class="am-switch-link" data-target="login">Sign in</button></p>
        </div>
    </div>
</div>

<style>
    /* ══════════════════════════════════════════════
       OVERLAY
    ══════════════════════════════════════════════ */
    .am-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(6, 26, 15, .65);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }

    .am-overlay.am-open {
        display: flex;
    }

    /* ══════════════════════════════════════════════
       CARD
    ══════════════════════════════════════════════ */
    .am-card {
        position: relative;
        background: #fff;
        border-radius: 24px;
        width: 100%;
        max-width: 460px;
        box-shadow:
            0 4px 6px rgba(0, 0, 0, .04),
            0 20px 50px rgba(0, 0, 0, .18),
            0 0 0 1px rgba(255, 255, 255, .08);
        overflow: hidden;
        animation: amSlideIn .3s cubic-bezier(.34, 1.56, .64, 1);
    }

    @keyframes amSlideIn {
        from {
            opacity: 0;
            transform: translateY(32px) scale(.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* ══════════════════════════════════════════════
       BRANDED HEADER BANNER
    ══════════════════════════════════════════════ */
    .am-banner {
        position: relative;
        background: linear-gradient(135deg, #064e3b 0%, #065f46 40%, #059669 80%, #10b981 100%);
        padding: 32px 28px 20px;
        overflow: hidden;
        text-align: center;
    }

    /* decorative blobs */
    .am-banner::before,
    .am-banner::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        opacity: .15;
    }

    .am-banner::before {
        width: 180px;
        height: 180px;
        background: #fff;
        top: -60px;
        right: -40px;
    }

    .am-banner::after {
        width: 120px;
        height: 120px;
        background: #fff;
        bottom: -50px;
        left: -30px;
    }

    .am-banner-logo {
        position: relative;
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, .18);
        border: 2px solid rgba(255, 255, 255, .35);
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #fff;
        backdrop-filter: blur(4px);
        margin-bottom: 12px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, .15);
    }

    .am-banner-title {
        position: relative;
        font-size: 1.1rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 2px;
        letter-spacing: .3px;
    }

    .am-banner-sub {
        position: relative;
        font-size: .8rem;
        color: rgba(255, 255, 255, .75);
        margin: 0;
    }

    /* ══════════════════════════════════════════════
       CLOSE BUTTON
    ══════════════════════════════════════════════ */
    .am-close {
        position: absolute;
        top: 14px;
        right: 16px;
        width: 32px;
        height: 32px;
        background: rgba(255, 255, 255, .18);
        border: 1px solid rgba(255, 255, 255, .3);
        border-radius: 50%;
        font-size: 1.1rem;
        line-height: 1;
        color: #fff;
        cursor: pointer;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .2s;
    }

    .am-close:hover {
        background: rgba(255, 255, 255, .32);
    }

    /* ══════════════════════════════════════════════
       PILL TAB SWITCHER
    ══════════════════════════════════════════════ */
    .am-tabs {
        display: flex;
        background: #f1f5f9;
        border-radius: 14px;
        padding: 4px;
        margin: 20px 24px 0;
        gap: 0;
    }

    .am-tab {
        flex: 1;
        background: transparent;
        border: none;
        border-radius: 10px;
        color: #64748b;
        font-weight: 600;
        font-size: .875rem;
        padding: 9px 0;
        cursor: pointer;
        transition: background .2s, color .2s, box-shadow .2s;
    }

    .am-tab:hover {
        color: #065f46;
    }

    .am-tab.active {
        background: #fff;
        color: #065f46;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .1);
    }

    /* ══════════════════════════════════════════════
       PANEL
    ══════════════════════════════════════════════ */
    .am-panel {
        padding: 20px 28px 28px;
    }

    .am-panel-hidden {
        display: none;
    }

    /* panel title (replaces old header) */
    .am-panel-header {
        margin-bottom: 16px;
    }

    .am-icon {
        display: none;
    }

    /* hidden — logo is in banner now */
    .am-panel-header h2 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 3px;
    }

    .am-panel-header p {
        color: #6b7280;
        font-size: .82rem;
        margin: 0;
    }

    /* ══════════════════════════════════════════════
       ALERT
    ══════════════════════════════════════════════ */
    .am-alert {
        display: none;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: .82rem;
        margin-bottom: 14px;
        font-weight: 500;
        display: flex;
        align-items: flex-start;
        gap: 8px;
    }

    .am-alert.am-error {
        display: flex;
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .am-alert.am-success {
        display: flex;
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }

    .am-alert:not(.am-error):not(.am-success) {
        display: none;
    }

    /* ══════════════════════════════════════════════
       FIELDS  (floating-label style)
    ══════════════════════════════════════════════ */
    .am-field {
        margin-bottom: 14px;
    }

    .am-field label {
        display: block;
        font-size: .78rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .am-field label i {
        margin-right: 5px;
        color: #059669;
    }

    .am-field input,
    .am-field select {
        width: 100%;
        padding: 11px 14px;
        border: 1.5px solid #e5e7eb;
        border-radius: 12px;
        font-size: .9rem;
        color: #111827;
        background: #f8fafc;
        transition: border-color .2s, background .2s, box-shadow .2s;
        outline: none;
        box-sizing: border-box;
        appearance: none;
    }

    .am-field input::placeholder {
        color: #9ca3af;
    }

    .am-field input:focus,
    .am-field select:focus {
        border-color: #059669;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(5, 150, 105, .1);
    }

    .am-field select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 36px;
        cursor: pointer;
    }

    /* ── Password wrap ── */
    .am-pw-wrap {
        position: relative;
    }

    .am-pw-wrap input {
        padding-right: 44px;
    }

    .am-pw-toggle {
        position: absolute;
        right: 13px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #9ca3af;
        cursor: pointer;
        padding: 0;
        font-size: .85rem;
        transition: color .2s;
    }

    .am-pw-toggle:hover {
        color: #059669;
    }

    /* ══════════════════════════════════════════════
       DIVIDER
    ══════════════════════════════════════════════ */
    .am-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 4px 0 16px;
        color: #9ca3af;
        font-size: .75rem;
        font-weight: 500;
        letter-spacing: .5px;
    }

    .am-divider::before,
    .am-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e5e7eb;
    }

    /* ══════════════════════════════════════════════
       SUBMIT BUTTON
    ══════════════════════════════════════════════ */
    .am-submit-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 13px;
        background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #059669 100%);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-size: .92rem;
        font-weight: 700;
        cursor: pointer;
        margin-top: 6px;
        letter-spacing: .3px;
        box-shadow: 0 4px 14px rgba(5, 150, 105, .35);
        transition: box-shadow .25s, transform .15s, opacity .2s;
        position: relative;
        overflow: hidden;
    }

    .am-submit-btn::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(255, 255, 255, .1) 0%, transparent 100%);
        pointer-events: none;
    }

    .am-submit-btn:hover {
        box-shadow: 0 6px 20px rgba(5, 150, 105, .45);
        transform: translateY(-1px);
    }

    .am-submit-btn:active {
        transform: scale(.98);
        box-shadow: none;
    }

    .am-submit-btn:disabled {
        opacity: .55;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .am-submit-btn .fa-spinner {
        animation: amSpin .7s linear infinite;
    }

    @keyframes amSpin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ══════════════════════════════════════════════
       SWITCH TEXT
    ══════════════════════════════════════════════ */
    .am-switch-text {
        text-align: center;
        margin-top: 18px;
        font-size: .82rem;
        color: #6b7280;
    }

    .am-switch-link {
        background: none;
        border: none;
        color: #059669;
        font-weight: 700;
        cursor: pointer;
        padding: 0;
        text-decoration: none;
        border-bottom: 1.5px solid transparent;
        transition: border-color .2s, color .2s;
    }

    .am-switch-link:hover {
        color: #065f46;
        border-bottom-color: #065f46;
    }

    /* ══════════════════════════════════════════════
       RESPONSIVE
    ══════════════════════════════════════════════ */
    @media (max-width: 480px) {
        .am-card {
            border-radius: 20px;
        }

        .am-panel {
            padding: 18px 20px 24px;
        }

        .am-tabs {
            margin: 16px 16px 0;
        }

        .am-banner {
            padding: 24px 20px 16px;
        }
    }
</style>

<script>
    (function() {
        const AUTH_URL = <?php echo json_encode($_auth_handler_url); ?>;
        const modal = document.getElementById('authModal');
        const closeBtn = document.getElementById('amClose');
        const tabLogin = document.getElementById('amTabLogin');
        const tabSignup = document.getElementById('amTabSignup');
        const panelLogin = document.getElementById('amPanelLogin');
        const panelSignup = document.getElementById('amPanelSignup');
        const loginForm = document.getElementById('amLoginForm');
        const signupForm = document.getElementById('amSignupForm');
        const loginAlert = document.getElementById('amLoginAlert');
        const signupAlert = document.getElementById('amSignupAlert');
        const loginBtn = document.getElementById('amLoginBtn');
        const signupBtn = document.getElementById('amSignupBtn');
        const csrfInput = document.getElementById('amLoginCsrf');
        const redirectInput = document.getElementById('amLoginRedirect');

        // ── Open / close ─────────────────────────────────────────
        function openModal(tab, redirectUrl) {
            modal.classList.add('am-open');
            document.body.style.overflow = 'hidden';
            if (redirectUrl) redirectInput.value = redirectUrl;
            switchTab(tab || 'login');
            clearAlerts();
        }

        function closeModal() {
            modal.classList.remove('am-open');
            document.body.style.overflow = '';
        }

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // ── Tab switching ─────────────────────────────────────────
        function switchTab(tab) {
            const isLogin = tab === 'login';
            tabLogin.classList.toggle('active', isLogin);
            tabSignup.classList.toggle('active', !isLogin);
            panelLogin.classList.toggle('am-panel-hidden', !isLogin);
            panelSignup.classList.toggle('am-panel-hidden', isLogin);
            clearAlerts();
            // Focus first input
            const panel = isLogin ? panelLogin : panelSignup;
            const first = panel.querySelector('input');
            if (first) setTimeout(() => first.focus(), 50);
        }

        tabLogin.addEventListener('click', () => switchTab('login'));
        tabSignup.addEventListener('click', () => switchTab('signup'));

        document.querySelectorAll('.am-switch-link').forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.target));
        });

        // ── Alert helpers ─────────────────────────────────────────
        function clearAlerts() {
            [loginAlert, signupAlert].forEach(a => {
                a.className = 'am-alert';
                a.textContent = '';
            });
        }

        function showAlert(el, msg, type) {
            el.className = 'am-alert am-' + type;
            el.textContent = msg;
        }

        // ── Loading state ─────────────────────────────────────────
        function setLoading(btn, loading) {
            btn.disabled = loading;
            if (loading) {
                btn.innerHTML = '<i class="fas fa-spinner"></i> Please wait…';
            } else {
                const isLogin = btn === loginBtn;
                btn.innerHTML = isLogin ?
                    '<span>Sign In</span><i class="fas fa-arrow-right"></i>' :
                    '<span>Create Account</span><i class="fas fa-arrow-right"></i>';
            }
        }

        // ── Refresh CSRF ──────────────────────────────────────────
        function refreshCsrf() {
            return fetch(AUTH_URL, {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'get_csrf'
                })
            }).then(r => r.json()).then(d => {
                if (d.token) csrfInput.value = d.token;
            });
        }

        // ── Login submit ──────────────────────────────────────────
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            clearAlerts();
            setLoading(loginBtn, true);

            const data = new FormData(loginForm);

            fetch(AUTH_URL, {
                    method: 'POST',
                    body: data
                })
                .then(r => r.json())
                .then(res => {
                    setLoading(loginBtn, false);
                    if (res.success) {
                        showAlert(loginAlert, 'Logged in! Refreshing…', 'success');
                        setTimeout(() => {
                            if (res.redirect) {
                                window.location.href = res.redirect;
                            } else {
                                window.location.reload();
                            }
                        }, 600);
                    } else {
                        showAlert(loginAlert, res.message, 'error');
                        refreshCsrf();
                    }
                })
                .catch(() => {
                    setLoading(loginBtn, false);
                    showAlert(loginAlert, 'Network error. Please try again.', 'error');
                    refreshCsrf();
                });
        });

        // ── Register submit ───────────────────────────────────────
        signupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            clearAlerts();
            setLoading(signupBtn, true);

            const data = new FormData(signupForm);

            fetch(AUTH_URL, {
                    method: 'POST',
                    body: data
                })
                .then(r => r.json())
                .then(res => {
                    setLoading(signupBtn, false);
                    if (res.success) {
                        showAlert(signupAlert, res.message + ' Switching to login…', 'success');
                        signupForm.reset();
                        setTimeout(() => switchTab('login'), 1800);
                    } else {
                        showAlert(signupAlert, res.message, 'error');
                    }
                })
                .catch(() => {
                    setLoading(signupBtn, false);
                    showAlert(signupAlert, 'Network error. Please try again.', 'error');
                });
        });

        // ── Password toggle ───────────────────────────────────────
        document.querySelectorAll('.am-pw-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                const inp = this.previousElementSibling;
                const show = inp.type === 'password';
                inp.type = show ? 'text' : 'password';
                this.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            });
        });

        // ── Global trigger: any element with data-auth-modal ─────
        // Usage: <a data-auth-modal="login" data-redirect="/some/page.php">Login</a>
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('[data-auth-modal]');
            if (trigger) {
                e.preventDefault();
                openModal(trigger.dataset.authModal, trigger.dataset.redirect || '');
            }
        });

        // Expose globally so nav links can call it
        window.openAuthModal = openModal;

        // ── Auto-open from URL param (?auth=login or ?auth=signup) ──
        (function() {
            const params = new URLSearchParams(window.location.search);
            const authParam = params.get('auth');
            if (authParam === 'login' || authParam === 'signup') {
                openModal(authParam, '');
                // Clean the param from the URL without reloading
                params.delete('auth');
                const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.replaceState({}, '', newUrl);
            }
        })();
    })();
</script>