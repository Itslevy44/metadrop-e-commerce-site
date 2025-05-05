<?php
session_start();
include 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Initialize variables
$search = '';
$users = [];
$message = '';
$error = '';
$total_users = 0;
$limit = 10; // Users per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, 'delete_user', ?)");
        $details = "Deleted user ID: " . $user_id;
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $details);
        $log_stmt->execute();
        
        // Delete user
        $delete_stmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $message = "User successfully deleted";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error deleting user: " . $e->getMessage();
        error_log("User deletion error: " . $e->getMessage());
    }
}

// Handle search
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Get total number of users (for pagination)
$count_sql = "SELECT COUNT(*) as total FROM admins";
if (!empty($search)) {
    $count_sql .= " WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
    $stmt = $conn->prepare($count_sql);
    $search_param = "%$search%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} else {
    $stmt = $conn->prepare($count_sql);
}

$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_users = $total_row['total'];
$total_pages = ceil($total_users / $limit);

// Get users for current page
$sql = "SELECT admin_id, username, email, full_name, registration_date, last_login, status, role, priority FROM admins";
if (!empty($search)) {
    $sql .= " WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
}
$sql .= " ORDER BY registration_date DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// Fetch users
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MetaDrop - Manage Users</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="logo.jpg" type="image/png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background-color: #f7f9fc;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .admin-title {
            color: #4361ee;
            font-size: 24px;
            font-weight: bold;
        }
        
        .admin-actions {
            display: flex;
            gap: 15px;
        }
        
        .admin-btn {
            padding: 8px 16px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .admin-btn:hover {
            background-color: #3a0ca3;
        }
        
        .admin-btn.secondary {
            background-color: #e0e6ed;
            color: #4361ee;
        }
        
        .admin-btn.secondary:hover {
            background-color: #d1d9e6;
        }
        
        .admin-btn.danger {
            background-color: #e63946;
        }
        
        .admin-btn.danger:hover {
            background-color: #d62828;
        }
        
        .search-panel {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .search-form {
            display: flex;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e0e6ed;
            border-radius: 5px 0 0 5px;
            font-size: 14px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #4361ee;
        }
        
        .search-btn {
            padding: 10px 20px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
            font-size: 14px;
        }
        
        .users-table {
            width: 100%;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e6ed;
        }
        
        th {
            background-color: #f7f9fc;
            font-weight: 600;
            color: #4361ee;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background-color: #f7f9fc;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status.active {
            background-color: #d1fae5;
            color: #047857;
        }
        
        .status.inactive {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .status.pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .role {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .role.super-admin {
            background-color: #c7d2fe;
            color: #4338ca;
        }
        
        .role.admin {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .role.editor {
            background-color: #e0e7ff;
            color: #4f46e5;
        }
        
        .role.manager {
            background-color: #ede9fe;
            color: #6d28d9;
        }
        
        .role.staff {
            background-color: #f5f3ff;
            color: #7c3aed;
        }
        
        .priority {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .priority.high {
            background-color: #fecaca;
            color: #b91c1c;
        }
        
        .priority.medium {
            background-color: #fed7aa;
            color: #c2410c;
        }
        
        .priority.low {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit, .btn-delete {
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-edit {
            background-color: #dbeafe;
            color: #2563eb;
        }
        
        .btn-edit:hover {
            background-color: #bfdbfe;
        }
        
        .btn-delete {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .btn-delete:hover {
            background-color: #fecaca;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 10px;
        }
        
        .page-link {
            padding: 8px 15px;
            background-color: white;
            border: 1px solid #e0e6ed;
            border-radius: 5px;
            color: #4361ee;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .page-link:hover, .page-link.active {
            background-color: #4361ee;
            color: white;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 20px;
            color: #4361ee;
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: #777;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #e0e6ed;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 14px;
            max-width: 400px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1 class="admin-title">Manage Users</h1>
            <div class="admin-actions">
                <a href="admin.php" class="admin-btn secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="add_user.php" class="admin-btn">
                    <i class="fas fa-plus"></i> Add New User
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="search-panel">
            <form action="" method="GET" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Search by username, email or name..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>
        
        <div class="users-table">
            <?php if (count($users) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Full Name</th>
                        <th>Registration Date</th>
                        <th>Last Login</th>
                        <th>Role</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['admin_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></td>
                        <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td>
                            <?php 
                            $role_class = '';
                            switch($user['role']) {
                                case 'super-admin':
                                    $role_class = 'super-admin';
                                    break;
                                case 'admin':
                                    $role_class = 'admin';
                                    break;
                                case 'editor':
                                    $role_class = 'editor';
                                    break;
                                case 'manager':
                                    $role_class = 'manager';
                                    break;
                                default:
                                    $role_class = 'staff';
                            }
                            ?>
                            <span class="role <?php echo $role_class; ?>"><?php echo ucfirst($user['role']); ?></span>
                        </td>
                        <td>
                            <?php 
                            $priority_class = '';
                            switch($user['priority']) {
                                case 'high':
                                    $priority_class = 'high';
                                    break;
                                case 'medium':
                                    $priority_class = 'medium';
                                    break;
                                default:
                                    $priority_class = 'low';
                            }
                            ?>
                            <span class="priority <?php echo $priority_class; ?>"><?php echo ucfirst($user['priority']); ?></span>
                        </td>
                        <td>
                            <?php if ($user['status'] == 'active'): ?>
                                <span class="status active">Active</span>
                            <?php elseif ($user['status'] == 'inactive'): ?>
                                <span class="status inactive">Inactive</span>
                            <?php else: ?>
                                <span class="status pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            <a href="edit_user.php?id=<?php echo $user['admin_id']; ?>" class="btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="#" class="btn-delete" onclick="confirmDelete(<?php echo $user['admin_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No users found</h3>
                <p>There are no users matching your search criteria. Try adjusting your search or add a new user.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Delete</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
                <p>This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="user_id" id="deleteUserId" value="">
                    <button type="button" class="admin-btn secondary" id="cancelDelete">Cancel</button>
                    <button type="submit" name="delete_user" class="admin-btn danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Delete confirmation modal
        var modal = document.getElementById("deleteModal");
        var closeButton = document.getElementsByClassName("close")[0];
        var cancelButton = document.getElementById("cancelDelete");
        
        function confirmDelete(userId, username) {
            document.getElementById("deleteUserId").value = userId;
            document.getElementById("deleteUsername").textContent = username;
            modal.style.display = "block";
        }
        
        closeButton.onclick = function() {
            modal.style.display = "none";
        }
        
        cancelButton.onclick = function() {
            modal.style.display = "none";
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>