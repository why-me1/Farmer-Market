<?php
$footer_base = (strpos($_SERVER['PHP_SELF'], '/farmer/') !== false
             || strpos($_SERVER['PHP_SELF'], '/user/')   !== false
             || strpos($_SERVER['PHP_SELF'], '/admin/')  !== false)
             ? '../' : '';
?>
<style>
.fm-footer {
    background: linear-gradient(160deg, #022c22 0%, #064e3b 60%, #065f46 100%);
    color: #d1fae5;
    padding: 56px 0 0;
    margin-top: 60px;
    font-size: 0.93rem;
}
.fm-footer .footer-brand-icon {
    width: 44px; height: 44px;
    background: rgba(16,185,129,0.2);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: #6ee7b7;
    margin-bottom: 12px;
}
.fm-footer .footer-brand-name {
    font-size: 1.2rem; font-weight: 700; color: #ecfdf5; margin-bottom: 4px;
}
.fm-footer .footer-brand-tagline {
    font-size: 0.8rem; color: #6ee7b7; margin-bottom: 14px;
}
.fm-footer p { color: #a7f3d0; line-height: 1.7; }
.fm-footer .footer-heading {
    font-size: 0.75rem; font-weight: 700; letter-spacing: 0.12em;
    text-transform: uppercase; color: #6ee7b7;
    margin-bottom: 18px; position: relative; padding-bottom: 10px;
}
.fm-footer .footer-heading::after {
    content: ''; position: absolute; bottom: 0; left: 0;
    width: 28px; height: 2px; background: #10b981; border-radius: 2px;
}
.fm-footer .footer-links { list-style: none; padding: 0; margin: 0; }
.fm-footer .footer-links li { margin-bottom: 10px; }
.fm-footer .footer-links a {
    color: #a7f3d0; text-decoration: none;
    display: flex; align-items: center; gap: 8px;
    transition: color 0.2s, gap 0.2s;
}
.fm-footer .footer-links a:hover { color: #ecfdf5; gap: 12px; }
.fm-footer .footer-links a i { font-size: 0.7rem; color: #10b981; }
.fm-footer .footer-contact-item {
    display: flex; align-items: flex-start; gap: 10px;
    margin-bottom: 12px; color: #a7f3d0;
}
.fm-footer .footer-contact-item .fc-icon {
    width: 32px; height: 32px;
    background: rgba(16,185,129,0.15); border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #6ee7b7; font-size: 0.8rem; flex-shrink: 0; margin-top: 1px;
}
.fm-footer .footer-social { display: flex; gap: 10px; margin-top: 6px; }
.fm-footer .footer-social a {
    width: 36px; height: 36px; border-radius: 9px;
    background: rgba(16,185,129,0.15); color: #6ee7b7;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; font-size: 0.95rem;
    transition: background 0.2s, color 0.2s, transform 0.2s;
}
.fm-footer .footer-social a:hover {
    background: #10b981; color: #fff; transform: translateY(-3px);
}
.fm-footer .footer-divider {
    border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 40px 0 0;
}
.fm-footer .footer-bottom { background: rgba(0,0,0,0.25); padding: 18px 0; }
.fm-footer .footer-bottom-inner {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
}
.fm-footer .footer-copyright { color: #6ee7b7; font-size: 0.82rem; }
.fm-footer .footer-bottom-links { display: flex; gap: 20px; }
.fm-footer .footer-bottom-links a {
    color: #6ee7b7; text-decoration: none; font-size: 0.82rem; transition: color 0.2s;
}
.fm-footer .footer-bottom-links a:hover { color: #ecfdf5; }
.fm-footer .footer-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(16,185,129,0.15);
    border: 1px solid rgba(16,185,129,0.3);
    border-radius: 20px; padding: 3px 10px;
    font-size: 0.75rem; color: #6ee7b7;
}
</style>

<footer class="fm-footer">
    <div class="container">
        <div class="row g-4">

            <!-- Brand -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand-icon"><i class="fas fa-seedling"></i></div>
                <div class="footer-brand-name">Farmers' Market</div>
                <div class="footer-brand-tagline">Fresh from the field</div>
                <p>Connecting local farmers directly with buyers. Fresh, organic produce auctioned live — fair prices, zero middlemen.</p>
                <div class="footer-social mt-3">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="Twitter / X"><i class="fab fa-x-twitter"></i></a>
                    <a href="#" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 col-6">
                <div class="footer-heading">Quick Links</div>
                <ul class="footer-links">
                    <li><a href="<?php echo $footer_base; ?>index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="<?php echo $footer_base; ?>browse.php"><i class="fas fa-chevron-right"></i> Browse</a></li>
                    <li><a href="<?php echo $footer_base; ?>index.php#live-auctions"><i class="fas fa-chevron-right"></i> Live Auctions</a></li>
                    <li><a href="<?php echo $footer_base; ?>bidding_guide.php"><i class="fas fa-chevron-right"></i> How it Works</a></li>
                    <li><a href="<?php echo $footer_base; ?>register.php"><i class="fas fa-chevron-right"></i> Register</a></li>
                    <li><a href="<?php echo $footer_base; ?>login.php"><i class="fas fa-chevron-right"></i> Login</a></li>
                </ul>
            </div>

            <!-- For Farmers -->
            <div class="col-lg-2 col-md-6 col-6">
                <div class="footer-heading">For Farmers</div>
                <ul class="footer-links">
                    <li><a href="<?php echo $footer_base; ?>farmer/dashboard.php"><i class="fas fa-chevron-right"></i> Dashboard</a></li>
                    <li><a href="<?php echo $footer_base; ?>farmer/create_post.php"><i class="fas fa-chevron-right"></i> List a Product</a></li>
                    <li><a href="<?php echo $footer_base; ?>farmer/view_posts.php"><i class="fas fa-chevron-right"></i> My Listings</a></li>
                    <li><a href="<?php echo $footer_base; ?>farmer/manage_orders.php"><i class="fas fa-chevron-right"></i> Manage Orders</a></li>
                    <li><a href="<?php echo $footer_base; ?>farmer/profile.php"><i class="fas fa-chevron-right"></i> My Profile</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-heading">Contact Us</div>
                <div class="footer-contact-item">
                    <div class="fc-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <span>123 Green Street, Farmville, BD</span>
                </div>
                <div class="footer-contact-item">
                    <div class="fc-icon"><i class="fas fa-phone"></i></div>
                    <span>+880 1234 567 890</span>
                </div>
                <div class="footer-contact-item">
                    <div class="fc-icon"><i class="fas fa-envelope"></i></div>
                    <span>support@farmersmarket.com</span>
                </div>
                <div class="footer-contact-item">
                    <div class="fc-icon"><i class="fas fa-clock"></i></div>
                    <span>Mon &ndash; Sat: 8:00 AM &ndash; 8:00 PM</span>
                </div>
            </div>

        </div>
        <hr class="footer-divider">
    </div>

    <!-- Bottom Bar -->
    <div class="footer-bottom">
        <div class="container">
            <div class="footer-bottom-inner">
                <div class="footer-copyright">
                    &copy; <?php echo date('Y'); ?> Farmers' Market. All rights reserved.
                </div>
                <div>
                    <span class="footer-badge"><i class="fas fa-leaf"></i> 100% Fresh &amp; Local</span>
                </div>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Use</a>
                </div>
            </div>
        </div>
    </div>
</footer>