<?php
// Core application files
require '../config/config.php';
require '../config/helpers.php';

// --- HELPER FUNCTIONS ---
function formatDuration($minutes) {
    if (!$minutes) return 'N/A';
    if ($minutes < 60) { return $minutes . 'min'; }
    $hours = floor($minutes / 60);
    $rem_minutes = $minutes % 60;
    return $rem_minutes > 0 ? $hours . 'h' . str_pad($rem_minutes, 2, '0', STR_PAD_LEFT) : $hours . 'h';
}
function getSessionStyleClass($subject) {
    if (!$subject) return 'session-card--default';
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $subject)));
    return 'session-card--' . $slug;
}

// --- CONFIGURATION & INPUTS ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 6;
$offset = ($page - 1) * $perPage;

$searchQuery = trim($_GET['search'] ?? '');
$subjects = isset($_GET['sujets']) && is_array($_GET['sujets']) ? array_filter($_GET['sujets']) : [];
$level = trim($_GET['niveau'] ?? 'tous');
$price = trim($_GET['tarif'] ?? 'tous');
$sort = trim($_GET['tri'] ?? 'pertinence');

// --- CHANGED: DYNAMICALLY FETCH FILTER OPTIONS FROM JSON FILE ---
$options = json_decode(file_get_contents('../config/options.json'), true);
$allSubjects = $options['sujets'] ?? [];
$allLevels = $options['niveaux'] ?? [];

// --- BUILD SQL QUERY (using the reliable mentors.php pattern) ---
$queryParams = [];
$whereClauses = [
    "(s.statutSession = 'disponible' OR (s.statutSession = 'en_attente' AND s.idEtudiantDemandeur IS NULL))",
    "s.dateSession >= CURDATE()"
];

if (!empty($searchQuery)) {
    $whereClauses[] = "(s.titreSession LIKE :search OR s.sujet LIKE :search OR u.prenomUtilisateur LIKE :search OR u.nomUtilisateur LIKE :search)";
    $queryParams[':search'] = "%{$searchQuery}%";
}
if (!empty($subjects)) {
    $subjectPlaceholders = [];
    foreach ($subjects as $key => $subject) {
        $placeholder = ':sujet' . $key;
        $subjectPlaceholders[] = $placeholder;
        $queryParams[$placeholder] = $subject;
    }
    $whereClauses[] = "s.sujet IN (" . implode(',', $subjectPlaceholders) . ")";
}
if ($level !== 'tous') {
    $whereClauses[] = "s.niveau = :niveau";
    $queryParams[':niveau'] = $level;
}
if ($price === 'gratuit') {
    $whereClauses[] = "s.tarifSession = 0";
} elseif ($price === 'payant') {
    $whereClauses[] = "s.tarifSession > 0";
}

$sqlWhere = 'WHERE ' . implode(' AND ', $whereClauses);

// --- CREATE THE BASE SQL FOR REUSE ---
$sqlBase = "
    FROM Session s
    JOIN Mentor m ON s.idMentorAnimateur = m.idMentor
    JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
    $sqlWhere
";

// --- GET TOTAL COUNT FOR PAGINATION ---
$countSql = "SELECT COUNT(s.idSession) " . $sqlBase;
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($queryParams);
$totalSessions = $stmtCount->fetchColumn();
$totalPages = ceil($totalSessions / $perPage);

// --- BUILD THE FINAL FETCH QUERY ---
$selectSql = "SELECT s.*, u.prenomUtilisateur AS mentor_prenom, u.nomUtilisateur AS mentor_nom, u.ville AS mentor_ville, u.photoUrl AS mentor_photo ";
$orderSql = "ORDER BY ";
switch ($sort) {
    case 'date':        $orderSql .= "s.dateSession ASC, s.heureSession ASC"; break;
    case 'prix_asc':    $orderSql .= "s.tarifSession ASC, s.dateSession ASC"; break;
    case 'prix_desc':   $orderSql .= "s.tarifSession DESC, s.dateSession ASC"; break;
    default:            $orderSql .= "s.dateSession ASC, s.heureSession ASC";
}
$limitSql = " LIMIT :limit OFFSET :offset";

$fetchSql = $selectSql . $sqlBase . $orderSql . $limitSql;
$stmtSessions = $pdo->prepare($fetchSql);

// Bind all filter parameters
foreach ($queryParams as $key => &$val) {
    $stmtSessions->bindParam($key, $val);
}
$stmtSessions->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtSessions->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmtSessions->execute();
$sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require_once '../includes/header.php'; ?>
<!-- Make sure to link to the corrected sessions.css file -->
<link rel="stylesheet" href="../assets/css/sessions.css?v=<?php echo time(); ?>">

<main class="sessions-page">
    <div class="container">
        <div class="page-header" data-aos="fade-up">
            <h1 class="page-title">Trouvez Votre Session Idéale</h1>
            <p class="page-subtitle">Utilisez les filtres pour affiner votre recherche et trouver le cours ou le mentorat parfait pour vous.</p>
        </div>

        <div class="search-layout">
            <aside class="filter-sidebar" data-aos="fade-right">
                <form action="sessions.php" method="GET" id="filter-form">
                    <div class="filter-card">
                        <div class="filter-group">
                            <label for="search-input-field" class="filter-label">Rechercher</label>
                            <div class="search-input-wrapper">
                                <input type="text" id="search-input-field" name="search" placeholder="Matière, mentor..." value="<?= htmlspecialchars($searchQuery) ?>">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                        <div class="filter-group">
                            <h4 class="filter-label">Matières</h4>
                            <ul class="filter-list">
                                <?php foreach ($allSubjects as $subj): ?>
                                <li><label><input type="checkbox" name="sujets[]" value="<?= htmlspecialchars($subj) ?>" <?= in_array($subj, $subjects) ? 'checked' : '' ?>> <?= htmlspecialchars($subj) ?></label></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="filter-group">
                             <h4 class="filter-label">Niveau</h4>
                             <select name="niveau" id="niveau-select" class="filter-select">
                                <option value="tous" <?= $level === 'tous' ? 'selected' : '' ?>>Tous les niveaux</option>
                                <?php foreach ($allLevels as $lvl): ?>
                                <option value="<?= htmlspecialchars($lvl) ?>" <?= $level === $lvl ? 'selected' : '' ?>><?= htmlspecialchars($lvl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <h4 class="filter-label">Tarif</h4>
                            <ul class="filter-list">
                                <li><label><input type="radio" name="tarif" value="tous" <?= $price === 'tous' ? 'checked' : '' ?>> Tous</label></li>
                                <li><label><input type="radio" name="tarif" value="gratuit" <?= $price === 'gratuit' ? 'checked' : '' ?>> Gratuit</label></li>
                                <li><label><input type="radio" name="tarif" value="payant" <?= $price === 'payant' ? 'checked' : '' ?>> Payant</label></li>
                            </ul>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary btn-full">Appliquer les filtres</button>
                            <a href="sessions.php" class="reset-link"><i class="fas fa-times"></i>&nbsp; Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </aside>

            <div class="results-content-wrapper" id="results-content-wrapper">
                <div class="results-header" data-aos="fade-in">
                    <span class="results-count"><b><?= $totalSessions ?></b> session(s) trouvée(s)</span>
                    <form action="sessions.php" method="GET" id="sort-form">
                        <!-- Pass existing filters through hidden inputs -->
                        <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>">
                        <?php foreach($subjects as $s): ?><input type="hidden" name="sujets[]" value="<?= htmlspecialchars($s) ?>"><?php endforeach; ?>
                        <input type="hidden" name="niveau" value="<?= htmlspecialchars($level) ?>">
                        <input type="hidden" name="tarif" value="<?= htmlspecialchars($price) ?>">
                        <div class="sort-control">
                            <label for="sort-select">Trier par :</label>
                            <select id="sort-select" name="tri" class="filter-select" onchange="this.form.submit()">
                                <option value="pertinence" <?= $sort === 'pertinence' ? 'selected' : '' ?>>Pertinence</option>
                                <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>Date</option>
                                <option value="prix_asc" <?= $sort === 'prix_asc' ? 'selected' : '' ?>>Prix croissant</option>
                                <option value="prix_desc" <?= $sort === 'prix_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                            </select>
                        </div>
                    </form>
                </div>

                <div class="sessions-grid">
                    <?php if (empty($sessions)): ?>
                        <p class="no-results">Aucune session ne correspond à vos critères de recherche.</p>
                    <?php else: ?>
                        <?php foreach ($sessions as $index => $session): ?>
                            <!-- CHANGED: This HTML structure now matches the index.php card style -->
                            <div class="session-card <?= getSessionStyleClass($session['sujet']) ?>" data-aos="fade-up" data-aos-delay="<?= ($index % 2) * 100 ?>">
                                <div class="session-header">
                                     <div class="session-header-icon"></div>
                                     <h3 class="session-title"><?= htmlspecialchars($session['titreSession']) ?></h3>
                                </div>
                                <div class="session-body">
                                    <div class="session-host">
                                        <img src="<?= get_profile_image_path($session['mentor_photo']) ?>" alt="Photo de <?= htmlspecialchars($session['mentor_prenom']) ?>" class="host-avatar">
                                        <div class="host-info">
                                            <h4><?= htmlspecialchars($session['mentor_prenom'] . ' ' . $session['mentor_nom']) ?></h4>
                                            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['mentor_ville'] ?? 'À distance') ?></p>
                                        </div>
                                    </div>
                                    <div class="session-details">
                                        <div class="session-detail"><i class="far fa-clock"></i><span>Durée: <?= formatDuration($session['duree_minutes']) ?></span></div>
                                        <div class="session-detail"><i class="fas fa-video"></i><span><?= ($session['typeSession'] == 'en_ligne') ? 'En ligne' : 'Présentiel' ?></span></div>
                                        <div class="session-detail"><i class="fas fa-graduation-cap"></i><span>Niveau: <?= htmlspecialchars($session['niveau']) ?></span></div>
                                        <div class="session-detail"><i class="fas fa-tag"></i><span class="session-price <?= ($session['tarifSession'] > 0) ? 'paid' : 'free' ?>"><?= ($session['tarifSession'] > 0) ? htmlspecialchars(number_format($session['tarifSession'], 2)) . ' €' : 'Gratuit' ?></span></div>
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

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Page navigation" data-aos="fade-up">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page'=>''])) ?>" class="pagination-link prev">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);

                        if ($start > 1) {
                            echo '<a href="?page=1&'.http_build_query(array_diff_key($_GET, ['page'=>''])).'" class="pagination-link">1</a>';
                        }
                        if ($start > 2) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }

                        for ($i = $start; $i <= $end; $i++): ?>
                            <a href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page'=>''])) ?>"
                               class="pagination-link <?= ($i == $page) ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor;

                        if ($end < $totalPages - 1) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                        if ($end < $totalPages) {
                            echo '<a href="?page='.$totalPages.'&'.http_build_query(array_diff_key($_GET, ['page'=>''])).'" class="pagination-link">'.$totalPages.'</a>';
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page'=>''])) ?>" class="pagination-link next">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>