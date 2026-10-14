
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "includes/db.php";

$user_id = (int) $_SESSION["user_id"];

// Warranties expiring within the next 7 days
$warranty_sql = "
    SELECT product_name, brand, warranty_expiry
    FROM products
    WHERE user_id = ?
      AND warranty_expiry BETWEEN CURDATE()
          AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY warranty_expiry ASC
";

$stmt = $conn->prepare($warranty_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$warranties = $stmt->get_result();

// Services due within the next 3 days
$service_sql = "
    SELECT product_name, brand, next_service_date
    FROM products
    WHERE user_id = ?
      AND next_service_date BETWEEN CURDATE()
          AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY next_service_date ASC
";

$stmt2 = $conn->prepare($service_sql);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$services = $stmt2->get_result();

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Reminders | WarrantyWise</title>

    <style>
        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 22px;
            margin: 20px 0;
            border-radius: 12px;
            box-shadow: 0 3px 14px #0000000d;
        }

        h1 {
            color: #4338ca;
        }

        h2 {
            margin-top: 0;
        }

        .item {
            padding: 14px;
            margin: 10px 0;
            background: #f8fafc;
            border-left: 4px solid #6366f1;
            border-radius: 6px;
        }

        .empty {
            color: #64748b;
        }

        .btn {
            display: inline-block;
            padding: 11px 16px;
            background: #4338ca;
            color: white;
            border-radius: 7px;
            text-decoration: none;
        }

        .note {
            background: #eef2ff;
            padding: 14px;
            border-radius: 8px;
            line-height: 1.6;
        }
    </style>
</head>

<body>
<div class="container">

    <h1>WarrantyWise Email Reminders</h1>

    <p>Review your upcoming warranty and service reminders.</p>

    <div class="note">
        These reminders are currently displayed on this page.
        Actual email delivery has not been configured yet.
    </div>

    <div class="card">
        <h2>Warranties Expiring Within 7 Days</h2>

        <?php if ($warranties->num_rows > 0): ?>
            <?php while ($row = $warranties->fetch_assoc()): ?>
                <div class="item">
                    <strong><?= e($row["product_name"]) ?></strong>
                    <br>
                    Brand: <?= e($row["brand"] ?: "Not specified") ?>
                    <br>
                    Expiry date: <?= e($row["warranty_expiry"]) ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty">No warranties expiring in the next 7 days.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Services Due Within 3 Days</h2>

        <?php if ($services->num_rows > 0): ?>
            <?php while ($row = $services->fetch_assoc()): ?>
                <div class="item">
                    <strong><?= e($row["product_name"]) ?></strong>
                    <br>
                    Brand: <?= e($row["brand"] ?: "Not specified") ?>
                    <br>
                    Service date: <?= e($row["next_service_date"]) ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty">No services due in the next 3 days.</p>
        <?php endif; ?>
    </div>

    <a href="dashboard.php" class="btn">Back to Dashboard</a>

</div>
</body>
</html>

<?php
$stmt->close();
$stmt2->close();
$conn->close();
?>

