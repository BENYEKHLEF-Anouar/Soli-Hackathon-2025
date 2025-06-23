<?php
require '../../config/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// 1. Security Checks - MODIFIED to accept POST
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
$idRessource = filter_input(INPUT_POST, 'idRessource', FILTER_VALIDATE_INT);

if (!$idRessource) {
    send_json_error('ID de ressource invalide.');
}

try {
    $pdo->beginTransaction();
    
    // First, get the file path to delete it from the server
    $stmt = $pdo->prepare("SELECT cheminRessource FROM Ressource WHERE idRessource = ? AND idUtilisateur = ?");
    $stmt->execute([$idRessource, $idUtilisateur]);
    $resource = $stmt->fetch();

    if ($resource) {
        // Delete from database
        $stmt_delete = $pdo->prepare("DELETE FROM Ressource WHERE idRessource = ? AND idUtilisateur = ?");
        $stmt_delete->execute([$idRessource, $idUtilisateur]);

        // Delete the actual file
        $filePath = '../../assets/uploads/' . $resource['cheminRessource'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Ressource supprimée avec succès.']);
        exit;
    } else {
        $pdo->rollBack();
        send_json_error('Ressource non trouvée ou suppression non autorisée.', 404);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete resource error: " . $e->getMessage());
    send_json_error('Une erreur de base de données est survenue.', 500);
}