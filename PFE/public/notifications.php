<?php
require '../config/config.php';
require '../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- Security Checks ---
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$_SESSION['last_activity'] = time();

// Get all notifications for the user (not just unread)
$stmt = $pdo->prepare("
    SELECT idNotification, typeNotification, contenuNotification, dateNotification, estParcourue
    FROM Notification 
    WHERE idUtilisateur = ? 
    ORDER BY dateNotification DESC
");
$stmt->execute([$_SESSION['user']['id']]);
$all_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format notifications for display
$formatted_notifications = [];
foreach ($all_notifications as $notification) {
    $formatted_notifications[] = [
        'id' => $notification['idNotification'],
        'type' => $notification['typeNotification'],
        'icon' => get_notification_icon($notification['typeNotification']),
        'color' => get_notification_color($notification['typeNotification']),
        'text' => $notification['contenuNotification'],
        'time' => format_notification_time($notification['dateNotification']),
        'date' => date('d/m/Y à H:i', strtotime($notification['dateNotification'])),
        'read' => (bool)$notification['estParcourue']
    ];
}

// Get user's badges for display
$stmt = $pdo->prepare("
    SELECT b.nomBadge, b.descriptionBadge, a.dateAttribution
    FROM Attribution a
    JOIN Badge b ON a.idBadge = b.idBadge
    WHERE a.idUtilisateur = ?
    ORDER BY a.dateAttribution DESC
");
$stmt->execute([$_SESSION['user']['id']]);
$user_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<main class="notifications-page">
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> Mes Notifications</h1>
            <p>Gérez vos notifications et consultez vos badges obtenus</p>
        </div>

        <div class="notifications-content">
            <div class="notifications-section">
                <div class="section-header">
                    <h2>Notifications récentes</h2>
                    <?php if (!empty($formatted_notifications)): ?>
                        <button id="clear-all-notifications" class="btn-secondary">
                            <i class="fas fa-trash"></i> Effacer tout
                        </button>
                    <?php endif; ?>
                </div>

                <div class="notifications-list">
                    <?php if (empty($formatted_notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h3>Aucune notification</h3>
                            <p>Vous n'avez pas encore de notifications.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($formatted_notifications as $notification): ?>
                            <div class="notification-card <?= $notification['read'] ? 'read' : 'unread' ?>" 
                                 data-notification-id="<?= $notification['id'] ?>">
                                <div class="notification-icon <?= $notification['color'] ?>">
                                    <i class="<?= $notification['icon'] ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-text"><?= htmlspecialchars($notification['text']) ?></p>
                                    <span class="notification-time"><?= $notification['date'] ?></span>
                                </div>
                                <?php if (!$notification['read']): ?>
                                    <div class="unread-indicator"></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="badges-section">
                <div class="section-header">
                    <h2>Mes Badges</h2>
                    <span class="badge-count"><?= count($user_badges) ?> badge<?= count($user_badges) > 1 ? 's' : '' ?></span>
                </div>

                <div class="badges-grid">
                    <?php if (empty($user_badges)): ?>
                        <div class="empty-state">
                            <i class="fas fa-medal"></i>
                            <h3>Aucun badge</h3>
                            <p>Continuez à utiliser Mentora pour débloquer des badges !</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($user_badges as $badge): ?>
                            <div class="badge-card">
                                <div class="badge-icon">
                                    <i class="fas fa-medal"></i>
                                </div>
                                <div class="badge-info">
                                    <h3><?= htmlspecialchars($badge['nomBadge']) ?></h3>
                                    <p><?= htmlspecialchars($badge['descriptionBadge']) ?></p>
                                    <span class="badge-date">Obtenu le <?= date('d/m/Y', strtotime($badge['dateAttribution'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.notifications-page {
    padding: 2rem 0;
    min-height: calc(100vh - 200px);
}

.page-header {
    text-align: center;
    margin-bottom: 3rem;
}

.page-header h1 {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.page-header p {
    color: var(--text-muted);
    font-size: 1.1rem;
}

.notifications-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.section-header h2 {
    font-size: 1.5rem;
    color: var(--text-primary);
}

.btn-secondary {
    background: var(--secondary-color);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: var(--secondary-dark);
    transform: translateY(-2px);
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.notification-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    cursor: pointer;
}

.notification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.notification-card.unread {
    border-left: 4px solid var(--primary-color);
    background: linear-gradient(135deg, #fff 0%, #f8f9ff 100%);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-text {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    line-height: 1.5;
    color: var(--text-primary);
}

.notification-time {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.unread-indicator {
    width: 8px;
    height: 8px;
    background: var(--primary-color);
    border-radius: 50%;
    position: absolute;
    top: 1rem;
    right: 1rem;
}

.badges-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.badge-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #fff 0%, #fffbf0 100%);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #f0c674;
}

.badge-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #f0c674 0%, #e6b800 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.badge-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.badge-info p {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: var(--text-muted);
    line-height: 1.4;
}

.badge-date {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-style: italic;
}

.badge-count {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

@media (max-width: 768px) {
    .notifications-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .notification-card {
        padding: 1rem;
    }
    
    .badge-card {
        padding: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clear all notifications (delete)
    const clearAllBtn = document.getElementById('clear-all-notifications');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            if (confirm('Êtes-vous sûr de vouloir supprimer toutes vos notifications ? Cette action est irréversible.')) {
                fetch('actions/mark_notifications_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'csrf_token=<?= generate_csrf_token() ?>&delete_all=1'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    }
    
    // Mark individual notification as read when clicked
    document.querySelectorAll('.notification-card.unread').forEach(card => {
        card.addEventListener('click', function() {
            const notificationId = this.dataset.notificationId;
            
            fetch('actions/mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=<?= generate_csrf_token() ?>&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.classList.remove('unread');
                    this.classList.add('read');
                    const indicator = this.querySelector('.unread-indicator');
                    if (indicator) indicator.remove();
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
