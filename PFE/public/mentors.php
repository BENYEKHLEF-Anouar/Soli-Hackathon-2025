<?php
// Core application files
require '../config/config.php';
require '../config/helpers.php';

// --- HELPER FUNCTION TO GENERATE FILTER URLS ---
function generate_filter_url(string $param, string $value): string {
    $current_params = $_GET;
    unset($current_params['page']);
    if (isset($current_params[$param]) && $current_params[$param] === $value) {
        unset($current_params[$param]);
    } else {
        $current_params[$param] = $value;
    }
    return '?' . http_build_query($current_params);
}

// --- CONFIGURATION & INPUTS ---
$limit = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$matiere = trim($_GET['matiere'] ?? '');
$disponible = isset($_GET['disponible']);
$note = trim($_GET['note'] ?? '');
$tarif = trim($_GET['tarif'] ?? '');
$options = json_decode(file_get_contents('../config/options.json'), true);
$sujets = $options['sujets'] ?? [];

// --- DYNAMICALLY BUILD SQL QUERY ---
$queryParams = [];
$whereClauses = [];
$havingClauses = [];

if (!empty($search)) {
    $whereClauses[] = "(CONCAT(u.prenomUtilisateur, ' ', u.nomUtilisateur) LIKE :search OR m.competences LIKE :search)";
    $queryParams[':search'] = '%' . $search . '%';
}
if (!empty($matiere)) {
    $whereClauses[] = "m.competences LIKE :matiere";
    $queryParams[':matiere'] = '%' . $matiere . '%';
}

date_default_timezone_set('Europe/Paris');
$currentDayName = ['sunday' => 'dimanche', 'monday' => 'lundi', 'tuesday' => 'mardi', 'wednesday' => 'mercredi', 'thursday' => 'jeudi', 'friday' => 'vendredi', 'saturday' => 'samedi'][strtolower(date('l'))];
$currentTime = date('H:i:s');

if ($disponible) {
    // The JOIN for this is now handled in the main query structure
    $whereClauses[] = "d.idDisponibilite IS NOT NULL";
}

if (!empty($tarif)) {
    if ($tarif === 'gratuit') $whereClauses[] = "NOT EXISTS (SELECT 1 FROM Session s WHERE s.idMentorAnimateur = m.idMentor AND s.tarifSession > 0)";
    if ($tarif === 'moins_de_20') $whereClauses[] = "EXISTS (SELECT 1 FROM Session s WHERE s.idMentorAnimateur = m.idMentor AND s.tarifSession > 0 AND s.tarifSession < 20)";
    if ($tarif === '20_a_50') $whereClauses[] = "EXISTS (SELECT 1 FROM Session s WHERE s.idMentorAnimateur = m.idMentor AND s.tarifSession BETWEEN 20 AND 50)";
}
if (!empty($note) && is_numeric($note)) {
    // FIX: Use the raw aggregate function in HAVING for maximum compatibility
    $havingClauses[] = "AVG(p.notation) >= :min_rating";
    $queryParams[':min_rating'] = (int)$note;
}

$sqlWhere = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);
$sqlHaving = empty($havingClauses) ? '' : 'HAVING ' . implode(' AND ', $havingClauses);

// --- RESTRUCTURED SQL BASE FOR BOTH QUERIES ---
$sqlBase = "
    FROM Mentor m
    JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
    LEFT JOIN Disponibilite d ON d.idUtilisateur = u.idUtilisateur AND d.jourSemaine = :current_day AND d.heureDebut <= :current_time AND d.heureFin > :current_time
    LEFT JOIN Session s ON s.idMentorAnimateur = m.idMentor
    LEFT JOIN Participation p ON p.idSession = s.idSession
    $sqlWhere
    GROUP BY m.idMentor, u.idUtilisateur
    $sqlHaving
";
$queryParams[':current_day'] = $currentDayName;
$queryParams[':current_time'] = $currentTime;

// --- COUNT QUERY ---
$countSql = "SELECT COUNT(*) FROM (SELECT m.idMentor $sqlBase) as count_query";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($queryParams);
$totalMentors = $stmtCount->fetchColumn();
$totalPages = ceil($totalMentors / $limit);

// --- MENTOR FETCH QUERY ---
$mentorsSql = "
    SELECT
        u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl,
        m.idMentor, m.competences,
        AVG(p.notation) AS average_rating,
        COUNT(DISTINCT p.idParticipation) AS review_count,
        (d.idDisponibilite IS NOT NULL) AS is_available
    $sqlBase
    ORDER BY is_available DESC, average_rating DESC, review_count DESC
    LIMIT :limit OFFSET :offset
";
$stmtMentors = $pdo->prepare($mentorsSql);

// Bind all parameters for the main query
foreach ($queryParams as $key => &$val) {
    // Use bindParam for the loop variable
    $stmtMentors->bindParam($key, $val);
}
// Use bindValue for literal values
$stmtMentors->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtMentors->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmtMentors->execute();
$mentors = $stmtMentors->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/mentors.css?v=<?php echo time(); ?>">

<main>
    <section class="search-page-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Trouvez votre mentor idéal</h2>
            <p class="section-subtitle" data-aos="fade-up">Utilisez les filtres pour affiner votre recherche et trouver l'expert qui correspond parfaitement à vos besoins.</p>
            
            <form method="GET" action="mentors.php" class="search-form" data-aos="fade-up" data-aos-delay="50">
                <div class="search-bar-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="search-input" placeholder="Recherche..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" style="display:none;">Rechercher</button>
                </div>
            </form>

            <div class="filters-bar" data-aos="fade-up" data-aos-delay="100">
                <div class="filter-dropdown">
                    <button class="filter-btn <?= !empty($matiere) ? 'active' : '' ?>"><i class="fas fa-book-open"></i><span><?= !empty($matiere) ? htmlspecialchars($matiere) : 'Matière' ?></span><i class="fas fa-chevron-down"></i></button>
                    <div class="dropdown-content">
                        <?php foreach ($sujets as $s): ?><a href="<?= generate_filter_url('matiere', $s) ?>" class="<?= ($matiere === $s) ? 'active' : '' ?>"><?= htmlspecialchars($s) ?></a><?php endforeach; ?>
                    </div>
                </div>
                <div class="filter-dropdown">
                     <button class="filter-btn <?= !empty($tarif) ? 'active' : '' ?>"><i class="fas fa-dollar-sign"></i><span><?= !empty($tarif) ? ucfirst(str_replace('_', ' ', $tarif)) : 'Tarif' ?></span><i class="fas fa-chevron-down"></i></button>
                    <div class="dropdown-content">
                        <a href="<?= generate_filter_url('tarif', 'gratuit') ?>" class="<?= ($tarif === 'gratuit') ? 'active' : '' ?>">Gratuit</a>
                        <a href="<?= generate_filter_url('tarif', 'moins_de_20') ?>" class="<?= ($tarif === 'moins_de_20') ? 'active' : '' ?>">Moins de 20€</a>
                        <a href="<?= generate_filter_url('tarif', '20_a_50') ?>" class="<?= ($tarif === '20_a_50') ? 'active' : '' ?>">20€ - 50€</a>
                    </div>
                </div>
                <a href="<?= generate_filter_url('disponible', $disponible ? '' : '1') ?>" class="filter-btn <?= $disponible ? 'active' : '' ?>"><i class="fas fa-calendar-check"></i><span>Disponibilité</span></a>
                <div class="filter-dropdown">
                    <button class="filter-btn <?= !empty($note) ? 'active' : '' ?>"><i class="fas fa-star"></i><span><?= !empty($note) ? 'Note ' . $note . '+' : 'Note' ?></span><i class="fas fa-chevron-down"></i></button>
                    <div class="dropdown-content">
                        <a href="<?= generate_filter_url('note', '4') ?>" class="<?= ($note === '4') ? 'active' : '' ?>">4+ étoiles</a>
                        <a href="<?= generate_filter_url('note', '3') ?>" class="<?= ($note === '3') ? 'active' : '' ?>">3+ étoiles</a>
                    </div>
                </div>
                <a href="mentors.php" class="clear-filters-btn"><i class="fas fa-times"></i> Réinitialiser</a>
            </div>

            <div class="profile-grid">
                <?php if (empty($mentors)): ?>
                    <p class="no-results">Aucun mentor ne correspond à votre recherche. Essayez de réinitialiser vos filtres.</p>
                <?php else: ?>
                    <?php foreach ($mentors as $index => $mentor): ?>
                        <div class="profile-card" data-aos="fade-up" data-aos-delay="<?= ($index % 3) * 100 ?>">
                            <div class="card-image-container"><img src="<?= get_profile_image_path($mentor['photoUrl']) ?>" alt="Photo de <?= htmlspecialchars($mentor['prenomUtilisateur']) ?>"><?php if (($mentor['average_rating'] ?? 0) >= 4.5 && ($mentor['review_count'] ?? 0) >= 5): ?><span class="card-badge">Top Mentor</span><?php endif; ?></div>
                            <div class="card-body">
                                <h3 class="profile-name"><?= htmlspecialchars($mentor['prenomUtilisateur'] . ' ' . $mentor['nomUtilisateur']) ?></h3>
                                <p class="profile-specialty"><?= htmlspecialchars($mentor['competences']) ?></p>
                                <div class="profile-rating"><?php if (!empty($mentor['review_count'])): ?><i class="fa-solid fa-star"></i><strong><?= number_format((float)$mentor['average_rating'], 1) ?></strong><span>(<?= $mentor['review_count'] ?> avis)</span><?php else: ?><span><i class="fa-regular fa-star"></i> Pas encore d'avis</span><?php endif; ?></div>
                            </div>
                            <div class="card-footer"><span><span class="status-dot <?= $mentor['is_available'] ? 'available' : 'busy' ?>"></span><?= $mentor['is_available'] ? 'Disponible' : 'Occupé' ?></span><a href="mentor_profile.php?id=<?= $mentor['idMentor'] ?>" class="card-action">Voir Profil <i class="fa-solid fa-arrow-right"></i></a></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Page navigation" data-aos="fade-up">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page'=>''])) ?>" class="pagination-link prev"><i class="fas fa-chevron-left"></i> Précédent</a><?php endif; ?>
                    <?php $start = max(1, $page - 2); $end = min($totalPages, $page + 2); if ($start > 1) echo '<a href="?page=1&'.http_build_query(array_diff_key($_GET, ['page'=>''])).'" class="pagination-link">1</a>'; if ($start > 2) echo '<span class="pagination-ellipsis">...</span>'; for ($i = $start; $i <= $end; $i++): ?><a href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page'=>''])) ?>" class="pagination-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a><?php endfor; if ($end < $totalPages - 1) echo '<span class="pagination-ellipsis">...</span>'; if ($end < $totalPages) echo '<a href="?page='.$totalPages.'&'.http_build_query(array_diff_key($_GET, ['page'=>''])).'" class="pagination-link">'.$totalPages.'</a>'; ?>
                    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page'=>''])) ?>" class="pagination-link next">Suivant <i class="fas fa-chevron-right"></i></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require_once '../includes/footer.php'; ?>