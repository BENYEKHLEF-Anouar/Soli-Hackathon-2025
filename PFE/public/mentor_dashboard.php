<?php
require '../config/config.php';
require '../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. AUTHENTICATION & SECURITY ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mentor') {
    header('Location: login.php'); exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$mentor_user_id = $_SESSION['user']['id'];

// --- 2. DATA FETCHING ---
try {
    // Basic Mentor Data - Check if mentor record exists
    $stmt = $pdo->prepare("SELECT idMentor, competences FROM Mentor WHERE idUtilisateur = ?");
    $stmt->execute([$mentor_user_id]);
    $mentor_data = $stmt->fetch();

    if (!$mentor_data) {
        // Show a helpful error message with instructions
        echo "<!DOCTYPE html><html><head><title>Configuration du profil</title>";
        echo "<style>body{font-family:Arial,sans-serif;max-width:600px;margin:50px auto;padding:20px;text-align:center;}";
        echo ".error-box{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:20px;border-radius:8px;margin:20px 0;}";
        echo ".btn{background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin:10px;}";
        echo "</style></head><body>";
        echo "<h1>Configuration du profil mentor</h1>";
        echo "<div class='error-box'>";
        echo "<h3>Profil mentor incomplet</h3>";
        echo "<p>Votre compte mentor nécessite une configuration supplémentaire.</p>";
        echo "<p>Cela peut arriver si votre inscription n'a pas été complètement finalisée.</p>";
        echo "</div>";
        echo "<p><strong>Solutions :</strong></p>";
        echo "<p>1. <a href='finish_register.php' class='btn'>Finaliser l'inscription</a></p>";
        echo "<p>2. <a href='login.php' class='btn'>Se reconnecter</a></p>";
        echo "<p>3. Contacter l'administrateur si le problème persiste</p>";
        echo "</body></html>";
        exit;
    }

    $mentor_id = $mentor_data['idMentor'];
    $_SESSION['user']['competences'] = $mentor_data['competences'] ?? 'Compétences à définir';

    // Info for Profile Card
    $stmt = $pdo->prepare("SELECT u.nomUtilisateur, u.prenomUtilisateur, u.photoUrl, COALESCE(AVG(p.notation), 0) AS average_rating, COUNT(DISTINCT p.idParticipation) AS review_count FROM Utilisateur u JOIN Mentor m ON u.idUtilisateur = m.idUtilisateur LEFT JOIN Session s ON s.idMentorAnimateur = m.idMentor LEFT JOIN Participation p ON s.idSession = p.idSession WHERE u.idUtilisateur = ? GROUP BY u.idUtilisateur");
    $stmt->execute([$mentor_user_id]);
    $mentor_info = $stmt->fetch();

    // Badges with Icons
    $badge_icons = [
        'Débutant' => 'fa-seedling',
        'Mentor engagé' => 'fa-rocket',
        'Assidu' => 'fa-calendar-check',
        'Orateur' => 'fa-microphone-alt',
        'Expert' => 'fa-medal',
        'Premier Message' => 'fa-envelope',
        'Communicateur' => 'fa-comments'
    ];
    $stmt = $pdo->prepare("SELECT b.nomBadge, b.descriptionBadge FROM Badge b JOIN Attribution a ON b.idBadge = a.idBadge WHERE a.idUtilisateur = ? LIMIT 6");
    $stmt->execute([$mentor_user_id]);
    $badges = $stmt->fetchAll();

    // Check and Assign Badges (Example Logic)
    $stmt = $pdo->prepare("SELECT COUNT(*) as session_count FROM Session WHERE idMentorAnimateur = ? AND statutSession = 'terminee'");
    $stmt->execute([$mentor_id]);
    $session_count = $stmt->fetchColumn();
    if ($session_count >= 10 && !in_array('Mentor engagé', array_column($badges, 'nomBadge'))) {
        $stmt = $pdo->prepare("INSERT INTO Attribution (idBadge, idUtilisateur, dateAttribution) SELECT idBadge, ?, CURDATE() FROM Badge WHERE nomBadge = 'Mentor engagé'");
        $stmt->execute([$mentor_user_id]);
    }

    // Stats
    $stmt = $pdo->prepare("SELECT COUNT(idSession) as sessions_this_month, COALESCE(SUM(tarifSession), 0) as revenue_this_month FROM Session WHERE idMentorAnimateur = ? AND statutSession = 'terminee' AND MONTH(dateSession) = MONTH(CURRENT_DATE()) AND YEAR(dateSession) = YEAR(CURRENT_DATE())");
    $stmt->execute([$mentor_id]);
    $stats_monthly = $stmt->fetch();
    $profile_views = rand(1200, 2500);

    // Chart Data
    $stmt = $pdo->prepare("SELECT YEAR(dateSession) AS year, MONTH(dateSession) AS month, COUNT(idSession) as count FROM Session WHERE idMentorAnimateur = ? AND statutSession = 'terminee' AND dateSession >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY year, month ORDER BY year, month ASC");
    $stmt->execute([$mentor_id]);
    $monthly_counts = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_KEY_PAIR);
    $chart_labels = []; $chart_values = [];
    $month_translations = ['January'=>'Janv','February'=>'Févr','March'=>'Mars','April'=>'Avr','May'=>'Mai','June'=>'Juin','July'=>'Juil','August'=>'Août','September'=>'Sept','October'=>'Oct','November'=>'Nov','December'=>'Déc'];
    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime("first day of -$i month");
        $chart_labels[] = $month_translations[$date->format('F')];
        $chart_values[] = $monthly_counts[$date->format('Y')][$date->format('n')][0] ?? 0;
    }

    // Pending Session Requests, Conversations, Evaluations, Resources
    $stmt = $pdo->prepare("SELECT s.idSession, s.titreSession, s.dateSession, s.heureSession, u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl FROM Session s JOIN Etudiant e ON s.idEtudiantDemandeur = e.idEtudiant JOIN Utilisateur u ON e.idUtilisateur = u.idUtilisateur WHERE s.idMentorAnimateur = ? AND s.statutSession = 'en_attente' ORDER BY s.dateSession ASC");
    $stmt->execute([$mentor_id]);
    $session_requests_data = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT m.contenuMessage, m.dateEnvoi, m.estLue, m.idExpediteur, u.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl FROM Message m JOIN (SELECT GREATEST(idExpediteur, idDestinataire) as u2, LEAST(idExpediteur, idDestinataire) as u1, MAX(idMessage) as max_id FROM Message WHERE ? IN (idExpediteur, idDestinataire) GROUP BY u1, u2) AS last_msg ON m.idMessage = last_msg.max_id JOIN Utilisateur u ON u.idUtilisateur = IF(m.idExpediteur = ?, m.idDestinataire, m.idExpediteur) ORDER BY m.dateEnvoi DESC");
    $stmt->execute([$mentor_user_id, $mentor_user_id]);
    $conversations_data = $stmt->fetchAll();
    $unread_messages_count = 0;
    foreach($conversations_data as $convo) { if ($convo['estLue'] == 0 && $convo['idExpediteur'] != $mentor_user_id) { $unread_messages_count++; } }

    $stmt = $pdo->prepare("SELECT p.notation, p.commentaire, s.titreSession, s.dateSession, u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl FROM Participation p JOIN Session s ON p.idSession = s.idSession JOIN Etudiant e ON p.idEtudiant = e.idEtudiant JOIN Utilisateur u ON e.idUtilisateur = u.idUtilisateur WHERE s.idMentorAnimateur = ? AND p.commentaire IS NOT NULL ORDER BY p.idParticipation DESC LIMIT 5");
    $stmt->execute([$mentor_id]);
    $evaluations_data = $stmt->fetchAll();

    // Fetch published sessions
    $stmt = $pdo->prepare("
        SELECT idSession, titreSession, descriptionSession, dateSession, heureSession,
               tarifSession, statutSession,
               (SELECT COUNT(*) FROM Participation WHERE idSession = s.idSession) as participantCount
        FROM Session s
        WHERE idMentorAnimateur = ?
        ORDER BY dateSession DESC, heureSession DESC
        LIMIT 20
    ");
    $stmt->execute([$mentor_id]);
    $published_sessions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT idRessource, titreRessource, cheminRessource, typeFichier FROM Ressource WHERE idUtilisateur = ? ORDER BY idRessource DESC");
    $stmt->execute([$mentor_user_id]);
    $resources_data = $stmt->fetchAll();

    // --- MODIFIED: Simplified Availability Data Fetching ---
    $stmt = $pdo->prepare("SELECT jourSemaine, TIME_FORMAT(heureDebut, '%H:%i') as heureDebut FROM Disponibilite WHERE idUtilisateur = ?");
    $stmt->execute([$mentor_user_id]);
    $availabilities_raw = $stmt->fetchAll(PDO::FETCH_GROUP);
    $availability_map = [];
    foreach ($availabilities_raw as $day => $slots) {
        $availability_map[$day] = array_column($slots, 'heureDebut');
    }
    $days_of_week = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $time_slots = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    die("Une erreur de base de données est survenue. Veuillez réessayer plus tard.");
}

require '../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="dashboard-container">
    <aside class="profile-sidebar">
        <a href="index.php" class="sidebar-back-link"><i class="fas fa-arrow-left"></i> Retour</a>
        <div class="profile-card">
            <div class="card-image-container"><img src="<?= get_profile_image_path($mentor_info['photoUrl']) ?>" alt="<?= sanitize($mentor_info['prenomUtilisateur']) ?>"></div>
            <div class="card-body">
                <h3 class="profile-name"><?= sanitize($mentor_info['prenomUtilisateur'] . ' ' . $mentor_info['nomUtilisateur']) ?></h3>
                <p class="profile-specialty"><?= sanitize($_SESSION['user']['competences']) ?></p>
                <div class="profile-rating"><i class="fa-solid fa-star"></i><strong><?= number_format($mentor_info['average_rating'], 1) ?></strong><span>(<?= $mentor_info['review_count'] ?> avis)</span></div>
                <div class="badge-showcase">
                    <h4>Mes Badges</h4>
                    <div class="badges-grid">
                        <?php if (empty($badges)): ?><p class="no-badges">Aucun badge.</p><?php else: foreach ($badges as $badge): ?>
                        <div class="badge" data-tooltip="<?= sanitize($badge['descriptionBadge']) ?>"><i class="fas <?= sanitize($badge_icons[$badge['nomBadge']] ?? 'fa-award') ?>"></i></div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer"><a href="edit_profile.php" class="btn-edit-profile"><i class="fas fa-pencil-alt"></i> Modifier le profil</a></div>
        </div>
        <a href="#sessions" class="btn-primary-full-width tab-link" data-tab="sessions"><i class="fas fa-plus"></i> Publier une session</a>
    </aside>

    <div class="dashboard-main-content">
        <nav class="dashboard-nav">
            <ul>
                <li><a href="#statistiques" class="dashboard-tab active" data-tab="statistiques"><i class="fas fa-chart-line"></i> Statistiques</a></li>
                <li><a href="#sessions" class="dashboard-tab" data-tab="sessions"><i class="fas fa-tasks"></i> Sessions <?php if(count($session_requests_data) > 0): ?><span class="notification-badge"><?= count($session_requests_data) ?></span><?php endif; ?></a></li>
                <li><a href="#mes-sessions" class="dashboard-tab" data-tab="mes-sessions"><i class="fas fa-list-alt"></i> Mes Sessions <span class="session-count-badge"><?= count($published_sessions_data) ?></span></a></li>
                <li><a href="#messagerie" class="dashboard-tab" data-tab="messagerie"><i class="fas fa-envelope"></i> Messagerie <?php if($unread_messages_count > 0): ?><span class="notification-badge"><?= $unread_messages_count ?></span><?php endif; ?></a></li>
                <li><a href="#disponibilites" class="dashboard-tab" data-tab="disponibilites"><i class="fas fa-calendar-alt"></i> Disponibilités</a></li>
                <li><a href="#ressources" class="dashboard-tab" data-tab="ressources"><i class="fas fa-book-open"></i> Ressources</a></li>
                <li><a href="#evaluations" class="dashboard-tab" data-tab="evaluations"><i class="fas fa-star-half-alt"></i> Évaluations</a></li>
            </ul>
        </nav>
        
        <div id="feedback-container-global" style="display: none; margin-bottom: 15px;"></div>
        
        <div id="statistiques" class="tab-content active">
            <!-- Stats content unchanged -->
            <h3 class="tab-title">Vos Performances</h3>
            <div class="stats-grid">
                <div class="stat-card"><i class="fas fa-users stat-icon"></i><span class="stat-value"><?= $stats_monthly['sessions_this_month'] ?></span><p class="stat-label">Sessions ce mois-ci</p></div>
                <div class="stat-card"><i class="fas fa-wallet stat-icon"></i><span class="stat-value"><?= number_format($stats_monthly['revenue_this_month'], 0, ',', ' ') ?> €</span><p class="stat-label">Revenus (Mois)</p></div>
                <div class="stat-card"><i class="fas fa-star stat-icon"></i><span class="stat-value"><?= number_format($mentor_info['average_rating'], 1) ?> / 5</span><p class="stat-label">Note moyenne</p></div>
                <div class="stat-card"><i class="fas fa-eye stat-icon"></i><span class="stat-value"><?= number_format($profile_views, 0, ',', ' ') ?></span><p class="stat-label">Vues du profil</p></div>
            </div>
            <div class="chart-container"><h4 class="tab-subtitle">Évolution des sessions (6 derniers mois)</h4><canvas id="sessionsChart"></canvas></div>
        </div>
        
        <div id="sessions" class="tab-content">
            <h3 class="tab-title">Gestion des Sessions</h3>

            <!-- Session Publishing Form -->
            <div class="form-card">
                <h4>Publier une nouvelle session</h4>
                <form id="publish-session-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div class="form-group">
                        <label for="titreSession">Titre de la session</label>
                        <input type="text" id="titreSession" name="titreSession" placeholder="Ex: Cours de Mathématiques - Algèbre" required>
                    </div>
                    <div class="form-group">
                        <label for="descriptionSession">Description</label>
                        <textarea id="descriptionSession" name="descriptionSession" placeholder="Décrivez le contenu de votre session..." rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="dateSession">Date (semaine en cours uniquement)</label>
                        <input type="date" id="dateSession" name="dateSession"
                               min="<?= date('Y-m-d', strtotime('monday this week')) ?>"
                               max="<?= date('Y-m-d', strtotime('sunday this week')) ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="heureSession">Heure</label>
                        <input type="time" id="heureSession" name="heureSession" required>
                    </div>
                    <div class="form-group">
                        <label for="tarifSession">Tarif (€)</label>
                        <input type="number" id="tarifSession" name="tarifSession" min="0" step="0.01" placeholder="25.00" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-add-resource">
                            <i class="fas fa-plus"></i> Publier la session
                        </button>
                    </div>
                </form>
            </div>

            <!-- Session Requests -->
            <div id="session-requests-list">
                <h4 class="tab-subtitle">Demandes en attente</h4>
                <?php if (empty($session_requests_data)): ?><p>Aucune demande de session en attente.</p><?php else: foreach ($session_requests_data as $req): ?>
                    <div class="session-request-card" data-id="<?= $req['idSession'] ?>"><img src="<?= get_profile_image_path($req['photoUrl']) ?>" alt="Photo"><div class="request-details"><p><strong><?= sanitize($req['prenomUtilisateur'].' '.$req['nomUtilisateur']) ?></strong> demande : <strong>"<?= sanitize($req['titreSession']) ?>"</strong></p><small>Pour le <?= date('d/m/Y', strtotime($req['dateSession'])) ?> à <?= substr($req['heureSession'], 0, 5) ?></small></div><div class="request-actions"><button class="btn-accept" data-id="<?= $req['idSession'] ?>" title="Accepter"><i class="fas fa-check"></i></button><button class="btn-decline" data-id="<?= $req['idSession'] ?>" title="Refuser"><i class="fas fa-times"></i></button></div></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        
        <div id="messagerie" class="tab-content">
            <!-- Messagerie content unchanged -->
            <div class="chat-container">
                <div class="conversation-list">
                    <?php if(empty($conversations_data)): ?><p class="empty-chat">Aucune conversation.</p><?php else: foreach($conversations_data as $convo): ?>
                    <div class="conversation-item" data-user-id="<?= $convo['idUtilisateur'] ?>" data-user-name="<?= sanitize($convo['prenomUtilisateur'].' '.$convo['nomUtilisateur']) ?>">
                        <div class="convo-avatar-wrapper"><img src="<?= get_profile_image_path($convo['photoUrl']) ?>"><?php if($convo['estLue'] == 0 && $convo['idExpediteur'] != $mentor_user_id): ?><span class="unread-dot"></span><?php endif; ?></div>
                        <div class="convo-details"><span class="convo-name"><?= sanitize($convo['prenomUtilisateur'].' '.$convo['nomUtilisateur']) ?></span><p class="convo-preview"><?= sanitize(substr($convo['contenuMessage'], 0, 30)) ?>...</p></div>
                        <span class="convo-time"><?= date('H:i', strtotime($convo['dateEnvoi'])) ?></span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="chat-window">
                    <div class="chat-header"><h5 id="chat-header-name">Sélectionnez une conversation</h5></div>
                    <div class="message-area" id="message-area"><p class="empty-chat">Vos messages apparaîtront ici.</p></div>
                    <form class="message-input" id="message-form" style="display: none;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <textarea name="message" placeholder="Écrire un message..." required></textarea>
                        <button type="submit" class="btn-send"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>

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

        <div id="ressources" class="tab-content">
            <h3 class="tab-title">Mes Ressources Pédagogiques</h3>
            <div class="form-card">
                <h4>Ajouter une nouvelle ressource</h4>
                <form id="add-resource-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div class="form-group">
                        <label for="titreRessource">Titre de la ressource</label>
                        <input type="text" id="titreRessource" name="titreRessource" placeholder="Ex: Exercices Corrigés" required>
                    </div>
                    <div class="form-group">
                        <label for="fileUpload" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i> <span id="file-upload-text">Choisir un fichier</span>
                        </label>
                        <input type="file" id="fileUpload" name="fileUpload" hidden required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-add-resource">
                            <i class="fa-solid fa-plus"></i> Ajouter
                        </button>
                    </div>
                </form>
            </div>
            <div class="resource-list-container">
                <h4 class="tab-subtitle">Ressources existantes</h4>
                <div id="resource-list">
                    <?php if (empty($resources_data)): ?>
                        <p id="no-resources-message">Aucune ressource pour le moment.</p>
                    <?php else: foreach ($resources_data as $res): ?>
                        <div class="resource-item" data-id="<?= $res['idRessource'] ?>">
                            <i class="resource-icon <?= get_file_icon_class($res['typeFichier']) ?>"></i>
                            <div class="resource-details">
                                <p class="resource-title"><?= sanitize($res['titreRessource']) ?></p>
                                <p class="resource-info">Type: <?= sanitize($res['typeFichier']) ?></p>
                            </div>
                            <div class="resource-actions">
                                <a href="actions/download_resource.php?id=<?= $res['idRessource'] ?>"
                                   class="action-btn download-resource-btn"
                                   title="Télécharger">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button class="action-btn delete-resource-btn" title="Supprimer" data-id="<?= $res['idRessource'] ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>


        <div id="mes-sessions" class="tab-content">
            <h3 class="tab-title">Mes Sessions Publiées</h3>
            <div class="published-sessions-container">
                <?php if(empty($published_sessions_data)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>Aucune session publiée</h4>
                        <p>Vous n'avez pas encore publié de sessions. Commencez par créer votre première session !</p>
                        <a href="#sessions" class="btn-primary" data-tab="sessions">
                            <i class="fas fa-plus"></i> Publier une session
                        </a>
                    </div>
                <?php else: ?>
                    <div class="sessions-grid" id="published-sessions-list">
                        <?php foreach($published_sessions_data as $session): ?>
                            <div class="session-card" data-id="<?= $session['idSession'] ?>">
                                <div class="session-header">
                                    <div class="session-status status-<?= $session['statutSession'] ?>">
                                        <?php
                                        $statusLabels = [
                                            'disponible' => 'Disponible',
                                            'en_attente' => 'En attente',
                                            'validee' => 'Validée',
                                            'terminee' => 'Terminée',
                                            'annulee' => 'Annulée'
                                        ];
                                        echo $statusLabels[$session['statutSession']] ?? ucfirst($session['statutSession']);
                                        ?>
                                    </div>
                                    <div class="session-actions">
                                        <button class="action-btn edit-session-btn" title="Modifier" data-id="<?= $session['idSession'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete-session-btn" title="Supprimer" data-id="<?= $session['idSession'] ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="session-content">
                                    <h4 class="session-title"><?= htmlspecialchars($session['titreSession']) ?></h4>
                                    <p class="session-description"><?= htmlspecialchars(substr($session['descriptionSession'], 0, 100)) ?><?= strlen($session['descriptionSession']) > 100 ? '...' : '' ?></p>
                                    <div class="session-details">
                                        <div class="session-datetime">
                                            <i class="fas fa-calendar"></i>
                                            <span><?= date('d/m/Y', strtotime($session['dateSession'])) ?></span>
                                            <i class="fas fa-clock"></i>
                                            <span><?= substr($session['heureSession'], 0, 5) ?></span>
                                        </div>
                                        <div class="session-price">
                                            <i class="fas fa-euro-sign"></i>
                                            <span><?= number_format($session['tarifSession'], 2) ?> €</span>
                                        </div>
                                        <div class="session-participants">
                                            <i class="fas fa-users"></i>
                                            <span><?= $session['participantCount'] ?> participant(s)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="evaluations" class="tab-content">
            <!-- Evaluations content unchanged -->
            <h3 class="tab-title">Évaluations de vos sessions</h3>
            <div class="evaluations-container">
                <?php if(empty($evaluations_data)): ?><p>Aucune évaluation.</p><?php else: foreach($evaluations_data as $eval): ?>
                    <div class="evaluation-card"><div class="evaluation-header"><div class="eval-author"><img src="<?= get_profile_image_path($eval['photoUrl']) ?>"><span><?= sanitize($eval['prenomUtilisateur'].' '.$eval['nomUtilisateur']) ?></span></div><div class="eval-rating"><?php for($i=0; $i<5; $i++) echo "<i class='fa" . ($i < $eval['notation'] ? 's' : 'r') . " fa-star'></i>"; ?></div></div><p class="evaluation-comment">"<?= sanitize($eval['commentaire']) ?>"</p><small class="evaluation-date">Pour "<?= sanitize($eval['titreSession']) ?>" le <?= date('d/m/Y', strtotime($eval['dateSession'])) ?></small></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- General Setup ---
    const csrfToken = <?= json_encode($csrf_token) ?>;
    const chartData = { labels: <?= json_encode($chart_labels) ?>, values: <?= json_encode($chart_values) ?> };

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
        if (tabName === 'statistiques') renderChart();
    }
    tabs.forEach(tab => tab.addEventListener('click', e => { e.preventDefault(); activateTab(e.currentTarget.dataset.tab); }));
    activateTab(window.location.hash.substring(1) || 'statistiques');

    // --- Chart Rendering ---
    let sessionsChart = null;
    function renderChart() {
        const canvas = document.getElementById('sessionsChart');
        if (!canvas || Chart.getChart(canvas)) return;
        sessionsChart = new Chart(canvas, { type:'bar', data: { labels: chartData.labels, datasets: [{ label: 'Sessions', data: chartData.values, backgroundColor: '#2563eb', borderRadius: 5, barPercentage: 0.5 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } } } });
    }
    
    // --- Event Delegation for Dynamic Content ---
    document.body.addEventListener('click', async (e) => {
        // Resource Deletion
        const deleteResourceBtn = e.target.closest('.delete-resource-btn');
        if (deleteResourceBtn) {
            handleDeleteResource(deleteResourceBtn);
            return;
        }

        // Session Management
        const deleteSessionBtn = e.target.closest('.delete-session-btn');
        if (deleteSessionBtn) {
            handleDeleteSession(deleteSessionBtn);
            return;
        }

        const editSessionBtn = e.target.closest('.edit-session-btn');
        if (editSessionBtn) {
            handleEditSession(editSessionBtn);
            return;
        }

        // Session Request Handling
        const acceptBtn = e.target.closest('.btn-accept');
        const declineBtn = e.target.closest('.btn-decline');

        if (acceptBtn) {
            handleSessionRequest(acceptBtn, 'accept');
            return;
        }

        if (declineBtn) {
            handleSessionRequest(declineBtn, 'decline');
            return;
        }
    });

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

    // --- Session Publishing ---
    const publishSessionForm = document.getElementById('publish-session-form');

    if (publishSessionForm) {
        console.log('✅ Session form found and event listener attached');
        publishSessionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            console.log('📝 Session form submitted');

            const button = publishSessionForm.querySelector('button[type="submit"]');
            const originalButtonHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publication...';

            try {
                const formData = new FormData(publishSessionForm);
                console.log('📤 Sending request to publish_session.php');

                const response = await fetch('actions/publish_session.php', { method: 'POST', body: formData });
                console.log('📥 Response received:', response.status, response.statusText);

                const result = await response.json();
                console.log('📋 Result:', result);

                if (response.ok && result.status === 'success') {
                    showGlobalFeedback(result.message, 'success');
                    publishSessionForm.reset();

                    // Reset date constraints to current week
                    const dateInput = document.getElementById('dateSession');
                    const today = new Date();
                    const monday = new Date(today.setDate(today.getDate() - today.getDay() + 1));
                    const sunday = new Date(monday);
                    sunday.setDate(monday.getDate() + 6);

                    dateInput.min = monday.toISOString().split('T')[0];
                    dateInput.max = sunday.toISOString().split('T')[0];
                } else {
                    console.error('❌ Session publication failed:', result);
                    throw new Error(result.message || "Erreur lors de la publication.");
                }
            } catch (error) {
                console.error('💥 Session publication error:', error);
                showGlobalFeedback(error.message, 'error');
            } finally {
                button.disabled = false;
                button.innerHTML = originalButtonHtml;
            }
        });
    }

    // --- Resource Management (Full AJAX Implementation) ---
    const addResourceForm = document.getElementById('add-resource-form');
    const resourceList = document.getElementById('resource-list');
    
    addResourceForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const button = addResourceForm.querySelector('button[type="submit"]');
        const originalButtonHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';

        try {
            const formData = new FormData(addResourceForm);
            const response = await fetch('actions/add_resource.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (response.ok && result.status === 'success') {
                showGlobalFeedback(result.message, 'success');
                addResourceForm.reset();
                document.getElementById('file-upload-text').textContent = 'Choisir un fichier';

                // Remove 'no resources' message if it exists
                const noResourcesMessage = document.getElementById('no-resources-message');
                if (noResourcesMessage) noResourcesMessage.remove();

                // Add new resource to the list
                const newResourceHtml = `
                    <div class="resource-item" data-id="${result.resource.idRessource}">
                        <i class="resource-icon ${result.resource.iconClass}"></i>
                        <div class="resource-details">
                            <p class="resource-title">${result.resource.titreRessource}</p>
                            <p class="resource-info">Type: ${result.resource.typeFichier}</p>
                        </div>
                        <div class="resource-actions">
                            <a href="actions/download_resource.php?id=${result.resource.idRessource}"
                               class="action-btn download-resource-btn"
                               title="Télécharger">
                                <i class="fas fa-download"></i>
                            </a>
                            <button class="action-btn delete-resource-btn" title="Supprimer" data-id="${result.resource.idRessource}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>`;
                resourceList.insertAdjacentHTML('afterbegin', newResourceHtml);
            } else {
                throw new Error(result.message || "Erreur lors de l'ajout.");
            }
        } catch (error) {
            showGlobalFeedback(error.message, 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalButtonHtml;
        }
    });

    async function handleDeleteResource(button) {
        const resourceId = button.dataset.id;
        if (!confirm('Voulez-vous vraiment supprimer cette ressource ?')) return;

        const resourceItem = button.closest('.resource-item');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const formData = new FormData();
            formData.append('idRessource', resourceId); // Ensure this matches your PHP script
            formData.append('csrf_token', csrfToken);

            const response = await fetch('actions/delete_resource.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (response.ok && result.status === 'success') {
                showGlobalFeedback(result.message, 'success');
                resourceItem.style.transition = 'opacity 0.3s, transform 0.3s';
                resourceItem.style.opacity = '0';
                resourceItem.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    resourceItem.remove();
                    if (resourceList.children.length === 0) {
                        resourceList.innerHTML = '<p id="no-resources-message">Aucune ressource pour le moment.</p>';
                    }
                }, 300);
            } else {
                throw new Error(result.message || 'Erreur de suppression.');
            }
        } catch (error) {
            showGlobalFeedback(error.message, 'error');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-trash-alt"></i>';
        }
    }

    async function handleSessionRequest(button, action) {
        const sessionId = button.dataset.id;
        const sessionCard = button.closest('.session-request-card');
        const originalHtml = button.innerHTML;

        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const formData = new FormData();
            formData.append('id', sessionId);
            formData.append('action', action);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('actions/handle_session_request.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (response.ok && result.status === 'success') {
                showGlobalFeedback(result.message, 'success');

                // Remove the session card with animation
                sessionCard.style.transition = 'opacity 0.3s, transform 0.3s';
                sessionCard.style.opacity = '0';
                sessionCard.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    sessionCard.remove();

                    // Check if there are no more session requests
                    const remainingRequests = document.querySelectorAll('.session-request-card');
                    if (remainingRequests.length === 0) {
                        const container = document.getElementById('session-requests-list');
                        const subtitle = container.querySelector('.tab-subtitle');
                        subtitle.insertAdjacentHTML('afterend', '<p>Aucune demande de session en attente.</p>');
                    }
                }, 300);
            } else {
                throw new Error(result.message || 'Erreur lors du traitement de la demande.');
            }
        } catch (error) {
            showGlobalFeedback(error.message, 'error');
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }

    const fileInput = document.getElementById('fileUpload');
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const fileUploadText = document.getElementById('file-upload-text');
            fileUploadText.textContent = fileInput.files.length > 0 ? fileInput.files[0].name : 'Choisir un fichier';
        });
    }

    // --- Messaging Functionality ---
    const messageForm = document.getElementById('message-form');
    const messageArea = document.getElementById('message-area');
    const chatHeaderName = document.getElementById('chat-header-name');
    const currentUserId = <?= json_encode($mentor_user_id) ?>;
    let activeChatUserId = null;

    // Conversation item click handlers
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.addEventListener('click', () => {
            const userId = item.dataset.userId;
            const userName = item.dataset.userName;
            openChat(userId, userName);
        });
    });

    async function openChat(userId, userName) {
        document.querySelectorAll('.conversation-item').forEach(item => item.classList.remove('active'));
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
        } catch (error) {
            messageArea.innerHTML = `<p class="empty-chat">Erreur au chargement des messages.</p>`;
        }
    }

    function renderMessages(messages) {
        messageArea.innerHTML = messages.length === 0 ? '<p class="empty-chat">Aucun message. Commencez la conversation !</p>' : '';
        messages.forEach(msg => {
            const msgDiv = document.createElement('div');
            msgDiv.className = `chat-message ${msg.idExpediteur == currentUserId ? 'message-outgoing' : 'message-incoming'}`;
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

        // Clear existing conversations
        conversationList.innerHTML = '';

        if (conversations.length === 0) {
            const emptyMsg = document.createElement('p');
            emptyMsg.className = 'empty-chat';
            emptyMsg.textContent = 'Aucune conversation.';
            conversationList.appendChild(emptyMsg);
            return;
        }

        conversations.forEach(convo => {
            const convoDiv = document.createElement('div');
            convoDiv.className = 'conversation-item';
            convoDiv.dataset.userId = convo.userId;
            convoDiv.dataset.userName = convo.userName;

            // Add active class if this is the current conversation
            if (activeChatUserId && activeChatUserId == convo.userId) {
                convoDiv.classList.add('active');
            }

            convoDiv.innerHTML = `
                <div class="convo-avatar-wrapper">
                    <img src="${convo.userPhoto}">
                    ${convo.unreadCount > 0 ? '<span class="unread-dot"></span>' : ''}
                </div>
                <div class="convo-details">
                    <span class="convo-name">${convo.userName}</span>
                    <p class="convo-preview">${convo.isFromMe ? 'Vous: ' : ''}${convo.lastMessage.substring(0, 30)}${convo.lastMessage.length > 30 ? '...' : ''}</p>
                </div>
                <span class="convo-time">${new Date(convo.lastMessageDate).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}</span>
            `;

            // Add click event listener
            convoDiv.addEventListener('click', () => {
                openChat(convo.userId, convo.userName);
            });

            conversationList.appendChild(convoDiv);
        });
    }

    if (messageForm) {
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
    }

    // --- Session Management Functions ---
    async function handleDeleteSession(button) {
        const sessionId = button.dataset.id;
        const sessionCard = button.closest('.session-card');
        const sessionTitle = sessionCard.querySelector('.session-title').textContent;

        if (!confirm(`Voulez-vous vraiment supprimer la session "${sessionTitle}" ?`)) return;

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const formData = new FormData();
            formData.append('sessionId', sessionId);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('actions/delete_session.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (response.ok && result.status === 'success') {
                showGlobalFeedback(result.message, 'success');

                // Remove session card with animation
                sessionCard.style.transition = 'opacity 0.3s, transform 0.3s';
                sessionCard.style.opacity = '0';
                sessionCard.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    sessionCard.remove();

                    // Check if no more sessions
                    const remainingSessions = document.querySelectorAll('.session-card');
                    if (remainingSessions.length === 0) {
                        const container = document.querySelector('.published-sessions-container');
                        container.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h4>Aucune session publiée</h4>
                                <p>Vous n'avez pas encore publié de sessions. Commencez par créer votre première session !</p>
                                <a href="#sessions" class="btn-primary" data-tab="sessions">
                                    <i class="fas fa-plus"></i> Publier une session
                                </a>
                            </div>
                        `;
                    }
                }, 300);
            } else {
                throw new Error(result.message || 'Erreur lors de la suppression.');
            }
        } catch (error) {
            showGlobalFeedback(error.message, 'error');
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }

    function handleEditSession(button) {
        const sessionId = button.dataset.id;
        const sessionCard = button.closest('.session-card');

        // Get current session data
        const title = sessionCard.querySelector('.session-title').textContent;
        const description = sessionCard.querySelector('.session-description').textContent.replace('...', '');
        const dateText = sessionCard.querySelector('.session-datetime span:first-of-type').textContent;
        const timeText = sessionCard.querySelector('.session-datetime span:last-of-type').textContent;
        const priceText = sessionCard.querySelector('.session-price span').textContent.replace(' €', '');

        // Convert date format from dd/mm/yyyy to yyyy-mm-dd
        const [day, month, year] = dateText.split('/');
        const dateValue = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;

        // Create edit form
        const editForm = document.createElement('div');
        editForm.className = 'session-edit-form';
        editForm.innerHTML = `
            <div class="edit-form-overlay">
                <div class="edit-form-content">
                    <h4>Modifier la session</h4>
                    <form id="edit-session-form-${sessionId}">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="sessionId" value="${sessionId}">

                        <div class="form-group">
                            <label>Titre de la session</label>
                            <input type="text" name="titreSession" value="${title}" required>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="descriptionSession" rows="3" required>${description}</textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="dateSession" value="${dateValue}"
                                       min="<?= date('Y-m-d', strtotime('monday this week')) ?>"
                                       max="<?= date('Y-m-d', strtotime('sunday this week')) ?>"
                                       required>
                            </div>
                            <div class="form-group">
                                <label>Heure</label>
                                <input type="time" name="heureSession" value="${timeText}" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Tarif (€)</label>
                            <input type="number" name="tarifSession" value="${priceText}" min="0" step="0.01" required>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel">Annuler</button>
                            <button type="submit" class="btn-save">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.body.appendChild(editForm);

        // Handle form submission
        const form = editForm.querySelector('form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await handleUpdateSession(form, sessionCard, editForm);
        });

        // Handle cancel
        editForm.querySelector('.btn-cancel').addEventListener('click', () => {
            editForm.remove();
        });

        // Handle overlay click
        editForm.querySelector('.edit-form-overlay').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                editForm.remove();
            }
        });
    }

    async function handleUpdateSession(form, sessionCard, editForm) {
        const submitBtn = form.querySelector('.btn-save');
        const originalBtnText = submitBtn.textContent;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';

        try {
            const formData = new FormData(form);
            const response = await fetch('actions/update_session.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (response.ok && result.status === 'success') {
                showGlobalFeedback(result.message, 'success');

                // Update session card with new data
                const data = result.data;
                sessionCard.querySelector('.session-title').textContent = data.titreSession;
                sessionCard.querySelector('.session-description').textContent = data.descriptionSession.length > 100 ?
                    data.descriptionSession.substring(0, 100) + '...' : data.descriptionSession;

                const dateFormatted = new Date(data.dateSession).toLocaleDateString('fr-FR');
                const timeFormatted = data.heureSession.substring(0, 5);

                sessionCard.querySelector('.session-datetime span:first-of-type').textContent = dateFormatted;
                sessionCard.querySelector('.session-datetime span:last-of-type').textContent = timeFormatted;
                sessionCard.querySelector('.session-price span').textContent = parseFloat(data.tarifSession).toFixed(2) + ' €';

                editForm.remove();
            } else {
                throw new Error(result.message || 'Erreur lors de la modification.');
            }
        } catch (error) {
            showGlobalFeedback(error.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>