<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get user's votes
$votes_query = "SELECT 
    e.title as election_title,
    e.election_id,
    p.title as position_title,
    u.name as candidate_name,
    v.voted_at
    FROM votes v
    JOIN elections e ON v.election_id = e.election_id
    JOIN positions p ON v.position_id = p.position_id
    JOIN candidates c ON v.candidate_id = c.candidate_id
    JOIN users u ON c.user_id = u.user_id
    WHERE v.voter_id = ?
    ORDER BY v.voted_at DESC";
$votes_stmt = $conn->prepare($votes_query);
$votes_stmt->bind_param("s", $user_id);
$votes_stmt->execute();
$votes_result = $votes_stmt->get_result();

// Store votes in array for grouping and DataTable
$votes_by_election = [];
while ($vote = $votes_result->fetch_assoc()) {
    $election_id = $vote['election_id'];
    if (!isset($votes_by_election[$election_id])) {
        $votes_by_election[$election_id] = [
            'title' => $vote['election_title'],
            'votes' => []
        ];
    }
    $votes_by_election[$election_id]['votes'][] = $vote;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Votes - Student Voting System</title>
    <link rel="stylesheet" href="style.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Student Voting System</h1>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="create_election.php">Propose Election</a>
                <a href="my_votes.php">My Votes</a>
                <a href="results.php">Results</a>
                <a href="edit_profile.php">Edit Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h2>My Voting History</h2>
        
        <?php if (count($votes_by_election) > 0): ?>
            <?php 
            $table_counter = 0;
            foreach ($votes_by_election as $election_id => $election_data): 
                $table_counter++;
                $table_id = "votesTable_" . $table_counter;
            ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($election_data['title']); ?></h3>
                    <p style="color: #666; margin-bottom: 15px;">
                        <strong>Total Votes Cast:</strong> <?php echo count($election_data['votes']); ?>
                    </p>
                    
                    <table id="<?php echo $table_id; ?>" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th>Voted For</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($election_data['votes'] as $vote): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($vote['position_title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($vote['candidate_name']); ?></td>
                                <td data-order="<?php echo strtotime($vote['voted_at']); ?>">
                                    <?php echo date('F j, Y g:i A', strtotime($vote['voted_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <div class="card" style="background-color: #f0f0f0; border: 2px solid #333;">
                <h3>📊 Voting Summary</h3>
                <div class="stats">
                    <div class="stat-card">
                        <h3><?php echo count($votes_by_election); ?></h3>
                        <p>Elections Participated</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo array_sum(array_map(function($e) { return count($e['votes']); }, $votes_by_election)); ?></h3>
                        <p>Total Votes Cast</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <p>You haven't cast any votes yet.</p>
                <br>
                <a href="index.php" class="btn">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; BSIT 2A 2025 Student Voting System</p>
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
            // Initialize all votes tables
            $('table[id^="votesTable_"]').each(function() {
                $(this).DataTable({
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'copy',
                            text: 'Copy to Clipboard'
                        },
                        {
                            extend: 'csv',
                            filename: 'my_votes_' + new Date().toISOString().slice(0,10)
                        },
                        {
                            extend: 'excel',
                            filename: 'my_votes_' + new Date().toISOString().slice(0,10)
                        },
                        {
                            extend: 'pdf',
                            filename: 'my_votes_' + new Date().toISOString().slice(0,10),
                            title: 'My Voting History'
                        },
                        {
                            extend: 'print',
                            title: 'My Voting History'
                        }
                    ],
                    pageLength: 25,
                    paging: true,
                    searching: true,
                    order: [[2, 'desc']], // Sort by date descending (most recent first)
                    language: {
                        search: "Search votes:",
                        lengthMenu: "Show _MENU_ votes per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ votes",
                        infoEmpty: "No votes found",
                        infoFiltered: "(filtered from _MAX_ total votes)",
                        zeroRecords: "No matching votes found",
                        emptyTable: "No votes cast in this election"
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
