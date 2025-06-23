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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_json_error('Méthode non autorisée.', 405); }
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'etudiant') { send_json_error('Accès refusé.', 403); }
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { send_json_error('Erreur CSRF.', 403); }

$student_user_id = $_SESSION['user']['id'];
$session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
$notation = filter_input(INPUT_POST, 'notation', FILTER_VALIDATE_INT);
$commentaire = trim($_POST['commentaire'] ?? '');

// Validation
if (!$session_id) { send_json_error('ID de session invalide.'); }
if (!$notation || $notation < 1 || $notation > 5) { send_json_error('La note doit être entre 1 et 5.'); }

try {
    $pdo->beginTransaction();

    // Get student ID from user ID
    $stmt_student = $pdo->prepare("SELECT idEtudiant FROM Etudiant WHERE idUtilisateur = ?");
    $stmt_student->execute([$student_user_id]);
    $student_id = $stmt_student->fetchColumn();

    // Find the relevant participation record
    $stmt_check = $pdo->prepare("SELECT p.idParticipation, s.statutSession FROM Participation p JOIN Session s ON p.idSession = s.idSession WHERE p.idSession = ? AND p.idEtudiant = ?");
    $stmt_check->execute([$session_id, $student_id]);
    $participation = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$participation) {
        send_json_error("Participation non trouvée.", 404);
    }
    if ($participation['statutSession'] !== 'terminee') {
        send_json_error("Vous ne pouvez évaluer qu'une session terminée.");
    }

    // Update the participation record with the evaluation
    $stmt_update = $pdo->prepare(
        "UPDATE Participation SET notation = ?, commentaire = ? WHERE idParticipation = ?"
    );
    $stmt_update->execute([$notation, $commentaire, $participation['idParticipation']]);
    
    $pdo->commit();
    
    // Optional: Add a badge for giving a first review, etc.
    
    echo json_encode(['status' => 'success', 'message' => 'Merci pour votre évaluation !']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Submit evaluation error: " . $e->getMessage());
    send_json_error('Erreur de base de données.', 500);
}