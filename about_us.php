<?php
include 'header.php';

if (!isset($base_url)) {
    $base_url = '/';
}

if (!isset($hero_image)) {
    $hero_image = $base_url . 'web_image/about_us.jpg';
}
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>about_us.css">

<!-- HERO SECTION (unchanged) -->
<section class="about-us" style="background-image: url('<?php echo htmlspecialchars($hero_image); ?>')">
  <div class="content">
    <h1>About Anveshan</h1>
    <p class="p_class">
      Anveshan is a reliable platform built to make room searching easier, safer, and more transparent across Nepal.
    </p>
  </div>
</section>

<!-- NEW MISSION SECTION -->
<section class="mission-section">
    <div class="container">
        <h2>Our Mission</h2>
        <p>
            At Anveshan, our mission is to make finding a room in Kathmandu, Lalitpur, and Bhaktapur as simple as a click.
            We use  algorithms to <b>recommend</b> the perfect match for your lifestyle and <b>validation</b> to ensure every listing is real. 
            With a community-driven <b> review and rating system</b>, we’re not just finding you a room—we’re building a more reliable way to come home."
        </p>
    </div>
</section>

<!-- NEW OFFER SECTION -->
<section class="offer-section">
    <div class="container">
        <h2>What We Offer</h2>

        <div class="offer-grid">
            <div class="offer-card">
                <h3>Verified Listings</h3>
                <p>
                    Transparent details with no confusing information, helping seekers trust what they see.
                </p>
            </div>

            <div class="offer-card">
                <h3>Smart Search System</h3>
                <p>
                    Our algorithms help users find rooms based on budget, location, and type — faster than traditional methods.
                </p>
            </div>

            <div class="offer-card">
                <h3>Secure Access Control</h3>
                <p>
                    Seekers must log in to view contact details, while owners must log in to post listings, ensuring safety.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- NEW TEAM SECTION -->
<section class="team-section">
    <div class="container">
        <h2>Who We Are</h2>
        <p>
            Anveshan is developed by a dedicated team of computer science students, passionate about building digital
            solutions that solve real-life problems.  
            We aim to bring simplicity, trust, and convenience to the rental experience across Nepal.
        </p>
    </div>
</section>

<!-- NEW CTA SECTION -->
<section class="cta-section">
    <div class="container cta-flex">
        <div class="cta-text">
            <h2>Start Your Search Today</h2>
            <p>Your perfect room is just a few clicks away.</p>
        </div>

        <div class="cta-btns">
            <a class="btn primary" href="rooms.php">Find Rooms</a>
            <a class="btn secondary" href="register.php">List Your Room</a>
        </div>
    </div>
</section>

<?php
include 'footer.php';
?>
