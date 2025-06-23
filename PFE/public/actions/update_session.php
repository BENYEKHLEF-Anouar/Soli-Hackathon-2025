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
$titreSession = trim($_POST['titreSession'] ?? '');
$descriptionSession = trim($_POST['descriptionSession'] ?? '');
$dateSession = $_POST['dateSession'] ?? '';
$heureSession = $_POST['heureSession'] ?? '';
$tarifSession = filter_input(INPUT_POST, 'tarifSession', FILTER_VALIDATE_FLOAT);

if (!$sessionId) {
    send_json_error('ID de session invalide.');
}

if (empty($titreSession) || empty($descriptionSession) || empty($dateSession) || empty($heureSession) || $tarifSession === false || $tarifSession < 0) {
    send_json_error('Tous les champs sont obligatoires et le tarif doit être positif.');
}

// Validate date format
$sessionDateTime = DateTime::createFromFormat('Y-m-d H:i', $dateSession . ' ' . $heureSession);
if (!$sessionDateTime) {
    send_json_error('Format de date ou heure invalide.');
}

if ($sessionDateTime <= new DateTime()) {
    send_json_error('La date et l\'heure de la session doivent être dans le futur.');
}

// Restrict session updates to current week only
$currentWeekStart = new DateTime();
$currentWeekStart->setISODate($currentWeekStart->format('Y'), $currentWeekStart->format('W'), 1);
$currentWeekEnd = clone $currentWeekStart;
$currentWeekEnd->add(new DateInterval('P6D'));

if ($sessionDateTime < $currentWeekStart || $sessionDateTime > $currentWeekEnd) {
    send_json_error('Vous ne pouvez modifier des sessions que pour la semaine en cours (' . 
                   $currentWeekStart->format('d/m/Y') . ' - ' . $currentWeekEnd->format('d/m/Y') . ').');
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
        SELECT idSession, statutSession, 
               (SELECT COUNT(*) FROM Participation WHERE idSession = ? AND statutParticipation IN ('validee', 'en_attente')) as hasParticipants
        FROM Session 
        WHERE idSession = ? AND idMentorAnimateur = ?
    ");
    $stmt->execute([$sessionId, $sessionId, $mentorId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        send_json_error('Session non trouvée ou vous n\'êtes pas autorisé à la modifier.', 404);
    }
    
    // Check if session can be modified
    if ($session['statutSession'] === 'terminee') {
        send_json_error('Impossible de modifier une session terminée.');
    }
    
    if ($session['hasParticipants'] > 0 && ($dateSession !== date('Y-m-d', strtotime($session['dateSession'])) || $heureSession !== $session['heureSession'])) {
        send_json_error('Impossible de modifier la date/heure d\'une session avec des participants inscrits.');
    }
    
    // Check availability for new time slot (if time changed)
    $dayOfWeek = strtolower($sessionDateTime->format('l'));
    $sessionTime = $sessionDateTime->format('H:i');
    
    $dayTranslations = [
        'monday' => 'lundi', 'tuesday' => 'mardi', 'wednesday' => 'mercredi',
        'thursday' => 'jeudi', 'friday' => 'vendredi', 'saturday' => 'samedi', 'sunday' => 'dimanche'
    ];
    
    $frenchDay = $dayTranslations[$dayOfWeek] ?? $dayOfWeek;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM Disponibilite 
        WHERE idUtilisateur = ? AND jourSemaine = ? AND heureDebut <= ? AND heureFin > ?
    ");
    $stmt->execute([$userId, $frenchDay, $sessionTime, $sessionTime]);
    
    if ($stmt->fetchColumn() == 0) {
        send_json_error('Vous n\'êtes pas disponible à cette nouvelle heure. Veuillez vérifier vos disponibilités.');
    }
    
    // Check for conflicting sessions (excluding current session)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM Session 
        WHERE idMentorAnimateur = ? AND dateSession = ? AND heureSession = ? 
        AND statutSession IN ('en_attente', 'validee', 'disponible') AND idSession != ?
    ");
    $stmt->execute([$mentorId, $dateSession, $heureSession, $sessionId]);
    
    if ($stmt->fetchColumn() > 0) {
        send_json_error('Vous avez déjà une session prévue à cette date et heure.');
    }
    
    // Update the session
    $stmt = $pdo->prepare("
        UPDATE Session 
        SET titreSession = ?, descriptionSession = ?, dateSession = ?, heureSession = ?, tarifSession = ?
        WHERE idSession = ? AND idMentorAnimateur = ?
    ");
    $success = $stmt->execute([$titreSession, $descriptionSession, $dateSession, $heureSession, $tarifSession, $sessionId, $mentorId]);
    
    if ($success && $stmt->rowCount() > 0) {
        send_json_success('Session modifiée avec succès.', [
            'sessionId' => $sessionId,
            'titreSession' => htmlspecialchars($titreSession),
            'descriptionSession' => htmlspecialchars($descriptionSession),
            'dateSession' => $dateSession,
            'heureSession' => $heureSession,
            'tarifSession' => $tarifSession
        ]);
    } else {
        send_json_error('Aucune modification effectuée ou erreur lors de la mise à jour.');
    }

} catch (PDOException $e) {
    error_log("Update session error: " . $e->getMessage());
    send_json_error('Erreur de base de données.', 500);
} catch (Exception $e) {
    error_log("Update session error: " . $e->getMessage());
    send_json_error('Erreur lors de la modification.', 500);
}
?>
