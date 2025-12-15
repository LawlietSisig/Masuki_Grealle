<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM elections) as total_elections,
    (SELECT COUNT(*) FROM elections WHERE status = 'active') as active_elections,
    (SELECT COUNT(*) FROM votes) as total_votes";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get all elections
$elections_query = "SELECT * FROM elections ORDER BY created_at DESC";
$elections_result = $conn->query($elections_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Voting System</title>
    <link rel="stylesheet" href="style.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Student Voting System - Admin Panel</h1>
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
        <h2>Dashboard Statistics</h2>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $stats['total_students']; ?></h3>
                <p>Total Students</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['total_elections']; ?></h3>
                <p>Total Elections</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['active_elections']; ?></h3>
                <p>Active Elections</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['total_votes']; ?></h3>
                <p>Total Votes Cast</p>
            </div>
        </div>
        
        <h2 class="mt-20">All Elections</h2>
        <a href="admin_elections.php?action=create" class="btn mb-20">Create New Election</a>
        
        <table id="electionsTable" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($election = $elections_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $election['election_id']; ?></td>
                    <td><?php echo htmlspecialchars($election['title']); ?></td>
                    <td data-order="<?php echo strtotime($election['start_date']); ?>">
                        <?php echo date('Y-m-d H:i', strtotime($election['start_date'])); ?>
                    </td>
                    <td data-order="<?php echo strtotime($election['end_date']); ?>">
                        <?php echo date('Y-m-d H:i', strtotime($election['end_date'])); ?>
                    </td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 4px; 
                            background-color: <?php 
                                echo $election['status'] == 'active' ? '#d4edda' : 
                                    ($election['status'] == 'completed' ? '#cce5ff' : 
                                    ($election['status'] == 'upcoming' ? '#fff3cd' : '#f8d7da')); 
                            ?>; 
                            color: <?php 
                                echo $election['status'] == 'active' ? '#155724' : 
                                    ($election['status'] == 'completed' ? '#004085' : 
                                    ($election['status'] == 'upcoming' ? '#856404' : '#721c24')); 
                            ?>;">
                            <?php echo ucfirst($election['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="admin_elections.php?action=manage&id=<?php echo $election['election_id']; ?>" class="btn">Manage</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
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
            $('#electionsTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'copy', 
                    'csv', 
                    'excel', 
                    'pdf', 
                    'print'
                ],
                pageLength: 10,
                order: [[2, 'desc']], // Sort by start date descending (newest first)
                language: {
                    search: "Search elections:",
                    lengthMenu: "Show _MENU_ elections per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ elections",
                    infoEmpty: "No elections found",
                    infoFiltered: "(filtered from _MAX_ total elections)",
                    zeroRecords: "No matching elections found"
                },
                columnDefs: [
                    { orderable: false, targets: 5 } // Disable sorting on Actions column
                ]
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
