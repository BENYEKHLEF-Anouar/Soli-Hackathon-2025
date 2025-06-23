<?php
require '../config/config.php';
require '../config/helpers.php';

// --- DATA FETCHING (No changes needed) ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: sessions.php");
    exit();
}
$sessionId = (int)$_GET['id'];
$stmt = $pdo->prepare("
    SELECT s.*, m.idMentor, u.prenomUtilisateur AS mentor_prenom, u.nomUtilisateur AS mentor_nom, u.photoUrl AS mentor_photo
    FROM Session s
    JOIN Mentor m ON s.idMentorAnimateur = m.idMentor
    JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
    WHERE s.idSession = :session_id");
$stmt->execute([':session_id' => $sessionId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) { header("Location: sessions.php"); exit(); }

// --- HELPER FUNCTIONS ---
function formatDuration($m) { return ($m < 60) ? $m . 'min' : floor($m / 60) . 'h' . str_pad($m % 60, 2, '0', STR_PAD_LEFT); }

// This function generates a CSS class based on the session subject
function getSessionStyleClass($subject) {
    if (empty($subject)) {
        return 'session-card--default';
    }
    // Clean up the subject name to create a CSS-friendly slug
    $subject = str_replace(['é', 'è', 'ê', 'à', 'ç', 'ô', 'î', 'û', ' ', '/'], ['e', 'e', 'e', 'a', 'c', 'o', 'i', 'u', '-', '-'], $subject);
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '', $subject));
    return 'session-card--' . trim($slug, '-');
}

require_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/profile.css?v=<?php echo time(); ?>">

<main class="profile-page-main">
    <div class="container">
        <div class="profile-container">
            <!-- Main Content (Left Column) -->
            <div class="profile-main-content">
                <div class="session-page-header <?= getSessionStyleClass($session['sujet']) ?>">
                    <div class="session-header-icon"></div>
                    <span class="tag"><?= htmlspecialchars($session['sujet']) ?></span>
                    <h1><?= htmlspecialchars($session['titreSession']) ?></h1>
                    <p class="lead">Rejoignez cette session pour approfondir vos connaissances et poser vos questions à un expert.</p>
                </div>

                <div class="content-card">
                    <h2>Détails de la session</h2>
                    <div class="session-details-grid">
                        <div class="detail-item"><strong>Date:</strong> <span><?= date('l j F Y', strtotime($session['dateSession'])) ?></span></div>
                        <div class="detail-item"><strong>Heure:</strong> <span><?= date('H:i', strtotime($session['heureSession'])) ?></span></div>
                        <div class="detail-item"><strong>Durée:</strong> <span><?= formatDuration($session['duree_minutes']) ?></span></div>
                        <div class="detail-item"><strong>Niveau:</strong> <span><?= htmlspecialchars($session['niveau']) ?></span></div>
                        <div class="detail-item"><strong>Format:</strong> <span><?= htmlspecialchars($session['typeSession']) ?></span></div>
                        <div class="detail-item"><strong>Tarif:</strong> <span><?= ($session['tarifSession'] > 0) ? number_format($session['tarifSession'], 2) . ' €' : 'Gratuit' ?></span></div>
                    </div>
                </div>
            </div>

            <!-- Sidebar (Right Column) -->
            <aside class="profile-sidebar">
                <div class="sidebar-card">
                     <a href="register_for_session.php?id=<?= $session['idSession'] ?>" class="btn btn-primary"><i class="fas fa-check"></i>  Réserver ma place</a>
                </div>
                 <div class="sidebar-card">
                    <h2>Animé par</h2>
                    <a href="mentor_profile.php?id=<?= $session['idMentor'] ?>" class="session-mentor-card">
                        <img src="<?= get_profile_image_path($session['mentor_photo']) ?>" alt="Photo de <?= htmlspecialchars($session['mentor_prenom']) ?>">
                        <div>
                            <h3><?= htmlspecialchars($session['mentor_prenom'] . ' ' . $session['mentor_nom']) ?></h3>
                        </div>
                    </a>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>