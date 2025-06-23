<?php
require '../../config/config.php';
require '../../config/helpers.php'; // Required for get_file_icon_class

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// 1. Security Checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Méthode non autorisée.', 405);
}
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mentor') {
    send_json_error('Accès refusé.', 403);
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    send_json_error('Erreur de validation CSRF.', 403);
}

$idUtilisateur = $_SESSION['user']['id'];
$titre = trim($_POST['titreRessource'] ?? '');

if (empty($titre) || !isset($_FILES['fileUpload']) || $_FILES['fileUpload']['error'] == UPLOAD_ERR_NO_FILE) {
    send_json_error('Le titre et le fichier sont obligatoires.');
}

$file = $_FILES['fileUpload'];

// 2. File Validation
$uploadDir = '../../assets/uploads/resources/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowedExtensions = ['pdf', 'docx', 'pptx', 'mp4', 'mov', 'jpg', 'jpeg', 'png', 'gif', 'mp3'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($file['size'] > 10 * 1024 * 1024) { // 10 MB limit
    send_json_error('Le fichier est trop volumineux (max 10MB).');
}

if (!in_array($fileExtension, $allowedExtensions)) {
    send_json_error('Type de fichier non autorisé. Autorisés : ' . implode(', ', $allowedExtensions));
}

// Map extension to ENUM type in DB
$typeFichier = 'image'; // Default
if ($fileExtension === 'pdf') $typeFichier = 'pdf';
elseif ($fileExtension === 'docx') $typeFichier = 'docx';
elseif ($fileExtension === 'pptx') $typeFichier = 'pptx';
elseif (in_array($fileExtension, ['mp4', 'mov'])) $typeFichier = 'video';
elseif (in_array($fileExtension, ['mp3', 'wav'])) $typeFichier = 'audio';

// 3. Generate unique filename and move file
$newFileName = uniqid('res_', true) . '.' . $fileExtension;
$uploadPath = $uploadDir . $newFileName;
$dbPath = 'resources/' . $newFileName; // Relative path for the database

if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO Ressource (titreRessource, cheminRessource, typeFichier, idUtilisateur) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$titre, $dbPath, $typeFichier, $idUtilisateur]);
        $newResourceId = $pdo->lastInsertId();

        // Return a success response with the new resource data
        echo json_encode([
            'status' => 'success',
            'message' => 'Ressource ajoutée avec succès.',
            'resource' => [
                'idRessource' => $newResourceId,
                'titreRessource' => htmlspecialchars($titre),
                'typeFichier' => $typeFichier,
                'iconClass' => get_file_icon_class($typeFichier) // Use helper to get the icon
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        unlink($uploadPath); // Clean up the uploaded file if DB insert fails
        error_log("Add resource error: " . $e->getMessage());
        send_json_error('Erreur de base de données.', 500);
    }
} else {
    send_json_error('Erreur lors du téléversement du fichier.');
}