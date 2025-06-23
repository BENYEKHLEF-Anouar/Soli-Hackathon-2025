<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'etudiant') {
    header("Location: login.php");
    exit();
}

// Get session ID from URL
$sessionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$sessionId) {
    header("Location: sessions.php");
    exit();
}

// Get session details
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.prenomUtilisateur AS mentor_prenom, u.nomUtilisateur AS mentor_nom, u.photoUrl AS mentor_photo
        FROM Session s
        JOIN Mentor m ON s.idMentorAnimateur = m.idMentor
        JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
        WHERE s.idSession = ? AND s.statutSession = 'disponible'
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        $_SESSION['error'] = "Session non trouvée ou non disponible.";
        header("Location: sessions.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données.";
    header("Location: sessions.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        unset($_SESSION['csrf_token']);
        
        $message = trim($_POST['message'] ?? '');
        
        try {
            $pdo->beginTransaction();
            
            // Get student ID
            $stmt = $pdo->prepare("SELECT idEtudiant FROM Etudiant WHERE idUtilisateur = ?");
            $stmt->execute([$_SESSION['user']['id']]);
            $studentId = $stmt->fetchColumn();
            
            if (!$studentId) {
                throw new Exception("Profil étudiant non trouvé.");
            }
            
            // Check if student already has a booking for this session
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM Session 
                WHERE idSession = ? AND idEtudiantDemandeur = ?
            ");
            $stmt->execute([$sessionId, $studentId]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Vous avez déjà réservé cette session.");
            }
            
            // Update session with student request
            $stmt = $pdo->prepare("
                UPDATE Session 
                SET idEtudiantDemandeur = ?, statutSession = 'en_attente' 
                WHERE idSession = ? AND statutSession = 'disponible'
            ");
            $stmt->execute([$studentId, $sessionId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("La session n'est plus disponible.");
            }
            
            // Create participation record
            $stmt = $pdo->prepare("
                INSERT INTO Participation (idEtudiant, idSession) 
                VALUES (?, ?)
            ");
            $stmt->execute([$studentId, $sessionId]);
            
            // Send message to mentor if provided
            if (!empty($message)) {
                $stmt = $pdo->prepare("
                    INSERT INTO Message (idExpediteur, idDestinataire, contenuMessage) 
                    VALUES (?, ?, ?)
                ");
                $mentorUserId = $pdo->prepare("SELECT idUtilisateur FROM Mentor WHERE idMentor = ?");
                $mentorUserId->execute([$session['idMentorAnimateur']]);
                $mentorId = $mentorUserId->fetchColumn();
                
                if ($mentorId) {
                    $stmt->execute([$_SESSION['user']['id'], $mentorId, $message]);
                }
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Votre demande de réservation a été envoyée au mentor. Vous recevrez une confirmation par message.";
            header("Location: student_dashboard.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/profile.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="../assets/css/forms.css?v=<?php echo time(); ?>">

<main class="profile-page-main">
    <div class="container">
        <div class="profile-container">
            <!-- Main Content -->
            <div class="profile-main-content">
                <div class="content-card">
                    <h1><i class="fas fa-calendar-plus"></i> Réserver une session</h1>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="session-booking-details">
                        <h2><?= htmlspecialchars($session['titreSession']) ?></h2>
                        <div class="session-info-grid">
                            <div class="info-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?= date_french('l j F Y', strtotime($session['dateSession'])) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span><?= date('H:i', strtotime($session['heureSession'])) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-tag"></i>
                                <span class="<?= ($session['tarifSession'] > 0) ? 'price-paid' : 'price-free' ?>">
                                    <?= ($session['tarifSession'] > 0) ? number_format($session['tarifSession'], 2) . ' €' : 'Gratuit' ?>
                                </span>
                            </div>
                            <?php if ($session['niveau']): ?>
                            <div class="info-item">
                                <i class="fas fa-graduation-cap"></i>
                                <span><?= htmlspecialchars($session['niveau']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($session['descriptionSession']): ?>
                        <div class="session-description">
                            <h3>Description</h3>
                            <p><?= nl2br(htmlspecialchars($session['descriptionSession'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" class="booking-form">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        
                        <div class="form-group">
                            <label for="message">Message au mentor (optionnel)</label>
                            <textarea 
                                id="message" 
                                name="message" 
                                rows="4" 
                                placeholder="Présentez-vous et expliquez vos attentes pour cette session..."
                            ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <a href="sessions.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Envoyer la demande
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sidebar -->
            <aside class="profile-sidebar">
                <div class="sidebar-card">
                    <h3>Mentor</h3>
                    <div class="mentor-info">
                        <img src="<?= get_profile_image_path($session['mentor_photo']) ?>" 
                             alt="Photo de <?= htmlspecialchars($session['mentor_prenom']) ?>" 
                             class="mentor-avatar">
                        <div class="mentor-details">
                            <h4><?= htmlspecialchars($session['mentor_prenom'] . ' ' . $session['mentor_nom']) ?></h4>
                            <a href="mentor_profile.php?id=<?= $session['idMentorAnimateur'] ?>" class="btn btn-outline">
                                Voir le profil
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="sidebar-card">
                    <h3>Informations importantes</h3>
                    <ul class="info-list">
                        <li><i class="fas fa-info-circle"></i> Votre demande sera envoyée au mentor</li>
                        <li><i class="fas fa-clock"></i> Vous recevrez une réponse sous 24h</li>
                        <li><i class="fas fa-envelope"></i> Un message de confirmation vous sera envoyé</li>
                        <?php if ($session['tarifSession'] > 0): ?>
                        <li><i class="fas fa-credit-card"></i> Le paiement se fera après validation</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>
