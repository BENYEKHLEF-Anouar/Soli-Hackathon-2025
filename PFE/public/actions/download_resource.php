<?php
require '../../config/config.php';
require '../../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security check - user must be logged in
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    die('Accès refusé. Vous devez être connecté.');
}

// Validate resource ID
$resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$resourceId) {
    http_response_code(400);
    die('ID de ressource invalide.');
}

try {
    // Get resource information
    $stmt = $pdo->prepare("
        SELECT r.titreRessource, r.cheminRessource, r.typeFichier, r.idUtilisateur,
               u.prenomUtilisateur, u.nomUtilisateur
        FROM Ressource r
        JOIN Utilisateur u ON r.idUtilisateur = u.idUtilisateur
        WHERE r.idRessource = ?
    ");
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        http_response_code(404);
        die('Ressource non trouvée.');
    }

    // Build file path
    $filePath = '../../assets/uploads/' . $resource['cheminRessource'];
    
    // Check if file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('Fichier non trouvé sur le serveur.');
    }

    // Get file info
    $fileSize = filesize($filePath);
    $fileName = $resource['titreRessource'];
    $fileExtension = pathinfo($resource['cheminRessource'], PATHINFO_EXTENSION);
    
    // Add extension to filename if not present
    if (!str_ends_with(strtolower($fileName), '.' . strtolower($fileExtension))) {
        $fileName .= '.' . $fileExtension;
    }

    // Set appropriate headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Clear any output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Read and output file
    readfile($filePath);
    exit;

} catch (PDOException $e) {
    error_log("Download resource error: " . $e->getMessage());
    http_response_code(500);
    die('Erreur de base de données.');
} catch (Exception $e) {
    error_log("Download resource error: " . $e->getMessage());
    http_response_code(500);
    die('Erreur lors du téléchargement.');
}
?>
