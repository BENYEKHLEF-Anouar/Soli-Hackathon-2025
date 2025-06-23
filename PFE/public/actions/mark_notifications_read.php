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

$userId = $_SESSION['user']['id'];

try {
    if (isset($_POST['mark_all']) && $_POST['mark_all'] == '1') {
        // Mark all notifications as read
        $success = mark_all_notifications_read($pdo, $userId);

        if ($success) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Toutes les notifications ont été marquées comme lues.',
                'unread_count' => 0
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la mise à jour.']);
        }

    } elseif (isset($_POST['delete_all']) && $_POST['delete_all'] == '1') {
        // Delete all notifications
        $success = delete_all_notifications($pdo, $userId);

        if ($success) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Toutes les notifications ont été supprimées.',
                'unread_count' => 0
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la suppression.']);
        }
        
    } elseif (isset($_POST['notification_id'])) {
        // Mark specific notification as read
        $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
        
        if (!$notificationId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID de notification invalide.']);
            exit;
        }
        
        $success = mark_notification_read($pdo, $notificationId, $userId);
        
        if ($success) {
            $unreadCount = get_unread_notification_count($pdo, $userId);
            echo json_encode([
                'status' => 'success', 
                'message' => 'Notification marquée comme lue.',
                'unread_count' => $unreadCount
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la mise à jour.']);
        }
        
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Paramètres manquants.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Mark notifications read error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']);
}
?>
