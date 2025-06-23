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

$currentUserId = $_SESSION['user']['id'];

try {
    // Fetch conversations with latest message for each conversation
    $stmt_conversations = $pdo->prepare("
        SELECT 
            m.contenuMessage, 
            m.dateEnvoi, 
            m.estLue, 
            m.idExpediteur, 
            u.idUtilisateur, 
            u.prenomUtilisateur, 
            u.nomUtilisateur, 
            u.photoUrl,
            (SELECT COUNT(*) FROM Message m2 
             WHERE m2.idExpediteur = u.idUtilisateur 
             AND m2.idDestinataire = ? 
             AND m2.estLue = 0) as unread_count
        FROM Message m 
        JOIN (
            SELECT 
                GREATEST(idExpediteur, idDestinataire) as u2, 
                LEAST(idExpediteur, idDestinataire) as u1, 
                MAX(idMessage) as max_id 
            FROM Message 
            WHERE ? IN (idExpediteur, idDestinataire) 
            GROUP BY u1, u2
        ) AS last_msg ON m.idMessage = last_msg.max_id 
        JOIN Utilisateur u ON u.idUtilisateur = IF(m.idExpediteur = ?, m.idDestinataire, m.idExpediteur) 
        ORDER BY m.dateEnvoi DESC
    ");
    $stmt_conversations->execute([$currentUserId, $currentUserId, $currentUserId]);
    $conversations = $stmt_conversations->fetchAll(PDO::FETCH_ASSOC);
    
    // Format conversations for frontend
    $formatted_conversations = [];
    foreach ($conversations as $convo) {
        $formatted_conversations[] = [
            'userId' => $convo['idUtilisateur'],
            'userName' => $convo['prenomUtilisateur'] . ' ' . $convo['nomUtilisateur'],
            'userPhoto' => get_profile_image_path($convo['photoUrl']),
            'lastMessage' => $convo['contenuMessage'],
            'lastMessageDate' => $convo['dateEnvoi'],
            'unreadCount' => (int)$convo['unread_count'],
            'isFromMe' => $convo['idExpediteur'] == $currentUserId
        ];
    }
    
    echo json_encode([
        'status' => 'success', 
        'conversations' => $formatted_conversations
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Fetch conversations error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur lors du chargement des conversations.']);
}
