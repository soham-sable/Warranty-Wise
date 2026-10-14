
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/includes/db.php";

$user_id = (int) $_SESSION["user_id"];
$services = [];

// Create a CSRF token for the delete form.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Fetch service records for the logged-in user.
$sql = "SELECT
            sr.id,
            sr.service_date,
            sr.service_type,
            sr.service_cost,
            sr.service_notes,
            p.product_name,
            p.brand
        FROM service_records sr
        INNER JOIN products p ON p.id = sr.product_id
        WHERE p.user_id = ?
        ORDER BY sr.service_date DESC, sr.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Service Records | WarrantyWise</title>

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
            max-width: 1200px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
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

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            white-space: nowrap;
        }

        .primary {
            background: #4f46e5;
            color: white;
        }

        .secondary {
            background: #e9ecf5;
            color: #344054;
        }

        .danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .success-alert {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            padding: 14px 18px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .error-alert {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 14px 18px;
            border-radius: 9px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border: 1px solid #e8ebf3;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 4px 15px #18234805;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            padding: 15px 12px;
            background: #f8f9fd;
            color: #697386;
            font-size: 12px;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid #edf0f6;
            font-size: 13px;
            vertical-align: top;
            line-height: 1.5;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .product-name {
            font-weight: bold;
            color: #202940;
        }

        .brand {
            color: #7b8497;
            font-size: 12px;
        }

        .cost {
            font-weight: bold;
            white-space: nowrap;
        }

        .notes {
            max-width: 250px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .delete-form {
            display: inline;
        }

        .empty {
            text-align: center;
            padding: 50px 20px;
        }

        .empty h2 {
            margin-top: 0;
            font-size: 21px;
        }

        .empty p {
            color: #778198;
            margin-bottom: 22px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            .card {
                padding: 12px;
            }

            h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="header">
        <div>
            <h1>Service Records</h1>

            <div class="subtitle">
                Track repairs, maintenance, inspections, and service costs.
            </div>
        </div>

        <div class="actions">
            <a href="dashboard.php" class="btn secondary">
                Dashboard
            </a>

            <a href="add_service.php" class="btn primary">
                + Add Service
            </a>
        </div>
    </div>

    <?php if (($_GET["added"] ?? "") === "1"): ?>
        <div class="success-alert" role="status">
            ✓ Service record added successfully!
        </div>
    <?php endif; ?>

    <?php if (($_GET["updated"] ?? "") === "1"): ?>
        <div class="success-alert" role="status">
            ✓ Service record updated successfully!
        </div>
    <?php endif; ?>

    <?php if (($_GET["deleted"] ?? "") === "1"): ?>
        <div class="success-alert" role="status">
            ✓ Service record deleted successfully!
        </div>
    <?php endif; ?>

    <?php if (($_GET["error"] ?? "") === "notfound"): ?>
        <div class="error-alert" role="alert">
            The requested service record was not found.
        </div>
    <?php elseif (($_GET["error"] ?? "") === "delete"): ?>
        <div class="error-alert" role="alert">
            The service record could not be deleted. Please try again.
        </div>
    <?php endif; ?>

    <div class="card">

        <?php if (count($services) === 0): ?>

            <div class="empty">
                <h2>No Service Records Yet</h2>

                <p>
                    Your service history will appear here after you
                    record your first repair or maintenance visit.
                </p>

                <a href="add_service.php" class="btn primary">
                    Add Your First Service
                </a>
            </div>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Service Date</th>
                            <th>Service Type</th>
                            <th>Cost</th>
                            <th>Service Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <div class="product-name">
                                    <?= e($service["product_name"]) ?>
                                </div>

                                <?php if (!empty($service["brand"])): ?>
                                    <div class="brand">
                                        <?= e($service["brand"]) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e(date(
                                    "d M Y",
                                    strtotime($service["service_date"])
                                )) ?>
                            </td>

                            <td>
                                <?= e($service["service_type"]) ?>
                            </td>

                            <td class="cost">
                                ₹<?= number_format(
                                    (float)$service["service_cost"],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <div class="notes">
                                    <?= e(
                                        $service["service_notes"] ?: "—"
                                    ) ?>
                                </div>
                            </td>

                            <td>
                                <div class="actions">
                                    <a
                                        href="edit_service.php?id=<?= (int)$service["id"] ?>"
                                        class="btn primary">
                                        Edit
                                    </a>

                                    <form
                                        class="delete-form"
                                        method="POST"
                                        action="delete_service.php"
                                        onsubmit="return confirm('Are you sure you want to delete this service record? This action cannot be undone.');">

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$service["id"] ?>">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrf_token) ?>">

                                        <button
                                            type="submit"
                                            class="btn danger">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </div>
</div>
</body>
</html>
