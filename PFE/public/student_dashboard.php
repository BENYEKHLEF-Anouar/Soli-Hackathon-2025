<?php
require '../config/config.php';
require '../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. AUTHENTICATION & SECURITY ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'etudiant') {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$student_user_id = $_SESSION['user']['id'];

// --- 2. DATA FETCHING ---
try {
    // Basic Student Info for Sidebar
    $stmt_student_info = $pdo->prepare("SELECT u.nomUtilisateur, u.prenomUtilisateur, u.photoUrl, e.idEtudiant, e.niveau FROM Utilisateur u JOIN Etudiant e ON u.idUtilisateur = e.idUtilisateur WHERE u.idUtilisateur = ?");
    $stmt_student_info->execute([$student_user_id]);
    $student_info = $stmt_student_info->fetch(PDO::FETCH_ASSOC);
    if (!$student_info) { die("Erreur: Profil étudiant non trouvé."); }
    $student_id = $student_info['idEtudiant'];

    // Sidebar Stats
    $stmt_stats = $pdo->prepare("
        SELECT
        (SELECT COUNT(*) FROM Participation WHERE idEtudiant = ? AND idSession IN (SELECT idSession FROM Session WHERE statutSession = 'terminee')) as sessions_done_count,
        (SELECT COUNT(*) FROM Session WHERE idEtudiantDemandeur = ? AND statutSession = 'en_attente') as sessions_pending_count,
        (SELECT COUNT(DISTINCT idMentorAnimateur) FROM Session WHERE idEtudiantDemandeur = ?) as mentors_contacted_count,
        (SELECT COUNT(DISTINCT sujet) FROM Session WHERE idEtudiantDemandeur = ? AND statutSession = 'terminee') as subjects_studied_count
    ");
    $stmt_stats->execute([$student_id, $student_id, $student_id, $student_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // Badges
    $badge_icons = [
        'Débutant' => 'fa-seedling',
        'Mentor engagé' => 'fa-rocket',
        'Assidu' => 'fa-calendar-check',
        'Orateur' => 'fa-microphone-alt',
        'Expert' => 'fa-medal',
        'Premier Message' => 'fa-envelope',
        'Communicateur' => 'fa-comments'
    ];
    $stmt_badges = $pdo->prepare("SELECT b.nomBadge, b.descriptionBadge FROM Badge b JOIN Attribution a ON b.idBadge = a.idBadge WHERE a.idUtilisateur = ? LIMIT 3");
    $stmt_badges->execute([$student_user_id]);
    $badges = $stmt_badges->fetchAll(PDO::FETCH_ASSOC);

    // Main Dashboard: Next Session & Recent Messages
    $stmt_next_session = $pdo->prepare("SELECT s.titreSession, s.dateSession, s.heureSession, u.prenomUtilisateur as mentorPrenom, u.nomUtilisateur as mentorNom FROM Session s JOIN Mentor m ON s.idMentorAnimateur = m.idMentor JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur WHERE s.idEtudiantDemandeur = ? AND s.statutSession = 'validee' AND CONCAT(s.dateSession, ' ', s.heureSession) >= NOW() ORDER BY s.dateSession, s.heureSession LIMIT 1");
    $stmt_next_session->execute([$student_id]);
    $next_session = $stmt_next_session->fetch(PDO::FETCH_ASSOC);

    $stmt_messages = $pdo->prepare("SELECT m.contenuMessage, u.prenomUtilisateur, u.nomUtilisateur FROM Message m JOIN Utilisateur u ON m.idExpediteur = u.idUtilisateur WHERE m.idDestinataire = ? ORDER BY m.dateEnvoi DESC LIMIT 2");
    $stmt_messages->execute([$student_user_id]);
    $recent_messages = $stmt_messages->fetchAll(PDO::FETCH_ASSOC);

    // "Mes Sessions" Tab: Upcoming and Past
    $stmt_upcoming = $pdo->prepare("SELECT s.idSession, s.titreSession, s.dateSession, s.heureSession, s.statutSession, u.prenomUtilisateur as p, u.nomUtilisateur as n, u.photoUrl as pic FROM Session s JOIN Mentor m ON s.idMentorAnimateur=m.idMentor JOIN Utilisateur u ON m.idUtilisateur=u.idUtilisateur WHERE s.idEtudiantDemandeur=? AND s.statutSession IN ('validee','en_attente') AND CONCAT(s.dateSession, ' ', s.heureSession) >= NOW() ORDER BY s.dateSession, s.heureSession");
    $stmt_upcoming->execute([$student_id]);
    $upcoming_sessions = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);

    $stmt_past = $pdo->prepare("SELECT s.idSession, s.titreSession, s.dateSession, u.prenomUtilisateur as p, u.nomUtilisateur as n, u.photoUrl as pic, p.notation FROM Session s JOIN Mentor m ON s.idMentorAnimateur=m.idMentor JOIN Utilisateur u ON m.idUtilisateur=u.idUtilisateur LEFT JOIN Participation p ON p.idSession = s.idSession AND p.idEtudiant = ? WHERE s.idEtudiantDemandeur=? AND (s.statutSession = 'terminee' OR (s.statutSession = 'validee' AND CONCAT(s.dateSession, ' ', s.heureSession) < NOW())) ORDER BY s.dateSession DESC");
    $stmt_past->execute([$student_id, $student_id]);
    $past_sessions = $stmt_past->fetchAll(PDO::FETCH_ASSOC);

    // "Mes Mentors" Tab
    $stmt_my_mentors = $pdo->prepare("SELECT DISTINCT u.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl, m.competences FROM Utilisateur u JOIN Mentor m ON u.idUtilisateur=m.idUtilisateur JOIN Session s ON s.idMentorAnimateur=m.idMentor WHERE s.idEtudiantDemandeur=?");
    $stmt_my_mentors->execute([$student_id]);
    $my_mentors = $stmt_my_mentors->fetchAll(PDO::FETCH_ASSOC);
    
    // "Messagerie" Tab
    $stmt_conversations = $pdo->prepare("SELECT m.contenuMessage, m.dateEnvoi, m.estLue, m.idExpediteur, u.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl FROM Message m JOIN (SELECT GREATEST(idExpediteur, idDestinataire) as u2, LEAST(idExpediteur, idDestinataire) as u1, MAX(idMessage) as max_id FROM Message WHERE ? IN (idExpediteur, idDestinataire) GROUP BY u1, u2) AS last_msg ON m.idMessage = last_msg.max_id JOIN Utilisateur u ON u.idUtilisateur = IF(m.idExpediteur = ?, m.idDestinataire, m.idExpediteur) ORDER BY m.dateEnvoi DESC");
    $stmt_conversations->execute([$student_user_id, $student_user_id]);
    $conversations_data = $stmt_conversations->fetchAll(PDO::FETCH_ASSOC);

    // Count evaluations to give
    $stmt_eval_todo = $pdo->prepare("SELECT COUNT(*) FROM Participation p JOIN Session s ON p.idSession = s.idSession WHERE p.idEtudiant = ? AND s.statutSession = 'terminee' AND p.notation IS NULL");
    $stmt_eval_todo->execute([$student_id]);
    $evaluations_to_give_count = $stmt_eval_todo->fetchColumn();

    // Count unread messages
    $stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM Message WHERE idDestinataire = ? AND estLue = 0");
    $stmt_unread->execute([$student_user_id]);
    $unread_messages_count = $stmt_unread->fetchColumn();

    // --- Availability Data Fetching ---
    $stmt_availability = $pdo->prepare("SELECT jourSemaine, TIME_FORMAT(heureDebut, '%H:%i') as heureDebut FROM Disponibilite WHERE idUtilisateur = ?");
    $stmt_availability->execute([$student_user_id]);
    $availabilities_raw = $stmt_availability->fetchAll(PDO::FETCH_GROUP);
    $availability_map = [];
    foreach ($availabilities_raw as $day => $slots) {
        $availability_map[$day] = array_column($slots, 'heureDebut');
    }
    $days_of_week = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $time_slots = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];

} catch (PDOException $e) {
    error_log("Student Dashboard Error: " . $e->getMessage());
    die("Une erreur est survenue. Veuillez réessayer plus tard.");
}

require '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/student_dashboard.css?v=<?php echo time(); ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="dashboard-container">
    <aside class="profile-sidebar">
        <a href="index.php" class="sidebar-back-link"><i class="fas fa-arrow-left"></i> Retour</a>
        <div class="profile-card">
            <div class="card-image-container"><img src="<?= get_profile_image_path($student_info['photoUrl']) ?>" alt="<?= sanitize($student_info['prenomUtilisateur']) ?>"></div>
            <div class="card-body">
                <h3 class="profile-name"><?= sanitize($student_info['prenomUtilisateur'] . ' ' . $student_info['nomUtilisateur']) ?></h3>
                <p class="profile-specialty"><?= sanitize($student_info['niveau']) ?></p>
                <div class="profile-rating"><i class="fa-solid fa-star"></i><strong><?= number_format(4.5, 1) ?></strong><span>(<?= $stats['sessions_done_count'] ?> sessions)</span></div>
                <div class="badge-showcase">
                    <h4>Mes Badges</h4>
                    <div class="badges-grid">
                        <?php if (empty($badges)): ?><p class="no-badges">Aucun badge.</p><?php else: foreach ($badges as $badge): ?>
                        <div class="badge" data-tooltip="<?= sanitize($badge['descriptionBadge']) ?>"><i class="fas <?= sanitize($badge_icons[$badge['nomBadge']] ?? 'fa-award') ?>"></i></div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer"><a href="edit_profile2.php" class="btn-edit-profile"><i class="fas fa-pencil-alt"></i> Modifier le profil</a></div>
        </div>
        <a href="sessions.php" class="btn-primary-full-width tab-link" data-tab="mes-sessions"><i class="fas fa-search"></i> Trouver des sessions</a>
    </aside>

    <div class="dashboard-main-content">
        <nav class="dashboard-nav">
            <ul>
                <li><a href="#statistiques" class="dashboard-tab active" data-tab="statistiques"><i class="fas fa-chart-line"></i> Statistiques</a></li>
                <li><a href="#mes-sessions" class="dashboard-tab" data-tab="mes-sessions"><i class="fas fa-tasks"></i> Mes Sessions <?php if($evaluations_to_give_count > 0): ?><span class="notification-badge"><?= $evaluations_to_give_count ?></span><?php endif; ?></a></li>
                <li><a href="#messagerie" class="dashboard-tab" data-tab="messagerie"><i class="fas fa-envelope"></i> Messagerie <?php if($unread_messages_count > 0): ?><span class="notification-badge"><?= $unread_messages_count ?></span><?php endif; ?></a></li>
                <li><a href="#disponibilites" class="dashboard-tab" data-tab="disponibilites"><i class="fas fa-calendar-alt"></i> Disponibilités</a></li>
            </ul>
        </nav>

        <div id="feedback-container-global" style="display: none; margin-bottom: 15px;"></div>

        <div id="statistiques" class="tab-content active">
            <h3 class="tab-title">Vos Statistiques</h3>
            <div class="stats-grid">
                <div class="stat-card"><i class="fas fa-graduation-cap stat-icon"></i><span class="stat-value"><?= $stats['sessions_done_count'] ?></span><p class="stat-label">Sessions suivies</p></div>
                <div class="stat-card"><i class="fas fa-clock stat-icon"></i><span class="stat-value"><?= $stats['sessions_pending_count'] ?></span><p class="stat-label">Sessions en attente</p></div>
                <div class="stat-card"><i class="fas fa-star stat-icon"></i><span class="stat-value"><?= number_format(4.5, 1) ?> / 5</span><p class="stat-label">Note moyenne donnée</p></div>
            </div>

            <div class="dashboard-grid" style="margin-top: 2rem;">
                <div class="info-card">
                    <h3 class="card-title">Prochaine Session</h3>
                    <?php if ($next_session): ?>
                        <div class="session-info">
                            <p class="session-title-dash"><strong><?= sanitize($next_session['titreSession']) ?></strong></p>
                            <p class="session-mentor-dash">avec <?= sanitize($next_session['mentorPrenom'] . ' ' . substr($next_session['mentorNom'], 0, 1) . '.') ?></p>
                            <p class="session-time-dash"><i class="fas fa-calendar-alt"></i> <?= date_french('d M Y', strtotime($next_session['dateSession'])) ?> à <?= date('H:i', strtotime($next_session['heureSession'])) ?></p>
                        </div>
                        <a href="#mes-sessions" class="btn-primary-small tab-link" data-tab="mes-sessions">Voir mes sessions</a>
                    <?php else: ?>
                        <p class="no-data-text" style="padding:0; margin-bottom:1rem;">Aucune session à venir. C'est le moment d'en programmer une !</p>
                        <a href="sessions.php" class="btn-primary-small">Trouver des sessions</a>
                    <?php endif; ?>
                </div>
                <div class="info-card">
                    <h3 class="card-title">Messages Récents</h3>
                    <ul class="message-list">
                        <?php if (empty($recent_messages)): ?><p class="no-data-text" style="padding:0">Aucun message récent.</p><?php else: foreach ($recent_messages as $msg): ?>
                            <li>
                                <p class="message-author"><?= sanitize($msg['prenomUtilisateur'].' '.$msg['nomUtilisateur']) ?></p>
                                <p class="message-preview">"<?= sanitize(substr($msg['contenuMessage'], 0, 45)) ?>..."</p>
                                <a href="#messagerie" class="tab-link" data-tab="messagerie">Lire</a>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div id="mes-sessions" class="tab-content">
            <h3 class="tab-title">Historique de vos sessions</h3>
            <div class="session-list">
                <h4 class="tab-subtitle">Sessions à venir</h4>
                <div id="upcoming-sessions-list">
                    <?php if (empty($upcoming_sessions)): ?><p class="no-data-text">Aucune session à venir.</p><?php else: foreach ($upcoming_sessions as $s): ?>
                    <div class="session-card" data-id="<?= $s['idSession'] ?>">
                        <img src="<?= get_profile_image_path($s['pic']) ?>" class="mentor-avatar" alt="Avatar de <?= sanitize($s['p']) ?>">
                        <div class="session-details">
                            <p class="session-title"><strong><?= sanitize($s['titreSession']) ?></strong> avec <?= sanitize($s['p'].' '.$s['n']) ?></p>
                            <p class="session-time"><i class="fas fa-calendar-day"></i> <?= date_french('l d M Y', strtotime($s['dateSession'])) ?> à <?= date('H:i', strtotime($s['heureSession'])) ?></p>
                        </div>
                        <div class="session-action-area">
                            <?php if($s['statutSession'] == 'en_attente'): ?><span class="session-status pending">En attente</span>
                            <?php else: ?><button class="btn-cancel" data-id="<?= $s['idSession'] ?>">Annuler</button><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <h4 class="tab-subtitle">Sessions passées</h4>
                <div id="past-sessions-list">
                    <?php if (empty($past_sessions)): ?><p class="no-data-text">Aucune session passée.</p><?php else: foreach ($past_sessions as $s): ?>
                    <div class="session-card past" data-id="<?= $s['idSession'] ?>">
                        <img src="<?= get_profile_image_path($s['pic']) ?>" class="mentor-avatar" alt="Avatar de <?= sanitize($s['p']) ?>">
                        <div class="session-details">
                            <p class="session-title"><strong><?= sanitize($s['titreSession']) ?></strong> avec <?= sanitize($s['p'].' '.$s['n']) ?></p>
                            <p class="session-time"><i class="fas fa-calendar-check"></i> Le <?= date_french('d M Y', strtotime($s['dateSession'])) ?></p>
                        </div>
                        <div class="session-action-area">
                            <?php if ($s['notation'] === null): ?><button class="btn-evaluate" data-id="<?= $s['idSession'] ?>">Évaluer</button><?php else: ?>
                            <div class="rating-display" title="Votre note : <?= $s['notation'] ?>/5"><?php for($i=1;$i<=5;$i++)echo "<i class='fa".($i<=$s['notation']?'s':'r')." fa-star'></i>";?></div><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Messagerie Tab -->
        <div id="messagerie" class="tab-content" style="padding:0;">
             <div class="chat-container">
                <div class="conversation-list">
                    <div class="chat-header" style="text-align:center"><h5>Conversations</h5></div>
                    <?php if(empty($conversations_data)): ?><p class="empty-chat" style="padding:1rem;">Aucune conversation.</p><?php else: foreach($conversations_data as $convo): ?>
                    <div class="conversation-item" data-user-id="<?= $convo['idUtilisateur'] ?>" data-user-name="<?= sanitize($convo['prenomUtilisateur'].' '.$convo['nomUtilisateur']) ?>" data-user-photo="<?= get_profile_image_path($convo['photoUrl']) ?>">
                        <div class="convo-avatar-wrapper"><img src="<?= get_profile_image_path($convo['photoUrl']) ?>"><?php if($convo['estLue'] == 0 && $convo['idExpediteur'] != $student_user_id): ?><span class="unread-dot"></span><?php endif; ?></div>
                        <div class="convo-details"><span class="convo-name"><?= sanitize($convo['prenomUtilisateur'].' '.$convo['nomUtilisateur']) ?></span><p class="convo-preview"><?= $convo['idExpediteur'] == $student_user_id ? 'Vous: ' : '' ?><?= sanitize(substr($convo['contenuMessage'], 0, 25)) ?>...</p></div>
                        <span class="convo-time"><?= date('H:i', strtotime($convo['dateEnvoi'])) ?></span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="chat-window">
                    <div class="chat-header"><h5 id="chat-header-name">Sélectionnez une conversation</h5></div>
                    <div class="message-area" id="message-area"><p class="empty-chat">Vos messages apparaîtront ici.</p></div>
                    <form class="message-input" id="message-form" style="display: none;"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><textarea name="message" placeholder="Écrire un message..." required></textarea><button type="submit" class="btn-send"><i class="fas fa-paper-plane"></i></button></form>
                </div>
            </div>
        </div>

        <!-- Mes Mentors Tab -->
        <!-- <div id="mes-mentors" class="tab-content">
            <h2 class="tab-title">Vos Mentors</h2>
            <div class="mentors-grid">
                <?php if (empty($my_mentors)): ?><p class="no-data-text">Vous n'avez pas encore eu de session. <a href="mentors.php" style="color:var(--primary-blue); text-decoration:underline;">Trouvez un mentor</a> pour commencer !</p><?php else: foreach ($my_mentors as $mentor): ?>
                <div class="mentor-card-small">
                    <img src="<?= get_profile_image_path($mentor['photoUrl']) ?>" class="mentor-photo-small" alt="Photo de <?= sanitize($mentor['prenomUtilisateur']) ?>">
                    <h5 class="mentor-name-small"><?= sanitize($mentor['prenomUtilisateur'] . ' ' . $mentor['nomUtilisateur']) ?></h5>
                    <p class="mentor-specialty-small"><?= sanitize(explode(',', $mentor['competences'])[0]) ?></p>
                    <a href="#messagerie" class="btn-contact-small contact-from-grid tab-link" data-tab="messagerie" data-user-id="<?= $mentor['idUtilisateur'] ?>" data-user-name="<?= sanitize($mentor['prenomUtilisateur'].' '.$mentor['nomUtilisateur']) ?>" data-user-photo="<?= get_profile_image_path($mentor['photoUrl']) ?>">Contacter</a>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div> -->

        <div id="disponibilites" class="tab-content">
            <h3 class="tab-title">Gérez vos Disponibilités</h3>
            <div class="availability-card">
                <div class="availability-header"><h4><i class="far fa-calendar-check"></i> Disponibilités hebdomadaires récurrentes</h4><p>Cochez les créneaux où vous êtes généralement disponible.</p></div>
                <form id="availability-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div id="availability-feedback" style="display: none; margin-bottom: 10px;"></div>
                    <div class="availability-grid">
                        <div class="grid-header">Heure</div>
                        <?php foreach ($days_of_week as $day): ?><div class="grid-header"><?= sanitize($day) ?></div><?php endforeach; ?>
                        <?php foreach ($time_slots as $slot): ?>
                            <div class="time-label"><?= sanitize($slot) ?></div>
                            <?php foreach ($days_of_week as $day): ?>
                                <?php
                                $day_db_format = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $day));
                                $is_available = isset($availability_map[$day_db_format]) && in_array($slot, $availability_map[$day_db_format]);
                                ?>
                                <div class="time-slot">
                                    <input type="checkbox" name="slots[<?= $day_db_format ?>][]" value="<?= $slot ?>" <?= $is_available ? 'checked' : '' ?> id="slot-<?= $day_db_format ?>-<?= str_replace(':', '', $slot) ?>">
                                    <label for="slot-<?= $day_db_format ?>-<?= str_replace(':', '', $slot) ?>"></label>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="availability-actions"><button type="submit" class="btn-save-availability"><i class="fas fa-save"></i> Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- Evaluation Modal -->
<div id="evaluation-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <button class="modal-close-btn">×</button>
        <h3>Évaluer la session</h3>
        <form id="evaluation-form">
            <input type="hidden" name="session_id" id="modal-session-id">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label>Votre note :</label>
                <div class="star-rating-input">
                    <i class="far fa-star" data-value="1"></i><i class="far fa-star" data-value="2"></i><i class="far fa-star" data-value="3"></i><i class="far fa-star" data-value="4"></i><i class="far fa-star" data-value="5"></i>
                </div>
                <input type="hidden" name="notation" id="notation-input" required>
            </div>
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label for="commentaire">Votre commentaire (optionnel) :</label>
                <textarea id="commentaire" name="commentaire" rows="4" placeholder="Qu'avez-vous pensé de la session ?"></textarea>
            </div>
            <button type="submit" class="btn-primary">Envoyer l'évaluation</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- General Setup ---
    const csrfToken = <?= json_encode($csrf_token) ?>;
    const currentUserId = <?= json_encode($student_user_id) ?>;
    let activeChatUserId = null;

    // --- Global Feedback Function ---
    const feedbackGlobal = document.getElementById('feedback-container-global');
    function showGlobalFeedback(message, type = 'success') {
        feedbackGlobal.className = `message ${type}`;
        feedbackGlobal.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        feedbackGlobal.style.display = 'block';
        setTimeout(() => { feedbackGlobal.style.display = 'none'; }, 4000);
    }

    // --- Tab Navigation ---
    const tabs = document.querySelectorAll('.dashboard-tab, .tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    function activateTab(tabName) {
        if (!tabName) return;
        tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabName));
        tabContents.forEach(c => c.classList.toggle('active', c.id === tabName));
        if(window.location.hash !== `#${tabName}`) { history.pushState(null, null, `#${tabName}`); }
    }
    tabs.forEach(tab => tab.addEventListener('click', e => { e.preventDefault(); activateTab(e.currentTarget.dataset.tab); }));
    activateTab(window.location.hash.substring(1) || 'statistiques');

    // --- Availability Form ---
    const availabilityForm = document.getElementById('availability-form');
    availabilityForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const feedbackDiv = document.getElementById('availability-feedback');
        feedbackDiv.style.display = 'block';
        feedbackDiv.className = 'message info';
        feedbackDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';

        try {
            const formData = new FormData(availabilityForm);
            const response = await fetch('actions/update_availability.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (response.ok && result.status === 'success') {
                feedbackDiv.className = 'message success';
                feedbackDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${result.message}`;
            } else {
                throw new Error(result.message || 'La mise à jour a échoué');
            }
        } catch (error) {
            feedbackDiv.className = 'message error';
            feedbackDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${error.message}`;
        }
        setTimeout(() => { feedbackDiv.style.display = 'none'; }, 3000);
    });

    // --- Event Delegation for Dynamic Content ---
    document.body.addEventListener('click', async (e) => {
        // Session Actions
        if (e.target.closest('.btn-cancel')) {
            handleCancelSession(e.target.closest('.btn-cancel'));
            return;
        }
        if (e.target.closest('.btn-evaluate')) {
            openEvaluationModal(e.target.closest('.btn-evaluate'));
            return;
        }
        // Modal Actions
        if (e.target.closest('.modal-overlay:not(.modal-content)') || e.target.closest('.modal-close-btn')) {
            document.getElementById('evaluation-modal').style.display = 'none';
            return;
        }
        // Chat Actions
        if (e.target.closest('.conversation-item')) {
            handleConversationClick(e.target.closest('.conversation-item'));
            return;
        }
        if (e.target.closest('.contact-from-grid')) {
            handleContactFromGrid(e.target.closest('.contact-from-grid'));
            return;
        }
    });
    
    // --- Session Actions ---
    async function handleCancelSession(button) {
        const sessionId = button.dataset.id;
        if (!confirm('Êtes-vous sûr de vouloir annuler cette session ?')) return;
        button.disabled = true; button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const formData = new FormData();
            formData.append('session_id', sessionId);
            formData.append('csrf_token', csrfToken);
            const response = await fetch('actions/cancel_session.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Network response was not ok');
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);
            showGlobalFeedback(result.message, 'success');
            const card = button.closest('.session-card');
            card.style.opacity = '0.5';
            button.remove();
        } catch (error) {
            showGlobalFeedback(error.message || "Erreur lors de l'annulation.", 'error');
            button.disabled = false; button.innerHTML = 'Annuler';
        }
    }

    // --- Evaluation Modal & Form ---
    const evaluationModal = document.getElementById('evaluation-modal');
    const evaluationForm = document.getElementById('evaluation-form');
    const stars = evaluationModal.querySelectorAll('.star-rating-input .fa-star');
    
    function openEvaluationModal(button) {
        evaluationForm.reset();
        stars.forEach(s => s.classList.replace('fas','far'));
        document.getElementById('modal-session-id').value = button.dataset.id;
        evaluationModal.style.display = 'flex';
    }

    stars.forEach(star => {
        star.addEventListener('mouseover', function() {
            const value = this.dataset.value;
            stars.forEach(s => s.classList.toggle('fas', s.dataset.value <= value));
            stars.forEach(s => s.classList.toggle('far', s.dataset.value > value));
        });
        star.addEventListener('click', function() {
            document.getElementById('notation-input').value = this.dataset.value;
        });
    });
    evaluationModal.querySelector('.star-rating-input').addEventListener('mouseleave', () => {
        const selectedValue = document.getElementById('notation-input').value;
        stars.forEach(s => s.classList.toggle('fas', s.dataset.value <= selectedValue));
        stars.forEach(s => s.classList.toggle('far', s.dataset.value > selectedValue));
    });

    evaluationForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const button = this.querySelector('[type=submit]');
        const originalButtonHtml = button.innerHTML;
        if (!this.querySelector('#notation-input').value) { alert('Veuillez sélectionner une note.'); return; }
        button.disabled = true; button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
        try {
            const formData = new FormData(this);
            const response = await fetch('actions/submit_evaluation.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Network response was not ok');
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);
            
            showGlobalFeedback(result.message, 'success');
            evaluationModal.style.display = 'none';
            const sessionId = formData.get('session_id');
            const actionArea = document.querySelector(`.session-card[data-id='${sessionId}'] .session-action-area`);
            let starsHtml = '';
            for(let i = 1; i <= 5; i++) { starsHtml += `<i class="fa${i <= formData.get('notation') ? 's' : 'r'} fa-star"></i>`; }
            actionArea.innerHTML = `<div class="rating-display" title="Votre note : ${formData.get('notation')}/5">${starsHtml}</div>`;
        } catch (error) {
            showGlobalFeedback(error.message || "Erreur lors de l'envoi de l'évaluation.", 'error');
        } finally {
            button.disabled = false; button.innerHTML = originalButtonHtml;
        }
    });

    // --- CHAT LOGIC ---
    const messageArea = document.getElementById('message-area');
    const messageForm = document.getElementById('message-form');
    const chatHeaderName = document.getElementById('chat-header-name');
    const convoList = document.querySelector('.conversation-list');

    function handleContactFromGrid(button) {
        const { userId, userName, userPhoto } = button.dataset;
        // Check if a conversation item already exists
        let convoItem = convoList.querySelector(`.conversation-item[data-user-id='${userId}']`);
        if (!convoItem) {
            // Create a new conversation item and add it to the top of the list
            const newConvoHtml = `
            <div class="conversation-item" data-user-id="${userId}" data-user-name="${userName}" data-user-photo="${userPhoto}">
                <div class="convo-avatar-wrapper"><img src="${userPhoto}"></div>
                <div class="convo-details"><span class="convo-name">${userName}</span><p class="convo-preview">Commencez la conversation...</p></div>
                <span class="convo-time"></span>
            </div>`;
            convoList.querySelector('.empty-chat')?.remove();
            convoList.insertAdjacentHTML('beforeend', newConvoHtml);
            convoItem = convoList.querySelector(`.conversation-item[data-user-id='${userId}']`);
        }
        convoItem.click(); // Simulate a click to open the chat
    }

    function handleConversationClick(convoItem) {
        const { userId, userName } = convoItem.dataset;
        openChatWindow(userId, userName);
    }

    async function openChatWindow(userId, userName) {
        document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
        const targetConvo = document.querySelector(`.conversation-item[data-user-id='${userId}']`);
        if(targetConvo) targetConvo.classList.add('active');

        activeChatUserId = userId;
        chatHeaderName.textContent = userName;
        messageArea.innerHTML = '<p class="empty-chat"><i class="fas fa-spinner fa-spin"></i> Chargement des messages...</p>';
        messageForm.style.display = 'flex';
        targetConvo?.querySelector('.unread-dot')?.remove();

        try {
            const formData = new FormData();
            formData.append('userId', activeChatUserId);
            formData.append('csrf_token', csrfToken);
            const response = await fetch('actions/fetch_messages.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Network response was not ok');
            const result = await response.json();
            if (result.status === 'success') {
                renderMessages(result.messages);
            } else {
                throw new Error(result.message);
            }
        } catch (error) { messageArea.innerHTML = `<p class="empty-chat">Erreur au chargement des messages.</p>`; }
    }
    
    function renderMessages(messages) {
        messageArea.innerHTML = messages.length === 0 ? '<p class="empty-chat">Aucun message. Commencez la conversation !</p>' : '';
        messages.forEach(msg => {
            const msgDiv = document.createElement('div');
            msgDiv.className = `chat-message ${msg.idExpediteur == currentUserId ? 'message-outgoing' : 'message-incoming'}`;
            // Sanitize message content before inserting
            const p = document.createElement('p');
            p.innerText = msg.contenuMessage;
            msgDiv.innerHTML = p.innerHTML.replace(/\n/g, '<br>');
            messageArea.appendChild(msgDiv);
        });
        messageArea.scrollTop = messageArea.scrollHeight;
    }

    async function refreshConversations() {
        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);

            const response = await fetch('actions/fetch_conversations.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error('Failed to fetch conversations');

            const result = await response.json();
            if (result.status === 'success') {
                updateConversationsList(result.conversations);
            }
        } catch (error) {
            console.error('Error refreshing conversations:', error);
        }
    }

    function updateConversationsList(conversations) {
        const conversationList = document.querySelector('.conversation-list');
        const header = conversationList.querySelector('.chat-header');

        // Clear existing conversations but keep header
        conversationList.innerHTML = '';
        conversationList.appendChild(header);

        if (conversations.length === 0) {
            const emptyMsg = document.createElement('p');
            emptyMsg.className = 'empty-chat';
            emptyMsg.style.padding = '1rem';
            emptyMsg.textContent = 'Aucune conversation.';
            conversationList.appendChild(emptyMsg);
            return;
        }

        conversations.forEach(convo => {
            const convoDiv = document.createElement('div');
            convoDiv.className = 'conversation-item';
            convoDiv.dataset.userId = convo.userId;
            convoDiv.dataset.userName = convo.userName;
            convoDiv.dataset.userPhoto = convo.userPhoto;

            // Add active class if this is the current conversation
            if (activeChatUserId && activeChatUserId == convo.userId) {
                convoDiv.classList.add('active');
            }

            convoDiv.innerHTML = `
                <img src="${convo.userPhoto}" alt="Photo de ${convo.userName}" class="conversation-avatar">
                <div class="conversation-info">
                    <h6 class="conversation-name">${convo.userName}</h6>
                    <p class="conversation-preview">${convo.isFromMe ? 'Vous: ' : ''}${convo.lastMessage.substring(0, 30)}${convo.lastMessage.length > 30 ? '...' : ''}</p>
                </div>
                ${convo.unreadCount > 0 ? '<div class="unread-dot"></div>' : ''}
            `;

            // Add click event listener
            convoDiv.addEventListener('click', () => {
                openChat(convo.userId, convo.userName, convo.userPhoto);
            });

            conversationList.appendChild(convoDiv);
        });
    }

    messageForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const textarea = e.target.querySelector('textarea');
        const messageText = textarea.value.trim();
        if (!messageText || !activeChatUserId) return;
        
        // Add message locally for instant feedback
        const p = document.createElement('p');
        p.innerText = messageText;
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-message message-outgoing';
        msgDiv.innerHTML = p.innerHTML.replace(/\n/g, '<br>');
        messageArea.querySelector('.empty-chat')?.remove();
        messageArea.appendChild(msgDiv);
        messageArea.scrollTop = messageArea.scrollHeight;
        textarea.value = '';
        
        try {
            const formData = new FormData(messageForm);
            formData.append('recipientId', activeChatUserId);
            // The form has a hidden CSRF token, so it's sent automatically
            const response = await fetch('actions/send_message.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Failed to send');

            // Refresh conversations list to show updated last message
            await refreshConversations();
        } catch (error) {
            console.error('Send error:', error);
            msgDiv.style.opacity = '0.5';
            msgDiv.title = "Le message n'a pas pu être envoyé.";
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>