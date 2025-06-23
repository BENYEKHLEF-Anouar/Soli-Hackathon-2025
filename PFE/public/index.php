<?php

require '../config/config.php';
require '../config/helpers.php';


// --- Helper function to format duration from minutes to "1h" or "45min" ---
function formatDuration($minutes) {
    if (!$minutes) return null;
    if ($minutes < 60) { return $minutes . 'min'; }
    $hours = floor($minutes / 60);
    $rem_minutes = $minutes % 60;
    // Correctly format time like "1h05"
    return $rem_minutes > 0 ? $hours . 'h' . str_pad($rem_minutes, 2, '0', STR_PAD_LEFT) : $hours . 'h';
}


// --- UPDATED HELPER FUNCTION TO GENERATE CSS CLASS ---
function getSessionStyleClass($subject) {
    // If the subject is null or empty, return the default style class
    if (empty($subject)) {
        return 'session-card--default';
    }
    // Clean up the subject name to create a CSS-friendly slug
    $subject = str_replace(['é', 'è', 'ê', 'à', 'ç', 'ô', 'î', 'û', ' ', '/'], ['e', 'e', 'e', 'a', 'c', 'o', 'i', 'u', '-', '-'], $subject);
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '', $subject));
    return 'session-card--' . trim($slug, '-');
}



// --- A MORE SCALABLE MENTOR QUERY ---
date_default_timezone_set('Europe/Paris');
$days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$currentDayName = $days[date('w')];
$currentTime = date('H:i:s');

$stmtMentors = $pdo->prepare("
    SELECT
        u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl,
        m.idMentor, m.competences,
        AVG(p.notation) AS average_rating,
        COUNT(DISTINCT p.idParticipation) AS review_count,
        MAX(CASE WHEN d.idUtilisateur IS NOT NULL AND d.jourSemaine = :current_day AND :current_time BETWEEN d.heureDebut AND d.heureFin THEN 1 ELSE 0 END) AS is_available
    FROM Mentor m
    JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
    LEFT JOIN Disponibilite d ON d.idUtilisateur = u.idUtilisateur
    LEFT JOIN Animation a ON m.idMentor = a.idMentor
    LEFT JOIN Participation p ON a.idSession = p.idSession AND p.notation IS NOT NULL
    GROUP BY m.idMentor, u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl
    ORDER BY is_available DESC, average_rating DESC
    LIMIT 3
");
$stmtMentors->execute(['current_day' => $currentDayName, 'current_time' => $currentTime]);
$mentors = $stmtMentors->fetchAll(PDO::FETCH_ASSOC);


// --- FETCH DATA FOR AVAILABLE SESSIONS ---
$stmtSessions = $pdo->query("
    SELECT
        s.idSession,
        s.titreSession,
        s.sujet,
        s.typeSession,
        s.tarifSession,
        s.duree_minutes,
        s.niveau,
        u.prenomUtilisateur AS mentor_prenom,
        u.nomUtilisateur AS mentor_nom,
        u.ville AS mentor_ville,
        u.photoUrl AS mentor_photo
    FROM Session s
    JOIN Mentor m ON s.idMentorAnimateur = m.idMentor
    JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
    WHERE s.statutSession = 'disponible' AND s.idMentorAnimateur IS NOT NULL
    ORDER BY s.dateSession ASC, s.heureSession ASC
    LIMIT 3
");
$sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

// --- MODIFIED: FETCH 3 STUDENTS WITH THEIR LOCATION ---
$stmtStudents = $pdo->prepare("
    SELECT
        u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl, u.ville, -- Added u.ville here
        e.idEtudiant, e.niveau, e.sujetRecherche
    FROM Etudiant e
    JOIN Utilisateur u ON e.idUtilisateur = u.idUtilisateur
    ORDER BY u.idUtilisateur ASC
    LIMIT 3
");
$stmtStudents->execute();
$students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require_once '../includes/header.php'; ?>

    <main>
        <!-- Hero Section -->
        <section class="hero-section" id="home">
            <div class="container hero-container">
                <div class="hero-text" data-aos="fade-right">
                    <div class="decorative-circle"></div>
                    <h1 class="heading-main">Le mentorat nouvelle génération vous attend</h1>
                    <p class="hero-description">Trouvez le mentor parfait pour débloquer votre plein potentiel académique et professionnel.</p>
                </div>
                <div class="hero-image-container" data-aos="fade-left">
                    <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80" alt="Students studying together" class="hero-image">
                    <div class="stats-overlay">
                        <div class="stat-item">
                            <span class="stat-value">1K+</span>
                            <p class="stat-label">Mentors</p>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">5K+</span>
                            <p class="stat-label">Étudiants</p>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">150+</span>
                            <p class="stat-label">Compétences</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section" id="features">
            <div class="container features-container" data-aos="fade-up">
                <h2 class="section-title heading-features">Un écosystème d'apprentissage complet</h2>
                <p class="section-subtitle features-subtitle">Découvrez les fonctionnalités qui rendent Mentora unique et efficace pour tous les apprenants.</p>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="icon-background" style="background-color: #E6F4EA; color: #5DC689;">
                            <i class="fa-solid fa-lightbulb"></i>
                        </div>
                        <h3 class="feature-title">Matching Intelligent</h3>
                        <p class="feature-description">Notre algorithme vous connecte au mentor idéal selon vos objectifs et disponibilités.</p>
                        <ul class="feature-list">
                            <li class="feature-list-item">Objectifs académiques</li>
                            <li class="feature-list-item">Compétences ciblées</li>
                            <li class="feature-list-item">Affinités personnelles</li>
                        </ul>
                    </div>
                    <div class="feature-card">
                        <div class="icon-background" style="background-color: #FEEEEE; color: #F47174;">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <h3 class="feature-title">Espace Sécurisé</h3>
                        <p class="feature-description">Échangez en toute confiance grâce à notre messagerie et nos profils vérifiés.</p>
                        <ul class="feature-list">
                            <li class="feature-list-item">Messagerie intégrée</li>
                            <li class="feature-list-item">Partage de fichiers</li>
                            <li class="feature-list-item">Profils 100% vérifiés</li>
                        </ul>
                    </div>
                    <div class="feature-card">
                        <div class="icon-background" style="background-color: #FFF9E6; color: #F8B400;">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h3 class="feature-title">Suivi de Progrès</h3>
                        <p class="feature-description">Visualisez votre évolution avec des tableaux de bord et des objectifs clairs.</p>
                        <ul class="feature-list">
                            <li class="feature-list-item">Statistiques détaillées</li>
                            <li class="feature-list-item">Badges et récompenses</li>
                            <li class="feature-list-item">Historique des sessions</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="how-it-works">
            <div class="container">
                <h2 class="section-title">Comment ça marche ?</h2>
                <p class="section-subtitle">En quelques étapes simples, rejoignez une communauté d'apprentissage
                    dynamique.</p>
                <div class="steps">
                    <div class="step" data-aos="fade-up" data-aos-delay="100">
                        <div class="step-number">1</div>
                        <h3 class="step-title">Créez votre profil</h3>
                        <p class="step-description">Indiquez vos objectifs, matières et disponibilités pour un matching
                            optimal.</p>
                    </div>
                    <div class="step" data-aos="fade-up" data-aos-delay="200">
                        <div class="step-number">2</div>
                        <h3 class="step-title">Trouvez votre mentor</h3>
                        <p class="step-description">Explorez les profils et choisissez le mentor qui vous correspond.
                        </p>
                    </div>
                    <div class="step" data-aos="fade-up" data-aos-delay="300">
                        <div class="step-number">3</div>
                        <h3 class="step-title">Planifiez vos sessions</h3>
                        <p class="step-description">Réservez des créneaux pour des sessions personnalisées.</p>
                    </div>
                    <div class="step" data-aos="fade-up" data-aos-delay="400">
                        <div class="step-number">4</div>
                        <h3 class="step-title">Progressez ensemble</h3>
                        <p class="step-description">Participez à des sessions enrichissantes et suivez vos progrès.</p>
                    </div>
                </div>
            </div>
            <div class="container">
        <div class="steps steps--reverse">
            <div class="step" data-aos="fade-up" data-aos-delay="100">
                <div class="step-number">4</div>
                <h3 class="step-title">Définissez votre expertise</h3>
                <p class="step-description">Créez un profil attractif en listant vos compétences, vos disponibilités et votre approche.</p>
            </div>
            <div class="step" data-aos="fade-up" data-aos-delay="200">
                <div class="step-number">3</div>
                <h3 class="step-title">Recevez des demandes</h3>
                <p class="step-description">Les étudiants intéressés par votre profil vous contactent directement via la plateforme.</p>
            </div>
            <div class="step" data-aos="fade-up" data-aos-delay="300">
                <div class="step-number">2</div>
                <h3 class="step-title">Organisez vos sessions</h3>
                <p class="step-description">Acceptez les demandes et planifiez des sessions de mentorat en ligne selon vos créneaux.</p>
            </div>
            <div class="step" data-aos="fade-up" data-aos-delay="400">
                <div class="step-number">1</div>
                <h3 class="step-title">Faites la différence</h3>
                <p class="step-description">Partagez votre expérience, guidez vos étudiants vers la réussite et recevez des avis positifs.</p>
            </div>
        </div>
    </div>
        </section>


<!-- MENTORS SECTION -->
<section class="section" id="profiles">
            <div class="container">
                <h2 class="section-title" data-aos="fade-up">Trouvez votre expert</h2>
                <p class="section-subtitle" data-aos="fade-up">Filtrez parmi nos meilleurs talents et choisissez le mentor qui vous inspire.</p>
                
                <div class="profile-grid">
                    <?php if (empty($mentors)): ?>
                        <p class="text-center">Aucun mentor n'est disponible pour le moment.</p>
                    <?php else: ?>
                        <?php foreach ($mentors as $mentor): ?>
                            <div class="profile-card" data-aos="fade-up">
                                <div class="card-image-container">
                                    <img src="<?= get_profile_image_path($mentor['photoUrl']) ?>" alt="Photo de <?= htmlspecialchars($mentor['prenomUtilisateur']) ?>">
                                </div>
                                <div class="card-body">
                                    <h3 class="profile-name"><?= htmlspecialchars($mentor['prenomUtilisateur'] . ' ' . $mentor['nomUtilisateur']) ?></h3>
                                    <p class="profile-specialty"><?= htmlspecialchars($mentor['competences']) ?></p>
                                    <div class="profile-rating">
                                        <?php if ($mentor['review_count'] > 0): ?>
                                            <i class="fa-solid fa-star"></i>
                                            <strong><?= number_format((float)$mentor['average_rating'], 1) ?></strong>
                                            <span>(<?= $mentor['review_count'] ?> avis)</span>
                                        <?php else: ?>
                                            <span><i class="fa-regular fa-star"></i> Pas encore d'avis</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <span>
                                        <span class="status-dot <?= $mentor['is_available'] ? 'available' : 'busy' ?>"></span>
                                        <?= $mentor['is_available'] ? 'Disponible' : 'Occupé' ?>
                                    </span>
                                    <a href="mentor_profile.php?id=<?= $mentor['idMentor'] ?>" class="card-action">Voir Profil <i class="fa-solid fa-arrow-right"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="section-view-more" data-aos="fade-up">
                    <a href="mentors.php" class="btn btn-outline">Voir tous les mentors <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </section>

 <!-- Etudiants Section (index.php) -->
 <section class="section" id="etudiants">
            <div class="container">
                <h2 class="section-title" data-aos="fade-up">Nos étudiants talentueux</h2>
                <p class="section-subtitle" data-aos="fade-up">Découvrez les parcours et les réussites de nos étudiants.</p> 
                
                <div class="profile-grid" data-aos="fade-up" data-aos-delay="200">
                     <?php foreach ($students as $student): ?>
                        <div class="profile-card">
                            <div class="card-image-container">
                                <img src="<?= get_profile_image_path($student['photoUrl']) ?>" alt="Photo de <?= htmlspecialchars($student['prenomUtilisateur']) ?>">
                            </div>
                            <div class="card-body">
                                <h3 class="profile-name"><?= htmlspecialchars($student['prenomUtilisateur'] . ' ' . $student['nomUtilisateur']) ?></h3>
                                <p class="profile-specialty">Recherche: <?= htmlspecialchars($student['sujetRecherche']) ?> (<?= htmlspecialchars($student['niveau']) ?>)</p>
                                <div class="profile-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($student['ville']) ?></span>
                                </div>
                            </div>
                            <div class="card-footer">
                                <span>
                                    <span class="status-dot searching"></span>
                                    Recherche un mentor
                                </span>
                                <a href="student_profile.php?id=<?= $student['idEtudiant'] ?>" class="card-action">Voir Profil <i class="fa-solid fa-arrow-right"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-view-more" data-aos="fade-up">
                    <a href="students.php" class="btn btn-outline">Voir tous les étudiants <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </section>

           <!-- SESSIONS SECTION - FINAL VERSION -->
        <section class="sessions-section" id="sessions">
            <div class="container">
                <h2 class="section-title">Sessions à la une</h2>
                <p class="section-subtitle">Rejoignez des sessions de groupe animées par nos mentors experts.</p>
                
                <div class="sessions-list">
                    <?php if (empty($sessions)): ?>
                        <p class="text-center">Aucune session de groupe n'est actuellement programmée.</p>
                    <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                            <div class="session-card <?= getSessionStyleClass($session['sujet']) ?>" data-aos="fade-up">
                                <div class="session-header">
                                     <div class="session-header-icon"></div>
                                     <h3 class="session-title"><?= htmlspecialchars($session['titreSession']) ?></h3>
                                </div>
                                <div class="session-body">
                                <div class="session-host">
                                    <img src="<?= get_profile_image_path($session['mentor_photo']) ?>" alt="Photo de <?= htmlspecialchars($session['mentor_prenom']) ?>" class="host-avatar">
                                    <div class="host-info">
                                        <h4><?= htmlspecialchars($session['mentor_prenom'] . ' ' . $session['mentor_nom']) ?></h4>
                                        <p><?= htmlspecialchars($session['mentor_ville']) ?></p>
                                    </div>
                                </div>
                                    <div class="session-details">
                                        <div class="session-detail">
                                            <i class="far fa-clock"></i>
                                            <span>Durée: <?= formatDuration($session['duree_minutes']) ?></span>
                                        </div>
                                        <div class="session-detail">
                                             <i class="fas fa-video"></i>
                                             <span><?= ($session['typeSession'] == 'en_ligne') ? 'En ligne' : 'Présentiel' ?></span>
                                        </div>
                                        <div class="session-detail">
                                            <?php if ($session['sujet'] === 'Mathématiques'): ?>
                                                <i class="fas fa-graduation-cap"></i>
                                            <?php else: ?>
                                                <i class="fas fa-user-tie"></i>
                                            <?php endif; ?>
                                            <span>Niveau: <?= htmlspecialchars($session['niveau']) ?></span>
                                        </div>
                                        <div class="session-detail">
                                            <i class="fas fa-tag"></i>
                                            <span class="session-price free">
                                                <?= ($session['tarifSession'] > 0) ? htmlspecialchars(number_format($session['tarifSession'], 2)) . ' €' : 'Gratuit' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="session-actions">
                                    <a href="register_for_session.php?id=<?= $session['idSession'] ?>" class="btn btn-primary"><i class="fa-solid fa-check"></i>  Réserver</a>
                                    <a href="session_details.php?id=<?= $session['idSession'] ?>" class="session-details-link">Voir détails <i class="fa-solid fa-arrow-right session-details-icon"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="section-view-more" data-aos="fade-up">
                    <a href="sessions.php" class="btn btn-outline">Voir toutes les sessions <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </section>

       <!-- Testimonials Section -->
        <section class="testimonials-section" id="testimonials">
            <div class="container">
                <h2 class="section-title" data-aos="fade-up">Ils nous font confiance</h2>
                <p class="section-subtitle" data-aos="fade-up">Découvrez comment Mentora a transformé le parcours de nos étudiants et mentors.</p>
                
                <div class="testimonial-slider-container" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-slider-track">
                        <!-- Testimonial Card 1 -->
                        <div class="testimonial-card">
                            <i class="fa-solid fa-quote-left testimonial-quote-icon"></i>
                            <p class="testimonial-text">
                                "Grâce à mon mentor, j'ai non seulement compris les chapitres de physique qui me bloquaient, mais j'ai aussi gagné une confiance en moi incroyable pour les examens. Une expérience qui a changé ma scolarité."
                            </p>
                            <div class="testimonial-author">
                                <img src="https://images.unsplash.com/photo-1599566150163-29194dcaad36?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=387&q=80" alt="Léa Dubois" class="author-image">
                                <div class="author-info">
                                    <h4 class="author-name">Léa Dubois</h4>
                                    <p class="author-role">Étudiante en Terminale S</p>
                                </div>
                            </div>
                        </div>

                        <!-- Testimonial Card 2 -->
                        <div class="testimonial-card">
                            <i class="fa-solid fa-quote-left testimonial-quote-icon"></i>
                            <p class="testimonial-text">
                                "En tant que jeune retraité, Mentora m'a donné l'opportunité de transmettre ma passion pour l'histoire. C'est extrêmement gratifiant de voir la curiosité s'éveiller chez les plus jeunes. La plateforme est simple et efficace."
                            </p>
                            <div class="testimonial-author">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=387&q=80" alt="Karim Alami" class="author-image">
                                <div class="author-info">
                                    <h4 class="author-name">Karim Alami</h4>
                                    <p class="author-role">Mentor en Histoire-Géographie</p>
                                </div>
                            </div>
                        </div>

                        <!-- Testimonial Card 3 -->
                        <div class="testimonial-card">
                            <i class="fa-solid fa-quote-left testimonial-quote-icon"></i>
                            <p class="testimonial-text">
                                "L'aide pour mon orientation a été décisive. Mon mentor m'a aidé à y voir plus clair dans mes choix post-bac et à préparer mes dossiers. Je me sens beaucoup plus sereine pour l'avenir."
                            </p>
                            <div class="testimonial-author">
                                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=387&q=80" alt="Sofia Cherkaoui" class="author-image">
                                <div class="author-info">
                                    <h4 class="author-name">Sofia Cherkaoui</h4>
                                    <p class="author-role">Étudiante en 1ère Année</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Testimonial Card 4 (Duplicate for scrolling) -->
                        <div class="testimonial-card">
                            <i class="fa-solid fa-quote-left testimonial-quote-icon"></i>
                            <p class="testimonial-text">
                                "La flexibilité de la plateforme est un vrai plus. J'ai pu trouver un mentor qui correspondait parfaitement à mon emploi du temps chargé. Je recommande vivement !"
                            </p>
                            <div class="testimonial-author">
                                <img src="https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=387&q=80" alt="Adam N." class="author-image">
                                <div class="author-info">
                                    <h4 class="author-name">Adam Naciri</h4>
                                    <p class="author-role">Mentor en Programmation</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>


       <!-- CTA Section -->
        <section class="cta-section">
            <div class="container">
                <div class="cta-content">
                    <h2 class="cta-title">Prêt à transformer votre apprentissage ?</h2>
                    <p class="cta-text">Rejoignez des milliers d'étudiants et mentors qui révolutionnent déjà leur façon
                        d'apprendre.</p>
                </div>
                <div class="cta-buttons">
                    <a href="register.php?role=etudiant" class="cta-link cta-link-reverse"><i class="fa-solid fa-arrow-left"></i> Inscription étudiant</a>
                    <a href="register.php?role=mentor" class="cta-link">Devenir mentor <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
        </section>
    </main>

<?php require_once '../includes/footer.php'; ?>