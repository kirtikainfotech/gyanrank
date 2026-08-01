<?php
$homeUrl = app_url('index');
?>
<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="footer-grid">
            <div class="footer-brand-col">
                <div class="footer-text-brand">Gyan Rank</div>
                <h4>National education ranking infrastructure built for trust.</h4>
                <p>GYAN RANK is a national education ranking infrastructure focused on transparency, verification, public visibility, and disciplined operational workflows.</p>
                <div class="footer-pill-row">
                    <span>Verified Records</span>
                    <span>Rule-Based Rankings</span>
                    <span>Category Modules</span>
                </div>
            </div>
            <div>
                <h4>Company</h4>
                <a href="<?= h(app_url('about')); ?>">About Us</a>
                <a href="<?= h(app_url('schools')); ?>">Institution Directory</a>
                <a href="<?= h(app_url('contact')); ?>">Contact Us</a>
            </div>
            <div>
                <h4>Platform</h4>
                <a href="<?= h(app_url('ranking')); ?>">Institution Ranking</a>
                <a href="<?= h(app_url('leaderboard')); ?>">Leaderboards</a>
                <a href="<?= h(app_url('register-institution')); ?>">Institution Registration</a>
                <a href="<?= h(app_url('institute-login')); ?>">Institution Login</a>
            </div>
            <div>
                <h4>Legal &amp; Compliance</h4>
                <a href="<?= h(app_url('terms-and-conditions')); ?>">Terms &amp; Conditions</a>
                <a href="<?= h(app_url('privacy')); ?>">Privacy Policy</a>
                <a href="<?= h(app_url('cancellation-policy')); ?>">Cancellation Policy</a>
                <a href="<?= h(app_url('refund-policy')); ?>">Refund Policy</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= h(date('Y')); ?> GYAN RANK &bull; Government-Style Education Ranking Portal</span>
            <span>Built for School/College, Degree College, and Institute/Coaching Center modules.</span>
        </div>
    </div>
</footer>
