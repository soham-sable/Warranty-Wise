
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: services.php");
    exit();
}

require_once __DIR__ . "/includes/db.php";

$user_id = (int) $_SESSION["user_id"];
$service_id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
$submitted_token = $_POST["csrf_token"] ?? "";
$session_token = $_SESSION["csrf_token"] ?? "";

if (
    !$service_id ||
    $service_id < 1 ||
    !is_string($submitted_token) ||
    !is_string($session_token) ||
    $session_token === "" ||
    !hash_equals($session_token, $submitted_token)
) {
    $conn->close();
    header("Location: services.php?error=delete");
    exit();
}

// Delete only a service record linked to this user's product.
$stmt = $conn->prepare(
    "DELETE sr
     FROM service_records sr
     INNER JOIN products p ON p.id = sr.product_id
     WHERE sr.id = ? AND p.user_id = ?"
);

$stmt->bind_param("ii", $service_id, $user_id);
$success = $stmt->execute();
$deleted = $stmt->affected_rows;

$stmt->close();
$conn->close();

if ($success && $deleted === 1) {
    header("Location: services.php?deleted=1");
    exit();
}

header("Location: services.php?error=notfound");
exit();
?>
