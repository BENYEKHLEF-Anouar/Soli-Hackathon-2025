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

$currentUserId = $_SESSION['user']['id'];
$otherUserId = filter_input(INPUT_POST, 'userId', FILTER_VALIDATE_INT);

if (!$otherUserId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID utilisateur invalide.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Mark messages as read
    $stmt_update = $pdo->prepare(
        "UPDATE Message SET estLue = 1 WHERE idExpediteur = ? AND idDestinataire = ? AND estLue = 0"
    );
    $stmt_update->execute([$otherUserId, $currentUserId]);

    // Fetch conversation history
    $stmt_fetch = $pdo->prepare(
        "SELECT idMessage, idExpediteur, contenuMessage, dateEnvoi FROM Message
         WHERE (idExpediteur = :currentUser AND idDestinataire = :otherUser)
            OR (idExpediteur = :otherUser AND idDestinataire = :currentUser)
         ORDER BY dateEnvoi ASC"
    );
    $stmt_fetch->execute([':currentUser' => $currentUserId, ':otherUser' => $otherUserId]);
    $messages = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'messages' => $messages]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Fetch messages error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']);
}