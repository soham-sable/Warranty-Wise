
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "includes/db.php";

// Allow deletion only through POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_products.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$product_id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$product_id || $product_id < 1) {
    header("Location: my_products.php");
    exit();
}

// Delete only if the product belongs to this user.
$sql = "DELETE FROM products WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $product_id, $user_id);
$stmt->execute();

$deleted = $stmt->affected_rows > 0;

$stmt->close();
$conn->close();

if ($deleted) {
    header("Location: my_products.php?deleted=1");
} else {
    header("Location: my_products.php?error=notfound");
}
exit();
?>
