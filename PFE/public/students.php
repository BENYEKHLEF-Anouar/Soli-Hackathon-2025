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
$sujet = trim($_GET['sujet'] ?? '');
$niveau = trim($_GET['niveau'] ?? '');

// --- DYNAMICALLY FETCH FILTER OPTIONS ---
$allSujets = $pdo->query("SELECT DISTINCT sujetRecherche FROM Etudiant WHERE sujetRecherche IS NOT NULL AND sujetRecherche != '' ORDER BY sujetRecherche ASC")->fetchAll(PDO::FETCH_COLUMN);
$allNiveaux = $pdo->query("SELECT DISTINCT niveau FROM Etudiant WHERE niveau IS NOT NULL AND niveau != '' ORDER BY niveau ASC")->fetchAll(PDO::FETCH_COLUMN);

// --- DYNAMICALLY BUILD SQL QUERY ---
$queryParams = [];
$whereClauses = [];

if (!empty($search)) {
    $whereClauses[] = "(CONCAT(u.prenomUtilisateur, ' ', u.nomUtilisateur) LIKE :search OR e.sujetRecherche LIKE :search)";
    $queryParams[':search'] = '%' . $search . '%';
}
if (!empty($sujet)) {
    $whereClauses[] = "e.sujetRecherche = :sujet";
    $queryParams[':sujet'] = $sujet;
}
if (!empty($niveau)) {
    $whereClauses[] = "e.niveau = :niveau";
    $queryParams[':niveau'] = $niveau;
}

$sqlWhere = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

// --- RESTRUCTURED SQL BASE FOR BOTH QUERIES ---
$sqlBase = "
    FROM Etudiant e
    JOIN Utilisateur u ON e.idUtilisateur = u.idUtilisateur
    $sqlWhere
";

// --- COUNT QUERY ---
$countSql = "SELECT COUNT(*) FROM (SELECT e.idEtudiant $sqlBase) as count_query";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($queryParams);
$totalStudents = $stmtCount->fetchColumn();
$totalPages = ceil($totalStudents / $limit);

// --- STUDENT FETCH QUERY ---
$studentsSql = "
    SELECT
        u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl, u.ville,
        e.idEtudiant, e.niveau, e.sujetRecherche
    $sqlBase
    ORDER BY u.idUtilisateur DESC
    LIMIT :limit OFFSET :offset
";
$stmtStudents = $pdo->prepare($studentsSql);

// Bind all parameters
foreach ($queryParams as $key => &$val) {
    $stmtStudents->bindParam($key, $val);
}
$stmtStudents->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtStudents->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmtStudents->execute();
$students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<!-- Reusing the same CSS as the mentors page for a consistent look and feel -->
<link rel="stylesheet" href="../assets/css/mentors.css?v=<?php echo time(); ?>">
<style>
    /* Add a specific style for the student status dot */
    .status-dot.searching { background: var( --primary-blue); }
</style>

<main>
    <section class="search-page-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Trouvez des étudiants à accompagner</h2>
            <p class="section-subtitle" data-aos="fade-up">Parcourez les profils des étudiants qui recherchent un mentor et proposez votre aide.</p>

            <form method="GET" action="students.php" class="search-form" data-aos="fade-up" data-aos-delay="50">
                <div class="search-bar-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="search-input" placeholder="Rechercher par nom ou besoin (ex: 'Maths', 'Orientation')..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" style="display:none;">Rechercher</button>
                </div>
            </form>

            <div class="filters-bar" data-aos="fade-up" data-aos-delay="100">
                <div class="filter-dropdown">
                    <button class="filter-btn <?= !empty($sujet) ? 'active' : '' ?>"><i class="fas fa-book-open"></i><span><?= !empty($sujet) ? htmlspecialchars($sujet) : 'Sujet' ?></span><i class="fas fa-chevron-down"></i></button>
                    <div class="dropdown-content">
                        <?php foreach ($allSujets as $s): ?><a href="<?= generate_filter_url('sujet', $s) ?>" class="<?= ($sujet === $s) ? 'active' : '' ?>"><?= htmlspecialchars($s) ?></a><?php endforeach; ?>
                    </div>
                </div>
                <div class="filter-dropdown">
                     <button class="filter-btn <?= !empty($niveau) ? 'active' : '' ?>"><i class="fas fa-graduation-cap"></i><span><?= !empty($niveau) ? htmlspecialchars($niveau) : 'Niveau' ?></span><i class="fas fa-chevron-down"></i></button>
                    <div class="dropdown-content">
                        <?php foreach ($allNiveaux as $n): ?><a href="<?= generate_filter_url('niveau', $n) ?>" class="<?= ($niveau === $n) ? 'active' : '' ?>"><?= htmlspecialchars($n) ?></a><?php endforeach; ?>
                    </div>
                </div>
                <a href="students.php" class="clear-filters-btn"><i class="fas fa-times"></i> Réinitialiser</a>
            </div>

            <div class="profile-grid">
                <?php if (empty($students)): ?>
                    <p class="no-results">Aucun étudiant ne correspond à votre recherche.</p>
                <?php else: ?>
                    <?php foreach ($students as $index => $student): ?>
                        <div class="profile-card" data-aos="fade-up" data-aos-delay="<?= ($index % 3) * 100 ?>">
                            <div class="card-image-container"><img src="<?= get_profile_image_path($student['photoUrl']) ?>" alt="Photo de <?= htmlspecialchars($student['prenomUtilisateur']) ?>"></div>
                            <div class="card-body">
                                <h3 class="profile-name"><?= htmlspecialchars($student['prenomUtilisateur'] . ' ' . $student['nomUtilisateur']) ?></h3>
                                <p class="profile-specialty"><b>Besoin d'aide :</b> <?= htmlspecialchars($student['sujetRecherche']) ?></p>
                                <p class="profile-specialty" style="color: var(--slate-700);"><b>Niveau :</b> <?= htmlspecialchars($student['niveau']) ?></p>
                            </div>
                            <div class="card-footer">
                                <span><span class="status-dot searching"></span>Recherche un mentor</span>
                                <a href="student_profile.php?id=<?= $student['idEtudiant'] ?>" class="card-action">Voir Profil <i class="fa-solid fa-arrow-right"></i></a>
                            </div>
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