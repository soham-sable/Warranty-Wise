
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/includes/db.php";

$user_id = (int) $_SESSION["user_id"];
$errors = [];
$service = null;

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$service_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $service_id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
}

if (!$service_id || $service_id < 1) {
    $conn->close();
    header("Location: services.php?error=notfound");
    exit();
}

// Load a service record belonging to the logged-in user.
$stmt = $conn->prepare(
    "SELECT
        sr.id,
        sr.product_id,
        sr.service_date,
        sr.service_type,
        sr.service_cost,
        sr.service_notes,
        p.product_name,
        p.brand
     FROM service_records sr
     INNER JOIN products p ON p.id = sr.product_id
     WHERE sr.id = ? AND p.user_id = ?"
);

$stmt->bind_param("ii", $service_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$service = $result->fetch_assoc();

$stmt->close();

if (!$service) {
    $conn->close();
    header("Location: services.php?error=notfound");
    exit();
}

$allowed_types = [
    "Regular Maintenance",
    "Repair",
    "Inspection",
    "Cleaning",
    "Warranty Service",
    "Other"
];

$service_date = $service["service_date"];
$service_type = $service["service_type"];
$service_cost = $service["service_cost"];
$service_notes = $service["service_notes"] ?? "";

// Process changes submitted through the form.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $service_date = trim($_POST["service_date"] ?? "");
    $service_type = trim($_POST["service_type"] ?? "");
    $cost_input = trim($_POST["service_cost"] ?? "");
    $service_notes = trim($_POST["service_notes"] ?? "");

    // Validate date.
    $date_object = DateTime::createFromFormat(
        "!Y-m-d",
        $service_date
    );

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

    // Validate service cost.
    if (
        $cost_input === "" ||
        !is_numeric($cost_input) ||
        (float)$cost_input < 0 ||
        (float)$cost_input > 99999999
    ) {
        $errors[] = "Please enter a valid service cost.";
    } else {
        $service_cost = (float)$cost_input;
    }

    // Validate notes.
    if (strlen($service_notes) > 5000) {
        $errors[] = "Service notes must be 5000 characters or fewer.";
    }

    if (!$errors) {
        // Update only the record belonging to the user's product.
        $update = $conn->prepare(
            "UPDATE service_records
             SET service_date = ?,
                 service_type = ?,
                 service_cost = ?,
                 service_notes = ?
             WHERE id = ?
               AND product_id IN (
                   SELECT id FROM products WHERE user_id = ?
               )"
        );

        $update->bind_param(
            "ssdsii",
            $service_date,
            $service_type,
            $service_cost,
            $service_notes,
            $service_id,
            $user_id
        );

        if ($update->execute()) {
            $update->close();
            $conn->close();

            header("Location: services.php?updated=1");
            exit();
        }

        $errors[] = "Unable to update the service record. Please try again.";
        $update->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service | WarrantyWise</title>

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

        h1 {
            margin: 0 0 8px;
            font-size: 29px;
        }

        .subtitle {
            color: #778198;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 28px;
            border: 1px solid #e8ebf3;
            border-radius: 14px;
        }

        .product-info {
            padding: 14px;
            margin-bottom: 22px;
            border-radius: 8px;
            background: #f3f4ff;
            line-height: 1.6;
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

    <h1>Edit Service Record</h1>

    <div class="subtitle">
        Update the service details for your registered product.
    </div>

    <div class="card">

        <div class="product-info">
            <strong>Product:</strong>
            <?= e($service["product_name"]) ?>

            <?php if (!empty($service["brand"])): ?>
                — <?= e($service["brand"]) ?>
            <?php endif; ?>
        </div>

        <?php if ($errors): ?>
            <div class="errors" role="alert">
                <strong>Please fix the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit_service.php">
            <input
                type="hidden"
                name="id"
                value="<?= (int)$service_id ?>">

            <div class="grid">

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
                    <select
                        id="service_type"
                        name="service_type"
                        required>

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
                        placeholder="Describe the service performed..."><?= e($service_notes) ?></textarea>
                </div>

            </div>

            <div class="actions">
                <button type="submit" class="btn primary">
                    Save Changes
                </button>

                <a href="services.php" class="btn secondary">
                    Cancel
                </a>
            </div>
        </form>

    </div>
</div>
</body>
</html>
