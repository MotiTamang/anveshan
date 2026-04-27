<?php
session_start();

// Strict Admin check
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'db_connection.php';
$conn = getDBConnection();

// Fetch users
$users = [];
$res_users = $conn->query("SELECT user_id, name, email, phone, role, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC");
if ($res_users) {
    while($row = $res_users->fetch_assoc()) {
        $users[] = $row;
    }
}

closeDBConnection($conn);

include 'header.php';
?>
<link rel="stylesheet" href="<?php echo $base_url; ?>admin_dashboard.css">

<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            Anveshan
            <span>Admin Portal</span>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
            <a href="manage_users.php" class="nav-link active">Manage Users</a>
            <a href="manage_reviews.php" class="nav-link">Manage Reviews</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-content">
        <h1>Manage Users</h1>

        <?php if (isset($_SESSION['flash'])): ?>
            <div style="background: #e6ffed; border: 1px solid #c7f1d3; padding: 10px; border-radius: 5px; margin-bottom: 20px; color: #0b8a3a;">
                <?php 
                echo htmlspecialchars($_SESSION['flash']); 
                unset($_SESSION['flash']);
                ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h2>All Registered Users</h2>
            <?php if (empty($users)): ?>
                <p class="muted">No users found.</p>
            <?php else: ?>
                <table class="listings-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Signup Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><strong style="text-transform: capitalize; color: <?php echo $u['role'] == 'owner' ? '#e67e22' : '#2980b9'; ?>;"><?php echo htmlspecialchars($u['role']); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <form method="POST" action="admin_actions.php" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this user and ALL of their listings/reports?');" style="margin: 0;">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                        <button type="submit" class="btn-delete">Delete User</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'footer.php'; ?>
