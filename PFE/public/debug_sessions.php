<?php
require '../config/config.php';
require '../config/helpers.php';

echo "<h1>Debug Sessions Query</h1>";

// Test 1: Check all sessions in database
echo "<h2>1. All Sessions in Database:</h2>";
$stmt = $pdo->query("SELECT idSession, titreSession, statutSession, dateSession, idMentorAnimateur, idEtudiantDemandeur FROM Session ORDER BY idSession");
$all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Date</th><th>Mentor ID</th><th>Student ID</th></tr>";
foreach ($all_sessions as $session) {
    echo "<tr>";
    echo "<td>{$session['idSession']}</td>";
    echo "<td>{$session['titreSession']}</td>";
    echo "<td>{$session['statutSession']}</td>";
    echo "<td>{$session['dateSession']}</td>";
    echo "<td>{$session['idMentorAnimateur']}</td>";
    echo "<td>{$session['idEtudiantDemandeur']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test 2: Check mentors
echo "<h2>2. All Mentors in Database:</h2>";
$stmt = $pdo->query("SELECT m.idMentor, m.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur FROM Mentor m JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur ORDER BY m.idMentor");
$all_mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>Mentor ID</th><th>User ID</th><th>First Name</th><th>Last Name</th></tr>";
foreach ($all_mentors as $mentor) {
    echo "<tr>";
    echo "<td>{$mentor['idMentor']}</td>";
    echo "<td>{$mentor['idUtilisateur']}</td>";
    echo "<td>{$mentor['prenomUtilisateur']}</td>";
    echo "<td>{$mentor['nomUtilisateur']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test 3: Test the sessions query from sessions.php
echo "<h2>3. Sessions Query from sessions.php:</h2>";

$whereClauses = [
    "(s.statutSession = 'disponible' OR (s.statutSession = 'en_attente' AND s.idEtudiantDemandeur IS NULL))", 
    "s.dateSession >= CURDATE()"
];

$sqlWhere = 'WHERE ' . implode(' AND ', $whereClauses);

$sqlBase = "
    FROM Session s
    JOIN Mentor m ON s.idMentorAnimateur = m.idMentor
    JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
    $sqlWhere
";

$selectSql = "SELECT s.*, u.prenomUtilisateur AS mentor_prenom, u.nomUtilisateur AS mentor_nom, u.ville AS mentor_ville, u.photoUrl AS mentor_photo ";
$fetchSql = $selectSql . $sqlBase . " ORDER BY s.dateSession ASC, s.heureSession ASC";

echo "<p><strong>Query:</strong> " . htmlspecialchars($fetchSql) . "</p>";

try {
    $stmt = $pdo->prepare($fetchSql);
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Results found:</strong> " . count($sessions) . "</p>";
    
    if (!empty($sessions)) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Date</th><th>Mentor</th><th>Student ID</th></tr>";
        foreach ($sessions as $session) {
            echo "<tr>";
            echo "<td>{$session['idSession']}</td>";
            echo "<td>{$session['titreSession']}</td>";
            echo "<td>{$session['statutSession']}</td>";
            echo "<td>{$session['dateSession']}</td>";
            echo "<td>{$session['mentor_prenom']} {$session['mentor_nom']}</td>";
            echo "<td>{$session['idEtudiantDemandeur']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No sessions found with the current query.</p>";
    }
} catch (PDOException $e) {
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: Test just disponible sessions
echo "<h2>4. Just 'disponible' Sessions:</h2>";
$stmt = $pdo->query("SELECT s.*, u.prenomUtilisateur, u.nomUtilisateur FROM Session s JOIN Mentor m ON s.idMentorAnimateur = m.idMentor JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur WHERE s.statutSession = 'disponible'");
$disponible_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Disponible sessions found:</strong> " . count($disponible_sessions) . "</p>";

if (!empty($disponible_sessions)) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Title</th><th>Date</th><th>Mentor</th></tr>";
    foreach ($disponible_sessions as $session) {
        echo "<tr>";
        echo "<td>{$session['idSession']}</td>";
        echo "<td>{$session['titreSession']}</td>";
        echo "<td>{$session['dateSession']}</td>";
        echo "<td>{$session['prenomUtilisateur']} {$session['nomUtilisateur']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 5: Current date check
echo "<h2>5. Date Check:</h2>";
$stmt = $pdo->query("SELECT CURDATE() as current_date");
$current_date = $stmt->fetchColumn();
echo "<p><strong>Current date:</strong> {$current_date}</p>";

$stmt = $pdo->query("SELECT dateSession FROM Session WHERE statutSession = 'disponible'");
$session_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<p><strong>Available session dates:</strong> " . implode(', ', $session_dates) . "</p>";
?>
