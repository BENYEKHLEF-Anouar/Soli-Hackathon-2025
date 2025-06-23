<?php
require '../config/config.php';
require '../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a mentor
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mentor') {
    die("Accès refusé. Vous devez être connecté en tant que mentor.");
}

$user_id = $_SESSION['user']['id'];
$message = '';
$error = '';

try {
    // Check if mentor record exists
    $stmt = $pdo->prepare("SELECT idMentor FROM Mentor WHERE idUtilisateur = ?");
    $stmt->execute([$user_id]);
    $mentor_exists = $stmt->fetch();

    if ($mentor_exists) {
        $message = "✅ Votre profil mentor existe déjà. Vous pouvez accéder au tableau de bord.";
    } else {
        // Create the missing mentor record
        $stmt = $pdo->prepare("INSERT INTO Mentor (idUtilisateur, competences) VALUES (?, ?)");
        $success = $stmt->execute([$user_id, 'Compétences à définir']);
        
        if ($success) {
            $message = "✅ Profil mentor créé avec succès ! Vous pouvez maintenant accéder au tableau de bord.";
        } else {
            $error = "❌ Erreur lors de la création du profil mentor.";
        }
    }

} catch (PDOException $e) {
    $error = "❌ Erreur de base de données: " . $e->getMessage();
    error_log("Fix mentor profile error for user $user_id: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réparation du profil mentor</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 10px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Réparation du profil mentor</h1>
        
        <?php if ($message): ?>
            <div class="success">
                <h3><?= $message ?></h3>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <h3><?= $error ?></h3>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="mentor_dashboard.php" class="btn btn-success">
                📊 Accéder au tableau de bord
            </a>
            <a href="edit_profile.php" class="btn">
                ✏️ Modifier le profil
            </a>
        </div>
        
        <div style="margin-top: 20px; font-size: 14px; color: #666;">
            <p>Si vous continuez à avoir des problèmes, veuillez contacter l'administrateur.</p>
        </div>
    </div>
</body>
</html>
