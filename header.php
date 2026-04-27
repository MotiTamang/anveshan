<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$base_url = ""; // Update if needed

// Fetch user profile image if logged in
$nav_profile_img = null;
if (isset($_SESSION['user']['user_id'])) {
    require_once 'db_connection.php';
    $nav_conn = getDBConnection();
    $nav_stmt = $nav_conn->prepare("SELECT profile_image FROM users WHERE user_id = ?");
    $nav_stmt->bind_param('i', $_SESSION['user']['user_id']);
    $nav_stmt->execute();
    $nav_res = $nav_stmt->get_result();
    if ($nav_row = $nav_res->fetch_assoc()) {
        $nav_profile_img = $nav_row['profile_image'];
    }
    $nav_stmt->close();
    closeDBConnection($nav_conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Anveshan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo $base_url; ?>header.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>footer.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
</head>
<body>
<header>
    <nav class="navbar" role="navigation" aria-label="Main navigation">

        <div class="logo">
            <a href="<?php echo $base_url; ?>indexmain.php" class="brand-link" aria-label="Anveshan">
                <span class="logo-img-wrap">
                    <img src="<?php echo $base_url; ?>web_image/logo.png" alt="Anveshan Logo" class="logo-img">
                </span>
                <div class="brand-text">
                    <span class="brand-name">Anveshan</span>
                </div>
            </a>
        </div>

        <form class="search-form" action="<?php echo $base_url; ?>rooms.php" method="get" role="search" aria-label="Search rooms">
            <label for="nav-search" class="visually-hidden">Search</label>
            <input 
                id="nav-search" 
                type="text" 
                name="search" 
                placeholder="Search rooms, locations..." 
                value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                required
            >
            <button type="submit" class="search-btn" aria-label="Search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>

        <ul class="nav-links">
            <li><a href="<?php echo $base_url; ?>indexmain.php">Home</a></li>
            <li><a href="<?php echo $base_url; ?>rooms.php">Find Room</a></li>
            <li><a href="<?php echo $base_url; ?>about_us.php">About</a></li>

            <?php if (isset($_SESSION['user'])): ?>
                <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                    <li><a href="<?php echo $base_url; ?>admin_dashboard.php">Admin</a></li>
                <?php endif; ?>

                <?php if (($_SESSION['user']['role'] ?? '') === 'owner'): ?>
                    <li><a href="<?php echo $base_url; ?>owner_dashboard.php">Owner Dashboard</a></li>
                <?php endif; ?>

                <?php if (in_array(($_SESSION['user']['role'] ?? ''), ['seeker', 'owner'])): ?>
                    <?php 
                        $role = $_SESSION['user']['role'];
                        $profile_url = $role === 'owner' ? 'owner_profile.php' : 'seeker_profile.php';
                        $settings_url = $role === 'owner' ? 'owner_settings.php' : 'seeker_settings.php';
                    ?>
                    <li class="profile-dropdown">
                        <button class="profile-icon-btn" id="profileDropdownBtn" aria-label="Profile menu" aria-expanded="false" style="padding: 0; background: none; border: none; cursor: pointer;">
                            <?php if (!empty($nav_profile_img) && file_exists($nav_profile_img)): ?>
                                <img src="<?php echo $base_url . htmlspecialchars($nav_profile_img); ?>" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--brand-color);">
                            <?php else: ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            <?php endif; ?>
                        </button>
                        <ul class="profile-dropdown-menu" id="profileDropdownMenu">
                            <li>
                                <a href="<?php echo $base_url . $profile_url; ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <span>My Profile</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo $base_url . $settings_url; ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"></path>
                                    </svg>
                                    <span>Settings</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a href="<?php echo $base_url; ?>logout.php">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                    <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li><a href="<?php echo $base_url; ?>logout.php">Log Out</a></li>
                <?php endif; ?>
            <?php else: ?>
                <li><a href="<?php echo $base_url; ?>login.php">Log In</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const profileBtn = document.getElementById('profileDropdownBtn');
    const profileMenu = document.getElementById('profileDropdownMenu');
    
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = profileMenu.classList.contains('is-open');
            profileMenu.classList.toggle('is-open');
            profileBtn.setAttribute('aria-expanded', String(!isOpen));
        });

        document.addEventListener('click', function(e) {
            if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
                profileMenu.classList.remove('is-open');
                profileBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
});
</script>