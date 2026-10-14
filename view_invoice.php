
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "includes/db.php";

$user_id = (int) $_SESSION["user_id"];
$product_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$product_id || $product_id < 1) {
    http_response_code(400);
    exit("Invalid product ID.");
}

$stmt = $conn->prepare(
    "SELECT invoice FROM products WHERE id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $product_id, $user_id);
$stmt->execute();
$stmt->bind_result($invoice);

if (!$stmt->fetch()) {
    $stmt->close();
    $conn->close();
    http_response_code(404);
    exit("Product not found or you do not have permission to view it.");
}

$stmt->close();
$conn->close();

if (empty($invoice)) {
    http_response_code(404);
    exit("No invoice uploaded for this product.");
}

// Only allow generated invoice filenames and approved extensions.
if (!preg_match('/\A[a-f0-9]{32}\.(pdf|jpg|jpeg|png)\z/i', $invoice)) {
    http_response_code(400);
    exit("Invalid invoice filename.");
}

$file_path = __DIR__ . "/uploads/invoices/" . $invoice;

if (!is_file($file_path) || !is_readable($file_path)) {
    http_response_code(404);
    exit("Invoice file not found on the server.");
}

$extension = strtolower(pathinfo($invoice, PATHINFO_EXTENSION));

$allowed_types = [
    "pdf"  => "application/pdf",
    "jpg"  => "image/jpeg",
    "jpeg" => "image/jpeg",
    "png"  => "image/png"
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file_path);

if (
    !isset($allowed_types[$extension]) ||
    $mime_type !== $allowed_types[$extension]
) {
    http_response_code(415);
    exit("Invoice file type validation failed.");
}

// Clear accidental output buffers before sending the file.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header("Content-Type: " . $mime_type);
header('Content-Disposition: inline; filename="invoice.' . $extension . '"');
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store");

readfile($file_path);
exit();
?>

