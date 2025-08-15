<?php
session_start();

// Load configuration
require_once 'config.php';

// Set timezone
$app_config = Config::getApp();
date_default_timezone_set($app_config['timezone']);

// Create database connection
try {
    $conn = Config::getConnection();
} catch (Exception $e) {
    die("Database connection failed. Please check your configuration.");
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['admin_user_id']) && isset($_SESSION['session_token']);
}

// Validate session
function validateSession($conn) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT u.id, u.username, u.full_name, u.role FROM admin_users u 
                           JOIN admin_sessions s ON u.id = s.user_id 
                           WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1");
    $stmt->bind_param("s", $_SESSION['session_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    // Invalid session, clear it
    session_destroy();
    return false;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $login_username = trim($_POST['username']);
    $login_password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, username, password_hash, full_name, role FROM admin_users WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $login_username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($login_password, $user['password_hash'])) {
            // Create session
            $session_token = bin2hex(random_bytes(32));
            $session_timeout_hours = $app_config['session_timeout'];
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$session_timeout_hours} hours"));
            
            $stmt = $conn->prepare("INSERT INTO admin_sessions (session_token, user_id, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sis", $session_token, $user['id'], $expires_at);
            $stmt->execute();
            
            // Update last login
            $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['session_token'] = $session_token;
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    $login_error = "Invalid username or password";
}

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['session_token'])) {
        $stmt = $conn->prepare("DELETE FROM admin_sessions WHERE session_token = ?");
        $stmt->bind_param("s", $_SESSION['session_token']);
        $stmt->execute();
    }
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$current_user = validateSession($conn);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $current_user) {
    $submission_id = $_POST['submission_id'];
    $new_status = $_POST['status'];
    $admin_notes = $_POST['admin_notes'];
    
    $stmt = $conn->prepare("UPDATE submissions SET status = ?, admin_notes = ?, updated_at = NOW() WHERE submission_id = ?");
    $stmt->bind_param("sss", $new_status, $admin_notes, $submission_id);
    $stmt->execute();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// If not logged in, show login form
if (!$current_user) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Admin Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .login-btn:hover {
            opacity: 0.9;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo htmlspecialchars($app_config['site_name']); ?></h1>
            <p>Please log in to access the dashboard</p>
        </div>
        
        <?php if (isset($login_error)): ?>
            <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="login-btn">Log In</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// Get submissions with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter && $status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($search) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR artwork_title LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'ssss';
}

$where_clause = '';
if ($where_conditions) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM submissions $where_clause";
if ($params) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_count = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_count = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_count / $per_page);

// Get submissions
$sql = "SELECT * FROM submissions $where_clause ORDER BY submission_date DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8em;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .filter-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .submissions-grid {
            display: grid;
            gap: 20px;
        }
        
        .submission-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .submission-card:hover {
            transform: translateY(-2px);
        }
        
        .submission-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .submission-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .submission-artist {
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .submission-date {
            font-size: 0.9em;
            color: #95a5a6;
        }
        
        .submission-content {
            display: grid;
            grid-template-columns: 200px 1fr 200px;
            gap: 20px;
            padding: 20px;
        }
        
        .submission-images {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .submission-images img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .submission-details {
            font-size: 0.9em;
        }
        
        .detail-row {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            display: inline-block;
            width: 80px;
        }
        
        .submission-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-align: center;
        }
        
        .status-pending { background: #f39c12; color: white; }
        .status-reviewed { background: #3498db; color: white; }
        .status-accepted { background: #27ae60; color: white; }
        .status-rejected { background: #e74c3c; color: white; }
        
        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            text-align: center;
            transition: opacity 0.3s;
        }
        
        .action-btn:hover { opacity: 0.8; }
        
        .btn-view { background: #3498db; color: white; }
        .btn-edit { background: #f39c12; color: white; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a {
            padding: 8px 12px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5em;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .submission-content {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-content {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><?php echo htmlspecialchars($app_config['site_name']); ?> - Admin</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?></span>
                <a href="?logout=1" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics -->
        <div class="stats">
            <?php
            $status_counts = [];
            $status_result = $conn->query("SELECT status, COUNT(*) as count FROM submissions GROUP BY status");
            while ($row = $status_result->fetch_assoc()) {
                $status_counts[$row['status']] = $row['count'];
            }
            ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_count; ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $status_counts['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $status_counts['accepted'] ?? 0; ?></div>
                <div class="stat-label">Accepted</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $status_counts['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Filters -->
        <form class="filters" method="GET">
            <div class="filter-group">
                <label for="status">Status:</label>
                <select name="status" id="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                    <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" placeholder="Artist name, email, or artwork title" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" class="filter-btn">Filter</button>
        </form>

        <!-- Submissions -->
        <div class="submissions-grid">
            <?php foreach ($submissions as $submission): ?>
                <?php
                $images = json_decode($submission['image_files'], true) ?: [];
                ?>
                <div class="submission-card">
                    <div class="submission-header">
                        <div class="submission-title"><?php echo htmlspecialchars($submission['artwork_title']); ?></div>
                        <div class="submission-artist">by <?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></div>
                        <div class="submission-date">Submitted: <?php echo date('M j, Y g:i A', strtotime($submission['submission_date'])); ?></div>
                    </div>
                    
                    <div class="submission-content">
                        <div class="submission-images">
                            <?php 
                            $displayed = 0;
                            foreach (array_slice($images, 0, 4) as $image): 
                                if ($displayed >= 4) break;
                                $image_path = "submissions/{$submission['submission_id']}/{$image}";
                                if (file_exists($image_path)):
                            ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Artwork">
                            <?php 
                                $displayed++;
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        
                        <div class="submission-details">
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <?php echo htmlspecialchars($submission['email']); ?>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Medium:</span>
                                <?php echo htmlspecialchars($submission['medium']); ?>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Price:</span>
                                <?php echo htmlspecialchars($submission['price'] ?: 'Not specified'); ?>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Description:</span>
                                <?php echo htmlspecialchars(substr($submission['description'], 0, 100)) . (strlen($submission['description']) > 100 ? '...' : ''); ?>
                            </div>
                        </div>
                        
                        <div class="submission-actions">
                            <div class="status-badge status-<?php echo $submission['status']; ?>">
                                <?php echo ucfirst($submission['status']); ?>
                            </div>
                            <a href="submissions/<?php echo $submission['submission_id']; ?>/submission_details.html" 
                               target="_blank" class="action-btn btn-view">View Details</a>
                            <button onclick="editSubmission('<?php echo $submission['submission_id']; ?>', '<?php echo $submission['status']; ?>', '<?php echo htmlspecialchars($submission['admin_notes']); ?>')" 
                                    class="action-btn btn-edit">Update Status</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
                       class="<?php echo $i === $page ? 'current' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3>Update Submission Status</h3>
            <form method="POST">
                <input type="hidden" id="edit_submission_id" name="submission_id">
                <div style="margin-bottom: 15px;">
                    <label for="edit_status">Status:</label><br>
                    <select name="status" id="edit_status" style="width: 100%; padding: 8px; margin-top: 5px;">
                        <option value="pending">Pending</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="accepted">Accepted</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="edit_notes">Admin Notes:</label><br>
                    <textarea name="admin_notes" id="edit_notes" rows="4" style="width: 100%; padding: 8px; margin-top: 5px;"></textarea>
                </div>
                <button type="submit" name="update_status" style="background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Update</button>
            </form>
        </div>
    </div>

    <script>
        function editSubmission(submissionId, currentStatus, currentNotes) {
            document.getElementById('edit_submission_id').value = submissionId;
            document.getElementById('edit_status').value = currentStatus;
            document.getElementById('edit_notes').value = currentNotes;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>