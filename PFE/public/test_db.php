<?php
require '../config/config.php';

try {
    // Test 1: Count total sessions
    $stmt = $pdo->query("SELECT COUNT(*) FROM Session");
    $total_sessions = $stmt->fetchColumn();
    echo "Total sessions in database: " . $total_sessions . "\n";
    
    // Test 2: Count disponible sessions
    $stmt = $pdo->query("SELECT COUNT(*) FROM Session WHERE statutSession = 'disponible'");
    $disponible_sessions = $stmt->fetchColumn();
    echo "Disponible sessions: " . $disponible_sessions . "\n";
    
    // Test 3: List all session statuses
    $stmt = $pdo->query("SELECT statutSession, COUNT(*) as count FROM Session GROUP BY statutSession");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Session statuses:\n";
    foreach ($statuses as $status) {
        echo "  {$status['statutSession']}: {$status['count']}\n";
    }
    
    // Test 4: Check if mentors exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM Mentor");
    $total_mentors = $stmt->fetchColumn();
    echo "Total mentors: " . $total_mentors . "\n";
    
    // Test 5: Test the join
    $stmt = $pdo->query("
        SELECT s.idSession, s.titreSession, s.statutSession, u.prenomUtilisateur, u.nomUtilisateur
        FROM Session s 
        JOIN Mentor m ON s.idMentorAnimateur = m.idMentor 
        JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur 
        WHERE s.statutSession = 'disponible'
        LIMIT 5
    ");
    $test_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Test join results:\n";
    foreach ($test_sessions as $session) {
        echo "  {$session['idSession']}: {$session['titreSession']} by {$session['prenomUtilisateur']} {$session['nomUtilisateur']}\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
