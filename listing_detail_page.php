<?php
session_start();
require_once 'helpers.php';

// Store current URL in session if not logged in
if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
}

if (!isset($_GET['listing_id']) || !is_numeric($_GET['listing_id'])) {
    header('Location: rooms.php');
    exit();
}

$listing_id = intval($_GET['listing_id']);
$is_logged_in = isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'seeker';
$user_id = $is_logged_in ? intval($_SESSION['user']['user_id']) : 0;

// Get listing details using helper function
$listing = getListingDetails($listing_id);
if (!$listing) {
    header('Location: rooms.php');
    exit();
}

// Get listing images using helper function
$images = getListingImages($listing_id);

// Track inquiry ONLY here (as you wanted)
if ($is_logged_in) {
    require_once 'track_inquiry.php';
    trackInquiry($listing_id, $user_id, $listing['owner_id']);
}

// Get reviews using helper function
$reviews = getListingReviews($listing_id);
$total_rating = array_sum(array_column($reviews, 'rating'));
$avg_rating = count($reviews) > 0 ? round($total_rating / count($reviews), 1) : 0;

// Get replies for reviews using helper function
$review_ids = array_column($reviews, 'review_id');
$replies = getReviewReplies($review_ids);

// Custom Recommendation Algorithm
require_once 'recommendation_algorithm.php';
$conn = getDBConnection();
$recommendations = get_recommendations($conn, $listing, 4);
closeDBConnection($conn);

include 'header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>listing_detail_page.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<section class="detail-section">
    <div class="container">
        <!-- Back navigation -->
        <div class="back-nav">
            <a href="rooms.php" class="back-btn">← Back to Rooms</a>
        </div>

        <!-- Main Content Grid -->
        <div class="detail-grid">
            <!-- Left Column: Images -->
            <div class="images-column">
                <?php if (!empty($images)): ?>
                    <div class="main-image">
                        <img id="mainListingImage" src="<?php echo htmlspecialchars($images[0]['image_path']); ?>" alt="Main listing image">
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="thumbnail-row">
                            <?php for ($i = 0; $i < count($images); $i++): ?>
                                <div class="thumbnail" onclick="document.getElementById('mainListingImage').src = this.querySelector('img').src;">
                                    <img src="<?php echo htmlspecialchars($images[$i]['image_path']); ?>" alt="Thumbnail <?php echo $i; ?>" style="cursor: pointer;">
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-image">
                        <div class="no-image-icon">📷</div>
                        <p>No images available</p>
                    </div>
                <?php endif; ?>

                <!-- Owner Contact -->
                <div class="contact-box" style="margin-top: 20px;">
                    <div class="contact-header">
                        <h3>Owner Contact Information</h3>
                        <?php if ($is_logged_in): ?>
                            <span class="verified-badge">✓ Visible to logged-in users</span>
                        <?php endif; ?>
                    </div>

                    <div class="owner-details">
                        <?php if ($is_logged_in): ?>
                            <div class="owner-profile">
                                <?php if (!empty($listing['owner_profile_image']) && file_exists($listing['owner_profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($listing['owner_profile_image']); ?>" alt="Owner avatar" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 15px;">
                                <?php else: ?>
                                    <div class="owner-avatar placeholder">
                                        <?php echo strtoupper(substr($listing['owner_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="owner-info">
                                    <h4><?php echo htmlspecialchars($listing['owner_name']); ?></h4>
                                    <div class="contact-links" style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px;">
                                        <a href="mailto:<?php echo htmlspecialchars($listing['owner_email']); ?>" class="contact-link email" style="display: inline-flex; align-items: center; gap: 8px; color: #e74c3c; text-decoration: none;">
                                            <span>📧</span>
                                            <?php echo htmlspecialchars($listing['owner_email']); ?>
                                        </a>
                                        <?php if (!empty($listing['owner_phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($listing['owner_phone']); ?>" class="contact-link phone" style="display: inline-flex; align-items: center; gap: 8px; color: #2ecc71; text-decoration: none;">
                                                <span>📱</span>
                                                <?php echo htmlspecialchars($listing['owner_phone']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="login-required">
                                <div class="blurred-contact">
                                    <div class="blurred-item" style="filter: blur(4px); opacity: 0.5;">
                                        <span class="blurred-text">█████████</span>
                                        <span class="label">Name</span>
                                    </div>
                                    <div class="blurred-item" style="filter: blur(4px); opacity: 0.5; margin-top: 10px;">
                                        <span class="blurred-text">█████████████</span>
                                        <span class="label">Email</span>
                                    </div>
                                    <div class="blurred-item" style="filter: blur(4px); opacity: 0.5; margin-top: 10px;">
                                        <span class="blurred-text">██████████</span>
                                        <span class="label">Phone</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right Column: Details -->
            <div class="details-column">
                <!-- Header -->
                <div class="listing-header">
                    <div>
                        <h1><?php echo htmlspecialchars($listing['room_type']); ?></h1>
                        <?php if ($avg_rating > 0): ?>
                            <div style="color: #f39c12; font-weight: bold; margin-bottom: 5px;">
                                ⭐ <?php echo $avg_rating; ?> / 5 (<?php echo count($reviews); ?> Reviews)
                            </div>
                        <?php endif; ?>
                        <div class="location-info">
                            <span class="city-badge"><?php echo htmlspecialchars($listing['city']); ?></span>
                            <span class="address"><?php echo htmlspecialchars($listing['address']); ?></span>
                        </div>
                    </div>
                    <div class="price-box">
                        <span class="price">Rs. <?php echo number_format($listing['price']); ?></span>
                        <span class="period">/month</span>
                    </div>
                </div>

                <!-- Description -->
                <div class="description-box">
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($listing['description'])); ?></p>
                </div>

                <!-- Additional Details -->
                <div class="details-box">
                    <h3>Additional Details</h3>
                    <div class="detail-grid-small">
                        <div class="detail-item">
                            <span class="detail-label">Listed On</span>
                            <span class="detail-value"><?php echo date('F j, Y', strtotime($listing['created_at'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Room Type</span>
                            <span class="detail-value"><?php echo htmlspecialchars($listing['room_type']); ?></span>
                        </div>
                        <?php if (!empty($listing['amenities'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Amenities</span>
                            <span class="detail-value"><?php echo htmlspecialchars($listing['amenities']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Google Maps Location Section -->
                <div class="location-map-section">
                    <!-- <h3>Location</h3>
                    <div class="map-container">
                        
                        <div class="map-placeholder" id="map-placeholder">
                            <div class="map-overlay">
                                <div class="map-icon">📍</div>
                                <div class="map-address">
                                    <strong><?php echo htmlspecialchars($listing['address']); ?></strong><br>
                                    <?php echo htmlspecialchars($listing['city']); ?>
                                </div>
                                <div class="map-note">
                                    <p>Google Maps will be integrated here</p>
                                    <small>Showing location: <?php echo htmlspecialchars($listing['city']); ?></small>
                                </div>
                            </div>
                        </div>
                        
                      
                        <input type="hidden" id="listing-address" value="<?php echo htmlspecialchars($listing['address'] . ', ' . $listing['city']); ?>">
                        <input type="hidden" id="listing-city" value="<?php echo htmlspecialchars($listing['city']); ?>">
                        
                     
                        <div class="directions-btn">
                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($listing['address'] . ', ' . $listing['city']); ?>" 
                               target="_blank" class="btn directions-btn-link">
                                🗺️ Get Directions on Google Maps
                            </a>
                        </div>
                    </div> -->
                    <!-- Google Maps Location Section -->

    <h3>Location</h3>
    <div class="map-container">
        <p style="font-size: 13px; color: #666; margin: 0 0 8px;">
            Click the map to pick the exact location. Drag the pin to adjust.
        </p>

        <div id="map" style="width: 100%; height: 340px; border-radius: 8px; border: 1px solid #ddd; z-index: 0;"></div>

        <div style="display: flex; gap: 12px; margin-top: 12px;">
            <div style="flex: 1;">
                <label style="display: block; font-size: 12px; color: #888; margin-bottom: 4px;">Latitude</label>
                <input type="text" id="latitude" name="latitude" readonly placeholder="Click map to set"
                    value="<?php echo htmlspecialchars($listing['latitude'] ?? ''); ?>"
                    style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #f9f9f9; box-sizing: border-box;" />
            </div>
            <div style="flex: 1;">
                <label style="display: block; font-size: 12px; color: #888; margin-bottom: 4px;">Longitude</label>
                <input type="text" id="longitude" name="longitude" readonly placeholder="Click map to set"
                    value="<?php echo htmlspecialchars($listing['longitude'] ?? ''); ?>"
                    style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #f9f9f9; box-sizing: border-box;" />
            </div>
        </div>

        <!-- Directions Button -->
        <div class="directions-btn" style="margin-top: 12px;">
            <a id="directions-link"
               href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($listing['address'] . ', ' . $listing['city']); ?>"
               target="_blank" class="btn directions-btn-link">
                🗺️ Get Directions on Google Maps
            </a>
        </div>
    </div>



                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if ($is_logged_in): ?>
                        <a href="report_listing.php?listing_id=<?php echo $listing_id; ?>" class="btn report-btn" style="background-color: #f8d7da; color: #721c24; padding: 10px 15px; border-radius: 5px; text-decoration: none; display: inline-block;">
                            ⚠️ Report This Listing
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn primary-btn" style="background-color: var(--brand-color); color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; display: inline-block;">
                            🔒 Login to Contact Owner
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Success message for reports and reviews -->
        <?php if (isset($_GET['reported']) && $_GET['reported'] == '1'): ?>
            <div class="success-message">
                <p>✅ Report submitted successfully. Thank you for helping us maintain quality listings.</p>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="success-message" style="background: #e6f4ea; border-left: 4px solid #1e8e3e; padding: 15px; margin-top: 15px;">
                <p style="color: #1e8e3e; margin: 0;">✅ <?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Reviews Section -->
        <div class="reviews-section" style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 30px;">
            <h2 style="margin-bottom: 20px;">Reviews & Ratings</h2>
            
            <?php if ($is_logged_in): ?>
                <div class="review-form-container" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                    <h4 style="margin-top: 0;">Leave a Review</h4>
                    <form action="submit_review.php" method="POST">
                        <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Rating (1 to 5):</label>
                            <select name="rating" required style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; width: 100px;">
                                <option value="5">⭐⭐⭐⭐⭐ 5</option>
                                <option value="4">⭐⭐⭐⭐ 4</option>
                                <option value="3">⭐⭐⭐ 3</option>
                                <option value="2">⭐⭐ 2</option>
                                <option value="1">⭐ 1</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Your Review:</label>
                            <textarea name="comment" required rows="3" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; resize: vertical;" placeholder="Tell others about your experience..."></textarea>
                        </div>
                        <button type="submit" class="btn primary-btn">Submit Review</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="reviews-list">
                <?php if (count($reviews) > 0): ?>
                    <?php foreach ($reviews as $rev): ?>
                        <div class="review-item" style="border-bottom: 1px solid #eee; padding: 15px 0; display: flex; gap: 15px;">
                            <div class="reviewer-avatar">
                                <?php if (!empty($rev['reviewer_image']) && file_exists($rev['reviewer_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($rev['reviewer_image']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #555;">
                                        <?php echo strtoupper(substr($rev['reviewer_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="review-content" style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <strong><?php echo htmlspecialchars($rev['reviewer_name']); ?></strong>
                                    <small style="color: #999;"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></small>
                                </div>
                                <div style="color: #f39c12; font-size: 14px; margin: 5px 0;">
                                    <?php echo str_repeat('⭐', $rev['rating']); ?>
                                </div>
                                <p style="margin: 0; color: #444; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                                
                                <!-- Display Existing Replies -->
                                <?php if (isset($replies[$rev['review_id']])): ?>
                                    <div style="margin-top: 15px; padding-left: 15px; border-left: 2px solid #ddd;">
                                        <?php foreach ($replies[$rev['review_id']] as $reply): ?>
                                            <div style="margin-top: 10px; padding: 10px; background: <?php echo $reply['user_role'] === 'owner' ? '#effbf0' : '#f9f9f9'; ?>; border-radius: 4px; font-size: 14px;">
                                                <div style="font-size: 12px; color: #888; margin-bottom: 5px;">
                                                    <strong><?php echo htmlspecialchars($reply['user_name']); ?></strong>
                                                    <?php if($reply['user_role'] === 'owner'): ?><span style="background: var(--brand-color); color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: 5px;">Owner</span><?php endif; ?>
                                                    <span style="float: right;"><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></span>
                                                </div>
                                                <div style="color: #333;">
                                                    <?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Reply Form for logged in users -->
                                <?php if (isset($_SESSION['user'])): ?>
                                    <form action="submit_reply.php" method="POST" style="margin-top: 15px; display: flex; gap: 10px;">
                                        <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                        <input type="hidden" name="return_url" value="listing_detail_page.php?listing_id=<?php echo $listing_id; ?>">
                                        <input type="text" name="reply_text" placeholder="Write a reply..." required style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                        <button type="submit" style="padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">Reply</button>
                                    </form>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; font-style: italic;">No reviews yet. Be the first to review this room!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recommendations Section -->
        <?php if (!empty($recommendations)): ?>
        <div class="recommendations-section" style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 30px;">
            <h2 style="margin-bottom: 20px;">Rooms You May Like</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                <?php foreach ($recommendations as $rec): ?>
                    <a href="listing_detail_page.php?listing_id=<?php echo $rec['listing_id']; ?>"
                       style="text-decoration: none; color: inherit; border: 1px solid #ddd; border-radius: 8px;
                              overflow: hidden; display: block; transition: transform 0.2s, box-shadow 0.2s;
                              position: relative;"
                       onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)';"
                       onmouseout="this.style.transform=''; this.style.boxShadow='';">

                        <!-- Top-rated badge -->
                        <?php if (floatval($rec['avg_rating']) >= 4.0): ?>
                            <div style="position: absolute; top: 10px; left: 10px; background: #f39c12;
                                        color: white; font-size: 11px; font-weight: bold;
                                        padding: 3px 8px; border-radius: 12px; z-index: 1;">
                                ⭐ Top Rated
                            </div>
                        <?php endif; ?>

                        <div style="height: 160px; background: #f0f0f0;">
                            <?php if (!empty($rec['main_image'])): ?>
                                <img src="<?php echo htmlspecialchars($rec['main_image']); ?>"
                                     style="width: 100%; height: 100%; object-fit: cover;" alt="Room">
                            <?php else: ?>
                                <div style="display:flex; align-items:center; justify-content:center;
                                            height:100%; color:#aaa;">No Image</div>
                            <?php endif; ?>
                        </div>

                        <div style="padding: 14px;">
                            <h4 style="margin: 0 0 4px 0; color: #333; font-size: 15px;">
                                <?php echo htmlspecialchars($rec['room_type']); ?>
                            </h4>
                            <p style="margin: 0 0 6px 0; color: #666; font-size: 13px;">
                                <?php echo htmlspecialchars($rec['city']); ?>
                            </p>

                            <!-- Rating display -->
                            <?php if (intval($rec['review_count']) > 0): ?>
                                <p style="margin: 0 0 6px 0; color: #f39c12; font-size: 13px; font-weight: bold;">
                                    ⭐ <?php echo number_format(floatval($rec['avg_rating']), 1); ?>
                                    <span style="color: #999; font-weight: normal;">
                                        (<?php echo intval($rec['review_count']); ?> review<?php echo $rec['review_count'] > 1 ? 's' : ''; ?>)
                                    </span>
                                </p>
                            <?php else: ?>
                                <p style="margin: 0 0 6px 0; color: #bbb; font-size: 12px;">No reviews yet</p>
                            <?php endif; ?>

                            <p style="margin: 0; font-weight: bold; color: #28a745; font-size: 14px;">
                                Rs. <?php echo number_format($rec['price']); ?>/month
                            </p>

                            <!-- Relevance score (subtle) -->
                            <p style="margin: 6px 0 0 0; font-size: 11px; color: #ccc; text-align: right;">
                                Match: <?php echo $rec['relevance_score']; ?>%
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    const existingLat = <?php echo json_encode($listing['latitude'] ?? null); ?>;
    const existingLng = <?php echo json_encode($listing['longitude'] ?? null); ?>;

    const defaultLat = existingLat ?? 27.7172;
    const defaultLng = existingLng ?? 85.3240;

    const map = L.map('map').setView([defaultLat, defaultLng], existingLat ? 15 : 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    let marker = null;

    if (existingLat && existingLng) {
        placePin(existingLat, existingLng);
    }

    map.on('click', function(e) {
        placePin(e.latlng.lat, e.latlng.lng);
    });

    function placePin(lat, lng) {
        const latR = parseFloat(parseFloat(lat).toFixed(6));
        const lngR = parseFloat(parseFloat(lng).toFixed(6));

        document.getElementById('latitude').value  = latR;
        document.getElementById('longitude').value = lngR;

        const directionsLink = document.getElementById('directions-link');
        if (directionsLink) {
            directionsLink.href = `https://www.google.com/maps/search/?api=1&query=${latR},${lngR}`;
        }

        if (marker) {
            marker.setLatLng([latR, lngR]);
        } else {
            marker = L.marker([latR, lngR], { draggable: true }).addTo(map);
            marker.on('dragend', function(e) {
                const pos = e.target.getLatLng();
                placePin(pos.lat, pos.lng);
            });
        }
    }
})();
</script>

<?php include 'footer.php'; ?>