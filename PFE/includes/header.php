<?php
// This file assumes config.php has been included and a session has been started.

// --- DATA FETCHING & PREPARATION ---
$notifications = [];
$notification_count = 0;

// Only fetch notifications if a user is logged in
if (isset($_SESSION['user']['id'])) {
    // Fetch real notifications from database
    $notifications = get_user_notifications($pdo, $_SESSION['user']['id']);
    $notification_count = get_unread_notification_count($pdo, $_SESSION['user']['id']);

    // --- MODIFICATION: GET CURRENT PAGE FOR ACTIVE NAV LINK ---
    $current_page = basename($_SERVER['PHP_SELF']);
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentora - Plateforme de Tutorat Intergénérationnel</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <link rel="stylesheet" href="../assets/css/index.css?v=<?php echo time(); ?>">
    
    <!-- MODIFICATION: Dynamically load page-specific CSS -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'mentors.php'): ?>
        <link rel="stylesheet" href="../assets/css/mentors.css?v=<?php echo time(); ?>">
    <?php elseif (basename($_SERVER['PHP_SELF']) == 'mentor_dashboard.php'): ?>
        <link rel="stylesheet" href="../assets/css/mentor_dashboard.css?v=<?php echo time(); ?>">
    <?php elseif (basename($_SERVER['PHP_SELF']) == 'student_dashboard.php'): ?>
        <link rel="stylesheet" href="../assets/css/student_dashboard.css?v=<?php echo time(); ?>">
    <?php elseif (basename($_SERVER['PHP_SELF']) == 'edit_profile.php'): ?>
        <link rel="stylesheet" href="../assets/css/edit_profile.css?v=<?php echo time(); ?>">
    <?php endif; ?>

    <link rel="icon" href="../assets/images/White_Tower_Symbol.webp" type="image/x-icon">
</head>
<body>
    <div id="preloader">
        <div class="preloader-spinner"></div>
    </div>

    <header>
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <img src="../assets/images/White_Tower_Symbol.webp" alt="Mentora Logo">
                <span class="logo-text">Mentora</span>
            </a>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php" class="<?= ($current_page == 'index.php') ? 'active' : '' ?>"><i class="fas fa-home"></i> Accueil</a></li>
                    <li><a href="mentors.php" class="<?= ($current_page == 'mentors.php') ? 'active' : '' ?>"><i class="fas fa-users"></i> Mentors</a></li>
                    <li><a href="students.php" class="<?= ($current_page == 'students.php') ? 'active' : '' ?>"><i class="fas fa-user-graduate"></i> Étudiants</a></li>
                    <li><a href="sessions.php" class="<?= ($current_page == 'sessions.php') ? 'active' : '' ?>"><i class="fas fa-tasks"></i> Sessions</a></li>
                </ul>
            </nav>
            <div class="nav-right">
                <?php if (isset($_SESSION['user'])): ?>
                    <!-- LOGGED-IN STATE -->
                    <div class="notification-dropdown">
                        <button class="notification-bell-trigger" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <?php if ($notification_count > 0): ?>
                                <span class="notification-badge"><?= $notification_count ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu notification-menu">
                            <div class="dropdown-header">
                                <span>Notifications</span>
                                <a href="#" class="mark-as-read" id="clear-all-notifications">Effacer tout</a>
                            </div>
                            <ul class="notification-list">
                                <?php if (empty($notifications)): ?>
                                    <li class="notification-item empty">
                                        <div class="item-icon-wrapper"><i class="fas fa-check-circle"></i></div>
                                        <div class="item-content">
                                            <p>Vous êtes à jour !</p>
                                            <span>Aucune nouvelle notification.</span>
                                        </div>
                                    </li>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                    <li class="notification-item <?= $notification['read'] ? 'read' : 'unread' ?>" data-notification-id="<?= $notification['id'] ?>">
                                        <div class="item-icon-wrapper <?= htmlspecialchars($notification['color']) ?>">
                                            <i class="<?= htmlspecialchars($notification['icon']) ?>"></i>
                                        </div>
                                        <div class="item-content">
                                            <p><?= htmlspecialchars($notification['text']) ?></p>
                                            <span><?= htmlspecialchars($notification['time']) ?></span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            <div class="dropdown-footer">
                                <a href="notifications.php">Voir toutes les notifications</a>
                            </div>
                        </div>
                    </div>

                    <div class="profile-dropdown">
                        <button class="profile-menu-trigger">
                            <img src="<?= get_profile_image_path($_SESSION['user']['photoUrl']) ?>" class="nav-profile-img" alt="User Avatar">
                            <span><?= htmlspecialchars($_SESSION['user']['prenom']) ?> <?= htmlspecialchars(substr($_SESSION['user']['nom'], 0, 1)) ?>.</span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <?php
                                $dashboard_link = ($_SESSION['user']['role'] === 'mentor') ? 'mentor_dashboard.php' : 'student_dashboard.php';
                                $edit_profile_link = ($_SESSION['user']['role'] === 'mentor') ? 'edit_profile.php' : 'edit_profile2.php';
                                $messagerie_link = $dashboard_link . '#messagerie';
                            ?>
                            <li><a href="<?= $dashboard_link ?>"><i class="fas fa-tachometer-alt"></i> Mon Espace</a></li>
                            <li><a href="<?= $edit_profile_link ?>"><i class="fas fa-user-cog"></i> Modifier Profil</a></li>
                            <li><a href="<?= $messagerie_link ?>"><i class="fas fa-envelope"></i> Messagerie</a></li>
                            <li class="dropdown-separator"></li>
                            <li><a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <!-- LOGGED-OUT STATE -->
                    <a href="login.php" class="login-btn">Login</a>
                    <a href="register.php" class="register-btn">
                        <svg height="24" width="24" fill="#FFFFFF" viewBox="0 0 24 24" class="sparkle"><path d="M10,21.236,6.755,14.745.264,11.5,6.755,8.255,10,1.764l3.245,6.491L19.736,11.5l-6.491,3.245ZM18,21l1.5,3L21,21l3-1.5L21,18l-1.5-3L18,18l-3,1.5ZM19.333,4.667,20.5,7l1.167-2.333L24,3.5,21.667,2.333,20.5,0,19.333,2.333,17,3.5Z"></path></svg>
                        <span class="text">Register</span>
                    </a>
                <?php endif; ?>
            </div>
            <button class="mobile-menu-toggle"><i class="fas fa-bars"></i></button>
        </div>
    </header>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle clear all notifications (delete)
        const clearAllBtn = document.getElementById('clear-all-notifications');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function(e) {
                e.preventDefault();

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
                            // Update notification badge
                            const badge = document.querySelector('.notification-badge');
                            if (badge) {
                                badge.style.display = 'none';
                            }

                            // Remove all notification items
                            const notificationList = document.querySelector('.notification-list');
                            if (notificationList) {
                                notificationList.innerHTML = `
                                    <li class="notification-item empty">
                                        <div class="item-icon-wrapper"><i class="fas fa-check-circle"></i></div>
                                        <div class="item-content">
                                            <p>Vous êtes à jour !</p>
                                            <span>Aucune nouvelle notification.</span>
                                        </div>
                                    </li>
                                `;
                            }

                            // Hide the clear all link
                            clearAllBtn.style.display = 'none';
                        }
                    })
                    .catch(error => console.error('Error:', error));
                }
            });
        }

        // Handle individual notification clicks
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.notificationId;
                if (!notificationId) return;

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

                        // Update notification badge
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            if (data.unread_count > 0) {
                                badge.textContent = data.unread_count;
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });
    });
    </script>