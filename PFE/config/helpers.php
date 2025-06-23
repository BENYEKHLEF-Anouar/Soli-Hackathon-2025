<?php
/**
 * Sanitizes data to prevent XSS attacks.
 * @param mixed $data Input data (string or array).
 * @return mixed Sanitized data.
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Retrieves the web path to a user’s profile image, checking if the file exists on the server.
 * @param string|null $photoUrl Filename of the uploaded photo from the database.
 * @return string Full web path to the image for use in an <img src> tag.
 */
function get_profile_image_path($photoUrl) {
    // Define the server's file system path to the uploads directory.
    // __DIR__ gives the absolute path to the current file's directory (e.g., /var/www/html/mentora/config)
    $server_path_to_uploads = __DIR__ . '/../assets/uploads/';

    // Define the relative web path that the browser will use.
    // This is relative to the files in the 'pages' directory.
    $web_path_to_uploads = '../assets/uploads/';
    $default_web_path = '../assets/images/default_avatar.png';

    // Check if a photo URL is provided, it's not the default, and the file ACTUALLY EXISTS on the server.
    if (!empty($photoUrl) && $photoUrl !== 'default_avatar.png' && file_exists($server_path_to_uploads . $photoUrl)) {
        // If it exists, return the WEB PATH for the browser.
        return $web_path_to_uploads . htmlspecialchars($photoUrl);
    }

    // Otherwise, return the path to the default avatar.
    return $default_web_path;
}


/**
 * Generates a CSRF token for form security.
 * @return string Random token stored in session.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a CSRF token from a form submission.
 * @param string $token Submitted token.
 * @return bool True if valid, false otherwise.
 */
function validate_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['csrf_token']);
        return true;
    }
    return false;
}

/**
 * Logs errors to the server log.
 * @param string $message Error message to log.
 */
function log_error($message) {
    error_log($message);
}

/**
 * Returns a Font Awesome icon class based on the file type.
 * @param string $fileType The type of the file (e.g., 'pdf', 'docx').
 * @return string The corresponding Font Awesome class.
 */
function get_file_icon_class($fileType) {
    switch (strtolower($fileType)) {
        case 'pdf':
            return 'fas fa-file-pdf';
        case 'docx':
            return 'fas fa-file-word';
        case 'pptx':
            return 'fas fa-file-powerpoint';
        case 'video':
            return 'fas fa-file-video';
        case 'audio':
            return 'fas fa-file-audio';
        case 'image':
            return 'fas fa-file-image';
        default:
            return 'fas fa-file-alt';
    }
}

/**
 * Creates a new notification for a user.
 * @param PDO $pdo Database connection.
 * @param int $userId User ID to send notification to.
 * @param string $type Type of notification ('session', 'message', 'badge').
 * @param string $content Notification content message.
 * @return bool True if notification was created successfully.
 */
function create_notification($pdo, $userId, $type, $content) {
    try {
        $stmt = $pdo->prepare("INSERT INTO Notification (idUtilisateur, typeNotification, contenuNotification) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $type, $content]);
    } catch (PDOException $e) {
        log_error("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches unread notifications for a user.
 * @param PDO $pdo Database connection.
 * @param int $userId User ID.
 * @return array Array of notifications with formatted data.
 */
function get_user_notifications($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT idNotification, typeNotification, contenuNotification, dateNotification, estParcourue
            FROM Notification
            WHERE idUtilisateur = ?
            ORDER BY dateNotification DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format notifications for display
        $formatted_notifications = [];
        foreach ($notifications as $notification) {
            $formatted_notifications[] = [
                'id' => $notification['idNotification'],
                'type' => $notification['typeNotification'],
                'icon' => get_notification_icon($notification['typeNotification']),
                'color' => get_notification_color($notification['typeNotification']),
                'text' => $notification['contenuNotification'],
                'time' => format_notification_time($notification['dateNotification']),
                'read' => (bool)$notification['estParcourue']
            ];
        }

        return $formatted_notifications;
    } catch (PDOException $e) {
        log_error("Failed to fetch notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets the count of unread notifications for a user.
 * @param PDO $pdo Database connection.
 * @param int $userId User ID.
 * @return int Number of unread notifications.
 */
function get_unread_notification_count($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Notification WHERE idUtilisateur = ? AND estParcourue = FALSE");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        log_error("Failed to get notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Marks a notification as read.
 * @param PDO $pdo Database connection.
 * @param int $notificationId Notification ID.
 * @param int $userId User ID (for security).
 * @return bool True if marked successfully.
 */
function mark_notification_read($pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE Notification SET estParcourue = TRUE WHERE idNotification = ? AND idUtilisateur = ?");
        return $stmt->execute([$notificationId, $userId]);
    } catch (PDOException $e) {
        log_error("Failed to mark notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Marks all notifications as read for a user.
 * @param PDO $pdo Database connection.
 * @param int $userId User ID.
 * @return bool True if marked successfully.
 */
function mark_all_notifications_read($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE Notification SET estParcourue = TRUE WHERE idUtilisateur = ? AND estParcourue = FALSE");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        log_error("Failed to mark all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes all notifications for a user.
 * @param PDO $pdo Database connection.
 * @param int $userId User ID.
 * @return bool True if deleted successfully.
 */
function delete_all_notifications($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Notification WHERE idUtilisateur = ?");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        log_error("Failed to delete all notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Gets the appropriate icon for a notification type.
 * @param string $type Notification type.
 * @return string Font Awesome icon class.
 */
function get_notification_icon($type) {
    switch ($type) {
        case 'session':
            return 'fa-solid fa-calendar-check';
        case 'message':
            return 'fa-solid fa-envelope';
        case 'badge':
            return 'fa-solid fa-medal';
        default:
            return 'fa-solid fa-bell';
    }
}

/**
 * Gets the appropriate color class for a notification type.
 * @param string $type Notification type.
 * @return string CSS color class.
 */
function get_notification_color($type) {
    switch ($type) {
        case 'session':
            return 'text-success';
        case 'message':
            return 'text-primary';
        case 'badge':
            return 'text-warning';
        default:
            return 'text-info';
    }
}

/**
 * Formats notification timestamp for display.
 * @param string $timestamp Database timestamp.
 * @return string Formatted time string.
 */
function format_notification_time($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'À l\'instant';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "Il y a {$minutes} min";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "Il y a {$hours}h";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "Il y a {$days} jour" . ($days > 1 ? 's' : '');
    } else {
        return date('d/m/Y', $time);
    }
}

/**
 * Creates a badge and assigns it to a user, with notification.
 * @param PDO $pdo Database connection.
 * @param int $userId User ID.
 * @param int $badgeId Badge ID to assign.
 * @return bool True if badge was assigned successfully.
 */
function assign_badge_to_user($pdo, $userId, $badgeId) {
    try {
        // Check if user already has this badge
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Attribution WHERE idUtilisateur = ? AND idBadge = ?");
        $stmt->execute([$userId, $badgeId]);
        if ($stmt->fetchColumn() > 0) {
            return false; // Badge already assigned
        }

        // Get badge information
        $stmt = $pdo->prepare("SELECT nomBadge FROM Badge WHERE idBadge = ?");
        $stmt->execute([$badgeId]);
        $badgeName = $stmt->fetchColumn();

        if (!$badgeName) {
            return false; // Badge doesn't exist
        }

        // Assign badge
        $stmt = $pdo->prepare("INSERT INTO Attribution (idBadge, idUtilisateur, dateAttribution) VALUES (?, ?, CURDATE())");
        $success = $stmt->execute([$badgeId, $userId]);

        if ($success) {
            // Create notification
            $content = "Félicitations ! Vous avez obtenu le badge \"{$badgeName}\" !";
            create_notification($pdo, $userId, 'badge', $content);
        }

        return $success;
    } catch (PDOException $e) {
        log_error("Failed to assign badge: " . $e->getMessage());
        return false;
    }
}
?>