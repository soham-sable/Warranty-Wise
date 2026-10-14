
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
    header("Location: my_products.php");
    exit();
}

$message = "";
$errors = [];

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Load only the product owned by this user.
$stmt = $conn->prepare(
    "SELECT * FROM products WHERE id = ? AND user_id = ?"
);
$stmt->bind_param("ii", $product_id, $user_id);
$stmt->execute();

$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    exit("Product not found or you do not have permission to edit it.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $product_name = trim($_POST["product_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $brand = trim($_POST["brand"] ?? "");
    $model_number = trim($_POST["model_number"] ?? "");
    $serial_number = trim($_POST["serial_number"] ?? "");
    $purchase_date = $_POST["purchase_date"] ?? "";
    $purchase_price_input = trim($_POST["purchase_price"] ?? "");
    $warranty_period_input = trim($_POST["warranty_period"] ?? "");
    $next_service_date = $_POST["next_service_date"] ?? "";
    $seller = trim($_POST["seller"] ?? "");

    if ($product_name === "" || $category === "") {
        $errors[] = "Product name and category are required.";
    }

    $purchase_timestamp = strtotime($purchase_date);
    if (!$purchase_timestamp ||
        date("Y-m-d", $purchase_timestamp) !== $purchase_date) {
        $errors[] = "Please enter a valid purchase date.";
    }

    $purchase_price = null;
    if ($purchase_price_input !== "") {
        if (!is_numeric($purchase_price_input) ||
            (float)$purchase_price_input < 0) {
            $errors[] = "Purchase price must be a valid non-negative number.";
        } else {
            $purchase_price = (float)$purchase_price_input;
        }
    }

    $warranty_period = filter_var(
        $warranty_period_input,
        FILTER_VALIDATE_INT
    );

    if ($warranty_period === false || $warranty_period < 1 ||
        $warranty_period > 1200) {
        $errors[] = "Warranty period must be between 1 and 1200 months.";
    }

    if ($next_service_date !== "") {
        $service_timestamp = strtotime($next_service_date);
        if (!$service_timestamp ||
            date("Y-m-d", $service_timestamp) !== $next_service_date) {
            $errors[] = "Please enter a valid next service date.";
        }
    } else {
        $next_service_date = null;
    }

    if (!$errors) {
        try {
            $purchase = new DateTime($purchase_date);
            $expiry = clone $purchase;
            $expiry->modify("+".$warranty_period." months");
            $warranty_expiry = $expiry->format("Y-m-d");

            $sql = "UPDATE products SET
                    product_name = ?,
                    category = ?,
                    brand = ?,
                    model_number = ?,
                    serial_number = ?,
                    purchase_date = ?,
                    purchase_price = ?,
                    warranty_period = ?,
                    warranty_expiry = ?,
                    next_service_date = ?,
                    seller = ?
                    WHERE id = ? AND user_id = ?";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "ssssssdisssii",
                $product_name,
                $category,
                $brand,
                $model_number,
                $serial_number,
                $purchase_date,
                $purchase_price,
                $warranty_period,
                $warranty_expiry,
                $next_service_date,
                $seller,
                $product_id,
                $user_id
            );

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: my_products.php?updated=1");
                exit();
            }

            $errors[] = "Could not update the product.";
            $stmt->close();

        } catch (Exception $ex) {
            $errors[] = "Could not calculate the warranty expiry date.";
        }
    }

    // Preserve submitted values if validation fails.
    $product = array_merge($product, [
        "product_name" => $product_name,
        "category" => $category,
        "brand" => $brand,
        "model_number" => $model_number,
        "serial_number" => $serial_number,
        "purchase_date" => $purchase_date,
        "purchase_price" => $purchase_price_input,
        "warranty_period" => $warranty_period_input,
        "next_service_date" => $next_service_date,
        "seller" => $seller
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | WarrantyWise</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 25px;
            background: #f4f7fb;
            color: #1f2937;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 850px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 3px 14px #0000000d;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .full { grid-column: 1 / -1; }

        label { font-weight: bold; }

        input, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 15px;
        }

        input:focus, select:focus {
            outline: 2px solid #93c5fd;
            border-color: #2563eb;
        }

        .btn {
            display: inline-block;
            padding: 12px 18px;
            border: none;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .save {
            background: #2563eb;
            color: white;
        }

        .cancel {
            background: #e5e7eb;
            color: #111827;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }

        .errors {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 18px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .hint {
            color: #6b7280;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            body { padding: 12px; }
            .card { padding: 18px; }
            .grid { grid-template-columns: 1fr; }
            .full { grid-column: auto; }
        }
    </style>
</head>

<body>
<div class="container">
    <h1>Edit Product</h1>
    <p>Update your product and warranty details.</p>

    <div class="card">

        <?php if ($errors): ?>
            <div class="errors">
                <strong>Please fix the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="grid">

                <div class="field">
                    <label for="product_name">Product Name *</label>
                    <input id="product_name" name="product_name"
                        value="<?= e($product["product_name"]) ?>"
                        maxlength="150" required>
                </div>

                <div class="field">
                    <label for="category">Category *</label>
                    <input id="category" name="category"
                        value="<?= e($product["category"]) ?>"
                        maxlength="100" required>
                </div>

                <div class="field">
                    <label for="brand">Brand</label>
                    <input id="brand" name="brand"
                        value="<?= e($product["brand"]) ?>"
                        maxlength="100">
                </div>

                <div class="field">
                    <label for="model_number">Model Number</label>
                    <input id="model_number" name="model_number"
                        value="<?= e($product["model_number"]) ?>"
                        maxlength="100">
                </div>

                <div class="field">
                    <label for="serial_number">Serial Number</label>
                    <input id="serial_number" name="serial_number"
                        value="<?= e($product["serial_number"]) ?>"
                        maxlength="100">
                </div>

                <div class="field">
                    <label for="purchase_date">Purchase Date *</label>
                    <input type="date" id="purchase_date"
                        name="purchase_date"
                        value="<?= e($product["purchase_date"]) ?>"
                        required>
                </div>

                <div class="field">
                    <label for="purchase_price">Purchase Price (₹)</label>
                    <input type="number" id="purchase_price"
                        name="purchase_price" min="0" step="0.01"
                        value="<?= e($product["purchase_price"]) ?>">
                </div>

                <div class="field">
                    <label for="warranty_period">Warranty (months) *</label>
                    <input type="number" id="warranty_period"
                        name="warranty_period" min="1" max="1200"
                        value="<?= e($product["warranty_period"]) ?>"
                        required>
                    <span class="hint">Expiry is recalculated from purchase date.</span>
                </div>

                <div class="field">
                    <label for="next_service_date">Next Service Date</label>
                    <input type="date" id="next_service_date"
                        name="next_service_date"
                        value="<?= e($product["next_service_date"]) ?>">
                </div>

                <div class="field">
                    <label for="seller">Seller / Shop</label>
                    <input id="seller" name="seller"
                        value="<?= e($product["seller"]) ?>"
                        maxlength="150">
                </div>

            </div>

            <div class="actions">
                <button type="submit" class="btn save">
                    Save Changes
                </button>

                <a href="my_products.php" class="btn cancel">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>

<?php
$conn->close();
?>
