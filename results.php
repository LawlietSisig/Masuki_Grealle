<?php
// results.php - View election results with DataTables
require_once 'config.php';
requireLogin();

$conn = getDBConnection();

// Get completed and active elections (all users can see live results)
$elections_query = "SELECT * FROM elections WHERE status IN ('completed', 'active') ORDER BY 
    CASE WHEN status = 'active' THEN 0 ELSE 1 END, end_date DESC";
$elections_result = $conn->query($elections_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - Student Voting System</title>
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
                <?php if (isAdmin()): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                    <a href="admin_approve.php">Approve Elections</a>
                    <a href="admin_users.php">Manage Users</a>
                    <a href="results.php">View Results</a>
                    <a href="edit_profile.php">Edit Profile</a>
                <?php else: ?>
                    <a href="index.php">Dashboard</a>
                    <a href="create_election.php">Propose Election</a>
                    <a href="my_votes.php">My Votes</a>
                    <a href="results.php">Results</a>
                <a href="edit_profile.php">Edit Profile</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h2>Election Results</h2>
        <p style="color: #666; margin-bottom: 20px;">
            <em>Note: Live results are shown for active elections. Results update in real-time as votes are cast.</em>
        </p>
        
        <?php if ($elections_result->num_rows > 0): ?>
            <?php 
            $election_counter = 0;
            while ($election = $elections_result->fetch_assoc()): 
                $election_counter++;
                $is_active = $election['status'] == 'active';
            ?>
                <div class="card">
                    <h3>
                        <?php echo htmlspecialchars($election['title']); ?>
                        <?php if ($is_active): ?>
                            <span style="background-color: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 4px; font-size: 14px; margin-left: 10px;">
                                🔴 LIVE
                            </span>
                        <?php endif; ?>
                    </h3>
                    
                    <?php if ($is_active): ?>
                        <p><strong>Status:</strong> <span style="color: #856404;">Active (Ongoing)</span></p>
                        <p><strong>Ends:</strong> <?php echo date('F j, Y g:i A', strtotime($election['end_date'])); ?></p>
                    <?php else: ?>
                        <p><strong>Ended:</strong> <?php echo date('F j, Y g:i A', strtotime($election['end_date'])); ?></p>
                    <?php endif; ?>
                    
                    <?php
                    // Get total eligible voters (students)
                    $total_voters_query = "SELECT COUNT(*) as total FROM users WHERE role = 'student' AND is_active = 1";
                    $total_voters = $conn->query($total_voters_query)->fetch_assoc()['total'];
                    
                    // For active elections, get live results
                    if ($is_active) {
                        // Get positions
                        $positions_query = "SELECT p.position_id, p.title as position_title 
                            FROM positions p 
                            WHERE p.election_id = ? 
                            ORDER BY p.display_order";
                        $positions_stmt = $conn->prepare($positions_query);
                        $positions_stmt->bind_param("i", $election['election_id']);
                        $positions_stmt->execute();
                        $positions_result = $positions_stmt->get_result();
                        
                        $position_data = [];
                        while ($position = $positions_result->fetch_assoc()) {
                            $pos_id = $position['position_id'];
                            
                            // Get candidates and their vote counts
                            $candidates_query = "SELECT 
                                c.candidate_id,
                                u.name as candidate_name,
                                u.class as candidate_class,
                                COUNT(v.vote_id) as total_votes
                                FROM candidates c
                                JOIN users u ON c.user_id = u.user_id
                                LEFT JOIN votes v ON c.candidate_id = v.candidate_id
                                WHERE c.position_id = ?
                                GROUP BY c.candidate_id, u.name, u.class
                                ORDER BY total_votes DESC";
                            
                            $candidates_stmt = $conn->prepare($candidates_query);
                            $candidates_stmt->bind_param("i", $pos_id);
                            $candidates_stmt->execute();
                            $candidates_result = $candidates_stmt->get_result();
                            
                            $position_data[$pos_id] = [
                                'title' => $position['position_title'],
                                'candidates' => []
                            ];
                            
                            while ($candidate = $candidates_result->fetch_assoc()) {
                                $position_data[$pos_id]['candidates'][] = $candidate;
                            }
                            $candidates_stmt->close();
                        }
                        $positions_stmt->close();
                    } else {
                        // For completed elections, use the view
                        $results_query = "SELECT * FROM election_results WHERE election_id = ? ORDER BY position_id, total_votes DESC";
                        $results_stmt = $conn->prepare($results_query);
                        $results_stmt->bind_param("i", $election['election_id']);
                        $results_stmt->execute();
                        $results = $results_stmt->get_result();
                        
                        $position_data = [];
                        while ($result = $results->fetch_assoc()) {
                            $pos_id = $result['position_id'];
                            if (!isset($position_data[$pos_id])) {
                                $position_data[$pos_id] = [
                                    'title' => $result['position_title'],
                                    'candidates' => []
                                ];
                            }
                            $position_data[$pos_id]['candidates'][] = [
                                'candidate_name' => $result['candidate_name'],
                                'candidate_class' => $result['candidate_class'],
                                'total_votes' => $result['total_votes']
                            ];
                        }
                        $results_stmt->close();
                    }
                    ?>
                    
                    <?php 
                    $position_counter = 0;
                    foreach ($position_data as $pos_id => $position): 
                        $position_counter++;
                        $table_id = "resultsTable_" . $election_counter . "_" . $position_counter;
                        
                        // Calculate votes for this position
                        $total_votes_position = array_sum(array_column($position['candidates'], 'total_votes'));
                        $abstain_votes = $total_voters - $total_votes_position;
                        $abstain_percentage = $total_voters > 0 ? round(($abstain_votes / $total_voters) * 100, 2) : 0;
                    ?>
                        <h4 class="mt-20"><?php echo htmlspecialchars($position['title']); ?></h4>
                        <table id="<?php echo $table_id; ?>" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Candidate</th>
                                    <th>Class</th>
                                    <th>Votes</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 0;
                                foreach ($position['candidates'] as $candidate): 
                                    $rank++;
                                    $percentage = $total_voters > 0 ? 
                                        round(($candidate['total_votes'] / $total_voters) * 100, 2) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($rank == 1): ?>
                                            <span style="background-color: #FFD700; color: #333; padding: 4px 8px; border-radius: 4px; font-weight: bold;">🏆 #<?php echo $rank; ?></span>
                                        <?php elseif ($rank == 2): ?>
                                            <span style="background-color: #C0C0C0; color: #333; padding: 4px 8px; border-radius: 4px; font-weight: bold;">🥈 #<?php echo $rank; ?></span>
                                        <?php elseif ($rank == 3): ?>
                                            <span style="background-color: #CD7F32; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold;">🥉 #<?php echo $rank; ?></span>
                                        <?php else: ?>
                                            <span style="padding: 4px 8px;">#<?php echo $rank; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($candidate['candidate_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($candidate['candidate_class']); ?></td>
                                    <td><strong><?php echo $candidate['total_votes']; ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <div style="flex-grow: 1; background-color: #f0f0f0; height: 20px; border-radius: 4px; margin-right: 10px; overflow: hidden;">
                                                <div style="background-color: #333; height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.3s;"></div>
                                            </div>
                                            <span style="min-width: 50px; text-align: right;"><strong><?php echo $percentage; ?>%</strong></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if ($abstain_votes > 0): ?>
                                <tr style="background-color: #f8f8f8;">
                                    <td>
                                        <span style="color: #666; padding: 4px 8px;">-</span>
                                    </td>
                                    <td><em style="color: #666;">Abstain / Did Not Vote</em></td>
                                    <td><em style="color: #666;">-</em></td>
                                    <td><strong style="color: #666;"><?php echo $abstain_votes; ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <div style="flex-grow: 1; background-color: #f0f0f0; height: 20px; border-radius: 4px; margin-right: 10px; overflow: hidden;">
                                                <div style="background-color: #999; height: 100%; width: <?php echo $abstain_percentage; ?>%; transition: width 0.3s;"></div>
                                            </div>
                                            <span style="min-width: 50px; text-align: right;"><strong style="color: #666;"><?php echo $abstain_percentage; ?>%</strong></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background-color: #f8f8f8; font-weight: bold;">
                                    <td colspan="3">Total Eligible Voters</td>
                                    <td><strong><?php echo $total_voters; ?></strong></td>
                                    <td>100%</td>
                                </tr>
                                <tr style="background-color: #fff; font-weight: bold;">
                                    <td colspan="3">Total Votes Cast</td>
                                    <td><strong><?php echo $total_votes_position; ?></strong></td>
                                    <td><strong><?php 
                                        $turnout_percentage = $total_voters > 0 ? round(($total_votes_position / $total_voters) * 100, 2) : 0;
                                        echo $turnout_percentage;
                                    ?>%</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endforeach; ?>
                    
                    <?php if ($is_active): ?>
                        <p style="color: #856404; font-style: italic; margin-top: 15px;">
                            ℹ️ These are live results. Results will continue to update as votes are cast.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <p>No active or completed elections yet.</p>
            </div>
        <?php endif; ?>
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
            // Initialize all result tables
            $('table[id^="resultsTable_"]').each(function() {
                $(this).DataTable({
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 
                        'csv', 
                        'excel', 
                        'pdf', 
                        'print'
                    ],
                    pageLength: 25,
                    paging: false,
                    searching: false,
                    info: false,
                    order: [[3, 'desc']],
                    language: {
                        emptyTable: "No candidates for this position"
                    },
                    columnDefs: [
                        { orderable: false, targets: 4 }
                    ]
                });
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
