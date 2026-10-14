<?php



session_start();



require_once "includes/db.php";



// Check whether the user is logged in

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");

    exit();

}



$user_id = (int) $_SESSION["user_id"];

$message = "";

$message_type = "";



// Handle form submission

if ($_SERVER["REQUEST_METHOD"] === "POST") {



    $product_name = trim($_POST["product_name"] ?? "");

    $category = trim($_POST["category"] ?? "");

    $brand = trim($_POST["brand"] ?? "");

    $model_number = trim($_POST["model_number"] ?? "");

    $serial_number = trim($_POST["serial_number"] ?? "");

    $purchase_date = $_POST["purchase_date"] ?? "";

    $purchase_price = $_POST["purchase_price"] ?? "";

    $warranty_period = (int) ($_POST["warranty_period"] ?? 0);

    $next_service_date = !empty($_POST["next_service_date"])

        ? $_POST["next_service_date"]

        : null;

    $seller = trim($_POST["seller"] ?? "");



    $invoice_name = null;



    // Validate required fields

    $allowed_categories = [

        "Laptop",

        "Mobile",

        "Television",

        "Refrigerator",

        "Washing Machine",

        "Air Conditioner",

        "Other"

    ];



    $allowed_warranty_periods = [3, 6, 12, 24, 36, 60];



    $valid_purchase_date = false;

    $purchase = DateTime::createFromFormat("!Y-m-d", $purchase_date);



    if ($purchase && $purchase->format("Y-m-d") === $purchase_date) {

        $valid_purchase_date = true;

    }



    $valid_service_date = true;



    if ($next_service_date !== null) {

        $service_date = DateTime::createFromFormat(

            "!Y-m-d",

            $next_service_date

        );



        $valid_service_date = (

            $service_date &&

            $service_date->format("Y-m-d") === $next_service_date

        );

    }



    if (

        $product_name === "" ||

        !in_array($category, $allowed_categories, true) ||

        !$valid_purchase_date ||

        !in_array($warranty_period, $allowed_warranty_periods, true)

    ) {

        $message = "Please enter valid values in all required fields.";

        $message_type = "error";



    } elseif (!$valid_service_date) {

        $message = "Please enter a valid next service date.";

        $message_type = "error";



    } elseif (

        $purchase_price !== "" &&

        (!is_numeric($purchase_price) || (float) $purchase_price < 0)

    ) {

        $message = "Please enter a valid purchase price.";

        $message_type = "error";



    } else {



        // Calculate warranty expiry date

        $purchase->modify("+$warranty_period months");

        $warranty_expiry = $purchase->format("Y-m-d");



        $purchase_price_value = (

            $purchase_price === ""

        ) ? null : (float) $purchase_price;



        // Handle optional invoice upload

        $upload_error = false;



        if (

            isset($_FILES["invoice"]) &&

            $_FILES["invoice"]["error"] !== UPLOAD_ERR_NO_FILE

        ) {



            if ($_FILES["invoice"]["error"] !== UPLOAD_ERR_OK) {

                $upload_error = true;

                $message = "Invoice upload failed. Please try again.";



            } elseif ($_FILES["invoice"]["size"] > 5 * 1024 * 1024) {

                $upload_error = true;

                $message = "Invoice must be smaller than 5 MB.";



            } else {



                $tmp_file = $_FILES["invoice"]["tmp_name"];

                $original_name = $_FILES["invoice"]["name"];



                $file_extension = strtolower(

                    pathinfo($original_name, PATHINFO_EXTENSION)

                );



                $allowed_extensions = ["pdf", "jpg", "jpeg", "png"];

                $allowed_mime_types = [

                    "pdf"  => ["application/pdf"],

                    "jpg"  => ["image/jpeg"],

                    "jpeg" => ["image/jpeg"],

                    "png"  => ["image/png"]

                ];



                $file_info = new finfo(FILEINFO_MIME_TYPE);

                $mime_type = $file_info->file($tmp_file);



                if (

                    !in_array($file_extension, $allowed_extensions, true) ||

                    !in_array(

                        $mime_type,

                        $allowed_mime_types[$file_extension] ?? [],

                        true

                    )

                ) {

                    $upload_error = true;

                    $message = "Only valid PDF, JPG, JPEG, or PNG invoices are allowed.";



                } else {



                    $upload_dir = __DIR__ . "/uploads/invoices/";



                    if (

                        !is_dir($upload_dir) &&

                        !mkdir($upload_dir, 0755, true)

                    ) {

                        $upload_error = true;

                        $message = "Unable to create the invoice upload folder.";



                    } else {



                        $invoice_name = bin2hex(random_bytes(16))

                            . "." . $file_extension;



                        if (!move_uploaded_file(

                            $tmp_file,

                            $upload_dir . $invoice_name

                        )) {

                            $upload_error = true;

                            $invoice_name = null;

                            $message = "Unable to save the invoice file.";

                        }

                    }

                }

            }

        }



        // Save product only when validation and upload succeed

        if (!$upload_error) {



            $stmt = $conn->prepare(

                "INSERT INTO products (

                    user_id,

                    product_name,

                    category,

                    brand,

                    model_number,

                    serial_number,

                    purchase_date,

                    purchase_price,

                    warranty_period,

                    warranty_expiry,

                    next_service_date,

                    seller,

                    invoice

                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"

            );



            if (!$stmt) {

                $message = "Unable to prepare the database query.";

                $message_type = "error";



            } else {



                // 13 parameters:

                // i + 6 strings + decimal + integer + 4 strings

                $stmt->bind_param(

                    "issssssdissss",

                    $user_id,

                    $product_name,

                    $category,

                    $brand,

                    $model_number,

                    $serial_number,

                    $purchase_date,

                    $purchase_price_value,

                    $warranty_period,

                    $warranty_expiry,

                    $next_service_date,

                    $seller,

                    $invoice_name

                );



                if ($stmt->execute()) {

                    $message = "Product added successfully!";

                    $message_type = "success";



                } else {

                    $message = "Failed to add product. Please try again.";



                    // Remove the uploaded invoice if saving the product fails

                    if ($invoice_name !== null) {

                        $saved_invoice = __DIR__

                            . "/uploads/invoices/"

                            . $invoice_name;



                        if (is_file($saved_invoice)) {

                            unlink($saved_invoice);

                        }

                    }

                }



                $stmt->close();



                $message_type = (

                    strpos($message, "successfully") !== false

                ) ? "success" : "error";

            }

        } else {

            $message_type = "error";

        }

    }

}



?>



<!DOCTYPE html>

<html lang="en">



<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Add Product - WarrantyWise</title>



    <style>

        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-soft: #eef2ff;
            --background: #f5f7fc;
            --text: #172033;
            --muted: #778198;
            --border: #e5e7ef;
            --white: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 30px 18px 48px;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
        }
        .page-header { max-width: 900px; margin: 0 auto 24px; text-align: center; }
        h1 { margin: 0; font-size: clamp(28px, 4vw, 40px); letter-spacing: -1px; font-weight: 800; }
        h1 span { color: var(--primary); }
        h2 { margin: 14px 0 0; font-size: clamp(22px, 3vw, 30px); letter-spacing: -.5px; }
        .page-subtitle { color: var(--muted); font-size: 14px; margin: 10px 0 0; line-height: 1.6; }
        .form-shell { max-width: 900px; margin: 0 auto; }
        .form-intro { display: flex; align-items: center; gap: 14px; padding: 0 2px 16px; }
        .form-intro-icon { width: 46px; height: 46px; border-radius: 13px; display: grid; place-items: center; background: var(--primary-soft); color: var(--primary); font-size: 23px; flex-shrink: 0; }
        .form-intro h3 { margin: 0; font-size: 17px; }
        .form-intro p { margin: 5px 0 0; color: var(--muted); font-size: 12px; }
        form { background: var(--white); padding: clamp(22px, 4vw, 34px); border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 35px rgba(23,32,51,.06); }
        label { display: block; margin: 0 0 8px; font-size: 14px; font-weight: 700; color: #29334a; }
        input, select { display: block; width: 100%; min-height: 48px; padding: 12px 14px; margin: 0 0 22px; border: 1px solid #d8dce7; border-radius: 9px; background: #fff; color: var(--text); font: inherit; font-size: 14px; transition: border-color .2s, box-shadow .2s; }
        input::placeholder { color: #9aa3b5; }
        input:focus, select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
        input[type="file"] { padding: 10px; background: #fafbff; }
        form br { display: none; }
        .message { max-width: 900px; margin: 0 auto 18px; padding: 14px 16px; border-radius: 10px; font-size: 14px; line-height: 1.5; }
        .success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
        button { display: inline-flex; align-items: center; justify-content: center; width: 100%; min-height: 50px; padding: 14px 20px; background: linear-gradient(135deg, var(--primary), #6d63f1); color: white; border: 0; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: 700; box-shadow: 0 7px 16px rgba(79,70,229,.22); transition: transform .2s, box-shadow .2s; }
        button:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 10px 20px rgba(79,70,229,.25); }
        .back-link { display: inline-flex; align-items: center; justify-content: center; gap: 7px; margin: 20px auto 0; width: 100%; color: var(--primary); font-size: 13px; font-weight: 700; text-decoration: none; }
        .back-link:hover { color: var(--primary-dark); text-decoration: underline; }
        @media (max-width: 600px) { body { padding: 22px 12px 34px; } form { padding: 20px 16px; border-radius: 14px; } input, select { margin-bottom: 19px; } }

    </style>

</head>



<body>



    <header class="page-header">
        <h1>Warranty<span>Wise</span></h1>
        <h2>Add New Product</h2>
        <p class="page-subtitle">Register your product and keep its warranty details organized.</p>
    </header>

    <main class="form-shell">
        <div class="form-intro">
            <div class="form-intro-icon">＋</div>
            <div><h3>Product information</h3><p>Fields marked with * are required.</p></div>
        </div>



    <?php if ($message !== ""): ?>

        <p class="message <?php echo htmlspecialchars($message_type); ?>">

            <?php echo htmlspecialchars($message); ?>

        </p>

    <?php endif; ?>



    <form method="POST" enctype="multipart/form-data">



        <label for="product_name">Product Name *</label>

        <input

            type="text"

            id="product_name"

            name="product_name"

            placeholder="Example: Laptop"

            maxlength="150"

            required

        >

        <br><br>



        <label for="category">Category *</label>

        <select id="category" name="category" required>

            <option value="">Select Category</option>

            <option value="Laptop">Laptop</option>

            <option value="Mobile">Mobile</option>

            <option value="Television">Television</option>

            <option value="Refrigerator">Refrigerator</option>

            <option value="Washing Machine">Washing Machine</option>

            <option value="Air Conditioner">Air Conditioner</option>

            <option value="Other">Other</option>

        </select>

        <br><br>



        <label for="brand">Brand</label>

        <input

            type="text"

            id="brand"

            name="brand"

            maxlength="100"

            placeholder="Example: Dell"

        >

        <br><br>



        <label for="model_number">Model Number</label>

        <input

            type="text"

            id="model_number"

            name="model_number"

            maxlength="100"

        >

        <br><br>



        <label for="serial_number">Serial Number</label>

        <input

            type="text"

            id="serial_number"

            name="serial_number"

            maxlength="100"

        >

        <br><br>



        <label for="purchase_date">Purchase Date *</label>

        <input

            type="date"

            id="purchase_date"

            name="purchase_date"

            required

        >

        <br><br>



        <label for="purchase_price">Purchase Price (₹)</label>

        <input

            type="number"

            id="purchase_price"

            name="purchase_price"

            step="0.01"

            min="0"

        >

        <br><br>



        <label for="warranty_period">Warranty Period *</label>

        <select id="warranty_period" name="warranty_period" required>

            <option value="">Select Warranty</option>

            <option value="3">3 Months</option>

            <option value="6">6 Months</option>

            <option value="12">1 Year</option>

            <option value="24">2 Years</option>

            <option value="36">3 Years</option>

            <option value="60">5 Years</option>

        </select>

        <br><br>



        <label for="next_service_date">Next Service Date</label>

        <input

            type="date"

            id="next_service_date"

            name="next_service_date"

        >

        <br><br>



        <label for="seller">Seller / Store</label>

        <input

            type="text"

            id="seller"

            name="seller"

            maxlength="150"

            placeholder="Example: Croma"

        >

        <br><br>



        <label for="invoice">Purchase Invoice (PDF, JPG, PNG; max 5 MB)</label>

        <input

            type="file"

            id="invoice"

            name="invoice"

            accept=".pdf,.jpg,.jpeg,.png"

        >

        <br><br>



        <button type="submit">Add Product</button>



    </form>



    <a class="back-link" href="dashboard.php">

        &larr; Back to Dashboard

    </a>



</body>

</html>
