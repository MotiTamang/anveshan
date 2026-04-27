<?php
// Include the header at the top
include 'header.php';

if (isset($_SESSION['redirect_after_login'])) {
    unset($_SESSION['redirect_after_login']);
}
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1>Find Your Perfect Room in <span class="anveshan-span">Kathmandu Valley</span></h1>
            <p class="hero-subtitle">A simple, fast, and free room finder for students and working professionals. Available in Kathmandu, Lalitpur, and Bhaktapur.</p>
            <div class="hero-buttons">
                <a class="find-btn primary" href="rooms.php">Search Rooms</a>
                <a class="find-btn secondary" href="register.php">List Your Room</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="web_image/home1.jpeg" alt="Student accommodation" class="hero-img">
        </div>
    </div>
</section>

<!-- Cities Section -->
<section class="cities-section">
    <div class="container">
        <h2 class="section-title">Available Cities</h2>
        <div class="cities-grid">
            <div class="city-card">
                <h3>Kathmandu</h3>
                <p>Find rooms and flats in Kathmandu</p>
            </div>
            <div class="city-card">
                <h3>Lalitpur</h3>
                <p>Browse available accommodations in Lalitpur</p>
            </div>
            <div class="city-card">
                <h3>Bhaktapur</h3>
                <p>Discover rooms and flats in Bhaktapur</p>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <h2 class="section-title">Why Use Anveshan?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <h3>Simple Search</h3>
                <p>Quickly find rooms by location, price, and amenities. No complicated filters.</p>
            </div>
            <div class="feature-card">
                <h3>Free to Use</h3>
                <p>Completely free for students and seekers. No hidden charges or fees.</p>
            </div>
            <div class="feature-card">
                <h3>Direct Contact</h3>
                <p>Contact room owners directly. Fast and transparent communication.</p>
            </div>
        </div>
    </div>
</section>

<!-- Quick Links Section -->
<section class="quick-links-section">
    <div class="container">
        <div class="quick-links-content">
            <h2>Ready to Find Your Room?</h2>
            <p>Start searching now or register to list your room for free.</p>
            <div class="quick-links-buttons">
                <a class="quick-btn primary" href="rooms.php">Browse Rooms</a>
                <a class="quick-btn secondary" href="register.php">Register Now</a>
            </div>
        </div>
    </div>
</section>

<?php
// Include the footer at the bottom
include 'footer.php';
?>