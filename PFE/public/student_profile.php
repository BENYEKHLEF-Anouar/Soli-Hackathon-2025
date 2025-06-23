<?php
require '../config/config.php';
require '../config/helpers.php';

// --- DATA FETCHING ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: students.php");
    exit();
}
$studentId = (int)$_GET['id'];

// Main student data
$stmt = $pdo->prepare("
    SELECT u.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur, u.ville, u.photoUrl, e.idEtudiant, e.niveau, e.sujetRecherche
    FROM Etudiant e 
    JOIN Utilisateur u ON e.idUtilisateur = u.idUtilisateur
    WHERE e.idEtudiant = :student_id");
$stmt->execute([':student_id' => $studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) { header("Location: students.php"); exit(); }

// --- NEW: Fetch all student's sessions for the calendar ---
$stmtCalendarSessions = $pdo->prepare("
    SELECT s.titreSession, s.dateSession, s.idSession
    FROM Participation p
    JOIN Session s ON p.idSession = s.idSession
    WHERE p.idEtudiant = :student_id AND s.statutSession IN ('validee', 'terminee')
    ORDER BY s.dateSession, s.heureSession
");
$stmtCalendarSessions->execute([':student_id' => $studentId]);
$calendar_sessions_raw = $stmtCalendarSessions->fetchAll(PDO::FETCH_ASSOC);

// Group sessions by date for easy use in JavaScript
$sessions_for_calendar = [];
foreach ($calendar_sessions_raw as $session) {
    $sessions_for_calendar[$session['dateSession']][] = [
        'title' => $session['titreSession'],
        'id' => $session['idSession']
    ];
}

// --- NEW: Fetch badges earned by the student ---
$stmtBadges = $pdo->prepare("
    SELECT b.nomBadge, b.descriptionBadge FROM Attribution a
    JOIN Badge b ON a.idBadge = b.idBadge
    WHERE a.idUtilisateur = :user_id
    ORDER BY a.dateAttribution DESC LIMIT 6
");
$stmtBadges->execute([':user_id' => $student['idUtilisateur']]);
$badges = $stmtBadges->fetchAll(PDO::FETCH_ASSOC);

// Fetch latest reviews written BY the student
$stmtMyReviews = $pdo->prepare("
    SELECT p.notation, p.commentaire, s.titreSession, u_mentor.prenomUtilisateur AS mentor_prenom
    FROM Participation p
    JOIN Session s ON p.idSession = s.idSession
    JOIN Mentor m ON s.idMentorAnimateur = m.idMentor
    JOIN Utilisateur u_mentor ON m.idUtilisateur = u_mentor.idUtilisateur
    WHERE p.idEtudiant = :student_id AND p.commentaire IS NOT NULL AND p.commentaire != ''
    ORDER BY s.dateSession DESC
    LIMIT 4
");
$stmtMyReviews->execute([':student_id' => $studentId]);
$my_reviews = $stmtMyReviews->fetchAll(PDO::FETCH_ASSOC);


require_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/profile.css?v=<?php echo time(); ?>">

<main class="profile-page-main">
    <div class="container">
       <div class="profile-container">

            <!-- Main Content (Left Column) -->
            <div class="profile-main-content">
                <div class="profile-header-card">
                    <img src="<?= get_profile_image_path($student['photoUrl']) ?>" alt="Photo de <?= htmlspecialchars($student['prenomUtilisateur']) ?>" class="profile-header-avatar">
                    <div class="profile-header-info">
                        <h1><?= htmlspecialchars($student['prenomUtilisateur'] . ' ' . $student['nomUtilisateur']) ?></h1>
                        <div class="profile-header-meta">
                             <?php if (!empty($student['ville'])): ?>
                                <div class="location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($student['ville']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h2>Objectifs d'apprentissage</h2>
                    <p>Cet étudiant cherche à développer ses compétences et connaissances dans les domaines suivants :</p>
                    <div class="tags-list">
                        <?php foreach (explode(',', $student['sujetRecherche']) as $sujet): ?>
                            <span class="tag"><?= htmlspecialchars(trim($sujet)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <br>
                    <span class="tag" style="background-color: var(--slate-100); color: var(--slate-600);">Niveau actuel: <?= htmlspecialchars($student['niveau']) ?></span>
                </div>
                
                <?php if (!empty($my_reviews)): ?>
                <div class="content-card">
                    <h2>Mes derniers avis</h2>
                    <div class="reviews-list">
                        <?php foreach($my_reviews as $review): ?>
                        <div class="review-card">
                             <div class="review-header">
                                <div class="review-rating">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= ($i <= $review['notation']) ? 'filled' : '' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="review-author">pour <strong><?= htmlspecialchars($review['mentor_prenom']) ?></strong></span>
                            </div>
                            <div class="review-body">
                                <p>"<?= htmlspecialchars($review['commentaire']) ?>"</p>
                                <small>Pour la session : <?= htmlspecialchars($review['titreSession']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Sidebar (Right Column) -->
            <aside class="profile-sidebar">
                <?php if (isset($_SESSION['user']) && ($_SESSION['user']['role'] === 'mentor' || ($_SESSION['user']['role'] === 'etudiant' && $_SESSION['user']['id'] != $student['idUtilisateur']))): ?>
                <div class="sidebar-card">
                     <button id="contact-student-btn" class="btn btn-primary" data-recipient-id="<?= $student['idUtilisateur'] ?>">
                         <i class="fas fa-hands-helping"></i> Proposer mon aide
                     </button>
                </div>
                <?php endif; ?>

                <?php if (!empty($sessions_for_calendar)): ?>
                <div class="sidebar-card" id="student-calendar-widget">
                    <h2>Mon activité</h2>
                    <div id="student-calendar">
                        <div class="calendar-header">
                            <button id="prev-month-btn" aria-label="Mois précédent"><i class="fas fa-chevron-left"></i></button>
                            <h3 id="month-year-header"></h3>
                            <button id="next-month-btn" aria-label="Mois suivant"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="calendar-grid">
                           <div class="day-name">Lun</div> <div class="day-name">Mar</div> <div class="day-name">Mer</div> <div class="day-name">Jeu</div> <div class="day-name">Ven</div> <div class="day-name">Sam</div> <div class="day-name">Dim</div>
                        </div>
                        <div class="calendar-grid" id="calendar-days-grid"></div>
                    </div>
                     <div id="session-popover" class="availability-popover">
                        <div id="popover-header" class="popover-header"></div>
                        <div id="popover-sessions" class="popover-slots"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($badges)): ?>
                <div class="sidebar-card">
                    <h2>Badges & Récompenses</h2>
                    <div class="badges-list-sidebar">
                        <?php foreach ($badges as $badge): ?>
                            <div class="badge-item" data-tooltip="<?= htmlspecialchars($badge['descriptionBadge']) ?>">
                                <div class="badge-icon"><i class="fas fa-medal"></i></div>
                                <div class="badge-info"><span><?= htmlspecialchars($badge['nomBadge']) ?></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </aside>

        </div>
    </div>
</main>

<!-- Global feedback container for contact messages -->
<div id="contact-feedback" style="display: none; position: fixed; top: 20px; right: 20px; z-index: 1000; max-width: 400px;"></div>

<script>
// Student Activity Calendar Script
document.addEventListener('DOMContentLoaded', function() {
    // Contact button functionality
    const contactBtn = document.getElementById('contact-student-btn');
    if (contactBtn) {
        contactBtn.addEventListener('click', async function() {
            const recipientId = this.dataset.recipientId;
            const originalText = this.innerHTML;
            
            // Disable button and show loading
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
            
            try {
                const formData = new FormData();
                formData.append('recipientId', recipientId);
                formData.append('messageType', 'student_help');
                formData.append('csrf_token', '<?= generate_csrf_token() ?>');
                
                const response = await fetch('actions/send_contact_message.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showContactFeedback(result.message, 'success');
                    this.innerHTML = '<i class="fas fa-check"></i> Message envoyé !';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 3000);
                } else {
                    throw new Error(result.message || 'Erreur lors de l\'envoi');
                }
            } catch (error) {
                showContactFeedback(error.message, 'error');
                this.innerHTML = originalText;
                this.disabled = false;
            }
        });
    }
    
    function showContactFeedback(message, type) {
        const feedback = document.getElementById('contact-feedback');
        feedback.className = `message ${type}`;
        feedback.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        feedback.style.display = 'block';
        setTimeout(() => {
            feedback.style.display = 'none';
        }, 5000);
    }

    // Data passed from PHP
    const sessionData = <?= json_encode($sessions_for_calendar ?? []); ?>;
    const calendarWidget = document.getElementById('student-calendar-widget');
    if (!calendarWidget) return;

    // Calendar functionality
    let currentDate = new Date();
    const monthNames = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        document.getElementById('month-year-header').textContent = `${monthNames[month]} ${year}`;

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - (firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1));

        const calendarGrid = document.getElementById('calendar-days-grid');
        calendarGrid.innerHTML = '';

        for (let i = 0; i < 42; i++) {
            const cellDate = new Date(startDate);
            cellDate.setDate(startDate.getDate() + i);

            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            dayElement.textContent = cellDate.getDate();

            if (cellDate.getMonth() !== month) {
                dayElement.classList.add('other-month');
            }

            const dateString = cellDate.toISOString().split('T')[0];
            if (sessionData[dateString]) {
                dayElement.classList.add('has-session');
                dayElement.addEventListener('click', () => showSessionPopover(cellDate, sessionData[dateString], dayElement));
            }

            calendarGrid.appendChild(dayElement);
        }
    }

    function showSessionPopover(date, sessions, element) {
        const popover = document.getElementById('session-popover');
        const header = document.getElementById('popover-header');
        const sessionsList = document.getElementById('popover-sessions');

        header.textContent = date.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        sessionsList.innerHTML = '';
        sessions.forEach(session => {
            const sessionElement = document.createElement('div');
            sessionElement.className = 'popover-slot';
            sessionElement.innerHTML = `<i class="fas fa-graduation-cap"></i> ${session.title}`;
            sessionsList.appendChild(sessionElement);
        });

        const rect = element.getBoundingClientRect();
        const calendarRect = calendarWidget.getBoundingClientRect();

        popover.style.display = 'block';
        popover.style.left = `${rect.left - calendarRect.left}px`;
        popover.style.top = `${rect.bottom - calendarRect.top + 5}px`;
    }

    document.getElementById('prev-month-btn').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    document.getElementById('next-month-btn').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    document.addEventListener('click', (e) => {
        if (!calendarWidget.contains(e.target)) {
            document.getElementById('session-popover').style.display = 'none';
        }
    });

    renderCalendar();
});
</script>

<?php require_once '../includes/footer.php'; ?>
