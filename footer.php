<?php
// Footer for Anveshan — quick update: Quick Links are vertical beside Contact Us, and logo size increased.
// Configurable variables (set before including this file):
// - $base_url (defaults to '/')
// - $contact_phone (defaults to a placeholder)
// - $contact_email (defaults to a placeholder)
// - $logo_url (optional override for logo image)
if (!isset($base_url)) {
    $base_url = '/';
}
if (!isset($contact_phone)) {
    $contact_phone = '+977-01-1234567';
}
if (!isset($contact_email)) {
    $contact_email = 'info@anveshan.example';
}
if (!isset($logo_url)) {
    $logo_url = $base_url . 'web_image/logo.png';
}

$contact_tel = preg_replace('/[^0-9+]/', '', $contact_phone);
?>
</main> <!-- This closes the <main> tag started in header.php -->
<footer>
    <div class="footer-container">
        <!-- Brand column: logo + name + descriptive text -->
        <div class="footer-brand">
            <a class="brand-link" href="<?php echo $base_url; ?>index.php">
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Anveshan logo" class="footer-logo-img" />
                <span class="footer-logo-text">Anveshan</span>
            </a>
            <p class="footer-description">
                Anveshan is a room-finder system in Nepal operating across the three major cities:
                Kathmandu, Lalitpur, and Bhaktapur.
            </p>
        </div>

        <!-- Contact & quick links column (Contact and Quick Links side-by-side; Quick Links vertical) -->
        <div class="footer-contact-quick">
            <div class="contact-and-links">
                <div class="footer-contact" aria-label="Contact information">
                    <h4 class="footer-contact-title">Contact Us</h4>

                    <div class="contact-vertical">
                        <a class="contact-line" href="tel:<?php echo $contact_tel; ?>" aria-label="Call us">
                            <svg class="icon phone-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M22 16.92V21a1 1 0 0 1-1.11 1A19 19 0 0 1 3 5.11 1 1 0 0 1 4 4h4.09a1 1 0 0 1 1 .75c.12.66.38 1.3.75 1.88a1 1 0 0 1-.22 1.09L8.91 9.91a14 14 0 0 0 6.18 6.18l1.18-1.18a1 1 0 0 1 1.09-.22c.58.37 1.22.63 1.88.75a1 1 0 0 1 .75 1V22z"></path>
                            </svg>
                            <span class="contact-text"><?php echo htmlspecialchars($contact_phone); ?></span>
                        </a>

                        <a class="contact-line" href="mailto:<?php echo htmlspecialchars($contact_email); ?>" aria-label="Email us">
                            <svg class="icon mail-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="3 8 12 13 21 8"></polyline>
                                <rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect>
                            </svg>
                            <span class="contact-text"><?php echo htmlspecialchars($contact_email); ?></span>
                        </a>
                    </div>
                </div>

                <div class="footer-quicklinks" aria-label="Quick links">
                    <h4 class="footer-quick-title">Quick Links</h4>
                    <nav class="quick-nav" aria-label="Footer quick links">
                        <a href="<?php echo $base_url; ?>about_us.php" class="quick-link">About Us</a>
                        <a href="<?php echo $base_url; ?>contact.php" class="quick-link">Contact Us</a>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Spacer or future column (kept for layout balance) -->
        <div class="footer-spacer" aria-hidden="true"></div>
    </div>

    <!-- Footer bottom: horizontal copyright bar -->
    <div class="footer-bottom">
        <div class="footer-bottom-inner">
            <span>&copy; <?php echo date('Y'); ?> Anveshan. All rights reserved.</span>
        </div>
    </div>
</footer>

</body>
</html>