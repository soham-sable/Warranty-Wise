
<?php
// TEMPORARY DEBUGGING: show PHP errors while testing locally.
ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/includes/db.php";

$user_id = (int) $_SESSION["user_id"];
$errors = [];

$product_id = "";
$service_date = date("Y-m-d");
$service_type = "";
$service_cost = "0.00";
$service_notes = "";

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Get only products belonging to the logged-in user.
$stmt = $conn->prepare(
    "SELECT id, product_name, brand
     FROM products
     WHERE user_id = ?
     ORDER BY product_name ASC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$products_result = $stmt->get_result();
$products = [];

while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

$allowed_types = [
    "Regular Maintenance",
    "Repair",
    "Inspection",
    "Cleaning",
    "Warranty Service",
    "Other"
];

// Process form submission.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $product_id = filter_input(
        INPUT_POST,
        "product_id",
        FILTER_VALIDATE_INT
    );

    $service_date = trim($_POST["service_date"] ?? "");
    $service_type = trim($_POST["service_type"] ?? "");
    $service_cost_input = trim($_POST["service_cost"] ?? "");
    $service_notes = trim($_POST["service_notes"] ?? "");

    // Validate product selection.
    if (!$product_id || $product_id < 1) {
        $errors[] = "Please select a valid product.";
    } else {
        $check = $conn->prepare(
            "SELECT id FROM products
             WHERE id = ? AND user_id = ?"
        );

        $check->bind_param("ii", $product_id, $user_id);
        $check->execute();

        $owns_product = $check->get_result()->num_rows === 1;
        $check->close();

        if (!$owns_product) {
            $errors[] = "The selected product is not in your account.";
        }
    }

    // Validate service date.
    $date_object = DateTime::createFromFormat("!Y-m-d", $service_date);
    $date_errors = DateTime::getLastErrors();

    if (
        !$date_object ||
        ($date_errors !== false &&
            ($date_errors["warning_count"] > 0 ||
             $date_errors["error_count"] > 0)) ||
        $date_object->format("Y-m-d") !== $service_date
    ) {
        $errors[] = "Please enter a valid service date.";
    }

    // Validate service type.
    if (!in_array($service_type, $allowed_types, true)) {
        $errors[] = "Please select a valid service type.";
    }

    // Validate cost.
    if (
        $service_cost_input === "" ||
        !is_numeric($service_cost_input) ||
        (float)$service_cost_input < 0 ||
        (float)$service_cost_input > 99999999
    ) {
        $errors[] = "Enter a valid service cost of ₹0 or more.";
    } else {
        $service_cost = (float)$service_cost_input;
    }

    if (strlen($service_notes) > 5000) {
        $errors[] = "Service notes must be 5000 characters or fewer.";
    }

    // Save the service record.
    if (!$errors) {
        $stmt = $conn->prepare(
            "INSERT INTO service_records
             (product_id, service_date, service_type,
              service_cost, service_notes)
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "issds",
            $product_id,
            $service_date,
            $service_type,
            $service_cost,
            $service_notes
        );

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();

            header("Location: services.php?added=1");
            exit();
        }

        $errors[] = "Unable to save the service record. Please try again.";
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Service | WarrantyWise</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px;
            background: #f5f7fc;
            color: #172033;
            font-family: Arial, Helvetica, sans-serif;
        }

        .container {
            max-width: 800px;
            margin: auto;
        }

        .top {
            margin-bottom: 25px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 29px;
        }

        .subtitle {
            color: #778198;
            font-size: 14px;
            line-height: 1.6;
        }

        .card {
            background: white;
            padding: 28px;
            border: 1px solid #e8ebf3;
            border-radius: 14px;
            box-shadow: 0 4px 15px #18234805;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9deea;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: white;
        }

        input:focus, select:focus, textarea:focus {
            outline: 2px solid #c7d2fe;
            border-color: #6366f1;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn {
            display: inline-block;
            padding: 12px 18px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }

        .primary {
            background: #4f46e5;
            color: white;
        }

        .secondary {
            background: #eef0f6;
            color: #38425a;
        }

        .errors {
            padding: 12px 18px;
            margin-bottom: 20px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            line-height: 1.6;
        }

        .empty {
            padding: 20px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 9px;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            .card {
                padding: 18px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="top">
        <h1>Add Service Record</h1>
        <div class="subtitle">
            Record a repair, inspection, or maintenance visit.
        </div>
    </div>

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

        <?php if (count($products) === 0): ?>

            <div class="empty">
                <strong>No products available.</strong>
                <p>Add a product before creating its service record.</p>

                <a href="add_product.php" class="btn primary">
                    Add Product
                </a>
            </div>

        <?php else: ?>

            <form method="POST" action="add_service.php">
                <div class="grid">

                    <div class="field full">
                        <label for="product_id">Select Product *</label>

                        <select id="product_id" name="product_id" required>
                            <option value="">Choose a product</option>

                            <?php foreach ($products as $product): ?>
                                <option
                                    value="<?= (int)$product["id"] ?>"
                                    <?= (string)$product_id === (string)$product["id"] ? "selected" : "" ?>>

                                    <?= e($product["product_name"]) ?>
                                    <?= !empty($product["brand"])
                                        ? " — " . e($product["brand"])
                                        : "" ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="service_date">Service Date *</label>

                        <input
                            type="date"
                            id="service_date"
                            name="service_date"
                            value="<?= e($service_date) ?>"
                            required>
                    </div>

                    <div class="field">
                        <label for="service_type">Service Type *</label>

                        <select id="service_type" name="service_type" required>
                            <option value="">Choose service type</option>

                            <?php foreach ($allowed_types as $type): ?>
                                <option
                                    value="<?= e($type) ?>"
                                    <?= $service_type === $type ? "selected" : "" ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field full">
                        <label for="service_cost">Service Cost (₹) *</label>

                        <input
                            type="number"
                            id="service_cost"
                            name="service_cost"
                            min="0"
                            max="99999999"
                            step="0.01"
                            value="<?= e($service_cost) ?>"
                            required>
                    </div>

                    <div class="field full">
                        <label for="service_notes">Service Notes</label>

                        <textarea
                            id="service_notes"
                            name="service_notes"
                            maxlength="5000"
                            placeholder="Describe the repair, parts replaced, or maintenance performed..."><?= e($service_notes) ?></textarea>
                    </div>

                </div>

                <div class="actions">
                    <button type="submit" class="btn primary">
                        Save Service Record
                    </button>

                    <a href="services.php" class="btn secondary">
                        Cancel
                    </a>
                </div>
            </form>

        <?php endif; ?>

    </div>
</div>
</body>
</html>
