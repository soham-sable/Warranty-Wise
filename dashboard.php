


<?php

session_start();



if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");

    exit();

}



require_once __DIR__ . "/includes/db.php";



$user_id = (int) $_SESSION["user_id"];

$user_name = $_SESSION["user_name"] ?? "User";



function e($value) {

    return htmlspecialchars(

        (string)($value ?? ""),

        ENT_QUOTES,

        "UTF-8"

    );

}



// Total products

$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total

     FROM products

     WHERE user_id = ?"

);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$total_products = (int)$stmt->get_result()->fetch_assoc()["total"];

$stmt->close();



// Active warranties

$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total

     FROM products

     WHERE user_id = ? AND warranty_expiry >= CURDATE()"

);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$active_warranties = (int)$stmt->get_result()->fetch_assoc()["total"];

$stmt->close();



// Expired warranties

$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total

     FROM products

     WHERE user_id = ? AND warranty_expiry < CURDATE()"

);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$expired_warranties = (int)$stmt->get_result()->fetch_assoc()["total"];

$stmt->close();



// Upcoming services within the next 30 days

$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total

     FROM products

     WHERE user_id = ?

       AND next_service_date BETWEEN CURDATE()

       AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"

);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$upcoming_services = (int)$stmt->get_result()->fetch_assoc()["total"];

$stmt->close();



// Warranties expiring within the next 30 days

$stmt = $conn->prepare(

    "SELECT product_name, brand, warranty_expiry

     FROM products

     WHERE user_id = ?

       AND warranty_expiry BETWEEN CURDATE()

       AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)

     ORDER BY warranty_expiry ASC"

);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$expiring_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();



// Services whose scheduled dates have passed

$stmt = $conn->prepare(

    "SELECT product_name, brand, next_service_date

     FROM products

     WHERE user_id = ?

       AND next_service_date < CURDATE()

     ORDER BY next_service_date ASC"

);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$overdue_services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();



// Services due today or within the next 7 days

$stmt = $conn->prepare(

    "SELECT product_name, brand, next_service_date

     FROM products

     WHERE user_id = ?

       AND next_service_date BETWEEN CURDATE()

       AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)

     ORDER BY next_service_date ASC"

);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$upcoming_service_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();



// Recent products

$stmt = $conn->prepare(

    "SELECT product_name, brand, category, warranty_expiry

     FROM products

     WHERE user_id = ?

     ORDER BY created_at DESC

     LIMIT 5"

);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$recent_products = $stmt->get_result();



// Close the database connection after fetching the required data.

$stmt->close();

$conn->close();

?>



<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard | WarrantyWise</title>



<style>

* {

    box-sizing: border-box;

}



:root {

    --primary: #4f46e5;

    --primary-light: #eef2ff;

    --background: #f5f7fc;

    --text: #172033;

    --muted: #778198;

    --border: #e8ebf3;

    --white: #ffffff;

}



body {

    margin: 0;

    font-family: Arial, Helvetica, sans-serif;

    background: var(--background);

    color: var(--text);

}



a {

    text-decoration: none;

}



.layout {

    display: flex;

    min-height: 100vh;

}



/* Sidebar */

.sidebar {

    width: 245px;

    flex-shrink: 0;

    background: #171d35;

    color: white;

    padding: 28px 18px;

    display: flex;

    flex-direction: column;

}



.brand {

    font-size: 25px;

    font-weight: 800;

    letter-spacing: -0.7px;

    padding: 0 10px 35px;

}



.brand span {

    color: #a5b4fc;

}



.nav-label {

    padding: 0 12px;

    margin-bottom: 12px;

    font-size: 11px;

    color: #8e98b5;

    font-weight: bold;

    letter-spacing: 1.5px;

}



.nav-link {

    display: flex;

    align-items: center;

    gap: 12px;

    color: #c5cbe0;

    padding: 13px 14px;

    border-radius: 9px;

    margin-bottom: 7px;

    font-size: 14px;

    transition: 0.2s;

}



.nav-link:hover,

.nav-link.active {

    color: white;

    background: #303858;

}



.nav-link.active {

    box-shadow: inset 3px 0 #a5b4fc;

}



.nav-icon {

    width: 23px;

    text-align: center;

    font-size: 17px;

}



.sidebar-bottom {

    margin-top: auto;

    padding-top: 30px;

}



.profile {

    display: flex;

    align-items: center;

    gap: 11px;

    padding: 15px 9px 0;

    border-top: 1px solid #343b55;

}



.avatar {

    width: 40px;

    height: 40px;

    border-radius: 12px;

    background: #c7d2fe;

    color: #312e81;

    display: grid;

    place-items: center;

    font-weight: bold;

    font-size: 18px;

    flex-shrink: 0;

}



.profile-name {

    font-size: 13px;

    font-weight: bold;

    overflow-wrap: anywhere;

}



.profile-role {

    font-size: 11px;

    color: #aab3cd;

    margin-top: 5px;

}



/* Main content */

.main {

    flex: 1;

    min-width: 0;

    padding: 30px 36px;

}



.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 35px;

    flex-wrap: wrap;

}



.eyebrow {

    color: var(--muted);

    font-size: 13px;

    margin-bottom: 8px;

}



h1 {

    margin: 0;

    font-size: 29px;

    letter-spacing: -0.7px;

}



.welcome-subtitle {

    color: var(--muted);

    font-size: 14px;

    margin-top: 10px;

}



.top-actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

}



.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    border: none;

    border-radius: 9px;

    padding: 12px 16px;

    font-size: 13px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;

}



.btn:hover {

    transform: translateY(-1px);

    opacity: 0.9;

}



.btn-primary {

    color: white;

    background: var(--primary);

    box-shadow: 0 5px 12px #4f46e526;

}



.btn-light {

    color: #38425a;

    background: white;

    border: 1px solid var(--border);

}



/* Welcome banner */

.welcome-banner {

    position: relative;

    overflow: hidden;

    background: linear-gradient(115deg, #4f46e5, #7770ef);

    border-radius: 16px;

    color: white;

    padding: 28px 30px;

    margin-bottom: 30px;

}



.welcome-banner h2 {

    font-size: 22px;

    margin: 0 0 10px;

}



.welcome-banner p {

    margin: 0;

    color: #e0e7ff;

    font-size: 14px;

    line-height: 1.7;

    max-width: 580px;

}



.banner-decoration {

    position: absolute;

    right: 40px;

    top: -32px;

    font-size: 125px;

    opacity: 0.14;

    transform: rotate(-15deg);

    pointer-events: none;

}



/* Section headings */

.section-heading {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-bottom: 17px;

}



.section-heading h2 {

    margin: 0;

    font-size: 18px;

}



.section-heading p {

    color: var(--muted);

    font-size: 12px;

    margin: 5px 0 0;

}



/* Statistics */

.stats {

    display: grid;

    grid-template-columns: repeat(4, minmax(0, 1fr));

    gap: 17px;

    margin-bottom: 32px;

}



.stat-card {

    background: white;

    border: 1px solid var(--border);

    border-radius: 13px;

    padding: 21px 18px;

    min-width: 0;

    box-shadow: 0 4px 15px #18234805;

}



.stat-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 8px;

    margin-bottom: 19px;

}



.stat-label {

    font-size: 12px;

    line-height: 1.5;

    color: var(--muted);

    font-weight: bold;

}



.stat-icon {

    display: grid;

    place-items: center;

    width: 38px;

    height: 38px;

    flex-shrink: 0;

    border-radius: 11px;

    font-size: 19px;

}



.purple { background: #eef2ff; color: #4f46e5; }

.green { background: #dcfce7; color: #15803d; }

.red { background: #fee2e2; color: #dc2626; }

.orange { background: #ffedd5; color: #c2410c; }



.stat-number {

    font-size: 31px;

    font-weight: 800;

    letter-spacing: -1px;

}



.stat-note {

    font-size: 11px;

    color: var(--muted);

    margin-top: 8px;

}



/* Reminder cards */

.reminders {

    display: grid;

    grid-template-columns: repeat(3, minmax(0, 1fr));

    gap: 17px;

    margin-bottom: 30px;

}



.reminder-card {

    background: white;

    border: 1px solid var(--border);

    border-radius: 13px;

    padding: 20px;

    min-width: 0;

    box-shadow: 0 4px 15px #18234805;

}



.reminder-card h3 {

    font-size: 15px;

    margin: 0 0 8px;

}



.reminder-count {

    font-size: 28px;

    font-weight: 800;

    margin: 10px 0;

}



.reminder-card p {

    font-size: 12px;

    color: var(--muted);

    line-height: 1.6;

}



.reminder-list {

    margin: 14px 0;

    padding-left: 18px;

}



.reminder-list li {

    font-size: 12px;

    line-height: 1.6;

    margin-bottom: 9px;

    overflow-wrap: anywhere;

}



.reminder-link {

    color: var(--primary);

    font-size: 12px;

    font-weight: bold;

}



/* Lower panels */

.bottom-grid {

    display: grid;

    grid-template-columns: minmax(0, 1.6fr) minmax(240px, 1fr);

    gap: 20px;

    align-items: start;

}



.panel {

    background: white;

    border: 1px solid var(--border);

    border-radius: 13px;

    padding: 22px;

    min-width: 0;

}



.view-all {

    font-size: 12px;

    font-weight: bold;

    color: var(--primary);

}



.product-list {

    width: 100%;

    border-collapse: collapse;

    margin-top: 8px;

}



.product-list th {

    font-size: 11px;

    color: var(--muted);

    text-align: left;

    padding: 13px 8px;

    background: #fafbff;

    font-weight: bold;

}



.product-list td {

    font-size: 12px;

    padding: 15px 8px;

    border-bottom: 1px solid #f0f1f6;

    vertical-align: middle;

}



.product-list tr:last-child td {

    border-bottom: none;

}



.product-name {

    font-weight: bold;

    font-size: 13px;

    margin-bottom: 5px;

}



.product-brand {

    font-size: 11px;

    color: var(--muted);

}



.badge {

    display: inline-block;

    border-radius: 20px;

    padding: 6px 9px;

    font-size: 10px;

    font-weight: bold;

    white-space: nowrap;

}



.badge-active {

    background: #dcfce7;

    color: #166534;

}



.badge-expired {

    background: #fee2e2;

    color: #991b1b;

}



.quick-actions {

    display: grid;

    gap: 12px;

    margin-top: 20px;

}



.quick-link {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 15px;

    border: 1px solid var(--border);

    border-radius: 10px;

    color: var(--text);

    transition: 0.2s;

}



.quick-link:hover {

    border-color: #a5b4fc;

    background: #fafaff;

    transform: translateY(-1px);

}



.quick-icon {

    width: 41px;

    height: 41px;

    flex-shrink: 0;

    display: grid;

    place-items: center;

    border-radius: 11px;

    font-size: 19px;

}



.quick-title {

    font-size: 13px;

    font-weight: bold;

}



.quick-desc {

    font-size: 11px;

    color: var(--muted);

    margin-top: 5px;

    line-height: 1.4;

}



.arrow {

    margin-left: auto;

    color: #929bb0;

    font-size: 19px;

}



.empty {

    padding: 25px 8px;

    text-align: center;

    color: var(--muted);

    font-size: 13px;

}



.footer {

    text-align: center;

    color: #929bb0;

    font-size: 11px;

    padding: 28px 0 4px;

}



/* Responsive layout */

@media (max-width: 1100px) {

    .main { padding: 25px 22px; }



    .stats {

        grid-template-columns: repeat(2, minmax(0, 1fr));

    }



    .reminders {

        grid-template-columns: 1fr;

    }



    .bottom-grid {

        grid-template-columns: 1fr;

    }

}



@media (max-width: 700px) {

    .layout { display: block; }



    .sidebar {

        width: 100%;

        padding: 17px;

    }



    .brand { padding: 0 4px 16px; }



    .nav-label,

    .sidebar-bottom {

        display: none;

    }



    .sidebar nav {

        display: flex;

        gap: 7px;

        overflow-x: auto;

    }



    .nav-link {

        margin: 0;

        flex-shrink: 0;

        padding: 10px 12px;

        font-size: 12px;

    }



    .main { padding: 23px 15px; }

    .topbar { margin-bottom: 23px; }

    h1 { font-size: 25px; }



    .welcome-banner { padding: 23px 20px; }



    .banner-decoration {

        right: 5px;

        font-size: 90px;

    }



    .stats { gap: 11px; }



    .stat-card { padding: 15px 12px; }



    .stat-icon {

        width: 31px;

        height: 31px;

        font-size: 15px;

    }



    .stat-number { font-size: 27px; }

    .panel { padding: 15px; }



    .product-list { min-width: 490px; }



    .product-table-wrap { overflow-x: auto; }

}

</style>

</head>



<body>

<div class="layout">



    <aside class="sidebar">

        <div class="brand">Warranty<span>Wise</span></div>



        <div class="nav-label">MAIN MENU</div>



        <nav>

            <a class="nav-link active" href="dashboard.php">

                <span class="nav-icon">⌂</span> Dashboard

            </a>



            <a class="nav-link" href="my_products.php">

                <span class="nav-icon">▣</span> My Products

            </a>



            <a class="nav-link" href="add_product.php">

                <span class="nav-icon">＋</span> Add Product

            </a>



            <a class="nav-link" href="services.php">

                <span class="nav-icon">⚙</span> Service Records

            </a>

        </nav>



        <div class="sidebar-bottom">

            <div class="profile">

                <div class="avatar">

                    <?= e(strtoupper(substr($user_name, 0, 1))) ?>

                </div>



                <div>

                    <div class="profile-name"><?= e($user_name) ?></div>

                    <div class="profile-role">WarrantyWise User</div>

                </div>

            </div>



            <a class="nav-link" href="logout.php" style="margin-top:15px;">

                <span class="nav-icon">↪</span> Logout

            </a>

        </div>

    </aside>



    <main class="main">



        <header class="topbar">

            <div>

                <div class="eyebrow">YOUR PERSONAL WARRANTY MANAGER</div>

                <h1>Welcome back, <?= e($user_name) ?>! 👋</h1>

                <div class="welcome-subtitle">

                    Here's an overview of your products and warranties.

                </div>

            </div>



            <div class="top-actions">

                <a href="my_products.php" class="btn btn-light">

                    View Products

                </a>



                <a href="add_product.php" class="btn btn-primary">

                    ＋ Add Product

                </a>

            </div>

        </header>



        <section class="welcome-banner">

            <h2>Stay on top of every warranty.</h2>

            <p>

                Keep your product information organized, track warranty

                expiry dates, and stay prepared for upcoming maintenance.

                Everything you need, in one place.

            </p>

            <div class="banner-decoration">✓</div>

        </section>



        <!-- Warranty and service reminders -->

        <section class="reminders">



            <div class="reminder-card">

                <h3>🗓️ Warranty Expiring Soon</h3>

                <div class="reminder-count">

                    <?= count($expiring_products) ?>

                </div>

                <p>Warranties expiring within the next 30 days.</p>



                <?php if ($expiring_products): ?>

                    <ul class="reminder-list">

                        <?php foreach (array_slice($expiring_products, 0, 5) as $item): ?>

                            <li>

                                <strong><?= e($item["product_name"]) ?></strong>

                                — <?= e(date("d M Y", strtotime($item["warranty_expiry"]))) ?>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                    <a class="reminder-link" href="my_products.php">

                        View products →

                    </a>

                <?php else: ?>

                    <p>No warranties expiring in the next 30 days.</p>

                <?php endif; ?>

            </div>



            <div class="reminder-card">

                <h3>⚠️ Overdue Services</h3>

                <div class="reminder-count">

                    <?= count($overdue_services) ?>

                </div>

                <p>Products whose scheduled service date has passed.</p>



                <?php if ($overdue_services): ?>

                    <ul class="reminder-list">

                        <?php foreach (array_slice($overdue_services, 0, 5) as $item): ?>

                            <li>

                                <strong><?= e($item["product_name"]) ?></strong>

                                — Due <?= e(date("d M Y", strtotime($item["next_service_date"]))) ?>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                    <a class="reminder-link" href="my_products.php">

                        View products →

                    </a>

                <?php else: ?>

                    <p>No overdue services. You're up to date!</p>

                <?php endif; ?>

            </div>



            <div class="reminder-card">

                <h3>🔧 Upcoming Services</h3>

                <div class="reminder-count">

                    <?= count($upcoming_service_products) ?>

                </div>

                <p>Services scheduled for today or the next 7 days.</p>



                <?php if ($upcoming_service_products): ?>

                    <ul class="reminder-list">

                        <?php foreach (array_slice($upcoming_service_products, 0, 5) as $item): ?>

                            <li>

                                <strong><?= e($item["product_name"]) ?></strong>

                                — <?= e(date("d M Y", strtotime($item["next_service_date"]))) ?>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                    <a class="reminder-link" href="services.php">

                        View service records →

                    </a>

                <?php else: ?>

                    <p>No services due within the next 7 days.</p>

                <?php endif; ?>

            </div>



        </section>



        <section>

            <div class="section-heading">

                <div>

                    <h2>Dashboard Overview</h2>

                    <p>Your warranty summary at a glance.</p>

                </div>

            </div>



            <div class="stats">



                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-label">Total Products</div>

                        <div class="stat-icon purple">▣</div>

                    </div>

                    <div class="stat-number"><?= $total_products ?></div>

                    <div class="stat-note">Products registered</div>

                </div>



                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-label">Active Warranties</div>

                        <div class="stat-icon green">✓</div>

                    </div>

                    <div class="stat-number"><?= $active_warranties ?></div>

                    <div class="stat-note">Currently under warranty</div>

                </div>



                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-label">Expired Warranties</div>

                        <div class="stat-icon red">!</div>

                    </div>

                    <div class="stat-number"><?= $expired_warranties ?></div>

                    <div class="stat-note">Warranty period has ended</div>

                </div>



                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-label">Upcoming Services</div>

                        <div class="stat-icon orange">◷</div>

                    </div>

                    <div class="stat-number"><?= $upcoming_services ?></div>

                    <div class="stat-note">Due in the next 30 days</div>

                </div>



            </div>

        </section>



        <div class="bottom-grid">



            <section class="panel">

                <div class="section-heading">

                    <div>

                        <h2>Recently Added Products</h2>

                        <p>Your latest registered items.</p>

                    </div>

                    <a class="view-all" href="my_products.php">View all →</a>

                </div>



                <div class="product-table-wrap">

                    <table class="product-list">

                        <thead>

                        <tr>

                            <th>PRODUCT</th>

                            <th>BRAND</th>

                            <th>WARRANTY STATUS</th>

                        </tr>

                        </thead>



                        <tbody>

                        <?php if ($recent_products->num_rows > 0): ?>

                            <?php while ($product = $recent_products->fetch_assoc()): ?>

                                <?php

                                $is_expired =

                                    $product["warranty_expiry"] < date("Y-m-d");

                                ?>

                                <tr>

                                    <td>

                                        <div class="product-name">

                                            <?= e($product["product_name"]) ?>

                                        </div>

                                        <div class="product-brand">

                                            <?= e($product["category"]) ?>

                                        </div>

                                    </td>



                                    <td><?= e($product["brand"]) ?: "—" ?></td>



                                    <td>

                                        <span class="badge <?= $is_expired ? "badge-expired" : "badge-active" ?>">

                                            <?= $is_expired ? "Expired" : "Active" ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="3">

                                    <div class="empty">

                                        No products yet.<br><br>

                                        <a href="add_product.php">Add your first product</a>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>



            <section class="panel">

                <div class="section-heading">

                    <div>

                        <h2>Quick Actions</h2>

                        <p>What would you like to do?</p>

                    </div>

                </div>



                <div class="quick-actions">



                    <a href="add_product.php" class="quick-link">

                        <div class="quick-icon purple">＋</div>

                        <div>

                            <div class="quick-title">Add New Product</div>

                            <div class="quick-desc">

                                Register a product and its warranty.

                            </div>

                        </div>

                        <span class="arrow">›</span>

                    </a>



                    <a href="my_products.php" class="quick-link">

                        <div class="quick-icon green">▣</div>

                        <div>

                            <div class="quick-title">Manage Products</div>

                            <div class="quick-desc">

                                View, edit, or delete your products.

                            </div>

                        </div>

                        <span class="arrow">›</span>

                    </a>



                    <a href="services.php" class="quick-link">

                        <div class="quick-icon orange">⚙</div>

                        <div>

                            <div class="quick-title">Service Records</div>

                            <div class="quick-desc">

                                Review product maintenance records.

                            </div>

                        </div>

                        <span class="arrow">›</span>

                    </a>

                    <a href="email_reminders.php" class="quick-link">
                        <div class="quick-icon purple">✉</div>
                        <div>
                            <div class="quick-title">Email Reminders</div>
                            <div class="quick-desc">
                                Check warranty and service reminders.
                            </div>
                        </div>
                        <span class="arrow">›</span>
                    </a>



                </div>

            </section>



        </div>



        <footer class="footer">

            © <?= date("Y") ?> WarrantyWise · Your warranties, organized.

        </footer>



    </main>

</div>

</body>

</html>
