<?php
// rooms.php - Display available room listings with sidebar filters + live search

session_start();
require_once 'helpers.php';
include 'header.php';

// Check login (seeker only)
$is_logged_in = isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'seeker';

// Get all filter parameters
$city_filter  = $_GET['city'] ?? '';
$room_type    = $_GET['room_type'] ?? '';
$min_price    = $_GET['min_price'] ?? '';
$max_price    = $_GET['max_price'] ?? '';
$amenities    = $_GET['amenities'] ?? '';
$search_query = $_GET['search'] ?? '';

// Get listings using helper function
$listings = getListingsWithFilters(
    $city_filter,
    $room_type,
    $min_price,
    $max_price,
    $amenities,
    $search_query
);

// Get all distinct cities for filter dropdown
$conn = getDBConnection();
$cities_query = "SELECT DISTINCT city FROM listings ORDER BY city";
$cities_result = $conn->query($cities_query);
$cities = $cities_result->fetch_all(MYSQLI_ASSOC);
closeDBConnection($conn);
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>rooms.css">

<section class="rooms-section">
    <div class="container rooms-layout">

        <!-- SIDEBAR FILTERS -->
        <aside class="filter-sidebar">
            <form method="get" id="filterForm">
                <h3>Filters</h3>

                <div class="form-group">
                    <label>City</label>
                    <select name="city" id="cityFilter" class="form-select">
                        <option value="">All Cities</option>
                        <option value="Kathmandu" <?= $city_filter=='Kathmandu'?'selected':'' ?>>Kathmandu</option>
                        <option value="Lalitpur" <?= $city_filter=='Lalitpur'?'selected':'' ?>>Lalitpur</option>
                        <option value="Bhaktapur" <?= $city_filter=='Bhaktapur'?'selected':'' ?>>Bhaktapur</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Room Type</label>
                    <select name="room_type" id="roomTypeFilter" class="form-select">
                        <option value="">All Types</option>
                        <option value="Single" <?= $room_type=='Single'?'selected':'' ?>>Single</option>
                        <option value="Shared" <?= $room_type=='Shared'?'selected':'' ?>>Shared</option>
                        <option value="1 BHK" <?= $room_type=='1 BHK'?'selected':'' ?>>1 BHK</option>
                        <option value="2 BHK" <?= $room_type=='2 BHK'?'selected':'' ?>>2 BHK</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Price Range (Rs.)</label>
                    <div class="price-range">
                        <input type="text" 
                               name="min_price" 
                               id="minPrice" 
                               class="price-input"
                               value="<?php echo htmlspecialchars($min_price); ?>"
                               placeholder="Min">
                        <span class="price-separator">to</span>
                        <input type="text" 
                               name="max_price" 
                               id="maxPrice" 
                               class="price-input"
                               value="<?php echo htmlspecialchars($max_price); ?>"
                               placeholder="Max">
                    </div>
                </div>

                <div class="form-group">
                    <label>Amenities</label>
                    <select name="amenities" id="amenitiesFilter" class="form-select">
                        <option value="">Any Amenities</option>
                        <option value="Parking" <?= $amenities=='Parking'?'selected':'' ?>>Parking Available</option>
                        <option value="Wifi" <?= $amenities=='Wifi'?'selected':'' ?>>Wifi</option>
                        <option value="Pets" <?= $amenities=='Pets'?'selected':'' ?>>Pets Allowed</option>
                    </select>
                </div>

                <div class="filter-buttons">
                    <button type="button" class="filter-btn apply-btn" onclick="fetchRooms()">Apply Filters</button>
                    <button type="button" class="filter-btn reset-btn" onclick="resetFilters()">Clear All</button>
                </div>
            </form>
        </aside>

        <!-- MAIN LISTINGS AREA -->
        <div class="listings-main">
            <div class="rooms-header">
                <h1>Available Rooms</h1>
                <div class="results-count">
                    Found <?php echo count($listings); ?> rooms
                </div>
            </div>

            <!-- Listings Grid (will be updated via AJAX) -->
            <div id="listingsGrid" class="listings-grid">
                <?php if (empty($listings)): ?>
                    <div class="no-listings">
                        <p>No rooms found. Try adjusting your filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($listings as $listing): ?>
                        <div class="listing-card">
                            <!-- Three dots menu at top right -->
                            <div class="listing-menu">
                                <button class="menu-dots">⋮</button>
                                <div class="menu-dropdown">
                                    <a href="report_listing.php?listing_id=<?php echo $listing['listing_id']; ?>" class="report-option">
                                        Report Listing
                                    </a>
                                </div>
                            </div>

                            <div class="listing-image">
                                <?php if ($listing['main_image']): ?>
                                    <img src="<?php echo htmlspecialchars($listing['main_image']); ?>">
                                <?php else: ?>
                                    <div class="no-image">No Image</div>
                                <?php endif; ?>
                            </div>

                            <div class="listing-content">
                                <div class="listing-header">
                                    <h3><?php echo htmlspecialchars($listing['room_type']); ?></h3>
                                    <span class="city-badge"><?php echo htmlspecialchars($listing['city']); ?></span>
                                </div>
                                
                                <?php if ($listing['review_count'] > 0): ?>
                                    <p style="margin: 5px 0; color: #f39c12; font-weight: bold; font-size: 14px;">
                                        ⭐ <?php echo round($listing['avg_rating'], 1); ?> (<?php echo $listing['review_count']; ?>)
                                    </p>
                                <?php endif; ?>

                                <p class="listing-address"><?php echo htmlspecialchars($listing['address']); ?></p>
                                <p class="listing-price">Rs. <?php echo number_format($listing['price']); ?>/month</p>

                                <p class="listing-description">
                                    <?php echo htmlspecialchars(substr($listing['description'], 0, 100)); ?>...
                                </p>

                                <!-- Owner Contact Info (only for logged-in seekers) -->
                                <?php if ($is_logged_in): ?>
                                    <div class="owner-info">
                                        <div class="owner-detail">
                                            <span class="owner-label">Owner:</span>
                                            <span class="owner-value"><?php echo htmlspecialchars($listing['owner_name']); ?></span>
                                        </div>
                                        <div class="owner-detail">
                                            <span class="owner-label">Email:</span>
                                            <span class="owner-value"><?php echo htmlspecialchars($listing['owner_email']); ?></span>
                                        </div>
                                        <div class="owner-detail">
                                            <span class="owner-label">Phone:</span>
                                            <span class="owner-value"><?php echo htmlspecialchars($listing['owner_phone']); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="listing-footer">
                                    <a href="listing_detail_page.php?listing_id=<?php echo $listing['listing_id']; ?>" class="view-details-btn">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<script>
// Get filter elements
const cityFilter = document.getElementById('cityFilter');
const roomTypeFilter = document.getElementById('roomTypeFilter');
const minPrice = document.getElementById('minPrice');
const maxPrice = document.getElementById('maxPrice');
const amenitiesFilter = document.getElementById('amenitiesFilter');
const listingsGrid = document.getElementById('listingsGrid');
const resultsCount = document.querySelector('.results-count');

// Function to fetch rooms with current filters
function fetchRooms() {
    // Build query string from all filters
    const params = new URLSearchParams();
    
    if (cityFilter.value) params.append('city', cityFilter.value);
    if (roomTypeFilter.value) params.append('room_type', roomTypeFilter.value);
    if (minPrice.value) params.append('min_price', minPrice.value);
    if (maxPrice.value) params.append('max_price', maxPrice.value);
    if (amenitiesFilter.value) params.append('amenities', amenitiesFilter.value);
    
    const globalSearch = document.getElementById('nav-search');
    if (globalSearch && globalSearch.value) {
        params.append('search', globalSearch.value);
    }
    
    // Show loading
    listingsGrid.innerHTML = '<div class="loading">Loading rooms...</div>';
    resultsCount.textContent = 'Loading...';
    
    // Fetch from server
    fetch(`search_rooms.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            updateListings(data);
        })
        .catch(error => {
            console.error('Error:', error);
            listingsGrid.innerHTML = '<div class="error">Error loading rooms. Please try again.</div>';
            resultsCount.textContent = 'Error loading results';
        });
}

// Function to update listings display
function updateListings(listings) {
    listingsGrid.innerHTML = '';
    
    if (!listings || listings.length === 0) {
        listingsGrid.innerHTML = `
            <div class="no-listings">
                <p>No rooms found. Try adjusting your filters.</p>
            </div>
        `;
        resultsCount.textContent = 'Found 0 rooms';
        return;
    }
    
    // Update results count
    resultsCount.textContent = `Found ${listings.length} rooms`;
    
    // Build HTML for each listing
    listings.forEach(listing => {
        const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        
        let ownerInfoHtml = '';
        if (isLoggedIn) {
            ownerInfoHtml = `
                <div class="owner-info">
                    <div class="owner-detail">
                        <span class="owner-label">Owner:</span>
                        <span class="owner-value">${escapeHtml(listing.owner_name)}</span>
                    </div>
                    <div class="owner-detail">
                        <span class="owner-label">Email:</span>
                        <span class="owner-value">${escapeHtml(listing.owner_email)}</span>
                    </div>
                    <div class="owner-detail">
                        <span class="owner-label">Phone:</span>
                        <span class="owner-value">${escapeHtml(listing.owner_phone)}</span>
                    </div>
                </div>
            `;
        }
        
        listingsGrid.innerHTML += `
            <div class="listing-card">
                <!-- Three dots menu at top right -->
                <div class="listing-menu">
                    <button class="menu-dots" onclick="toggleMenu(this)">⋮</button>
                    <div class="menu-dropdown">
                        <a href="report_listing.php?listing_id=${listing.listing_id}" class="report-option">
                            Report Listing
                        </a>
                    </div>
                </div>

                <div class="listing-image">
                    ${listing.main_image 
                        ? `<img src="${escapeHtml(listing.main_image)}" alt="Room image">`
                        : `<div class="no-image">No Image</div>`}
                </div>

                <div class="listing-content">
                    <div class="listing-header">
                        <h3>${escapeHtml(listing.room_type)}</h3>
                        <span class="city-badge">${escapeHtml(listing.city)}</span>
                    </div>
                    
                    ${listing.review_count > 0 ? `
                        <p style="margin: 5px 0; color: #f39c12; font-weight: bold; font-size: 14px;">
                            ⭐ ${Number(listing.avg_rating).toFixed(1)} (${listing.review_count})
                        </p>
                    ` : ''}

                    <p class="listing-address">${escapeHtml(listing.address)}</p>
                    <p class="listing-price">Rs. ${Number(listing.price).toLocaleString()}/month</p>

                    <p class="listing-description">
                        ${escapeHtml(listing.description.substring(0, 100))}...
                    </p>

                    ${ownerInfoHtml}

                    <div class="listing-footer">
                        <a href="listing_detail_page.php?listing_id=${listing.listing_id}" class="view-details-btn">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        `;
    });
    
    // Setup menu listeners for newly added cards
    setupMenuListeners();
}

// Function to setup menu listeners
function setupMenuListeners() {
    const menuDots = document.querySelectorAll('.menu-dots');
    
    menuDots.forEach(dot => {
        // Remove any existing listeners
        dot.replaceWith(dot.cloneNode(true));
    });
    
    // Get fresh references
    const freshMenuDots = document.querySelectorAll('.menu-dots');
    
    freshMenuDots.forEach(dot => {
        dot.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.nextElementSibling;
            
            // Close all other dropdowns first
            document.querySelectorAll('.menu-dropdown').forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('show');
        });
    });
}

// Function to toggle menu (for onclick attribute)
function toggleMenu(button) {
    const dropdown = button.nextElementSibling;
    
    // Close all other dropdowns first
    document.querySelectorAll('.menu-dropdown').forEach(d => {
        if (d !== dropdown) {
            d.classList.remove('show');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('show');
    
    // Stop event from bubbling up
    event.stopPropagation();
}

// Close dropdowns when clicking outside
document.addEventListener('click', function() {
    document.querySelectorAll('.menu-dropdown').forEach(dropdown => {
        dropdown.classList.remove('show');
    });
});

// Function to reset all filters
function resetFilters() {
    cityFilter.value = '';
    roomTypeFilter.value = '';
    minPrice.value = '';
    maxPrice.value = '';
    amenitiesFilter.value = '';
    
    // Fetch with empty filters
    fetchRooms();
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Remove spinner/arrows from number inputs
minPrice.addEventListener('wheel', function(e) {
    e.preventDefault();
});
maxPrice.addEventListener('wheel', function(e) {
    e.preventDefault();
});

// Event listeners for auto-filtering removed. Filters now only apply on 'Apply Filters' click.

// Initial load with any existing filters
window.addEventListener('load', function() {
    // If there are any filter values, use AJAX to load
    if (cityFilter.value || roomTypeFilter.value || minPrice.value || maxPrice.value || amenitiesFilter.value) {
        fetchRooms();
    }
    
    // Setup menu listeners for initial page load
    setupMenuListeners();
});
</script>

<?php include 'footer.php'; ?>