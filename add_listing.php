<?php
// add_listing.php
// Add new listing page for owners
// Only owners can access

session_start();

// Require login and owner role (safe check)
if (
    !isset($_SESSION['user']) ||
    !is_array($_SESSION['user']) ||
    ($_SESSION['user']['role'] ?? '') !== 'owner'
) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$owner_id = (int) ($user['user_id'] ?? 0);


// Include header
include 'header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>add_listing.css">

<section class="add-listing-section">
    <div class="container">
        <h1>Add New Listing</h1>
        
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="flash-message <?php echo strpos($_SESSION['flash'], 'Error') !== false ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
            </div>
        <?php endif; ?>

        <form class="listing-form" action="add_listing_process.php" method="post" enctype="multipart/form-data">
            <label for="city">City *</label>
            <select id="city" name="city" required>
                <option value="">Select City</option>
                <option value="Kathmandu">Kathmandu</option>
                <option value="Lalitpur">Lalitpur</option>
                <option value="Bhaktapur">Bhaktapur</option>
            </select>

            <label for="address">Full Address *</label>
            <textarea id="address" name="address" rows="3" placeholder="Street, building, apartment number, area..." required></textarea>

            <label for="room_type">Room Type *</label>
            <input id="room_type" name="room_type" type="text" placeholder="e.g. 1BHK, 2BHK, Single Room, Shared Room" required>

            <label for="price">Monthly Rent (Rs., whole number only) *</label>
            <input id="price" name="price" type="number" min="2000" max="100000" step="1" placeholder="e.g. 8500" required inputmode="numeric" pattern="[0-9]*" title="Digits only, no paise">

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="6" placeholder="Describe the property, amenities, rules, nearby facilities..."></textarea>

            <label for="latitude">Latitude *</label>
            <input id="latitude" name="latitude" type="number" step="0.00000001" placeholder="Click map to set" readonly required>

            <label for="longitude">Longitude *</label>
            <input id="longitude" name="longitude" type="number" step="0.00000001" placeholder="Click map to set" readonly required>

            <!-- Map Container -->
            <div class="map-section">
                <h3>Select Location on Map *</h3>
                <div class="map-controls">
                    <input type="text" id="mapSearch" placeholder="Search for a location..." style="padding: 8px; margin-bottom: 10px; width: 100%; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="button" id="useCurrentLocation" style="padding: 8px 16px; margin-bottom: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Use Current Location</button>
                </div>
                <div id="listingMap" style="width: 100%; height: 400px; border-radius: 8px; border: 1px solid #ddd; margin: 20px 0;"></div>
                <small style="color: #666; font-size: 12px;">Click on the map to set the exact location. The pin must be within the selected city boundaries.</small>
            </div>

            <label for="images">Images (Required - Upload at least one image) *</label>
            <input id="images" name="images[]" type="file" accept="image/*" multiple required>
            <small class="help-text">You can select multiple images. Supported formats: JPG, PNG, GIF</small>

            <div class="form-actions">
                <button type="submit" class="btn save">Save Listing</button>
                <a class="btn cancel" href="owner_dashboard.php">Cancel</a>
            </div>
        </form>
    </div>
</section>

<!-- Leaflet Map CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Leaflet Map JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Map initialization and functionality
let map;
let currentMarker = null;
const cityPolygons = {
    'Kathmandu': [
        [27.735, 85.255], [27.785, 85.295], [27.780, 85.350],
        [27.745, 85.372], [27.700, 85.360], [27.675, 85.315],
        [27.690, 85.270]
    ],
    'Lalitpur': [
        [27.700, 85.300], [27.705, 85.345], [27.690, 85.380],
        [27.655, 85.388], [27.620, 85.365], [27.618, 85.320],
        [27.640, 85.290], [27.675, 85.285]
    ],
    'Bhaktapur': [
        [27.720, 85.350], [27.725, 85.405], [27.710, 85.470],
        [27.680, 85.495], [27.640, 85.485], [27.625, 85.430],
        [27.635, 85.370], [27.670, 85.350]
    ]
};

function pointInPolygon(lat, lng, polygon) {
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const yi = polygon[i][0], xi = polygon[i][1];
        const yj = polygon[j][0], xj = polygon[j][1];
        const intersect = ((yi > lat) !== (yj > lat)) &&
            (lng < ((xj - xi) * (lat - yi)) / ((yj - yi) || 1e-12) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

function latLngInsideCity(lat, lng, city) {
    const polygon = cityPolygons[city];
    if (!polygon) return false;
    return pointInPolygon(lat, lng, polygon);
}

function latLngInsideSupportedArea(lat, lng) {
    return latLngInsideCity(lat, lng, 'Kathmandu') ||
           latLngInsideCity(lat, lng, 'Lalitpur') ||
           latLngInsideCity(lat, lng, 'Bhaktapur');
}

function addressHasOutOfCityKeyword(address, city) {
    const forbiddenByCity = {
        Kathmandu: ['lalitpur', 'patan', 'bhaktapur', 'thimi', 'sano thimi', 'madhyapur', 'suryabinayak', 'kausaltar', 'jagati'],
        Lalitpur: ['kathmandu', 'balaju', 'kalanki', 'swoyambhu', 'maharajgunj', 'bhaktapur', 'thimi', 'sano thimi', 'madhyapur', 'suryabinayak'],
        Bhaktapur: ['kathmandu', 'balaju', 'kalanki', 'swoyambhu', 'maharajgunj', 'lalitpur', 'patan', 'jawalakhel', 'pulchowk', 'kupondole']
    };
    const norm = (address || '').toLowerCase().replace(/[-_,]/g, ' ');
    const keywords = forbiddenByCity[city] || [];
    for (const k of keywords) {
        if (norm.includes(k)) return k;
    }
    return null;
}

// Initialize map
function initMap() {
    // Default to Kathmandu center
    map = L.map('listingMap').setView([27.7172, 85.3240], 13);
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add click handler
    map.on('click', function(e) {
        setMarker(e.latlng);
    });
}

// Set marker and update form fields
function setMarker(latlng) {
    const city = document.getElementById('city').value;
    const cityPolygon = cityPolygons[city];
    
    if (!cityPolygon) {
        alert('Please select a city first');
        return;
    }
    
    // Check if click is within city boundaries
    const withinBounds = latLngInsideCity(latlng.lat, latlng.lng, city);
    
    if (!withinBounds) {
        alert('Location must be within ' + city + ' city boundaries');
        document.getElementById('latitude').value = '';
        document.getElementById('longitude').value = '';
        if (currentMarker) {
            map.removeLayer(currentMarker);
            currentMarker = null;
        }
        return;
    }
    
    // Remove existing marker
    if (currentMarker) {
        map.removeLayer(currentMarker);
    }
    
    // Add new marker
    currentMarker = L.marker([latlng.lat, latlng.lng]).addTo(map);
    
    // Update form fields
    document.getElementById('latitude').value = latlng.lat.toFixed(8);
    document.getElementById('longitude').value = latlng.lng.toFixed(8);
}

// City change handler
function onCityChange() {
    const city = document.getElementById('city').value;
    const cityPolygon = cityPolygons[city];
    
    if (cityPolygon) {
        const lats = cityPolygon.map(p => p[0]);
        const lngs = cityPolygon.map(p => p[1]);
        // Center map on selected city
        const centerLat = (Math.min(...lats) + Math.max(...lats)) / 2;
        const centerLng = (Math.min(...lngs) + Math.max(...lngs)) / 2;
        map.setView([centerLat, centerLng], 13);
        
        // Clear existing marker
        if (currentMarker) {
            map.removeLayer(currentMarker);
            currentMarker = null;
        }
        
        // Clear form fields
        document.getElementById('latitude').value = '';
        document.getElementById('longitude').value = '';
    }
}

// Search functionality
function searchLocation() {
    const query = document.getElementById('mapSearch').value;
    if (!query) return;
    
    // Using Nominatim API for geocoding
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ', Nepal')}&limit=1`)
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                const result = data[0];
                const lat = parseFloat(result.lat);
                const lng = parseFloat(result.lon);
                const city = document.getElementById('city').value;

                if (!city) {
                    alert('Please select a city first.');
                    return;
                }
                if (!latLngInsideSupportedArea(lat, lng)) {
                    alert('Search is limited to Kathmandu, Lalitpur, and Bhaktapur only.');
                    return;
                }
                if (!latLngInsideCity(lat, lng, city)) {
                    alert('Search result is outside ' + city + '. Try a location inside the selected city.');
                    return;
                }
                
                map.setView([lat, lng], 15);
                setMarker({lat: lat, lng: lng});
            } else {
                alert('Location not found. Please try a different search term.');
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            alert('Search failed. Please try again.');
        });
}

// Current location functionality
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const city = document.getElementById('city').value;
                if (!city) {
                    alert('Please select a city first.');
                    return;
                }
                if (!latLngInsideSupportedArea(lat, lng)) {
                    alert('Your current location is outside supported area. Use Kathmandu, Lalitpur, or Bhaktapur only.');
                    return;
                }
                if (!latLngInsideCity(lat, lng, city)) {
                    alert('Your current location is outside ' + city + '. Please click inside the selected city.');
                    return;
                }
                
                map.setView([lat, lng], 15);
                setMarker({lat: lat, lng: lng});
            },
            function(error) {
                alert('Unable to get your current location. Please check your browser settings.');
            }
        );
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

// Form validation
function validateForm() {
    const city = document.getElementById('city').value;
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    const price = document.getElementById('price').value;
    const address = document.getElementById('address').value;
    const roomType = document.getElementById('room_type').value;
    const images = document.getElementById('images').files;
    
    let errors = [];
    
    // Required fields
    if (!city) errors.push('City is required');
    if (!address) errors.push('Address is required');
    if (!roomType) errors.push('Room type is required');
    if (!price) errors.push('Price is required');
    
    // Price validation
    const priceNum = parseInt(price);
    if (isNaN(priceNum) || priceNum < 2000 || priceNum > 100000) {
        errors.push('Price must be between Rs. 2,000 and Rs. 100,000');
    }
    
    // Location validation
    if (city && (!lat || !lng)) {
        errors.push('Please select a location on the map');
    } else if (city && lat && lng && !latLngInsideCity(parseFloat(lat), parseFloat(lng), city)) {
        errors.push('Map pin must be inside the selected city boundary');
    }

    const keyword = addressHasOutOfCityKeyword(address, city);
    if (city && keyword) {
        errors.push(`Address contains out-of-city keyword "${keyword}" for selected city ${city}`);
    }
    
    // Image validation
    if (!images || images.length === 0) {
        errors.push('At least one image is required');
    }
    
    if (errors.length > 0) {
        alert('Please fix these errors:\\n' + errors.join('\\n'));
        return false;
    }
    
    return true;
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    
    // Add city change listener
    document.getElementById('city').addEventListener('change', onCityChange);
    
    // Add search functionality
    document.getElementById('mapSearch').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchLocation();
        }
    });
    
    // Add current location button
    document.getElementById('useCurrentLocation').addEventListener('click', getCurrentLocation);
    
    // Add form validation
    document.querySelector('.listing-form').addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    // Prevent accidental mouse-wheel +/-1 changes.
    const priceInput = document.getElementById('price');
    priceInput.addEventListener('wheel', function(e) {
        e.preventDefault();
    });
    
    // Add arrow key support for price input
    priceInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            e.preventDefault();
            const currentValue = parseInt(this.value) || 2000;
            const delta = e.key === 'ArrowUp' ? 100 : -100;
            const newValue = Math.max(2000, Math.min(100000, currentValue + delta));
            this.value = newValue;
        }
    });
});
</script>

<?php
include 'footer.php';
?>