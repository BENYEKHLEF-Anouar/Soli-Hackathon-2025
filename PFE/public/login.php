<?php
require '../config/config.php';
require '../config/helpers.php';

// Session timeout handling
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

$errors = [];
$success = '';
$email = sanitize($_GET['email'] ?? $_POST['email'] ?? '');

// Rate limiting and attempt logging configuration
$rate_limit_file = '../config/login_attempts.json';
$max_attempts = 5;
$lockout_time = 900; // 15 minutes

/**
 * Checks if the user has exceeded login attempts.
 * @param string $identifier Unique user identifier (email_IP).
 * @return bool True if allowed, false if rate-limited.
 */
function check_rate_limit($identifier) {
    global $rate_limit_file, $max_attempts, $lockout_time;
    $attempts = file_exists($rate_limit_file) ? json_decode(file_get_contents($rate_limit_file), true) : [];
    
    if (isset($attempts[$identifier])) {
        $data = $attempts[$identifier];
        if ($data['count'] >= $max_attempts && (time() - $data['time']) < $lockout_time) {
            return false;
        } elseif ((time() - $data['time']) >= $lockout_time) {
            unset($attempts[$identifier]);
            file_put_contents($rate_limit_file, json_encode($attempts));
        }
    }
    return true;
}

/**
 * Updates login attempt data in JSON file.
 * @param string $identifier Unique user identifier.
 * @param bool $success Whether the attempt was successful.
 */
function update_login_attempt($identifier, $success) {
    global $rate_limit_file;
    $attempts = file_exists($rate_limit_file) ? json_decode(file_get_contents($rate_limit_file), true) : [];
    
    if (isset($attempts[$identifier])) {
        $attempts[$identifier]['count'] = $success ? 0 : $attempts[$identifier]['count'] + 1;
        $attempts[$identifier]['time'] = time();
        $attempts[$identifier]['last_status'] = $success ? 'success' : 'failed';
    } else {
        $attempts[$identifier] = [
            'count' => $success ? 0 : 1,
            'time' => time(),
            'last_status' => $success ? 'success' : 'failed'
        ];
    }
    
    if (!file_put_contents($rate_limit_file, json_encode($attempts, JSON_PRETTY_PRINT))) {
        log_error("Failed to write login attempt for $identifier to $rate_limit_file");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["submit"])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = "Requête invalide. Veuillez réessayer.";
        log_error("CSRF validation failed for login attempt: IP {$_SERVER['REMOTE_ADDR']}");
    } else {
        $password = $_POST['password'] ?? '';
        $identifier = $email . '_' . $_SERVER['REMOTE_ADDR'];

        if (!check_rate_limit($identifier)) {
            $errors['general'] = "Trop de tentatives de connexion. Réessayez dans 15 minutes.";
            log_error("Rate limit exceeded for $identifier");
        } else {
            if (empty($email)) $errors['email'] = "Veuillez renseigner votre email.";
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "Format d'email invalide.";

            if (empty($password)) $errors['password'] = "Veuillez renseigner votre mot de passe.";

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("SELECT idUtilisateur, nomUtilisateur, prenomUtilisateur, emailUtilisateur, motDePasse, role, photoUrl, verified FROM Utilisateur WHERE emailUtilisateur = :email");
                    $stmt->execute([':email' => $email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && password_verify($password, $user['motDePasse'])) {
                        if (!$user['verified']) {
                            $errors['general'] = "Veuillez vérifier votre email avant de vous connecter.";
                            log_error("Unverified login attempt: $email");
                            update_login_attempt($identifier, false);
                        } else {
                            $_SESSION['user'] = [
                                'id' => $user['idUtilisateur'],
                                'nom' => $user['nomUtilisateur'],
                                'prenom' => $user['prenomUtilisateur'],
                                'email' => $user['emailUtilisateur'],
                                'role' => $user['role'],
                                'photoUrl' => $user['photoUrl']
                            ];
                            $success = 'Connexion réussie ! Vous allez être redirigé.';
                            update_login_attempt($identifier, true);
                        }
                    } else {
                        $errors['general'] = "Email ou mot de passe incorrect.";
                        update_login_attempt($identifier, false);
                        log_error("Failed login attempt: $email from IP {$_SERVER['REMOTE_ADDR']}");
                    }
                } catch (PDOException $e) {
                    log_error("Login error for $email: " . $e->getMessage());
                    $errors['general'] = "Une erreur technique est survenue. Veuillez réessayer plus tard.";
                    update_login_attempt($identifier, false);
                }
            } else {
                update_login_attempt($identifier, false);
            }
        }
    }
}

/**
 * Determines CSS class for form fields based on state.
 * @param string $fieldName Field name to check.
 * @return string CSS class.
 */
function get_field_class($fieldName) {
    global $errors, $success;
    if ($success) return 'success';
    if (!empty($errors['general']) || !empty($errors[$fieldName])) return 'error';
    return '';
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mentora - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="../assets/css/login.css?v=<?php echo time(); ?>">
    <link rel="icon" href="../assets/images/White_Tower_Symbol.webp" type="image/x-icon">
</head>
<body>
<!-- =======================
         PRELOADER - START 
    ======================== -->
    <div id="preloader" class="preloader-hidden-on-load">
        <div class="preloader-spinner"></div>
    </div>
    <!-- =======================
         PRELOADER - END 
    ======================== -->

    <div class="header">
        <div class="logo-container">
            <div class="logo-icon"></div>
            <span class="logo-text">Mentora.</span>
        </div>
        <div class="social-icons">
            <a href="#" class="social-icon instagram" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" /></svg></a>
            <a href="#" class="social-icon telegram" aria-label="Telegram"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" /></svg></a>
        </div>
    </div>

    <div class="main-content">
        <div class="container" data-aos="fade-down" data-aos-duration="1600">
            <div class="title_container">
                <h2 class="title">Connexion</h2>
                <span class="subtitle">Entrez vos identifiants pour accéder à votre compte.</span>
            </div>

            <?php if (isset($_GET['status']) && $_GET['status'] === 'completed'): ?>
                <p class="message success"><i class="fa-solid fa-check-circle"></i> Profil complété avec succès ! Veuillez vous connecter.</p>
            <?php endif; ?>
            <?php if (isset($_GET['timeout']) && $_GET['timeout'] === '1'): ?>
                <p class="message error"><i class="fa-solid fa-circle-exclamation"></i> Session expirée. Veuillez vous reconnecter.</p>
            <?php endif; ?>
            <?php if (!empty($errors['general'])): ?>
                <p class="message error"><i class="fa-solid fa-circle-exclamation"></i> <?= sanitize($errors['general']) ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="message success"><i class="fa-solid fa-check-circle"></i> <?= sanitize($success) ?></p>
            <?php endif; ?>

            <form action="login.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="input-group">
                    <label class="input-label">Email :</label>
                    <div class="relative">
                        <input type="email" name="email" value="<?= sanitize($email) ?>" placeholder="nom@gmail.com" class="<?= get_field_class('email') ?>" />
                        <div>
                            <svg class="left-icon" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </div>
                    </div>
                    <?php if (!empty($errors['email'])): ?>
                        <p class="error-message"> <i class="fa-solid fa-circle-exclamation"></i> <?= sanitize($errors['email']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label class="input-label">Mot de passe :</label>
                    <div class="relative">
                        <input type="password" name="password" id="passwordInput" placeholder="• • • • • • • •" class="<?= get_field_class('password') ?>" />
                        <div>
                            <svg class="left-icon" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M2 18v3c0 .6.4 1 1 1h4v-3h3v-3h2l1.4-1.4a6.5 6.5 0 1 0-4-4Z"></path>
                                <circle cx="16.5" cy="7.5" r=".5"></circle>
                            </svg>
                        </div>
                        <svg class="eye-toggle" id="eyeToggle" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </div>
                    <?php if (!empty($errors['password'])): ?>
                        <p class="error-message"> <i class="fa-solid fa-circle-exclamation"></i> <?= sanitize($errors['password']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- <p class="forgot-password"><a href="forgot_password.php">Mot de passe oublié ?</a></p> -->
                <!-- Placeholder for password reset page -->

                <button class="btn" name="submit" type="submit">
                    <svg height="24" width="24" fill="#FFFFFF" viewBox="0 0 24 24" class="sparkle">
                        <path d="M10,21.236,6.755,14.745.264,11.5,6.755,8.255,10,1.764l3.245,6.491L19.736,11.5l-6.491,3.245ZM18,21l1.5,3L21,21l3-1.5L21,18l-1.5-3L18,18l-3,1.5ZM19.333,4.667,20.5,7l1.167-2.333L24,3.5,21.667,2.333,20.5,0,19.333,2.333,17,3.5Z"></path>
                    </svg>
                    <span class="text">Se connecter</span>
                </button>
            </form>

            <div class="separator"><hr class="line" /><span>Ou</span><hr class="line" /></div>
            <p class="switch-form">Pas de compte ? <a href="register.php">Inscrivez-vous ici</a>.</p>
        </div>
    </div>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init();

        const passwordInput = document.getElementById('passwordInput');
        const eyeToggle = document.getElementById('eyeToggle');
        if (eyeToggle) {
            eyeToggle.addEventListener('click', function() {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                eyeToggle.innerHTML = isPassword ?
                    `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>` :
                    `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>`;
            });
        }
    </script>

<?php if ($success): ?>
<script>
    document.querySelectorAll('form input, form button').forEach(el => el.disabled = true);
    const preloader = document.getElementById('preloader');
    preloader.style.transition = 'opacity 0.4s ease, visibility 0.4s ease';
    preloader.classList.remove('preloader-hidden-on-load');
    setTimeout(() => {
        window.location.href = "index.php";
    }, 1000); // Match minDisplayTime
</script>
<?php endif; ?>

<script>
    // --- Preloader for page switching ---
    const switchToRegisterLink = document.querySelector('.switch-form a[href="register.php"]');
    if (switchToRegisterLink) {
        switchToRegisterLink.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent the link from navigating immediately
            const preloader = document.getElementById('preloader');
            if (preloader) {
                // Show preloader
                preloader.style.transition = 'opacity 0.4s ease, visibility 0.4s ease';
                preloader.classList.remove('preloader-hidden-on-load');
                
                // Wait for animation then go to the link's href
                setTimeout(() => {
                    window.location.href = this.href;
                }, 400);
            } else {
                window.location.href = this.href; // Fallback if no preloader
            }
        });
    }
</script>

</body>
</html>