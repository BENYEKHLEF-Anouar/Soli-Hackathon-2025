<?php
require '../../config/config.php';
require '../../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

// --- Security Checks ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Erreur CSRF.']);
    exit;
}

$idExpediteur = $_SESSION['user']['id'];
$idDestinataire = filter_input(INPUT_POST, 'recipientId', FILTER_VALIDATE_INT);
$messageType = filter_input(INPUT_POST, 'messageType', FILTER_SANITIZE_SPECIAL_CHARS);

if (!$idDestinataire || !in_array($messageType, ['mentor_contact', 'student_help'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Données manquantes ou invalides.']);
    exit;
}

// Prevent users from contacting themselves
if ($idExpediteur == $idDestinataire) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Vous ne pouvez pas vous contacter vous-même.']);
    exit;
}

try {
    // Get sender and recipient information
    $stmt = $pdo->prepare("SELECT prenomUtilisateur, nomUtilisateur, role FROM Utilisateur WHERE idUtilisateur = ?");
    $stmt->execute([$idExpediteur]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt->execute([$idDestinataire]);
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sender || !$recipient) {
        throw new Exception('Utilisateur non trouvé.');
    }
    
    // Generate appropriate message based on type and roles
    $contenuMessage = '';
    $senderName = $sender['prenomUtilisateur'] . ' ' . $sender['nomUtilisateur'];
    
    if ($messageType === 'mentor_contact' && $sender['role'] === 'etudiant' && $recipient['role'] === 'mentor') {
        $contenuMessage = "Bonjour ! Je suis {$senderName}, étudiant sur Mentora. J'aimerais bénéficier de votre expertise et discuter d'une éventuelle collaboration. Pourriez-vous me dire quand vous seriez disponible pour une session ? Merci !";
    } elseif ($messageType === 'student_help' && $sender['role'] === 'mentor' && $recipient['role'] === 'etudiant') {
        $contenuMessage = "Bonjour ! Je suis {$senderName}, mentor sur Mentora. J'ai vu votre profil et je pense pouvoir vous aider dans vos objectifs d'apprentissage. N'hésitez pas à me contacter si vous souhaitez discuter d'une session de mentorat. À bientôt !";
    } else {
        throw new Exception('Type de message non autorisé pour votre rôle.');
    }
    
    // Check if a conversation already exists between these users
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM Message 
        WHERE (idExpediteur = ? AND idDestinataire = ?) 
           OR (idExpediteur = ? AND idDestinataire = ?)
    ");
    $stmt->execute([$idExpediteur, $idDestinataire, $idDestinataire, $idExpediteur]);
    $conversationExists = $stmt->fetchColumn() > 0;
    
    // Insert the message
    $stmt = $pdo->prepare(
        "INSERT INTO Message (idExpediteur, idDestinataire, contenuMessage) VALUES (?, ?, ?)"
    );
    $stmt->execute([$idExpediteur, $idDestinataire, $contenuMessage]);
    
    $responseMessage = $conversationExists 
        ? 'Message envoyé ! Vous pouvez continuer la conversation dans votre messagerie.'
        : 'Message envoyé ! Une nouvelle conversation a été créée. Rendez-vous dans votre messagerie pour continuer.';
    
    echo json_encode([
        'status' => 'success', 
        'message' => $responseMessage,
        'conversationExists' => $conversationExists
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Send contact message error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
