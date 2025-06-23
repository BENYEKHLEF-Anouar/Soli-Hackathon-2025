<?php
require '../config/config.php';
require '../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. AUTHENTICATION & SECURITY ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'etudiant') {
    header('Location: login.php');
    exit;
}
$student_user_id = $_SESSION['user']['id'];
$feedback = [];

// --- 2. HANDLE FORM SUBMISSION (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $feedback = ['type' => 'error', 'message' => 'Erreur de sécurité. Veuillez réessayer.'];
    } else {
        unset($_SESSION['csrf_token']);

        // --- Sanitize and retrieve POST data ---
        $prenom = sanitize($_POST['prenom'] ?? '');
        $nom = sanitize($_POST['nom'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $ville = sanitize($_POST['ville'] ?? '');
        $niveau = sanitize($_POST['niveau'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $update_password = false;

        // --- Validation ---
        if (empty($prenom) || empty($nom) || empty($email) || empty($niveau)) {
            $feedback = ['type' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $feedback = ['type' => 'error', 'message' => 'L\'adresse email n\'est pas valide.'];
        } elseif (!empty($new_password) && ($new_password !== $confirm_password)) {
            $feedback = ['type' => 'error', 'message' => 'Les nouveaux mots de passe ne correspondent pas.'];
        } elseif (!empty($new_password) && strlen($new_password) < 8) {
             $feedback = ['type' => 'error', 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        } else {
            if (!empty($new_password)) $update_password = true;

            try {
                $pdo->beginTransaction();

                // --- Handle Photo Upload ---
                $photo_to_update = null;
                if (isset($_FILES['photoUpload']) && $_FILES['photoUpload']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../assets/uploads/';
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $file_type = mime_content_type($_FILES['photoUpload']['tmp_name']);
                    
                    if (in_array($file_type, $allowed_types)) {
                        $file_extension = pathinfo($_FILES['photoUpload']['name'], PATHINFO_EXTENSION);
                        $new_filename = 'user_' . $student_user_id . '_' . time() . '.' . $file_extension;
                        if (move_uploaded_file($_FILES['photoUpload']['tmp_name'], $upload_dir . $new_filename)) {
                            $photo_to_update = $new_filename;
                        } else { throw new Exception("Erreur lors du déplacement du fichier."); }
                    } else { throw new Exception("Type de fichier non autorisé."); }
                }

                // --- Build and Execute Utilisateur Update Query ---
                $sql_user = "UPDATE Utilisateur SET prenomUtilisateur = ?, nomUtilisateur = ?, emailUtilisateur = ?, ville = ?";
                $params_user = [$prenom, $nom, $email, $ville];

                if ($update_password) {
                    $sql_user .= ", motDePasse = ?";
                    $params_user[] = password_hash($new_password, PASSWORD_DEFAULT);
                }
                if ($photo_to_update) {
                    $sql_user .= ", photoUrl = ?";
                    $params_user[] = $photo_to_update;
                }
                $sql_user .= " WHERE idUtilisateur = ?";
                $params_user[] = $student_user_id;

                $stmt_user = $pdo->prepare($sql_user);
                $stmt_user->execute($params_user);

                // --- Execute Etudiant Update Query ---
                $sql_etudiant = "UPDATE Etudiant SET niveau = ? WHERE idUtilisateur = ?";
                $stmt_etudiant = $pdo->prepare($sql_etudiant);
                $stmt_etudiant->execute([$niveau, $student_user_id]);

                $pdo->commit();
                
                $feedback = ['type' => 'success', 'message' => 'Votre profil a été mis à jour avec succès.'];
                
                // Update session data
                $_SESSION['user']['prenom'] = $prenom;
                $_SESSION['user']['nom'] = $nom;
                if($photo_to_update) $_SESSION['user']['photoUrl'] = $photo_to_update;

            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback = ['type' => 'error', 'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()];
                error_log("Student Profile Update Error: " . $e->getMessage());
            }
        }
    }
}

// --- 3. Regenerate CSRF token ---
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// --- 4. FETCH CURRENT USER DATA ---
try {
    $stmt = $pdo->prepare(
        "SELECT u.prenomUtilisateur, u.nomUtilisateur, u.emailUtilisateur, u.ville, u.photoUrl, e.niveau 
         FROM Utilisateur u
         JOIN Etudiant e ON u.idUtilisateur = e.idUtilisateur
         WHERE u.idUtilisateur = ?"
    );
    $stmt->execute([$student_user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) { die("Erreur: Impossible de charger les informations de l'utilisateur."); }
} catch (PDOException $e) { die("Erreur de base de données. " . $e->getMessage()); }

require '../includes/header.php';
// Link to the SAME CSS file as mentor edit profile for a perfect style match
echo '<link rel="stylesheet" href="../assets/css/edit-profile2.css?v=' . time() . '">';
?>
<main class="main-content">
    <div class="edit-profile-container" data-aos="fade-down" data-aos-duration="1600">
        <div class="edit-profile-header">
            <a href="student_dashboard.php" class="sidebar-back-link"><i class="fas fa-arrow-left"></i> Retour</a>
            <h1 class="page-title">Modifier le Profil</h1>
        </div>

        <div class="profile-card">
            <form action="edit_profile2.php" method="POST" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <?php if (!empty($feedback)): ?>
                    <div class="message <?php echo $feedback['type'] === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?= $feedback['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                        <?= sanitize($feedback['message']) ?>
                    </div>
                <?php endif; ?>

                <div class="photo-upload-area">
                    <img src="<?= get_profile_image_path($user_data['photoUrl']) ?>" alt="Aperçu de la photo" id="photoPreview">
                    <label for="photoUpload" class="photo-upload-label"><i class="fas fa-camera"></i> Changer de photo</label>
                    <input type="file" name="photoUpload" id="photoUpload" hidden accept="image/png, image/jpeg, image/gif">
                </div>

                <div class="form-section-title">Informations personnelles</div>
                <div class="form-grid">
                    <div class="input-group"><label for="prenom" class="input-label">Prénom</label><div class="relative"><input type="text" id="prenom" name="prenom" value="<?= sanitize($user_data['prenomUtilisateur']) ?>" required><i class="fas fa-user left-icon"></i></div></div>
                    <div class="input-group"><label for="nom" class="input-label">Nom</label><div class="relative"><input type="text" id="nom" name="nom" value="<?= sanitize($user_data['nomUtilisateur']) ?>" required><i class="fas fa-user left-icon"></i></div></div>
                    <div class="input-group"><label for="email" class="input-label">Adresse e-mail</label><div class="relative"><input type="email" id="email" name="email" value="<?= sanitize($user_data['emailUtilisateur']) ?>" required><i class="fas fa-envelope left-icon"></i></div></div>
                    <div class="input-group"><label for="ville" class="input-label">Ville</label><div class="relative"><input type="text" id="ville" name="ville" value="<?= sanitize($user_data['ville']) ?>" placeholder="Ex: Paris, France"><i class="fas fa-map-marker-alt left-icon"></i></div></div>
                </div>

                <hr class="form-divider">

                <div class="form-section-title">Informations académiques</div>
                <div class="input-group">
                    <label for="niveau" class="input-label">Votre niveau d'études actuel</label>
                    <div class="relative">
                         <select id="niveau" name="niveau" required>
                            <?php 
                            $niveaux = ["Lycée - Seconde", "Lycée - Première", "Lycée - Terminale", "Bac+1 / L1", "Bac+2 / L2", "Bac+3 / L3", "Master 1", "Master 2", "Doctorat", "Autre"];
                            foreach($niveaux as $n) {
                                $selected = (sanitize($user_data['niveau']) == $n) ? 'selected' : '';
                                echo "<option value=\"$n\" $selected>$n</option>";
                            }
                            ?>
                        </select>
                        <i class="fas fa-graduation-cap left-icon"></i>
                    </div>
                </div>
                
                <hr class="form-divider">

                <div class="form-section-title">Sécurité</div>
                <div class="form-grid">
                    <div class="input-group"><label for="new_password" class="input-label">Nouveau mot de passe</label><div class="relative"><input type="password" id="new_password" name="new_password" placeholder="••••••••"><i class="fas fa-lock left-icon"></i><i class="fas fa-eye eye-toggle" onclick="togglePasswordVisibility('new_password')"></i></div><span class="input-hint">Laissez vide pour ne pas changer</span></div>
                    <div class="input-group"><label for="confirm_password" class="input-label">Confirmer le mot de passe</label><div class="relative"><input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••"><i class="fas fa-lock left-icon"></i><i class="fas fa-eye eye-toggle" onclick="togglePasswordVisibility('confirm_password')"></i></div></div>
                </div>

                <div class="form-actions"><button class="btn" type="submit"><svg height="24" width="24" fill="#FFFFFF" viewBox="0 0 24 24" class="sparkle"><path d="M10,21.236,6.755,14.745.264,11.5,6.755,8.255,10,1.764l3.245,6.491L19.736,11.5l-6.491,3.245ZM18,21l1.5,3L21,21l3-1.5L21,18l-1.5-3L18,18l-3,1.5ZM19.333,4.667,20.5,7l1.167-2.333L24,3.5,21.667,2.333,20.5,0,19.333,2.333,17,3.5Z"></path></svg><span class="text">Enregistrer</span></button></div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init();
    const photoInput = document.getElementById('photoUpload');
    const photoPreview = document.getElementById('photoPreview');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) { photoPreview.src = event.target.result; }
                reader.readAsDataURL(file);
            }
        });
    }
});
function togglePasswordVisibility(fieldId) {
    const input = document.getElementById(fieldId);
    const icon = input.parentElement.querySelector('.eye-toggle');
    if (input && icon) {
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>