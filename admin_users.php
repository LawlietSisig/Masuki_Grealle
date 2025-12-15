<?php
// admin_users.php - Manage users with DataTables
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$error = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $class = $_POST['class'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("INSERT INTO users (user_id, name, class, password_hash, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $user_id, $name, $class, $password_hash, $role);
    
    if ($stmt->execute()) {
        $message = "User created successfully!";
    } else {
        $error = "Error creating user. User ID may already exist.";
    }
}

// Handle user status toggle
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    
    if ($stmt->execute()) {
        $message = "User status updated!";
    }
}

// Handle user deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Prevent deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        // Check if user has cast votes
        $check_votes = $conn->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE voter_id = ?");
        $check_votes->bind_param("s", $user_id);
        $check_votes->execute();
        $vote_result = $check_votes->get_result()->fetch_assoc();
        $check_votes->close();
        
        if ($vote_result['vote_count'] > 0) {
            $error = "Cannot delete user who has cast votes. You can deactivate them instead.";
        } else {
            // Log the deletion
            $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'USER_DELETED', ?)");
            $log_desc = "Deleted user: " . $user_id;
            $log_stmt->bind_param("ss", $_SESSION['user_id'], $log_desc);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Delete the user
            $delete_stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $delete_stmt->bind_param("s", $user_id);
            
            if ($delete_stmt->execute()) {
                $message = "User deleted successfully!";
            } else {
                $error = "Error deleting user.";
            }
            $delete_stmt->close();
        }
    }
}

// Handle password reset
if (isset($_POST['reset_password']) && isset($_POST['reset_user_id'])) {
    $user_id = $_POST['reset_user_id'];
    $new_password = $_POST['new_password'];
    
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->bind_param("ss", $password_hash, $user_id);
    
    if ($stmt->execute()) {
        $message = "Password reset successfully for user: " . htmlspecialchars($user_id);
        
        // Log the password reset
        $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'PASSWORD_RESET', ?)");
        $log_desc = "Reset password for user: " . $user_id;
        $log_stmt->bind_param("ss", $_SESSION['user_id'], $log_desc);
        $log_stmt->execute();
        $log_stmt->close();
    } else {
        $error = "Error resetting password.";
    }
}

// Get all users
$users_query = "SELECT * FROM users ORDER BY role, name";
$users_result = $conn->query($users_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="style.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    
    <style>
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            padding: 5px 10px;
            font-size: 12px;
            margin: 0;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #333;
        }
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
        }
        .modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .modal-close:hover {
            color: #000;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Manage Users</h1>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_approve.php">Approve Elections</a>
                <a href="admin_users.php">Manage Users</a>
                <a href="results.php">View Results</a>
                <a href="edit_profile.php">Edit Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <h2>Create New User</h2>
        <div class="card">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id">User ID:</label>
                    <input type="text" id="user_id" name="user_id" required placeholder="e.g., S2025005">
                </div>
                
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="class">Class:</label>
                    <input type="text" id="class" name="class" required placeholder="e.g., 12A">
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="student">Student</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <button type="submit" name="create_user">Create User</button>
            </form>
        </div>
        
        <h2 class="mt-20">All Users</h2>
        <table id="usersTable" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['class']); ?></td>
                    <td><?php echo ucfirst($user['role']); ?></td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo $user['is_active'] ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $user['is_active'] ? '#155724' : '#721c24'; ?>;">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="?toggle=1&id=<?php echo urlencode($user['user_id']); ?>" 
                               class="btn btn-secondary"
                               onclick="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?');">
                                <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            
                            <button class="btn btn-warning" 
                                    onclick="openResetModal('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')">
                                Reset Password
                            </button>
                            
                            <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                            <a href="?delete=1&id=<?php echo urlencode($user['user_id']); ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('⚠️ WARNING: Are you sure you want to DELETE this user?\n\nUser: <?php echo htmlspecialchars($user['name']); ?>\nID: <?php echo htmlspecialchars($user['user_id']); ?>\n\nThis action CANNOT be undone!');">
                                Delete
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Password Reset Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeResetModal()">&times;</span>
            <h3>Reset Password</h3>
            <p>User: <strong id="resetUserName"></strong></p>
            <form method="POST" action="">
                <input type="hidden" id="resetUserId" name="reset_user_id">
                
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" required minlength="6">
                </div>
                
                <button type="submit" name="reset_password" onclick="return validatePasswordReset()">Reset Password</button>
                <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
            </form>
        </div>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    
    <!-- DataTables Buttons Extension -->
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#usersTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'copy', 
                    'csv', 
                    'excel', 
                    'pdf', 
                    'print'
                ],
                pageLength: 25,
                order: [[3, 'asc'], [1, 'asc']], // Sort by role, then name
                language: {
                    search: "Search users:",
                    lengthMenu: "Show _MENU_ users per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ users",
                    infoEmpty: "No users found",
                    infoFiltered: "(filtered from _MAX_ total users)",
                    zeroRecords: "No matching users found"
                },
                columnDefs: [
                    { orderable: false, targets: 6 } // Disable sorting on Actions column
                ]
            });
        });
        
        // Modal functions
        function openResetModal(userId, userName) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUserName').textContent = userName + ' (' + userId + ')';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('resetModal').style.display = 'block';
        }
        
        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }
        
        function validatePasswordReset() {
            var newPass = document.getElementById('new_password').value;
            var confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPass.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return confirm('Are you sure you want to reset the password for this user?');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('resetModal');
            if (event.target == modal) {
                closeResetModal();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
