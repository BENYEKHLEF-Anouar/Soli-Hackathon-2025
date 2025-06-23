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
$contenuMessage = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS));

if (!$idDestinataire || empty($contenuMessage)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Données manquantes.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Insert the message
    $stmt = $pdo->prepare(
        "INSERT INTO Message (idExpediteur, idDestinataire, contenuMessage) VALUES (?, ?, ?)"
    );
    $stmt->execute([$idExpediteur, $idDestinataire, $contenuMessage]);

    // Check if this is the sender's first message
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Message WHERE idExpediteur = ?");
    $stmt->execute([$idExpediteur]);
    $senderMessageCount = $stmt->fetchColumn();

    // Check if this is the recipient's first received message
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Message WHERE idDestinataire = ?");
    $stmt->execute([$idDestinataire]);
    $recipientMessageCount = $stmt->fetchColumn();

    // Get recipient's name for notification
    $stmt = $pdo->prepare("SELECT prenomUtilisateur, nomUtilisateur FROM Utilisateur WHERE idUtilisateur = ?");
    $stmt->execute([$idExpediteur]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);
    $senderName = $sender['prenomUtilisateur'] . ' ' . $sender['nomUtilisateur'];

    // Create notification for recipient about new message
    create_notification($pdo, $idDestinataire, 'message', "Nouveau message de {$senderName}");

    // If this is sender's first message, give them a badge
    if ($senderMessageCount == 1) {
        // Assign "Premier Message" badge (ID 6)
        assign_badge_to_user($pdo, $idExpediteur, 6);
    }

    // If this is recipient's first received message, give them a badge
    if ($recipientMessageCount == 1) {
        // Assign "Communicateur" badge (ID 7)
        assign_badge_to_user($pdo, $idDestinataire, 7);
    }

    $pdo->commit();

    // You could also return the newly created message to append it perfectly
    echo json_encode(['status' => 'success', 'message' => 'Message envoyé.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Send message error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']);
}