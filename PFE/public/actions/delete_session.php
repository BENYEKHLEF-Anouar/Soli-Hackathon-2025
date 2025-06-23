<?php
require '../../config/config.php';
require '../../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function send_json_success($message, $data = null) {
    $response = ['status' => 'success', 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Méthode non autorisée.', 405);
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mentor') {
    send_json_error('Accès refusé.', 403);
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    send_json_error('Erreur de validation CSRF.', 403);
}

// Validate input
$sessionId = filter_input(INPUT_POST, 'sessionId', FILTER_VALIDATE_INT);
if (!$sessionId) {
    send_json_error('ID de session invalide.');
}

$userId = $_SESSION['user']['id'];

try {
    // Get mentor ID
    $stmt = $pdo->prepare("SELECT idMentor FROM Mentor WHERE idUtilisateur = ?");
    $stmt->execute([$userId]);
    $mentor = $stmt->fetch();
    
    if (!$mentor) {
        send_json_error('Profil mentor non trouvé.', 404);
    }
    
    $mentorId = $mentor['idMentor'];
    
    // Check if session exists and belongs to this mentor
    $stmt = $pdo->prepare("
        SELECT idSession, titreSession, statutSession, 
               (SELECT COUNT(*) FROM Participation WHERE idSession = ? AND statutParticipation IN ('validee', 'en_attente')) as hasParticipants
        FROM Session 
        WHERE idSession = ? AND idMentorAnimateur = ?
    ");
    $stmt->execute([$sessionId, $sessionId, $mentorId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        send_json_error('Session non trouvée ou vous n\'êtes pas autorisé à la supprimer.', 404);
    }
    
    // Check if session can be deleted
    if ($session['hasParticipants'] > 0) {
        send_json_error('Impossible de supprimer une session avec des participants inscrits. Veuillez d\'abord annuler la session.');
    }
    
    // Check if session is not already completed
    if ($session['statutSession'] === 'terminee') {
        send_json_error('Impossible de supprimer une session terminée.');
    }
    
    // Delete the session
    $stmt = $pdo->prepare("DELETE FROM Session WHERE idSession = ? AND idMentorAnimateur = ?");
    $success = $stmt->execute([$sessionId, $mentorId]);
    
    if ($success && $stmt->rowCount() > 0) {
        send_json_success('Session supprimée avec succès.', [
            'sessionId' => $sessionId,
            'sessionTitle' => $session['titreSession']
        ]);
    } else {
        send_json_error('Erreur lors de la suppression de la session.');
    }

} catch (PDOException $e) {
    error_log("Delete session error: " . $e->getMessage());
    send_json_error('Erreur de base de données.', 500);
} catch (Exception $e) {
    error_log("Delete session error: " . $e->getMessage());
    send_json_error('Erreur lors de la suppression.', 500);
}
?>
