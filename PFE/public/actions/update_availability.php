<?php
require '../../config/config.php';
require '../../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée.']);
    exit;
}

// --- MODIFICATION HERE ---
// Allow both 'mentor' and 'etudiant' to update their availability
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['mentor', 'etudiant'])) {
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé. Vous devez être connecté.']);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Erreur de validation CSRF.']);
    exit;
}

$idUtilisateur = $_SESSION['user']['id'];
$days_of_week = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
$submitted_slots = $_POST['slots'] ?? [];

try {
    $pdo->beginTransaction();

    // Clear existing availability for the user
    $stmt = $pdo->prepare("DELETE FROM Disponibilite WHERE idUtilisateur = ?");
    $stmt->execute([$idUtilisateur]);

    // Insert new availability
    $stmt_insert = $pdo->prepare(
        "INSERT INTO Disponibilite (jourSemaine, heureDebut, heureFin, idUtilisateur) VALUES (?, ?, ?, ?)"
    );

    foreach ($days_of_week as $day) {
        if (!empty($submitted_slots[$day])) {
            foreach ($submitted_slots[$day] as $time) {
                // Validate time format (e.g., 09:00)
                if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
                    // Skip invalid formats silently or throw an error
                    continue; 
                }
                $heureDebut = $time; // Already in H:i format
                // Assuming 1-hour slots
                $heureFin = date('H:i', strtotime($heureDebut . ' +1 hour')); 
                $stmt_insert->execute([$day, $heureDebut, $heureFin, $idUtilisateur]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Disponibilités mises à jour avec succès.']);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Update availability error for user $idUtilisateur: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur est survenue lors de la mise à jour.']);
}

exit;