<?php
require '../../config/config.php';
require '../../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// 1. Security Checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Méthode non autorisée.', 405);
}
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mentor') {
    send_json_error('Accès refusé.', 403);
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    send_json_error('Erreur de validation CSRF.', 403);
}

$idUtilisateur = $_SESSION['user']['id'];

// 2. Input Validation
$titreSession = trim($_POST['titreSession'] ?? '');
$descriptionSession = trim($_POST['descriptionSession'] ?? '');
$dateSession = $_POST['dateSession'] ?? '';
$heureSession = $_POST['heureSession'] ?? '';
$tarifSession = filter_input(INPUT_POST, 'tarifSession', FILTER_VALIDATE_FLOAT);

if (empty($titreSession) || empty($descriptionSession) || empty($dateSession) || empty($heureSession) || $tarifSession === false || $tarifSession < 0) {
    send_json_error('Tous les champs sont obligatoires et le tarif doit être positif.');
}

// Validate date format and ensure it's not in the past
$sessionDateTime = DateTime::createFromFormat('Y-m-d H:i', $dateSession . ' ' . $heureSession);
if (!$sessionDateTime) {
    send_json_error('Format de date ou heure invalide.');
}

if ($sessionDateTime <= new DateTime()) {
    send_json_error('La date et l\'heure de la session doivent être dans le futur.');
}

// Restrict session publishing to current week only
$currentWeekStart = new DateTime();
$currentWeekStart->setISODate($currentWeekStart->format('Y'), $currentWeekStart->format('W'), 1); // Monday of current week
$currentWeekEnd = clone $currentWeekStart;
$currentWeekEnd->add(new DateInterval('P6D')); // Sunday of current week

if ($sessionDateTime < $currentWeekStart || $sessionDateTime > $currentWeekEnd) {
    send_json_error('Vous ne pouvez publier des sessions que pour la semaine en cours (' .
                   $currentWeekStart->format('d/m/Y') . ' - ' . $currentWeekEnd->format('d/m/Y') . ').');
}

try {
    // Get mentor ID - create mentor record if it doesn't exist
    $stmt = $pdo->prepare("SELECT idMentor FROM Mentor WHERE idUtilisateur = ?");
    $stmt->execute([$idUtilisateur]);
    $mentor = $stmt->fetch();

    if (!$mentor) {
        // Create missing mentor record
        try {
            $stmt = $pdo->prepare("INSERT INTO Mentor (idUtilisateur, competences) VALUES (?, ?)");
            $stmt->execute([$idUtilisateur, 'Compétences à définir']);

            // Get the newly created mentor ID
            $stmt = $pdo->prepare("SELECT idMentor FROM Mentor WHERE idUtilisateur = ?");
            $stmt->execute([$idUtilisateur]);
            $mentor = $stmt->fetch();

            if (!$mentor) {
                send_json_error('Impossible de créer le profil mentor.', 500);
            }
        } catch (PDOException $e) {
            error_log("Failed to create mentor record in publish_session for user $idUtilisateur: " . $e->getMessage());
            send_json_error('Erreur de configuration du profil mentor.', 500);
        }
    }

    $idMentor = $mentor['idMentor'];
    
    // Check if mentor is available at this time
    $dayOfWeek = strtolower($sessionDateTime->format('l')); // Get day name in lowercase
    $sessionTime = $sessionDateTime->format('H:i');
    
    // Convert English day names to French for database lookup
    $dayTranslations = [
        'monday' => 'lundi',
        'tuesday' => 'mardi', 
        'wednesday' => 'mercredi',
        'thursday' => 'jeudi',
        'friday' => 'vendredi',
        'saturday' => 'samedi',
        'sunday' => 'dimanche'
    ];
    
    $frenchDay = $dayTranslations[$dayOfWeek] ?? $dayOfWeek;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM Disponibilite 
        WHERE idUtilisateur = ? 
        AND jourSemaine = ? 
        AND heureDebut <= ? 
        AND heureFin > ?
    ");
    $stmt->execute([$idUtilisateur, $frenchDay, $sessionTime, $sessionTime]);
    
    if ($stmt->fetchColumn() == 0) {
        send_json_error('Vous n\'êtes pas disponible à cette heure. Veuillez vérifier vos disponibilités.');
    }
    
    // Check for conflicting sessions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM Session 
        WHERE idMentorAnimateur = ? 
        AND dateSession = ? 
        AND heureSession = ? 
        AND statutSession IN ('en_attente', 'validee')
    ");
    $stmt->execute([$idMentor, $dateSession, $heureSession]);
    
    if ($stmt->fetchColumn() > 0) {
        send_json_error('Vous avez déjà une session prévue à cette date et heure.');
    }
    
    // Insert the session
    $stmt = $pdo->prepare("
        INSERT INTO Session (titreSession, descriptionSession, dateSession, heureSession, tarifSession, idMentorAnimateur, statutSession) 
        VALUES (?, ?, ?, ?, ?, ?, 'disponible')
    ");
    $stmt->execute([$titreSession, $descriptionSession, $dateSession, $heureSession, $tarifSession, $idMentor]);
    
    $sessionId = $pdo->lastInsertId();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Session publiée avec succès ! Elle est maintenant disponible pour les étudiants.',
        'session' => [
            'idSession' => $sessionId,
            'titreSession' => htmlspecialchars($titreSession),
            'dateSession' => $dateSession,
            'heureSession' => $heureSession,
            'tarifSession' => $tarifSession
        ]
    ]);

} catch (PDOException $e) {
    error_log("Publish session error: " . $e->getMessage());
    send_json_error('Erreur de base de données.', 500);
}
