<?php
require '../config/config.php';
require '../config/helpers.php';

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Security gate
if (!isset($_SESSION['new_user_id']) || !isset($_SESSION['registration_step']) || $_SESSION['registration_step'] !== 'finish') {
    header('Location: register.php');
    exit();
}

$idUtilisateur = $_SESSION['new_user_id'];
$errors = [];

$stmt = $pdo->prepare("SELECT prenomUtilisateur, role, emailUtilisateur, photoUrl FROM Utilisateur WHERE idUtilisateur = ?");
$stmt->execute([$idUtilisateur]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: register.php');
    exit();
}

$ville = sanitize($_POST['ville'] ?? '');
$niveau = sanitize($_POST['niveau'] ?? '');
$sujetRecherche = $_POST['sujets'] ?? []; // Keep as array
$competences = sanitize($_POST['competences'] ?? '');
$availabilities = $_POST['availabilities'] ?? [];

$options_file = '../config/options.json';
if (!file_exists($options_file)) {
    $errors['general'] = "Fichier de configuration manquant.";
    $options = ['niveaux' => [], 'sujets' => []];
} else {
    $options = json_decode(file_get_contents($options_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors['general'] = "Erreur dans le fichier de configuration.";
        $options = ['niveaux' => [], 'sujets' => []];
    }
}

$time_slots = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];
$days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["submit"])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = "Requête invalide. Veuillez réessayer.";
        log_error("CSRF validation failed for finish registration: User ID $idUtilisateur");
    } else {
        // Valeur initiale de la photo provenant de la base ou défaut
        $photoFileName = $user['photoUrl'] ?? 'default_avatar.png';

        // Validation
        if (empty($ville)) {
            $errors['ville'] = "Veuillez indiquer votre ville.";
        }
        if ($user['role'] === 'etudiant') {
            if (empty($niveau)) {
                $errors['niveau'] = "Veuillez sélectionner votre niveau d'études.";
            }
            if (empty($sujetRecherche)) {
                $errors['sujets'] = "Veuillez sélectionner au moins un sujet.";
            }
        }
        if ($user['role'] === 'mentor' && empty($competences)) {
            $errors['competences'] = "Veuillez décrire vos compétences.";
        }

        // Availability validation
        $valid_availabilities = [];
        $has_enabled_day = false;
        if (!empty($availabilities)) {
            foreach ($availabilities as $day => $times) {
                if (!empty($times['enabled'])) {
                    $has_enabled_day = true;
                    if (empty($times['start']) || empty($times['end'])) {
                        $errors['availabilities'] = "Veuillez sélectionner les horaires pour " . ucfirst($day) . ".";
                    } elseif (!in_array($times['start'], $time_slots) || !in_array($times['end'], $time_slots)) {
                        $errors['availabilities'] = "Horaires invalides pour " . ucfirst($day) . ".";
                    } else {
                        $start = DateTime::createFromFormat('H:i', $times['start']);
                        $end = DateTime::createFromFormat('H:i', $times['end']);
                        if ($start >= $end) {
                            $errors['availabilities'] = "L'heure de fin doit être après l'heure de début pour " . ucfirst($day) . ".";
                        } else {
                            $valid_availabilities[$day] = [
                                'start' => $times['start'],
                                'end' => $times['end']
                            ];
                        }
                    }
                }
            }
        }
        if (!$has_enabled_day) {
            $errors['availabilities'] = "Veuillez indiquer au moins une disponibilité.";
        }

        // File upload handling
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photo = $_FILES['photo'];
            $uploadDir = '../assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $fileExtension = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($fileExtension, $allowedExtensions)) {
                $errors['photo'] = "Format de fichier non autorisé (autorisés: jpg, png, gif).";
            } elseif ($photo['size'] > 6291456) { // 6MB
                $errors['photo'] = "Le fichier est trop volumineux (max 6MB).";
            } else {
                $photoFileName = 'user_' . $idUtilisateur . '_' . time() . '.' . $fileExtension;
                if (!move_uploaded_file($photo['tmp_name'], $uploadDir . $photoFileName)) {
                    $errors['photo'] = "Erreur lors du téléchargement de l'image.";
                    $photoFileName = 'default_avatar.png';
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmtUser = $pdo->prepare("UPDATE Utilisateur SET ville = :ville, photoUrl = :photoUrl WHERE idUtilisateur = :id");
                $stmtUser->execute([':ville' => $ville, ':photoUrl' => $photoFileName, ':id' => $idUtilisateur]);

                if ($user['role'] === 'etudiant') {
                    $sujetRechercheString = implode(', ', $sujetRecherche);
                    $stmtRole = $pdo->prepare("UPDATE Etudiant SET niveau = :niveau, sujetRecherche = :sujetRecherche WHERE idUtilisateur = :id");
                    $stmtRole->execute([':niveau' => $niveau, ':sujetRecherche' => $sujetRechercheString, ':id' => $idUtilisateur]);
                } elseif ($user['role'] === 'mentor') {
                    $stmtRole = $pdo->prepare("UPDATE Mentor SET competences = :competences WHERE idUtilisateur = :id");
                    $stmtRole->execute([':competences' => $competences, ':id' => $idUtilisateur]);
                }

                // Clear old availabilities before inserting new ones
                $stmtClear = $pdo->prepare("DELETE FROM Disponibilite WHERE idUtilisateur = ?");
                $stmtClear->execute([$idUtilisateur]);

                // Insert new availabilities (split into one-hour slots for consistency with mentor_dashboard.php)
                $stmtAvailability = $pdo->prepare("INSERT INTO Disponibilite (idUtilisateur, jourSemaine, heureDebut, heureFin) VALUES (:idUtilisateur, :jourSemaine, :heureDebut, :heureFin)");
                foreach ($valid_availabilities as $day => $times) {
                    $start_hour = (int)substr($times['start'], 0, 2);
                    $end_hour = (int)substr($times['end'], 0, 2);
                    for ($h = $start_hour; $h < $end_hour; $h++) {
                        $heureDebut = sprintf('%02d:00', $h);
                        $heureFin = sprintf('%02d:00', $h + 1);
                        $stmtAvailability->execute([
                            ':idUtilisateur' => $idUtilisateur,
                            ':jourSemaine' => $day,
                            ':heureDebut' => $heureDebut,
                            ':heureFin' => $heureFin
                        ]);
                    }
                }

                // Create welcome notification
                create_notification($pdo, $idUtilisateur, 'badge', 'Bienvenue sur Mentora ! Votre profil a été créé avec succès.');

                // Assign "Débutant" badge (ID 1 from the database)
                assign_badge_to_user($pdo, $idUtilisateur, 1);

                $pdo->commit();

                unset($_SESSION['new_user_id'], $_SESSION['registration_step']);
                header('Location: login.php?email=' . urlencode($user['emailUtilisateur']) . '&status=completed');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                log_error("Finish registration error for user ID $idUtilisateur: " . $e->getMessage());
                $errors['general'] = "Une erreur technique est survenue. Veuillez réessayer.";
            }
        }
    }
}

function get_field_class($fieldName) {
    global $errors;
    return !empty($errors[$fieldName]) ? 'error' : '';
}

$photoFileName = $user['photoUrl'] ?? 'default_avatar.png';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mentora - Finaliser l'inscription</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="../assets/css/register.css?v=<?php echo time(); ?>">
    <link rel="icon" href="../assets/images/White_Tower_Symbol.webp" type="image/x-icon">
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <div class="logo-icon"></div>
            <span class="logo-text">Mentora.</span>
        </div>
    </div>

    <div class="main-content">
        <div class="container" data-aos="fade-down">
            <div class="title_container">
                <h2 class="title">Bienvenue, <?= htmlspecialchars($user['prenomUtilisateur']) ?> !</h2>
                <span class="subtitle">Encore une étape pour compléter votre profil.</span>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <p class="message error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errors['general']) ?></p>
            <?php endif; ?>

            <form action="finish_register.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="form-row">
                    <div class="input-group">
                        <label class="input-label" for="photo">Photo de profil (Optionnel)</label>
                        <label class="input-file <?= get_field_class('photo') ?>" for="photo">
                            <span id="file-name-display">Choisir un fichier...</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/><path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/></svg>
                        </label>
                        <input type="file" id="photo" name="photo" accept="image/*" style="display:none;">
                        <?php
                        $currentPhotoPath = get_profile_image_path($user['photoUrl']);
                        $displayStyle = ($user['photoUrl'] && $user['photoUrl'] !== 'default_avatar.png') ? 'block' : 'none';
                        ?>
                        <img id="photo-preview" 
                             src="<?= htmlspecialchars($currentPhotoPath) ?>" 
                             alt="Aperçu de la photo" 
                             style="display:<?= $displayStyle ?>; max-width:100px; margin-top:10px; border-radius: 8px;">
                        <?php if (!empty($errors['photo'])): ?>
                            <p class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errors['photo']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="input-group">
                        <label class="input-label" for="ville">Votre ville</label>
                        <div class="relative">
                            <input type="text" id="ville" name="ville" placeholder="Ex: Casablanca, Paris..." value="<?= htmlspecialchars($ville) ?>" class="<?= get_field_class('ville') ?>" autocomplete="off">
                            <div class="left-icon-wrapper"><svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg></div>
                        </div>
                        <?php if (!empty($errors['ville'])): ?>
                            <p class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errors['ville']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="input-group">
                    <?php if ($user['role'] === 'etudiant'): ?>
                        <label class="input-label" for="niveau">Niveau d'études</label>
                        <select id="niveau" name="niveau" class="input-select <?= get_field_class('niveau') ?>">
                            <option value="" disabled <?= empty($niveau) ? 'selected' : '' ?>>-- Sélectionnez votre niveau --</option>
                            <?php foreach ($options['niveaux'] as $niv): ?>
                                <option value="<?= htmlspecialchars($niv) ?>" <?= $niveau === $niv ? 'selected' : '' ?>><?= htmlspecialchars($niv) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['niveau'])): ?>
                            <p class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errors['niveau']) ?></p>
                        <?php endif; ?>

                        <label class="input-label" id="sujet-label">Sujets recherchés</label>
                        <div class="checkbox-group <?= get_field_class('sujets') ?>">
                            <?php foreach ($options['sujets'] as $sujet): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="sujets[]" value="<?= htmlspecialchars($sujet) ?>" <?= in_array($sujet, $sujetRecherche) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($sujet) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($errors['sujets'])): ?>
                            <p class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errors['sujets']) ?></p>
                        <?php endif; ?>
                    
                    <?php elseif ($user['role'] === 'mentor'): ?>
                        <label class="input-label" for="competences">Vos compétences</label>
                        <textarea id="competences" name="competences" rows="4" class="<?= get_field_class('competences') ?>"
                            placeholder="Séparez les compétences par une virgule (ex: Python, Analyse de données, React, Aide à l'orientation...)"><?= htmlspecialchars($competences) ?></textarea>
                        <?php if (!empty($errors['competences'])): ?>
                            <p class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errors['competences']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label class="input-label" id="disponibilités-label">Vos disponibilités</label>
                    <div class="availability-group <?= get_field_class('availabilities') ?>">
                        <?php foreach ($days as $day): ?>
                        <div class="availability-row">
                            <label class="day-label">
                                <input type="checkbox" name="availabilities[<?= $day ?>][enabled]" value="1" <?= isset($availabilities[$day]['enabled']) ? 'checked' : '' ?>>
                                <span><?= ucfirst($day) ?></span>
                            </label>
                            <select name="availabilities[<?= $day ?>][start]" class="time-input" <?= !isset($availabilities[$day]['enabled']) ? 'disabled' : '' ?>>
                                <option value="" disabled selected>-- Début --</option>
                                <?php foreach ($time_slots as $slot): ?>
                                    <option value="<?= $slot ?>" <?= ($availabilities[$day]['start'] ?? '') === $slot ? 'selected' : '' ?>><?= $slot ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="time-separator">à</span>
                            <select name="availabilities[<?= $day ?>][end]" class="time-input" <?= !isset($availabilities[$day]['enabled']) ? 'disabled' : '' ?>>
                                <option value="" disabled selected>-- Fin --</option>
                                <?php foreach ($time_slots as $slot): ?>
                                    <option value="<?= $slot ?>" <?= ($availabilities[$day]['end'] ?? '') === $slot ? 'selected' : '' ?>><?= $slot ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($errors['availabilities'])): ?>
                        <p class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errors['availabilities']) ?></p>
                    <?php endif; ?>
                </div>
                
                <button class="btn" type="submit" name="submit">
                    <svg height="24" width="24" fill="#FFFFFF" viewBox="0 0 24 24" class="sparkle"><path d="M10,21.236,6.755,14.745.264,11.5,6.755,8.255,10,1.764l3.245,6.491L19.736,11.5l-6.491,3.245ZM18,21l1.5,3L21,21l3-1.5L21,18l-1.5-3L18,18l-3,1.5ZM19.333,4.667,20.5,7l1.167-2.333L24,3.5,21.667,2.333,20.5,0,19.333,2.333,17,3.5Z"></path></svg>
                    <span class="text">Terminer mon inscription</span>
                </button>
            </form>
        </div>
    </div>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, duration: 800 });

        const photoInput = document.getElementById('photo');
        const fileNameDisplay = document.getElementById('file-name-display');
        const photoPreview = document.getElementById('photo-preview');

        if (photoInput && fileNameDisplay && photoPreview) {
            photoInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    fileNameDisplay.textContent = this.files[0].name;
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                        photoPreview.style.display = 'block';
                    };
                    reader.readAsDataURL(this.files[0]);
                } else {
                    fileNameDisplay.textContent = 'Choisir un fichier...';
                    photoPreview.src = '';
                    photoPreview.style.display = 'none';
                }
            });
        }

        document.querySelectorAll('.day-label input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const row = this.closest('.availability-row');
                const timeInputs = row.querySelectorAll('select');
                timeInputs.forEach(input => {
                    input.disabled = !this.checked;
                    if (!this.checked) {
                        input.selectedIndex = 0;
                    }
                });
            });
        });
    </script>
</body>
</html>