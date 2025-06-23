<?php
require '../config/config.php';
require '../config/helpers.php';

// --- DATA FETCHING ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: mentors.php");
    exit();
}
$mentorId = (int)$_GET['id'];

// Mentor's main data
$stmt = $pdo->prepare("
    SELECT u.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur, u.ville, u.photoUrl, m.idMentor, m.competences,
           AVG(p.notation) AS average_rating, COUNT(DISTINCT p.idParticipation) AS review_count
    FROM Mentor m
    JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
    LEFT JOIN Session s ON s.idMentorAnimateur = m.idMentor
    LEFT JOIN Participation p ON p.idSession = s.idSession AND p.notation IS NOT NULL
    WHERE m.idMentor = :mentor_id
    GROUP BY u.idUtilisateur, m.idMentor, m.competences
");
$stmt->execute([':mentor_id' => $mentorId]);
$mentor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mentor) { header("Location: mentors.php"); exit(); }

// Fetch availability
$stmtDispo = $pdo->prepare("SELECT jourSemaine, TIME_FORMAT(heureDebut, '%H:%i') as heureDebut, TIME_FORMAT(heureFin, '%H:%i') as heureFin FROM Disponibilite WHERE idUtilisateur = :user_id ORDER BY FIELD(jourSemaine, 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'), heureDebut");
$stmtDispo->execute([':user_id' => $mentor['idUtilisateur']]);
$availability_raw = $stmtDispo->fetchAll(PDO::FETCH_ASSOC);
$availability_grouped = [];
foreach ($availability_raw as $slot) {
    $availability_grouped[$slot['jourSemaine']][] = $slot['heureDebut'] . ' - ' . $slot['heureFin'];
}

// Fetch upcoming sessions
$stmtSessions = $pdo->prepare("SELECT * FROM Session WHERE idMentorAnimateur = :mentor_id AND statutSession IN ('en_attente', 'validee') AND dateSession >= CURDATE() ORDER BY dateSession ASC, heureSession ASC LIMIT 5");
$stmtSessions->execute([':mentor_id' => $mentorId]);
$sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

// Badges with Icons
$badge_icons = ['Débutant' => 'fa-seedling', 'Mentor engagé' => 'fa-rocket', 'Assidu' => 'fa-calendar-check', 'Orateur' => 'fa-microphone-alt', 'Expert' => 'fa-medal'];
$stmtBadges = $pdo->prepare("SELECT b.nomBadge, b.descriptionBadge FROM Attribution a JOIN Badge b ON a.idBadge = b.idBadge WHERE a.idUtilisateur = :user_id ORDER BY a.dateAttribution DESC LIMIT 6");
$stmtBadges->execute([':user_id' => $mentor['idUtilisateur']]);
$badges = $stmtBadges->fetchAll(PDO::FETCH_ASSOC);

// Fetch latest reviews
$stmtReviews = $pdo->prepare("
    SELECT p.notation, p.commentaire, s.titreSession, u_etudiant.prenomUtilisateur AS student_prenom
    FROM Participation p
    JOIN Session s ON p.idSession = s.idSession
    JOIN Etudiant e ON p.idEtudiant = e.idEtudiant
    JOIN Utilisateur u_etudiant ON e.idUtilisateur = u_etudiant.idUtilisateur
    WHERE s.idMentorAnimateur = :mentor_id AND p.commentaire IS NOT NULL AND p.commentaire != ''
    ORDER BY s.dateSession DESC
    LIMIT 4
");
$stmtReviews->execute([':mentor_id' => $mentorId]);
$reviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);

// Fetch mentor's resources
$stmtResources = $pdo->prepare("
    SELECT idRessource, titreRessource, cheminRessource, typeFichier
    FROM Ressource
    WHERE idUtilisateur = :user_id
    ORDER BY idRessource DESC
    LIMIT 6
");
$stmtResources->execute([':user_id' => $mentor['idUtilisateur']]);
$resources = $stmtResources->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/profile.css?v=<?php echo time(); ?>">

<main class="profile-page-main">
    <div class="container">
        <div class="profile-container">

            <!-- Main Content (Left Column) -->
            <div class="profile-main-content">
                <div class="profile-header-card">
                    <img src="<?= get_profile_image_path($mentor['photoUrl']) ?>" alt="Photo de <?= htmlspecialchars($mentor['prenomUtilisateur']) ?>" class="profile-header-avatar">
                    <div class="profile-header-info">
                        <h1><?= htmlspecialchars($mentor['prenomUtilisateur'] . ' ' . $mentor['nomUtilisateur']) ?></h1>
                        <div class="profile-header-meta">
                            <div class="rating">
                                <i class="fas fa-star"></i>
                                <span><?= $mentor['review_count'] > 0 ? number_format($mentor['average_rating'], 1) : 'N/A'; ?></span>
                                <small>(<?= $mentor['review_count'] ?> avis)</small>
                            </div>
                            <?php if (!empty($mentor['ville'])): ?>
                                <div class="location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($mentor['ville']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h2>À propos de ce mentor</h2>
                    <p>Spécialiste dans les domaines suivants, ce mentor est prêt à vous accompagner dans votre parcours d'apprentissage.</p>
                    <div class="tags-list">
                        <?php foreach (explode(',', $mentor['competences']) as $skill): ?>
                            <span class="tag"><?= htmlspecialchars(trim($skill)) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($sessions)): ?>
                <div class="content-card">
                    <h2>Sessions à venir</h2>
                    <div class="schedule-list">
                        <?php foreach($sessions as $session): ?>
                            <a href="session_details.php?id=<?= $session['idSession'] ?>" class="schedule-item-link">
                                <div class="schedule-item">
                                    <span class="schedule-day"><?= htmlspecialchars($session['titreSession']) ?></span>
                                    <span class="schedule-tag"><?= date('d M Y', strtotime($session['dateSession'])) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($reviews)): ?>
                <div class="content-card">
                    <h2>Derniers avis</h2>
                    <div class="reviews-list">
                        <?php foreach($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="review-rating">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= ($i <= $review['notation']) ? 'filled' : '' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="review-author">par <strong><?= htmlspecialchars($review['student_prenom']) ?></strong></span>
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

                <?php if (!empty($resources)): ?>
                <div class="content-card">
                    <h2>Ressources Pédagogiques</h2>
                    <div class="resources-grid">
                        <?php foreach($resources as $resource): ?>
                            <div class="resource-item-profile">
                                <div class="resource-icon-container">
                                    <i class="resource-icon <?= get_file_icon_class($resource['typeFichier']) ?>"></i>
                                </div>
                                <div class="resource-content">
                                    <h4 class="resource-title"><?= htmlspecialchars($resource['titreRessource']) ?></h4>
                                    <p class="resource-type"><?= ucfirst($resource['typeFichier']) ?></p>
                                </div>
                                <div class="resource-actions">
                                    <a href="actions/download_resource.php?id=<?= $resource['idRessource'] ?>"
                                       class="resource-download-btn"
                                       title="Télécharger">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar (Right Column) -->
            <aside class="profile-sidebar">
                <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'etudiant'): ?>
                <div class="sidebar-card">
                     <button id="contact-mentor-btn" class="btn btn-primary" data-recipient-id="<?= $mentor['idUtilisateur'] ?>">
                         <i class="fas fa-paper-plane"></i>&nbsp; Contacter ce Mentor
                     </button>
                </div>
                <?php endif; ?>

                <?php if (!empty($availability_grouped)): ?>
                <div class="sidebar-card" id="availability-calendar-widget">
                    <h2>Disponibilités récurrentes</h2>
                    <div id="availability-calendar">
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
                    <div id="availability-popover" class="availability-popover">
                        <div id="popover-header" class="popover-header"></div>
                        <div id="popover-slots" class="popover-slots"></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($badges)): ?>
                <div class="sidebar-card">
                    <h2>Badges & Récompenses</h2>
                    <div class="badges-list-sidebar">
                        <?php foreach ($badges as $badge): ?>
                            <div class="badge-item" data-tooltip="<?= htmlspecialchars($badge['descriptionBadge']) ?>">
                                <div class="badge-icon"><i class="fas <?= htmlspecialchars($badge_icons[$badge['nomBadge']] ?? 'fa-award') ?>"></i></div>
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
document.addEventListener('DOMContentLoaded', function() {
    // Contact button functionality
    const contactBtn = document.getElementById('contact-mentor-btn');
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
                formData.append('messageType', 'mentor_contact');
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
    const availabilityData = <?= json_encode($availability_grouped); ?>;
    const calendarWidget = document.getElementById('availability-calendar-widget');
    if (!calendarWidget) return;

    const monthYearHeader = document.getElementById('month-year-header');
    const daysGrid = document.getElementById('calendar-days-grid');
    const prevMonthBtn = document.getElementById('prev-month-btn');
    const nextMonthBtn = document.getElementById('next-month-btn');
    const popover = document.getElementById('availability-popover');
    const popoverHeader = document.getElementById('popover-header');
    const popoverSlots = document.getElementById('popover-slots');

    let currentDate = new Date();
    let selectedDayElement = null;
    const dayIndexToName = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    function renderCalendar() {
        popover.classList.remove('show');
        selectedDayElement?.classList.remove('selected');
        selectedDayElement = null;

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        monthYearHeader.textContent = new Date(year, month).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
        daysGrid.innerHTML = '';
        
        const firstDayOfMonth = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let startingDay = firstDayOfMonth.getDay();
        if (startingDay === 0) startingDay = 6; else startingDay -= 1;

        for (let i = 0; i < startingDay; i++) { daysGrid.appendChild(document.createElement('div')); }

        for (let day = 1; day <= daysInMonth; day++) {
            const dayEl = document.createElement('div');
            dayEl.classList.add('calendar-day');
            dayEl.textContent = day;
            const today = new Date();
            if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dayEl.classList.add('today');
            }
            const currentDayDate = new Date(year, month, day);
            const dayOfWeekName = dayIndexToName[currentDayDate.getDay()];

            if (availabilityData[dayOfWeekName]) {
                dayEl.classList.add('available');
                dayEl.dataset.date = currentDayDate.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
                dayEl.dataset.slots = JSON.stringify(availabilityData[dayOfWeekName]);
                dayEl.addEventListener('click', handleDayClick);
            }
            daysGrid.appendChild(dayEl);
        }
    }

    function handleDayClick(event) {
        const target = event.currentTarget;
        if (selectedDayElement) { selectedDayElement.classList.remove('selected'); }
        if (selectedDayElement === target) {
            popover.classList.remove('show');
            selectedDayElement = null;
            return;
        }
        target.classList.add('selected');
        selectedDayElement = target;
        const slots = JSON.parse(target.dataset.slots);
        popoverHeader.textContent = target.dataset.date;
        popoverSlots.innerHTML = '';
        slots.forEach(slot => {
            const slotEl = document.createElement('div');
            slotEl.classList.add('popover-slot-item');
            slotEl.textContent = slot;
            popoverSlots.appendChild(slotEl);
        });
        popover.classList.add('show');
        const widgetRect = calendarWidget.getBoundingClientRect();
        const dayRect = target.getBoundingClientRect();
        popover.style.top = `${dayRect.bottom - widgetRect.top + 5}px`;
        const popoverHalfWidth = popover.offsetWidth / 2;
        const dayCenter = dayRect.left - widgetRect.left + (dayRect.width / 2);
        let leftPosition = dayCenter - popoverHalfWidth;
        if (leftPosition < 0) leftPosition = 0;
        if (leftPosition + popover.offsetWidth > calendarWidget.offsetWidth) {
            leftPosition = calendarWidget.offsetWidth - popover.offsetWidth;
        }
        popover.style.left = `${leftPosition}px`;
    }

    prevMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
    nextMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
    document.addEventListener('click', (event) => {
        if (!calendarWidget.contains(event.target)) {
            popover.classList.remove('show');
            if (selectedDayElement) { selectedDayElement.classList.remove('selected'); selectedDayElement = null; }
        }
    });
    renderCalendar();
});
</script>

<?php require_once '../includes/footer.php'; ?>