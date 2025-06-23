<?php
require '../../config/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Méthode non autorisée.', 405);
}
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'etudiant') {
    send_json_error('Accès refusé.', 403);
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    send_json_error('Erreur de validation CSRF.', 403);
}

$student_user_id = $_SESSION['user']['id'];
$session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);

if (!$session_id) {
    send_json_error('ID de session invalide.');
}

try {
    // Get student ID from user ID
    $stmt_student = $pdo->prepare("SELECT idEtudiant FROM Etudiant WHERE idUtilisateur = ?");
    $stmt_student->execute([$student_user_id]);
    $student_id = $stmt_student->fetchColumn();

    // Verify the student owns this session and it's not already passed
    $stmt = $pdo->prepare("SELECT statutSession FROM Session WHERE idSession = ? AND idEtudiantDemandeur = ?");
    $stmt->execute([$session_id, $student_id]);
    $session_status = $stmt->fetchColumn();

    if (!$session_status) {
        send_json_error("Session non trouvée ou vous n'êtes pas autorisé à la modifier.", 404);
    }
    if ($session_status === 'terminee' || $session_status === 'annulee') {
        send_json_error('Cette session ne peut plus être annulée.');
    }
    
    // Update session status to 'annulee'
    $stmt_update = $pdo->prepare("UPDATE Session SET statutSession = 'annulee' WHERE idSession = ?");
    $stmt_update->execute([$session_id]);
    
    // Here you could also add a notification for the mentor
    
    echo json_encode(['status' => 'success', 'message' => 'La session a été annulée avec succès.']);

} catch (PDOException $e) {
    error_log("Cancel session error: " . $e->getMessage());
    send_json_error('Erreur de base de données.', 500);
}