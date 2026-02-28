<?php include 'includes/nav.php'; ?>

<style>
    /* ── Hero ── */
    .hiw-hero {
        background: linear-gradient(135deg, #065f46 0%, #059669 60%, #10b981 100%);
        color: #fff;
        padding: 64px 0 52px;
        text-align: center;
    }

    .hiw-hero h1 {
        font-size: 2.4rem;
        font-weight: 800;
        margin-bottom: 12px;
        color: #fff;
    }

    .hiw-hero p {
        font-size: 1.05rem;
        color: #d1fae5;
        max-width: 560px;
        margin: 0 auto;
    }

    /* ── Tabs ── */
    .hiw-tabs {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        background: #f0fdf4;
        padding: 18px 20px;
        border-bottom: 1px solid #d1fae5;
        position: sticky;
        top: 64px;
        z-index: 100;
    }

    .hiw-tab-btn {
        padding: 10px 26px;
        border-radius: 50px;
        border: 2px solid #10b981;
        background: #fff;
        color: #065f46;
        font-weight: 600;
        font-size: 0.92rem;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .hiw-tab-btn.active,
    .hiw-tab-btn:hover {
        background: #10b981;
        color: #fff;
    }

    /* ── Panels ── */
    .hiw-panel {
        display: none;
        padding: 50px 0;
    }

    .hiw-panel.active {
        display: block;
    }

    /* ── Section heading ── */
    .hiw-section-title {
        font-size: 1.7rem;
        font-weight: 800;
        color: #065f46;
        margin-bottom: 8px;
        text-align: center;
    }

    .hiw-section-sub {
        color: #6b7280;
        margin-bottom: 32px;
        text-align: center;
    }

    /* ── Card grid (shared by all 3 panels) ── */
    .hiw-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 20px;
        margin-top: 8px;
    }

    .hiw-card {
        background: #fff;
        border: 1px solid #d1fae5;
        border-radius: 16px;
        padding: 26px 22px;
        box-shadow: 0 2px 10px rgba(16, 185, 129, 0.07);
        text-align: left;
        transition: box-shadow 0.22s, transform 0.22s;
        display: flex;
        flex-direction: column;
    }

    .hiw-card:hover {
        box-shadow: 0 8px 28px rgba(16, 185, 129, 0.16);
        transform: translateY(-3px);
    }

    .hiw-card .card-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #059669, #10b981);
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.15rem;
        margin-bottom: 16px;
        flex-shrink: 0;
    }

    .hiw-card .card-step {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #10b981;
        margin-bottom: 4px;
    }

    .hiw-card h5 {
        font-size: 1rem;
        font-weight: 700;
        color: #065f46;
        margin-bottom: 8px;
    }

    .hiw-card p {
        color: #4b5563;
        font-size: 0.88rem;
        margin: 0;
        line-height: 1.65;
        flex: 1;
    }

    .hiw-card ul {
        color: #4b5563;
        font-size: 0.88rem;
        margin: 0;
        padding-left: 16px;
        line-height: 1.65;
        flex: 1;
    }

    .hiw-card ul li {
        margin-bottom: 4px;
    }

    .hiw-card a {
        color: #059669;
        font-weight: 600;
    }

    .hiw-card a:hover {
        color: #065f46;
    }

    .hiw-card .hiw-note {
        background: #f0fdf4;
        border-left: 3px solid #10b981;
        border-radius: 0 6px 6px 0;
        padding: 8px 12px;
        color: #065f46;
        font-size: 0.82rem;
        margin-top: 10px;
    }

    /* ── CTA ── */
    .hiw-cta {
        background: linear-gradient(135deg, #065f46, #10b981);
        border-radius: 18px;
        padding: 40px 32px;
        text-align: center;
        color: #fff;
        margin-top: 32px;
    }

    .hiw-cta h3 {
        font-size: 1.45rem;
        font-weight: 800;
        margin-bottom: 10px;
        color: #fff;
    }

    .hiw-cta p {
        color: #d1fae5;
        margin-bottom: 22px;
    }

    .hiw-cta-btns {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .hiw-cta-btns a {
        padding: 11px 26px;
        border-radius: 50px;
        font-weight: 600;
        text-decoration: none;
        font-size: 0.93rem;
        transition: all 0.2s;
        display: inline-block;
    }

    .hiw-btn-primary {
        background: #fff;
        color: #065f46;
    }

    .hiw-btn-primary:hover {
        background: #ecfdf5;
        color: #065f46;
        text-decoration: none;
    }

    .hiw-btn-secondary {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        border: 2px solid rgba(255, 255, 255, 0.4);
    }

    .hiw-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.28);
        color: #fff;
        text-decoration: none;
    }
</style>

<!-- Hero -->
<div class="hiw-hero">
    <div class="container">
        <h1><i class="fas fa-info-circle mr-2"></i>How It Works</h1>
        <p>Everything you need to know about buying, selling, and bidding on Farmers' Market.</p>
    </div>
</div>

<!-- Sticky Tabs -->
<div class="hiw-tabs">
    <button class="hiw-tab-btn active" onclick="showTab('buyers', this)">
        <i class="fas fa-shopping-basket"></i> For Buyers
    </button>
    <button class="hiw-tab-btn" onclick="showTab('farmers', this)">
        <i class="fas fa-seedling"></i> For Farmers
    </button>
    <button class="hiw-tab-btn" onclick="showTab('auctions', this)">
        <i class="fas fa-gavel"></i> Auction Rules
    </button>
</div>

<div class="container">

    <!-- ══ BUYERS PANEL ══ -->
    <div id="panel-buyers" class="hiw-panel active">
        <div class="hiw-section-title">How to Buy</div>
        <p class="hiw-section-sub">Follow these steps to start purchasing fresh farm products.</p>
        <div class="hiw-card-grid">
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-user-plus"></i></div>
                <div class="card-step">Step 1</div>
                <h5>Create an Account &amp; Log In</h5>
                <ul>
                    <li><a href="#" data-auth-modal="signup">Register here</a> if you&rsquo;re new.</li>
                    <li><a href="#" data-auth-modal="login">Log in</a> if you already have an account.</li>
                </ul>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-th-large"></i></div>
                <div class="card-step">Step 2</div>
                <h5>Browse Available Products</h5>
                <ul>
                    <li>Use the <strong>search bar</strong> to find specific items.</li>
                    <li>Filter by <strong>category</strong> &mdash; Vegetables, Fruits, Dairy, etc.</li>
                    <li>Click any product to see details, minimum price, and time left.</li>
                </ul>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-hand-paper"></i></div>
                <div class="card-step">Step 3</div>
                <h5>Place a Bid</h5>
                <ul>
                    <li>Check the <strong>minimum price</strong> set by the farmer.</li>
                    <li>Enter a bid <strong>equal to or higher</strong> than the minimum.</li>
                    <li>Click <strong>"Place Bid"</strong> and wait for the timer.</li>
                </ul>
                <div class="hiw-note">Bids cannot be cancelled once placed.</div>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-trophy"></i></div>
                <div class="card-step">Step 4</div>
                <h5>Win the Auction</h5>
                <ul>
                    <li>The <strong>highest bid wins</strong> when the timer expires.</li>
                    <li>You&rsquo;ll get a <strong>notification</strong> confirming your win.</li>
                    <li>The farmer will arrange delivery or pickup.</li>
                </ul>
            </div>
        </div>
        <div class="hiw-cta">
            <h3>Ready to start bidding?</h3>
            <p>Browse live products and place your first bid today.</p>
            <div class="hiw-cta-btns">
                <a href="browse.php" class="hiw-btn-primary">Browse Products</a>
                <a href="index.php#live-auctions" class="hiw-btn-secondary">View Live Auctions</a>
            </div>
        </div>
    </div>

    <!-- ══ FARMERS PANEL ══ -->
    <div id="panel-farmers" class="hiw-panel">
        <div class="hiw-section-title">How to Sell</div>
        <p class="hiw-section-sub">List your products and reach buyers through live auctions.</p>
        <div class="hiw-card-grid">
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-seedling"></i></div>
                <div class="card-step">Step 1</div>
                <h5>Register as a Farmer</h5>
                <ul>
                    <li><a href="#" data-auth-modal="signup">Register here</a> and choose the <strong>Farmer</strong> role.</li>
                    <li><a href="#" data-auth-modal="login">Log in</a> if you already have an account.</li>
                </ul>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-plus-circle"></i></div>
                <div class="card-step">Step 2</div>
                <h5>List a Product</h5>
                <p>Go to your <strong>Farmer Dashboard</strong>, click <strong>"Add Product"</strong> and fill in the name, image, category, minimum price, and auction duration.</p>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-gavel"></i></div>
                <div class="card-step">Step 3</div>
                <h5>Bidding Starts</h5>
                <ul>
                    <li>Buyers bid at or above your minimum price.</li>
                    <li>If a bid meets your price, the product is <strong>SOLD</strong> instantly.</li>
                    <li>If no valid bid is placed in time, the timer extends.</li>
                </ul>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-check-circle"></i></div>
                <div class="card-step">Step 4</div>
                <h5>Sale Complete</h5>
                <ul>
                    <li>You receive a <strong>notification</strong> when your product is sold.</li>
                    <li>Arrange payment and delivery with the buyer.</li>
                    <li>Track everything in <a href="farmer/manage_orders.php">Manage Orders</a>.</li>
                </ul>
                <div class="hiw-note">Set a fair minimum price to attract more bids.</div>
            </div>
        </div>
        <div class="hiw-cta">
            <h3>Ready to list your first product?</h3>
            <p>It only takes a few minutes to create a listing and reach buyers.</p>
            <div class="hiw-cta-btns">
                <a href="farmer/create_post.php" class="hiw-btn-primary">List a Product</a>
                <a href="farmer/dashboard.php" class="hiw-btn-secondary">Go to Dashboard</a>
            </div>
        </div>
    </div>

    <!-- ══ AUCTION RULES PANEL ══ -->
    <div id="panel-auctions" class="hiw-panel">
        <div class="hiw-section-title">Auction Rules &amp; Mechanics</div>
        <p class="hiw-section-sub">Understand how bidding timers, pricing, and winning work.</p>
        <div class="hiw-card-grid">
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-play-circle"></i></div>
                <h5>Bidding Starts</h5>
                <p>The auction timer begins as soon as the first bid is placed. Until then, the listing stays open indefinitely.</p>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-clock"></i></div>
                <h5>Timer Duration</h5>
                <p>Farmers set their own duration &mdash; 2 minutes, 5 minutes, or custom. Admins can also set platform-wide defaults.</p>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-arrow-up"></i></div>
                <h5>Minimum Bid</h5>
                <p>All bids must be equal to or higher than the farmer&rsquo;s set minimum price. Lower bids are automatically rejected.</p>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-bolt"></i></div>
                <h5>Instant Sale</h5>
                <p>If a bid meets or exceeds the farmer&rsquo;s price, the product is marked <strong>SOLD</strong> immediately &mdash; no need to wait for the timer.</p>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-redo"></i></div>
                <h5>Timer Extension</h5>
                <p>If the timer expires with bids still below the farmer&rsquo;s price, the timer automatically extends to allow more bidding.</p>
            </div>
            <div class="hiw-card">
                <div class="card-icon"><i class="fas fa-trophy"></i></div>
                <h5>Winner</h5>
                <p>The highest valid bid when the timer ends wins. The winner is notified instantly via the notification system.</p>
            </div>
        </div>
        <div class="hiw-note" style="margin-top:24px;">
            <strong>Important:</strong> Bids cannot be cancelled once placed. All auction results are final when the timer expires.
        </div>
    </div>

</div>

<script>
    function showTab(name, btn) {
        document.querySelectorAll('.hiw-panel').forEach(function(p) {
            p.classList.remove('active');
        });
        document.querySelectorAll('.hiw-tab-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        document.getElementById('panel-' + name).classList.add('active');
        btn.classList.add('active');
    }
</script>

<?php include 'includes/footer.php'; ?>