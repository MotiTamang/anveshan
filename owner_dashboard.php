<?php
session_start();

// Require login and owner role
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'owner') {
    header('Location: login.php');
    exit();
}

// Include helper functions
require_once 'helpers.php';

// Get owner ID from session
$owner_id = intval($_SESSION['user']['user_id']);

include 'header.php';

$conn = getDBConnection();
$page = $_GET['page'] ?? 'dashboard';
$flash_message = $_SESSION['flash'] ?? null;
if ($flash_message !== null) {
    unset($_SESSION['flash']);
}

// Get dashboard statistics using helper function
$stats = getOwnerStats($owner_id);
$total_listings = $stats['total_listings'];
$total_inquiries = $stats['total_inquiries'];
$total_reports = $stats['pending_reports'];

// Get listings for the listings page
$listings = [];
if ($page === 'listings') {
    $stmt_listings = $conn->prepare("SELECT listing_id, city, address, room_type, price, description, latitude, longitude, created_at FROM listings WHERE owner_id = ? ORDER BY created_at DESC");
    $stmt_listings->bind_param('i', $owner_id);
    $stmt_listings->execute();
    $result_listings = $stmt_listings->get_result();
    while ($row = $result_listings->fetch_assoc()) {
        // Get images for each listing
        $img_stmt = $conn->prepare("SELECT image_id, image_path FROM listing_images WHERE listing_id = ? ORDER BY created_at ASC");
        $img_stmt->bind_param('i', $row['listing_id']);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        $row['images'] = [];
        while ($img_row = $img_result->fetch_assoc()) {
            $row['images'][] = $img_row;
        }
        $img_stmt->close();
        $listings[] = $row;
    }
    $stmt_listings->close();
}

// Get inquiries for inquiries page
$inquiries = [];
if ($page === 'inquiries') {
    $stmt_inq = $conn->prepare("SELECT i.inquiry_id, i.listing_id, i.seeker_id, i.viewed_at,
                                        l.room_type, l.city, l.address, l.price,
                                        u.name as seeker_name, u.email as seeker_email, u.phone as seeker_phone
                                 FROM inquiries i
                                 INNER JOIN listings l ON i.listing_id = l.listing_id
                                 INNER JOIN users u ON i.seeker_id = u.user_id
                                 WHERE i.owner_id = ?
                                 ORDER BY i.viewed_at DESC");
    $stmt_inq->bind_param('i', $owner_id);
    $stmt_inq->execute();
    $result_inq = $stmt_inq->get_result();
    while ($row = $result_inq->fetch_assoc()) {
        $inquiries[] = $row;
    }
    $stmt_inq->close();
}

// Get reports for reports page
$reports = [];
if ($page === 'reports') {
    $stmt_rep = $conn->prepare("SELECT r.report_id, r.reason, r.status, r.created_at,
                                       l.room_type, l.city, l.address,
                                       u.name as reporter_name
                                FROM reports r
                                INNER JOIN listings l ON r.listing_id = l.listing_id
                                LEFT JOIN users u ON r.reporter_id = u.user_id
                                WHERE l.owner_id = ? AND r.status = 'pending'
                                ORDER BY r.created_at DESC");
    $stmt_rep->bind_param('i', $owner_id);
    $stmt_rep->execute();
    $result_rep = $stmt_rep->get_result();
    while ($row = $result_rep->fetch_assoc()) {
        $reports[] = $row;
    }
    $stmt_rep->close();
}

// Get listing data for edit page
$edit_listing = null;
$edit_images = [];
if ($page === 'edit') {
    $listing_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($listing_id > 0) {
        $stmt_edit = $conn->prepare("SELECT listing_id, city, address, room_type, price, description, latitude, longitude, amenities FROM listings WHERE listing_id = ? AND owner_id = ?");
        $stmt_edit->bind_param('ii', $listing_id, $owner_id);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        $edit_listing = $result_edit->fetch_assoc();
        $stmt_edit->close();
        
        // If listing doesn't exist or doesn't belong to owner, redirect
        if (!$edit_listing) {
            closeDBConnection($conn);
            $_SESSION['flash'] = 'Error: Listing not found or you do not have permission to edit it.';
            header('Location: owner_dashboard.php?page=listings');
            exit();
        }
        
        // Get images for this listing
        $img_stmt = $conn->prepare("SELECT image_id, image_path FROM listing_images WHERE listing_id = ? ORDER BY created_at ASC");
        $img_stmt->bind_param('i', $listing_id);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        while ($img_row = $img_result->fetch_assoc()) {
            $edit_images[] = $img_row;
        }
        $img_stmt->close();
    } else {
        closeDBConnection($conn);
        header('Location: owner_dashboard.php?page=listings');
        exit();
    }
}

// Close database connection
closeDBConnection($conn);
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>owner_dashboard.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<?php if ($page === 'add'): ?>
<link rel="stylesheet" href="<?php echo $base_url; ?>add_listing.css">
<?php endif; ?>

<div class="owner-layout">

    <aside class="owner-sidebar">
        <a class="sidebar-brand" href="indexmain.php" style="text-decoration: none; color: inherit; display: block;">
            Anveshan
            <span>Owner Panel</span>
        </a>

        <nav class="sidebar-nav">
            <a href="owner_dashboard.php?page=dashboard"
               class="nav-link <?php echo ($page === 'dashboard') ? 'active' : ''; ?>">
               Dashboard
            </a>

            <a href="owner_dashboard.php?page=listings"
               class="nav-link <?php echo ($page === 'listings') ? 'active' : ''; ?>">
               My Listings
            </a>

            <a href="owner_dashboard.php?page=add"
               class="nav-link <?php echo ($page === 'add') ? 'active' : ''; ?>">
               Add Listing
            </a>

            <a href="owner_reviews.php" class="nav-link">
               My Reviews
            </a>
            
            <?php if ($page === 'edit'): ?>
            <a href="owner_dashboard.php?page=edit&id=<?php echo $edit_listing['listing_id']; ?>"
               class="nav-link active">
               Edit Listing
            </a>
            <?php endif; ?>

            <a href="owner_dashboard.php?page=inquiries"
               class="nav-link <?php echo ($page === 'inquiries') ? 'active' : ''; ?>">
               Inquiries
            </a>
            
            <a href="owner_dashboard.php?page=reports"
               class="nav-link <?php echo ($page === 'reports') ? 'active' : ''; ?>">
               Reports Received
            </a>
        </nav>
    </aside>

    <!-- Content -->
    <main class="owner-content">

        <?php if ($page === 'dashboard'): ?>
            <!-- DASHBOARD -->
            <h1>Dashboard</h1>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $total_listings; ?></h3>
                    <p>Total Listings</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_inquiries; ?></h3>
                    <p>Total Inquiries</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_reports; ?></h3>
                    <p>Reports Received</p>
                </div>
            </div>

            <div class="panel">
                <h2>Recent Listings</h2>
                <?php
                // Get recent listings for activity
                $conn_activity = getDBConnection();
                $stmt_recent = $conn_activity->prepare("SELECT listing_id, room_type, city, price, created_at FROM listings WHERE owner_id = ? ORDER BY created_at DESC LIMIT 5");
                $stmt_recent->bind_param('i', $owner_id);
                $stmt_recent->execute();
                $result_recent = $stmt_recent->get_result();
                $recent_listings = [];
                while ($row = $result_recent->fetch_assoc()) {
                    $recent_listings[] = $row;
                }
                $stmt_recent->close();
                closeDBConnection($conn_activity);
                
                if (empty($recent_listings)): ?>
                    <p class="muted">No listings yet. <a href="owner_dashboard.php?page=add">Add your first listing</a>.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($recent_listings as $recent): ?>
                            <li style="padding: 10px; border-bottom: 1px solid #eee;">
                                <strong><?php echo htmlspecialchars($recent['room_type']); ?></strong> - 
                                <?php echo htmlspecialchars($recent['city']); ?> - 
                                Rs. <?php echo number_format($recent['price']); ?>/month
                                <small style="color: #666; float: right;">
                                    <?php 
                                    $date = new DateTime($recent['created_at']);
                                    echo $date->format('M d, Y');
                                    ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'listings'): ?>
            <!-- LISTINGS -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1>My Listings</h1>
                <a href="owner_dashboard.php?page=add" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">+ Add New Listing</a>
            </div>

            <div class="panel">
                <?php if (empty($listings)): ?>
                    <p class="muted">You have no listings yet. <a href="owner_dashboard.php?page=add">Click here to add your first listing</a>.</p>
                <?php else: ?>
                    <table class="listings-table">
                        <thead>
                            <tr>
                                <th>Room Type</th>
                                <th>Location</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listings as $listing): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($listing['room_type'] ?? 'Room'); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($listing['city'] ?? ''); ?></strong><br>
                                        <small><?php echo htmlspecialchars(substr($listing['address'] ?? '', 0, 50)); ?><?php echo strlen($listing['address'] ?? '') > 50 ? '...' : ''; ?></small>
                                    </td>
                                    <td>Rs. <?php echo number_format($listing['price'] ?? 0); ?>/month</td>
                                    <td>
                                        <span style="color: green;">Active</span><br>
                                        <small style="color: #666;"><?php echo count($listing['images'] ?? []); ?> image(s)</small>
                                    </td>
                                    <td>
                                        <a href="owner_dashboard.php?page=edit&id=<?php echo $listing['listing_id']; ?>" style="color: #007bff; text-decoration: none; margin-right: 10px;">Edit</a>
                                        <a href="delete_listing.php?id=<?php echo $listing['listing_id']; ?>" onclick="return confirm('Are you sure you want to delete this listing? This will also delete all images.');" style="color: #dc3545; text-decoration: none;">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'add'): ?>
            <!-- ADD LISTING -->
            <h1>Add New Listing</h1>
            
            <?php if ($flash_message !== null): ?>
                <div class="flash-message <?php echo strpos($flash_message, 'Error') !== false ? 'error' : 'success'; ?>" style="padding: 15px; margin-bottom: 20px; border-radius: 5px; <?php echo strpos($flash_message, 'Error') !== false ? 'background: #f8d7da; color: #721c24;' : 'background: #d4edda; color: #155724;'; ?>">
                    <?php echo htmlspecialchars($flash_message); ?>
                </div>
            <?php endif; ?>

            <div class="panel">
                <form class="listing-form" action="add_listing_process.php" method="post" enctype="multipart/form-data" style="max-width: 800px;">
                    <label for="city">City *</label>
                    <select id="city" name="city" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">Select City</option>
                        <option value="Kathmandu">Kathmandu</option>
                        <option value="Lalitpur">Lalitpur</option>
                        <option value="Bhaktapur">Bhaktapur</option>
                    </select>

                    <label for="address">Full Address *</label>
                    <textarea id="address" name="address" rows="3" placeholder="Street, building, apartment number, area..." required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit;"></textarea>

                    <label for="room_type">Room Type *</label>
                    <select id="room_type" name="room_type" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">Select Room Type</option>
                        <option value="Single">Single</option>
                        <option value="Shared">Shared</option>
                        <option value="1 BHK">1 BHK</option>
                        <option value="2 BHK">2 BHK</option>
                    </select>

                    <label for="price">Monthly Rent (Rs., whole number only) *</label>
                    <input id="price" name="price" type="number" min="2000" max="100000" step="1" placeholder="e.g. 10000" required inputmode="numeric" pattern="[0-9]*" title="Digits only, no paise" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <small style="color:#666;display:block;margin:-10px 0 15px;">No decimals — e.g. 10000 not 10000.50</small>

                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="6" placeholder="Describe the property, amenities, rules, nearby facilities..." style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit;"></textarea>

                    <label>Amenities (Optional)</label>
                    <div style="display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
                        <label style="font-weight: normal; margin: 0;"><input type="checkbox" name="amenities[]" value="Parking"> Parking Available</label>
                        <label style="font-weight: normal; margin: 0;"><input type="checkbox" name="amenities[]" value="Wifi"> Wifi</label>
                        <label style="font-weight: normal; margin: 0;"><input type="checkbox" name="amenities[]" value="Pets"> Pets Allowed</label>
                    </div>

                    <!-- <label for="latitude">Latitude (Optional)</label>
                    <input id="latitude" name="latitude" type="number" step="0.00000001" placeholder="e.g. 27.7172" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">

                    <label for="longitude">Longitude (Optional)</label>
                    <input id="longitude" name="longitude" type="number" step="0.00000001" placeholder="e.g. 85.3240" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;"> -->

                     <label>Location on map *</label>
<p style="font-size: 13px; color: #666; margin: 0 0 8px;">Required for new listings. Search, click the map, or use my location — pin must match the city you chose above.</p>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
    <input type="text" id="map-search" placeholder="Search location (e.g., Patan Dhoka)" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
    <button type="button" id="btn-map-search" style="padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">Search</button>
    <button type="button" id="btn-my-location" style="padding: 8px 15px; background: #17a2b8; color: white; border: none; border-radius: 5px; cursor: pointer;">📍 Use My Location</button>
</div>
<div id="add-map" style="width: 100%; height: 320px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 12px; z-index: 0;"></div>

<div style="display: flex; gap: 12px; margin-bottom: 15px;">
    <div style="flex: 1;">
        <label for="latitude" style="font-size: 12px; color: #888; display: block; margin-bottom: 4px;">Latitude</label>
        <input id="latitude" name="latitude" type="text" readonly placeholder="Click map to set"
            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9; box-sizing: border-box;">
    </div>
    <div style="flex: 1;">
        <label for="longitude" style="font-size: 12px; color: #888; display: block; margin-bottom: 4px;">Longitude</label>
        <input id="longitude" name="longitude" type="text" readonly placeholder="Click map to set"
            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9; box-sizing: border-box;">
    </div>
    <div style="display: flex; align-items: flex-end;">
        <button type="button" onclick="clearAddPin()"
            style="padding: 10px 14px; border: 1px solid #ccc; background: transparent; border-radius: 5px; cursor: pointer; color: #666; white-space: nowrap;">
            Clear Pin
        </button>
    </div>
</div>

                    <label for="images">Images (Required - Upload at least one) *</label>
                    <input id="images" name="images[]" type="file" accept="image/*" multiple required style="width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #ddd; border-radius: 5px;">
                    <small style="display: block; color: #666; margin-bottom: 15px;">At least one image is required. Supported formats: JPG, PNG, GIF</small>

                    <div class="form-actions" style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn save" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Save Listing</button>
                        <a class="btn cancel" href="owner_dashboard.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">Cancel</a>
                    </div>
                </form>
            </div>

        <?php elseif ($page === 'edit'): ?>
            <!-- EDIT LISTING -->
            <h1>Edit Listing</h1>
            
            <?php if ($flash_message !== null): ?>
                <div class="flash-message <?php echo strpos($flash_message, 'Error') !== false ? 'error' : 'success'; ?>" style="padding: 15px; margin-bottom: 20px; border-radius: 5px; <?php echo strpos($flash_message, 'Error') !== false ? 'background: #f8d7da; color: #721c24;' : 'background: #d4edda; color: #155724;'; ?>">
                    <?php echo htmlspecialchars($flash_message); ?>
                </div>
            <?php endif; ?>

            <div class="panel">
                <form class="listing-form" action="edit_listing_process.php" method="post" enctype="multipart/form-data" style="max-width: 800px;">
                    <input type="hidden" name="listing_id" value="<?php echo $edit_listing['listing_id']; ?>">
                    
                    <label for="city">City *</label>
                    <select id="city" name="city" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">Select City</option>
                        <option value="Kathmandu" <?php echo ($edit_listing['city'] === 'Kathmandu') ? 'selected' : ''; ?>>Kathmandu</option>
                        <option value="Lalitpur" <?php echo ($edit_listing['city'] === 'Lalitpur') ? 'selected' : ''; ?>>Lalitpur</option>
                        <option value="Bhaktapur" <?php echo ($edit_listing['city'] === 'Bhaktapur') ? 'selected' : ''; ?>>Bhaktapur</option>
                    </select>

                    <label for="address">Full Address *</label>
                    <textarea id="address" name="address" rows="3" placeholder="Street, building, apartment number, area..." required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit;"><?php echo htmlspecialchars($edit_listing['address'] ?? ''); ?></textarea>

                    <label for="room_type">Room Type *</label>
                    <select id="room_type" name="room_type" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">Select Room Type</option>
                        <option value="Single" <?php echo ($edit_listing['room_type'] === 'Single') ? 'selected' : ''; ?>>Single</option>
                        <option value="Shared" <?php echo ($edit_listing['room_type'] === 'Shared') ? 'selected' : ''; ?>>Shared</option>
                        <option value="1 BHK" <?php echo ($edit_listing['room_type'] === '1 BHK') ? 'selected' : ''; ?>>1 BHK</option>
                        <option value="2 BHK" <?php echo ($edit_listing['room_type'] === '2 BHK') ? 'selected' : ''; ?>>2 BHK</option>
                    </select>

                    <label for="price">Monthly Rent (Rs., whole number only) *</label>
                    <input id="price" name="price" type="number" min="2000" max="100000" step="1" placeholder="e.g. 10000" value="<?php echo (int) round((float)($edit_listing['price'] ?? 0)); ?>" required inputmode="numeric" pattern="[0-9]*" title="Digits only" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <small style="color:#666;display:block;margin:-10px 0 15px;">No decimals</small>

                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="6" placeholder="Describe the property, amenities, rules, nearby facilities..." style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit;"><?php echo htmlspecialchars($edit_listing['description'] ?? ''); ?></textarea>

                    <?php 
                    $current_amenities = isset($edit_listing['amenities']) ? explode(', ', $edit_listing['amenities']) : [];
                    ?>
                    <label>Amenities (Optional)</label>
                    <div style="display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
                        <label style="font-weight: normal; margin: 0;"><input type="checkbox" name="amenities[]" value="Parking" <?php echo in_array('Parking', $current_amenities) ? 'checked' : ''; ?>> Parking Available</label>
                        <label style="font-weight: normal; margin: 0;"><input type="checkbox" name="amenities[]" value="Wifi" <?php echo in_array('Wifi', $current_amenities) ? 'checked' : ''; ?>> Wifi</label>
                        <label style="font-weight: normal; margin: 0;"><input type="checkbox" name="amenities[]" value="Pets" <?php echo in_array('Pets', $current_amenities) ? 'checked' : ''; ?>> Pets Allowed</label>
                    </div>

                    <!-- <label for="latitude">Latitude (Optional)</label>
                    <input id="latitude" name="latitude" type="number" step="0.00000001" placeholder="e.g. 27.7172" value="<?php echo htmlspecialchars($edit_listing['latitude'] ?? ''); ?>" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">

                    <label for="longitude">Longitude (Optional)</label>
                    <input id="longitude" name="longitude" type="number" step="0.00000001" placeholder="e.g. 85.3240" value="<?php echo htmlspecialchars($edit_listing['longitude'] ?? ''); ?>" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;"> -->
                     <label>Location on map *</label>
<p style="font-size: 13px; color: #666; margin: 0 0 8px;">Required on update. Pin must stay inside the city you choose (Kathmandu/Lalitpur/Bhaktapur only).</p>
<div style="display: flex; gap: 10px; margin-bottom: 10px;">
    <input type="text" id="map-search" placeholder="Search location (e.g., Patan Dhoka)" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
    <button type="button" id="btn-map-search" style="padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">Search</button>
    <button type="button" id="btn-my-location" style="padding: 8px 15px; background: #17a2b8; color: white; border: none; border-radius: 5px; cursor: pointer;">📍 Use My Location</button>
</div>
<div id="edit-map" style="width: 100%; height: 320px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 12px; z-index: 0;"></div>

<div style="display: flex; gap: 12px; margin-bottom: 15px;">
    <div style="flex: 1;">
        <label for="latitude" style="font-size: 12px; color: #888; display: block; margin-bottom: 4px;">Latitude</label>
        <input id="latitude" name="latitude" type="text" readonly
            placeholder="Click map to set"
            value="<?php echo htmlspecialchars($edit_listing['latitude'] ?? ''); ?>"
            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9; box-sizing: border-box;">
    </div>
    <div style="flex: 1;">
        <label for="longitude" style="font-size: 12px; color: #888; display: block; margin-bottom: 4px;">Longitude</label>
        <input id="longitude" name="longitude" type="text" readonly
            placeholder="Click map to set"
            value="<?php echo htmlspecialchars($edit_listing['longitude'] ?? ''); ?>"
            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9; box-sizing: border-box;">
    </div>
    <div style="display: flex; align-items: flex-end;">
        <button type="button" onclick="clearEditPin()"
            style="padding: 10px 14px; border: 1px solid #ccc; background: transparent; border-radius: 5px; cursor: pointer; color: #666; white-space: nowrap;">
            Clear Pin
        </button>
    </div>
</div>
                    <!-- Existing Images Section -->
                    <label style="margin-top: 20px; display: block;">Current Images</label>
                    <?php if (empty($edit_images)): ?>
                        <p style="color: #666; margin-bottom: 15px;">No images uploaded yet.</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                            <?php foreach ($edit_images as $img): ?>
                                <div style="position: relative; border: 1px solid #ddd; border-radius: 5px; overflow: hidden;">
                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Listing image" style="width: 100%; height: 150px; object-fit: cover; display: block;">
                                    <a href="delete_image.php?id=<?php echo $img['image_id']; ?>&listing_id=<?php echo $edit_listing['listing_id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete this image?');"
                                       style="position: absolute; top: 5px; right: 5px; background: rgba(220, 53, 69, 0.9); color: white; padding: 5px 10px; border-radius: 3px; text-decoration: none; font-size: 12px;">Delete</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add More Images -->
                    <label for="new_images">Add More Images</label>
                    <input id="new_images" name="new_images[]" type="file" accept="image/*" multiple style="width: 100%; padding: 10px; margin-bottom: 5px; border: 1px solid #ddd; border-radius: 5px;">
                    <small style="display: block; color: #666; margin-bottom: 15px;">At least one listing image must remain after update. Supported formats: JPG, PNG, GIF</small>

                    <div class="form-actions" style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn save" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Update Listing</button>
                        <a class="btn cancel" href="owner_dashboard.php?page=listings" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">Cancel</a>
                    </div>
                </form>
            </div>

        <?php elseif ($page === 'inquiries'): ?>
            <!-- INQUIRIES -->
            <h1>Inquiries</h1>

            <div class="panel">
                <?php if (empty($inquiries)): ?>
                    <p class="muted">No inquiries yet. Inquiries are created when seekers view your contact information.</p>
                <?php else: ?>
                    <p style="margin-bottom: 20px; color: #666;">You have received <strong><?php echo count($inquiries); ?></strong> inquiry/inquiries from seekers who viewed your contact information.</p>
                    
                    <table class="listings-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: left;">Listing</th>
                                <th style="padding: 12px; text-align: left;">Seeker</th>
                                <th style="padding: 12px; text-align: left;">Contact</th>
                                <th style="padding: 12px; text-align: left;">Viewed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inquiries as $inquiry): ?>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 12px;">
                                        <strong><?php echo htmlspecialchars($inquiry['room_type'] ?? 'Room'); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($inquiry['city'] ?? ''); ?> - Rs. <?php echo number_format($inquiry['price'] ?? 0); ?>/month</small>
                                    </td>
                                    <td style="padding: 12px;">
                                        <strong><?php echo htmlspecialchars($inquiry['seeker_name'] ?? 'N/A'); ?></strong>
                                    </td>
                                    <td style="padding: 12px;">
                                        <a href="mailto:<?php echo htmlspecialchars($inquiry['seeker_email'] ?? ''); ?>" style="color: #007bff; text-decoration: none;">
                                            <?php echo htmlspecialchars($inquiry['seeker_email'] ?? 'N/A'); ?>
                                        </a><br>
                                        <?php if (!empty($inquiry['seeker_phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($inquiry['seeker_phone']); ?>" style="color: #007bff; text-decoration: none;">
                                                <?php echo htmlspecialchars($inquiry['seeker_phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <small style="color: #666;">No phone</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php 
                                        $date = new DateTime($inquiry['viewed_at']);
                                        echo $date->format('M d, Y');
                                        ?><br>
                                        <small style="color: #666;"><?php echo $date->format('h:i A'); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'reports'): ?>
            <!-- REPORTS -->
            <h1>Reports Received</h1>

            <div class="panel">
                <?php if (empty($reports)): ?>
                    <p class="muted">Great news! None of your listings have been reported.</p>
                <?php else: ?>
                    <p style="margin-bottom: 20px; color: #666;">You have received <strong><?php echo count($reports); ?></strong> report(s) on your listings. Please review them and address any concerns.</p>
                    
                    <table class="listings-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: left;">Listing</th>
                                <th style="padding: 12px; text-align: left;">Reason</th>
                                <th style="padding: 12px; text-align: left;">Status</th>
                                <th style="padding: 12px; text-align: left;">Reported At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 12px; vertical-align: top;">
                                        <strong><?php echo htmlspecialchars($report['room_type'] ?? 'Room'); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($report['city'] ?? ''); ?> - <?php echo htmlspecialchars(substr($report['address'] ?? '', 0, 30)); ?>...</small>
                                    </td>
                                    <td style="padding: 12px; vertical-align: top; max-width: 300px;">
                                        <?php echo nl2br(htmlspecialchars($report['reason'])); ?>
                                    </td>
                                    <td style="padding: 12px; vertical-align: top;">
                                        <?php if ($report['status'] === 'pending'): ?>
                                            <span style="display: inline-block; padding: 3px 8px; background: #fff3cd; color: #856404; border-radius: 4px; font-size: 12px;">Pending Review</span>
                                        <?php elseif ($report['status'] === 'suspended'): ?>
                                            <span style="display: inline-block; padding: 3px 8px; background: #f8d7da; color: #721c24; border-radius: 4px; font-size: 12px;">⚠️ Listing Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; vertical-align: top;">
                                        <?php 
                                        $date = new DateTime($report['created_at']);
                                        echo $date->format('M d, Y');
                                        ?><br>
                                        <small style="color: #666;"><?php echo $date->format('h:i A'); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </main>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    // Same polygon logic as validation_algorithm.php
    var LISTING_CITY_POLYGONS = {
        Kathmandu: [
            [27.735, 85.255], [27.785, 85.295], [27.780, 85.350],
            [27.745, 85.372], [27.700, 85.360], [27.675, 85.315],
            [27.690, 85.270]
        ],
        Lalitpur: [
            [27.700, 85.300], [27.705, 85.345], [27.690, 85.380],
            [27.655, 85.388], [27.620, 85.365], [27.618, 85.320],
            [27.640, 85.290], [27.675, 85.285]
        ],
        Bhaktapur: [
            [27.720, 85.350], [27.725, 85.405], [27.710, 85.470],
            [27.680, 85.495], [27.640, 85.485], [27.625, 85.430],
            [27.635, 85.370], [27.670, 85.350]
        ]
    };

    function pointInPolygon(lat, lng, polygon) {
        var inside = false;
        for (var i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            var yi = polygon[i][0], xi = polygon[i][1];
            var yj = polygon[j][0], xj = polygon[j][1];
            var intersect = ((yi > lat) !== (yj > lat)) &&
                (lng < (xj - xi) * (lat - yi) / ((yj - yi) || 1e-12) + xi);
            if (intersect) inside = !inside;
        }
        return inside;
    }

    function latLngInsideListingCity(lat, lng, city) {
        var polygon = LISTING_CITY_POLYGONS[city];
        if (!polygon) return false;
        return pointInPolygon(lat, lng, polygon);
    }

    function latLngInsideSupportedArea(lat, lng) {
        return latLngInsideListingCity(lat, lng, 'Kathmandu') ||
               latLngInsideListingCity(lat, lng, 'Lalitpur') ||
               latLngInsideListingCity(lat, lng, 'Bhaktapur');
    }

    function boundsForCity(city) {
        var polygon = LISTING_CITY_POLYGONS[city];
        return polygon ? L.latLngBounds(polygon) : null;
    }

    // ── ADD LISTING MAP ──────────────────────────────────────────
    <?php if ($page === 'add'): ?>
    (function initAddMap() {
        var citySelect = document.getElementById('city');
        var map = L.map('add-map').setView([27.7172, 85.3240], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        var marker = null;

        function applyCityBounds() {
            var c = citySelect.value;
            var b = boundsForCity(c);
            if (b) {
                map.setMaxBounds(b.pad(0.02));
                map.flyToBounds(b, { padding: [24, 24], maxZoom: 14 });
            } else {
                map.setMaxBounds([[27.5, 85.1], [27.9, 85.65]]);
            }
        }

        citySelect.addEventListener('change', function() {
            window.clearAddPin();
            applyCityBounds();
        });

        function placePin(lat, lng) {
            var city = citySelect.value;
            if (!city) {
                alert('Select a city first (Kathmandu, Lalitpur, or Bhaktapur).');
                return;
            }
            lat = parseFloat(lat);
            lng = parseFloat(lng);
            if (!latLngInsideListingCity(lat, lng, city)) {
                alert('That location is outside ' + city + '. Pick a point inside the highlighted area or change the city.');
                // Keep validation strict by requiring a fresh valid pin.
                window.clearAddPin();
                return;
            }
            var latR = lat.toFixed(6);
            var lngR = lng.toFixed(6);
            document.getElementById('latitude').value = latR;
            document.getElementById('longitude').value = lngR;
            if (marker) {
                marker.setLatLng([latR, lngR]);
            } else {
                marker = L.marker([latR, lngR], { draggable: true }).addTo(map);
                marker.on('dragend', function(ev) {
                    var p = ev.target.getLatLng();
                    placePin(p.lat, p.lng);
                });
            }
        }

        window.clearAddPin = function() {
            if (marker) { map.removeLayer(marker); marker = null; }
            document.getElementById('latitude').value = '';
            document.getElementById('longitude').value = '';
        };

        map.on('click', function(e) { placePin(e.latlng.lat, e.latlng.lng); });

        if (citySelect.value) applyCityBounds();
        setupMapControls(map, placePin, function() { return citySelect.value; });
    })();
    <?php endif; ?>

    // ── EDIT LISTING MAP ─────────────────────────────────────────
    <?php if ($page === 'edit'): ?>
    (function initEditMap() {
        var citySelect = document.getElementById('city');
        var existingLat = <?php echo json_encode(!empty($edit_listing['latitude'])  ? (float)$edit_listing['latitude']  : null); ?>;
        var existingLng = <?php echo json_encode(!empty($edit_listing['longitude']) ? (float)$edit_listing['longitude'] : null); ?>;

        var centerLat = existingLat != null ? existingLat : 27.7172;
        var centerLng = existingLng != null ? existingLng : 85.3240;
        var zoom = existingLat != null ? 15 : 13;

        var map = L.map('edit-map').setView([centerLat, centerLng], zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        var marker = null;

        function applyCityBounds() {
            var c = citySelect.value;
            var b = boundsForCity(c);
            if (b) {
                map.setMaxBounds(b.pad(0.02));
                map.flyToBounds(b, { padding: [24, 24], maxZoom: 14 });
            }
        }

        function placePin(lat, lng) {
            var city = citySelect.value;
            if (!city) {
                alert('Select a city first.');
                return;
            }
            lat = parseFloat(lat);
            lng = parseFloat(lng);
            if (!latLngInsideListingCity(lat, lng, city)) {
                alert('That location is outside ' + city + '.');
                // Clear fields so invalid attempts are never submitted accidentally.
                window.clearEditPin();
                return;
            }
            var latR = lat.toFixed(6);
            var lngR = lng.toFixed(6);
            document.getElementById('latitude').value = latR;
            document.getElementById('longitude').value = lngR;
            if (marker) {
                marker.setLatLng([latR, lngR]);
            } else {
                marker = L.marker([latR, lngR], { draggable: true }).addTo(map);
                marker.on('dragend', function(ev) {
                    var p = ev.target.getLatLng();
                    placePin(p.lat, p.lng);
                });
            }
        }

        window.clearEditPin = function() {
            if (marker) { map.removeLayer(marker); marker = null; }
            document.getElementById('latitude').value = '';
            document.getElementById('longitude').value = '';
        };

        citySelect.addEventListener('change', function() {
            window.clearEditPin();
            applyCityBounds();
        });

        map.on('click', function(e) { placePin(e.latlng.lat, e.latlng.lng); });

        applyCityBounds();
        if (existingLat != null && existingLng != null && citySelect.value) {
            if (latLngInsideListingCity(existingLat, existingLng, citySelect.value)) {
                placePin(existingLat, existingLng);
            } else {
                document.getElementById('latitude').value = '';
                document.getElementById('longitude').value = '';
            }
        }

        setupMapControls(map, placePin, function() { return citySelect.value; });
    })();
    <?php endif; ?>

    function setupMapControls(map, placePinFn, getCity) {
        var searchBtn = document.getElementById('btn-map-search');
        var searchInput = document.getElementById('map-search');
        var locBtn = document.getElementById('btn-my-location');

        if (searchBtn && searchInput) {
            searchBtn.addEventListener('click', function() {
                var q = searchInput.value.trim();
                if (!q) return;
                var city = getCity();
                if (!city) {
                    alert('Select a city first.');
                    return;
                }
                var scopedQuery = q + ', Nepal';
                fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(scopedQuery))
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data && data.length > 0) {
                            var lat = parseFloat(data[0].lat);
                            var lon = parseFloat(data[0].lon);
                            if (!latLngInsideSupportedArea(lat, lon)) {
                                alert('Search result is outside supported area. Use Kathmandu, Lalitpur, or Bhaktapur only.');
                                return;
                            }
                            if (!latLngInsideListingCity(lat, lon, city)) {
                                alert('Search result is outside ' + city + '. Choose a location inside selected city.');
                                return;
                            }
                            map.flyTo([lat, lon], 16);
                            placePinFn(lat, lon);
                        } else {
                            alert('Location not found. Try a clear location name in Kathmandu, Lalitpur, or Bhaktapur.');
                        }
                    }).catch(function() {
                        alert('Error searching location.');
                    });
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchBtn.click();
                }
            });
        }

        if (locBtn) {
            locBtn.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported.');
                    return;
                }
                var city = getCity();
                if (!city) {
                    alert('Select a city first.');
                    return;
                }
                locBtn.textContent = '⌛ Locating...';
                navigator.geolocation.getCurrentPosition(function(position) {
                    var lat = position.coords.latitude;
                    var lon = position.coords.longitude;
                    if (!latLngInsideSupportedArea(lat, lon)) {
                        alert('Your current location is outside supported area. Only Kathmandu, Lalitpur, and Bhaktapur are allowed.');
                        locBtn.textContent = '📍 Use My Location';
                        return;
                    }
                    if (!latLngInsideListingCity(lat, lon, city)) {
                        alert('Your device location is outside ' + city + '. Click the map or search inside that city.');
                        locBtn.textContent = '📍 Use My Location';
                        return;
                    }
                    map.flyTo([lat, lon], 16);
                    placePinFn(lat, lon);
                    locBtn.textContent = '📍 Use My Location';
                }, function() {
                    alert('Could not get your location.');
                    locBtn.textContent = '📍 Use My Location';
                }, { enableHighAccuracy: true });
            });
        }
    }

    function setupPriceInputValidation() {
        var priceInput = document.getElementById('price');
        if (!priceInput) return;

        // Prevent accidental mouse-wheel +/-1 changes on number inputs.
        priceInput.addEventListener('wheel', function(e) {
            e.preventDefault();
        }, { passive: false });

        // Only allow whole-number rent and keep backend/client behavior aligned.
        var form = priceInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                var cityInput = document.getElementById('city');
                var addressInput = document.getElementById('address');
                var city = cityInput ? cityInput.value : '';
                var address = ((addressInput && addressInput.value) || '').toLowerCase().replace(/[-_,]/g, ' ');
                var forbiddenByCity = {
                    Kathmandu: ['lalitpur', 'patan', 'bhaktapur', 'thimi', 'sano thimi', 'madhyapur', 'suryabinayak', 'kausaltar', 'jagati'],
                    Lalitpur: ['kathmandu', 'balaju', 'kalanki', 'swoyambhu', 'maharajgunj', 'bhaktapur', 'thimi', 'sano thimi', 'madhyapur', 'suryabinayak'],
                    Bhaktapur: ['kathmandu', 'balaju', 'kalanki', 'swoyambhu', 'maharajgunj', 'lalitpur', 'patan', 'jawalakhel', 'pulchowk', 'kupondole']
                };
                if (city && address && forbiddenByCity[city]) {
                    for (var i = 0; i < forbiddenByCity[city].length; i++) {
                        if (address.indexOf(forbiddenByCity[city][i]) !== -1) {
                            alert('Address contains out-of-city keyword "' + forbiddenByCity[city][i] + '" for selected city ' + city + '.');
                            e.preventDefault();
                            return;
                        }
                    }
                }

                var raw = (priceInput.value || '').trim();
                if (!/^\d+$/.test(raw)) {
                    alert('Monthly rent must be a whole number in Rs. (no decimals).');
                    e.preventDefault();
                    return;
                }
                var n = parseInt(raw, 10);
                if (isNaN(n) || n < 2000 || n > 100000) {
                    alert('Monthly rent must be between Rs. 2,000 and Rs. 100,000.');
                    e.preventDefault();
                    return;
                }
                // Normalize any browser formatting edge cases before submit.
                priceInput.value = String(n);
            });
        }
    }

    function setupUpdatePopup() {
        var isEditPage = <?php echo json_encode($page === 'edit'); ?>;
        if (!isEditPage) return;
        <?php if ($flash_message !== null): ?>
        alert(<?php echo json_encode(strip_tags($flash_message)); ?>);
        <?php endif; ?>
    }

    setupUpdatePopup();
    setupPriceInputValidation();

})();
</script>

<?php include 'footer.php'; ?>
