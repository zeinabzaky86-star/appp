<?php
// Force error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- Configuration ---
$dbHost = 'localhost';
$dbName = 'aealexue_appp'; // <-- Replace
$dbUser = 'aealexue_appp';       // <-- Replace
$dbPass = 'uI.Od@3OUNj?';       // <-- Replace
$uploadDir = 'uploads/'; // The directory to save files in

// --- Main Logic ---
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdfFile'])) {
        http_response_code(400);
        throw new Exception('Invalid request.');
    }

    if ($_FILES['pdfFile']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        throw new Exception('File upload error code: ' . $_FILES['pdfFile']['error']);
    }

    $fileTmpPath = $_FILES['pdfFile']['tmp_name'];
    $pdfContent = file_get_contents($fileTmpPath);
    if ($pdfContent === false) {
        http_response_code(500);
        throw new Exception('Could not read uploaded file content.');
    }

    $newFileName = time() . '-' . basename($_FILES['pdfFile']['name']);
    $destPath = $uploadDir . $newFileName;

    if (file_put_contents($destPath, $pdfContent) === false) {
        http_response_code(500);
        throw new Exception('Could not save file to the server.');
    }

    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "INSERT INTO uploaded_pdfs (file_name, file_path) VALUES (:file_name, :file_path)";
    $stmt = $pdo->prepare($sql);
    
    $stmt->bindParam(':file_name', $_FILES['pdfFile']['name'], PDO::PARAM_STR);
    $stmt->bindParam(':file_path', $destPath, PDO::PARAM_STR);
    $stmt->execute();

    // --- Added: Return the file path in the success response ---
    echo json_encode([
        'status' => 'success',
        'message' => 'File uploaded and saved to database.',
        'filePath' => $destPath
    ]);

} catch (Exception $e) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
