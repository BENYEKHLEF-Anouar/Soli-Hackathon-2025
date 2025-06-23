<?php
require '../config/config.php';

try {
    echo "Fixing sessions database...\n";
    
    // Step 1: Alter the ENUM to include 'disponible'
    echo "1. Adding 'disponible' to statutSession ENUM...\n";
    $pdo->exec("ALTER TABLE Session MODIFY COLUMN statutSession ENUM('en_attente', 'validee', 'annulee', 'terminee', 'disponible') NOT NULL");
    echo "   ✓ ENUM updated successfully\n";
    
    // Step 2: Insert the missing 'disponible' sessions
    echo "2. Inserting missing 'disponible' sessions...\n";
    
    $sessions_to_insert = [
        [
            'titreSession' => 'Initiation au JavaScript',
            'sujet' => 'Informatique',
            'dateSession' => '2025-07-10',
            'heureSession' => '10:00:00',
            'statutSession' => 'disponible',
            'typeSession' => 'en_ligne',
            'tarifSession' => 25.00,
            'duree_minutes' => 90,
            'niveau' => 'L1',
            'idMentorAnimateur' => 4,
            'descriptionSession' => 'Apprenez les bases du JavaScript avec des exemples pratiques'
        ],
        [
            'titreSession' => 'Mathématiques Appliquées',
            'sujet' => 'Mathématiques',
            'dateSession' => '2025-07-11',
            'heureSession' => '14:00:00',
            'statutSession' => 'disponible',
            'typeSession' => 'en_ligne',
            'tarifSession' => 0.00,
            'duree_minutes' => 60,
            'niveau' => 'L2',
            'idMentorAnimateur' => 5,
            'descriptionSession' => 'Session gratuite sur les mathématiques appliquées'
        ],
        [
            'titreSession' => 'Introduction à la Biologie',
            'sujet' => 'Biologie',
            'dateSession' => '2025-07-12',
            'heureSession' => '09:00:00',
            'statutSession' => 'disponible',
            'typeSession' => 'presentiel',
            'tarifSession' => 20.00,
            'duree_minutes' => 120,
            'niveau' => 'L1',
            'idMentorAnimateur' => 3,
            'descriptionSession' => 'Découvrez les fondamentaux de la biologie'
        ],
        [
            'titreSession' => 'Économie de Base',
            'sujet' => 'Économie',
            'dateSession' => '2025-07-13',
            'heureSession' => '16:00:00',
            'statutSession' => 'disponible',
            'typeSession' => 'en_ligne',
            'tarifSession' => 15.00,
            'duree_minutes' => 75,
            'niveau' => 'L1',
            'idMentorAnimateur' => 6,
            'descriptionSession' => 'Les principes de base de l\'économie expliqués simplement'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO Session (titreSession, sujet, dateSession, heureSession, statutSession, typeSession, tarifSession, duree_minutes, niveau, idMentorAnimateur, descriptionSession)
        VALUES (:titreSession, :sujet, :dateSession, :heureSession, :statutSession, :typeSession, :tarifSession, :duree_minutes, :niveau, :idMentorAnimateur, :descriptionSession)
    ");
    
    foreach ($sessions_to_insert as $session) {
        // Check if session already exists
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM Session WHERE titreSession = ? AND dateSession = ?");
        $check_stmt->execute([$session['titreSession'], $session['dateSession']]);
        
        if ($check_stmt->fetchColumn() == 0) {
            $stmt->execute($session);
            echo "   ✓ Inserted: {$session['titreSession']}\n";
        } else {
            echo "   - Already exists: {$session['titreSession']}\n";
        }
    }
    
    // Step 3: Verify the fix
    echo "3. Verifying the fix...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM Session WHERE statutSession = 'disponible'");
    $disponible_count = $stmt->fetchColumn();
    echo "   ✓ Disponible sessions now: {$disponible_count}\n";
    
    // Step 4: Test the join query
    echo "4. Testing the join query...\n";
    $stmt = $pdo->query("
        SELECT s.idSession, s.titreSession, u.prenomUtilisateur, u.nomUtilisateur
        FROM Session s 
        JOIN Mentor m ON s.idMentorAnimateur = m.idMentor 
        JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur 
        WHERE s.statutSession = 'disponible'
    ");
    $test_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   ✓ Found {count($test_sessions)} sessions with mentors:\n";
    foreach ($test_sessions as $session) {
        echo "     - {$session['titreSession']} by {$session['prenomUtilisateur']} {$session['nomUtilisateur']}\n";
    }
    
    echo "\n✅ Database fix completed successfully!\n";
    echo "You can now visit sessions.php to see the available sessions.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
