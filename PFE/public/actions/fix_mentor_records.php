<?php
require '../../config/config.php';
require '../../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// This script should only be run by administrators or during maintenance
// For security, you might want to add additional checks here

try {
    // Find users with role 'mentor' but no corresponding Mentor record
    $stmt = $pdo->prepare("
        SELECT u.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur, u.emailUtilisateur
        FROM Utilisateur u
        LEFT JOIN Mentor m ON u.idUtilisateur = m.idUtilisateur
        WHERE u.role = 'mentor' AND m.idMentor IS NULL
    ");
    $stmt->execute();
    $missing_mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($missing_mentors)) {
        echo "✅ Tous les utilisateurs mentors ont des enregistrements Mentor correspondants.\n";
        exit;
    }

    echo "🔧 Trouvé " . count($missing_mentors) . " utilisateur(s) mentor(s) sans enregistrement Mentor:\n\n";

    $pdo->beginTransaction();

    foreach ($missing_mentors as $user) {
        echo "- Création d'un enregistrement Mentor pour: {$user['prenomUtilisateur']} {$user['nomUtilisateur']} ({$user['emailUtilisateur']})\n";
        
        $stmt = $pdo->prepare("INSERT INTO Mentor (idUtilisateur, competences) VALUES (?, ?)");
        $stmt->execute([$user['idUtilisateur'], 'Compétences à définir']);
    }

    // Similarly, check for students
    $stmt = $pdo->prepare("
        SELECT u.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur, u.emailUtilisateur
        FROM Utilisateur u
        LEFT JOIN Etudiant e ON u.idUtilisateur = e.idUtilisateur
        WHERE u.role = 'etudiant' AND e.idEtudiant IS NULL
    ");
    $stmt->execute();
    $missing_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($missing_students)) {
        echo "\n🔧 Trouvé " . count($missing_students) . " utilisateur(s) étudiant(s) sans enregistrement Etudiant:\n\n";
        
        foreach ($missing_students as $user) {
            echo "- Création d'un enregistrement Etudiant pour: {$user['prenomUtilisateur']} {$user['nomUtilisateur']} ({$user['emailUtilisateur']})\n";
            
            $stmt = $pdo->prepare("INSERT INTO Etudiant (idUtilisateur, niveau, sujetRecherche) VALUES (?, ?, ?)");
            $stmt->execute([$user['idUtilisateur'], 'Non spécifié', 'Besoin d\'aide générale']);
        }
    }

    $pdo->commit();
    echo "\n✅ Réparation terminée avec succès!\n";
    echo "📝 Note: Les utilisateurs concernés devront mettre à jour leurs compétences/informations dans leur profil.\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "❌ Erreur lors de la réparation: " . $e->getMessage() . "\n";
    error_log("Fix mentor records error: " . $e->getMessage());
}
?>
