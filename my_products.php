
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "includes/db.php";

$user_id = (int) $_SESSION["user_id"];

$sql = "SELECT * FROM products
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

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
    <title>My Products | WarrantyWise</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 1400px;
            margin: auto;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        h1 {
            margin-bottom: 6px;
        }

        .subtitle {
            color: #6b7280;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            margin: 3px;
            font-family: Arial, sans-serif;
        }

        .btn-blue {
            background: #2563eb;
            color: white;
        }

        .btn-green {
            background: #059669;
            color: white;
        }

        .btn-red {
            background: #dc2626;
            color: white;
        }

        .btn:hover {
            opacity: 0.88;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 14px #0000000d;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #eff4ff;
            color: #1d4ed8;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            white-space: nowrap;
        }

        .actions form {
            display: inline;
        }

        .empty {
            text-align: center;
            padding: 40px 15px;
        }

        .empty p {
            color: #6b7280;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-weight: bold;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .card {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="top">
        <div>
            <h1>My Products</h1>
            <p class="subtitle">
                Manage your registered products and warranties.
            </p>
        </div>

        <div>
            <a href="dashboard.php" class="btn btn-blue">
                Dashboard
            </a>

            <a href="add_product.php" class="btn btn-green">
                + Add Product
            </a>
        </div>
    </div>

    <!-- Success and error messages -->
    <?php if (isset($_GET["added"]) && $_GET["added"] === "1"): ?>

        <div class="alert success">
            &#10003; Product added successfully!
        </div>

    <?php elseif (isset($_GET["updated"]) && $_GET["updated"] === "1"): ?>

        <div class="alert success">
            &#10003; Product updated successfully!
        </div>

    <?php elseif (isset($_GET["deleted"]) && $_GET["deleted"] === "1"): ?>

        <div class="alert success">
            &#10003; Product deleted successfully!
        </div>

    <?php elseif (isset($_GET["error"]) && $_GET["error"] === "notfound"): ?>

        <div class="alert error">
            Product could not be deleted. It may not exist or may not
            belong to your account.
        </div>

    <?php endif; ?>

    <div class="card">

        <?php if ($result && $result->num_rows > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Purchase Date</th>
                        <th>Warranty Expiry</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php while ($product = $result->fetch_assoc()): ?>

                    <?php
                    $today = date("Y-m-d");
                    $isExpired =
                        $product["warranty_expiry"] < $today;
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e($product["product_name"]) ?>
                            </strong>
                            <br>
                            <small>
                                Model:
                                <?= e($product["model_number"]) ?: "—" ?>
                            </small>
                        </td>

                        <td>
                            <?= e($product["brand"]) ?: "—" ?>
                        </td>

                        <td>
                            <?= e($product["category"]) ?>
                        </td>

                        <td>
                            <?= e($product["purchase_date"]) ?>
                        </td>

                        <td>
                            <?= e($product["warranty_expiry"]) ?>
                        </td>

                        <td>
                            <span class="status <?= $isExpired ? 'expired' : 'active' ?>">
                                <?= $isExpired ? "Expired" : "Active" ?>
                            </span>
                        </td>

                        <td class="actions">

                            <!-- Edit Product -->
                            <a
                                href="edit_product.php?id=<?= (int)$product['id'] ?>"
                                class="btn btn-blue">
                                Edit
                            </a>

                            <!-- View Invoice: shown only if an invoice exists -->
                            <?php if (!empty($product["invoice"])): ?>
                                <a
                                    href="view_invoice.php?id=<?= (int)$product['id'] ?>"
                                    class="btn btn-green"
                                    target="_blank"
                                    rel="noopener noreferrer">
                                    View Invoice
                                </a>
                            <?php endif; ?>

                            <!-- Delete Product -->
                            <form
                                action="delete_product.php"
                                method="POST"
                                onsubmit="return confirm('Are you sure you want to delete this product? Its related service records may also be deleted.');">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$product['id'] ?>">

                                <button
                                    type="submit"
                                    class="btn btn-red">
                                    Delete
                                </button>
                            </form>

                        </td>
                    </tr>

                <?php endwhile; ?>
                </tbody>
            </table>

        <?php else: ?>

            <div class="empty">
                <h2>No Products Found</h2>

                <p>
                    You haven't registered any products yet.
                </p>

                <a href="add_product.php" class="btn btn-green">
                    Add Your First Product
                </a>
            </div>

        <?php endif; ?>

    </div>

</div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
