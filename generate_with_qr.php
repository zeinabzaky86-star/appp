<?php
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/fpdf/fpdf.php');
require_once(__DIR__ . '/fpdf/fpdi/src/autoload.php');
require_once(__DIR__ . '/qrlib.php');

ob_clean();
header('Content-Type: application/json');

$uploadDir = __DIR__ . '/uploads/';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdfFile'])) {
        http_response_code(400);
        throw new Exception('Invalid request.');
    }

    $originalPdfTmpPath = $_FILES['pdfFile']['tmp_name'];
    $originalFileName = basename($_FILES['pdfFile']['name']);
    $qrX = (int)($_POST['x'] ?? 10);
    $qrY = (int)($_POST['y'] ?? 10);

    $safeFileName = preg_replace("/[^a-zA-Z0-9-_\.]/", "", $originalFileName);
    $newFileName = time() . '-qr-' . $safeFileName;
    $finalPdfPath = 'uploads/' . $newFileName;
    $finalPdfAbsolutePath = $uploadDir . $newFileName;

    $finalPdfUrl = $baseUrl . $finalPdfPath;
    $qrCodeImagePath = $uploadDir . time() . '.png';

    if (!is_writable($uploadDir)) {
        http_response_code(500);
        throw new Exception("The uploads directory ('" . $uploadDir . "') is not writable by the server.");
    }
    
    QRcode::png($finalPdfUrl, $qrCodeImagePath, QR_ECLEVEL_L, 4);

    $pdf = new \setasign\Fpdi\Fpdi();
    $pageCount = $pdf->setSourceFile($originalPdfTmpPath);

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $templateId = $pdf->importPage($pageNo);
        $size = $pdf->getTemplateSize($templateId);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId);

        if ($pageNo == 1) {
            $qrWidth = 30;
            $qrYfromTop = $size['height'] - $qrY - $qrWidth;
            $pdf->Image($qrCodeImagePath, $qrX, $qrYfromTop, $qrWidth, 0, 'PNG');
        }
    }

    $pdf->Output('F', $finalPdfAbsolutePath);
    unlink($qrCodeImagePath);

    echo json_encode([
        'status' => 'success',
        'message' => 'PDF with QR code generated!',
        'filePath' => $finalPdfPath
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
}

ob_end_flush();
?>
