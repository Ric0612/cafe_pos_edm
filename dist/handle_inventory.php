<?php
// Fetch products from the database
$sql = "SELECT * FROM products";
$result = $conn->query($sql);

// Handle form submission for adding new product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    // Get the form data
    $name = $_POST['name'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        // Get the uploaded image file details
        $imageTmpName = $_FILES['image']['tmp_name'];
        $imageName = $_FILES['image']['name'];
        $imageType = $_FILES['image']['type'];

        // Define the upload directory
        $uploadDir = '../uploads/';
        
        // Generate a unique name for the image to avoid overwriting
        $imageNewName = uniqid('product_', true) . '.' . pathinfo($imageName, PATHINFO_EXTENSION);

        // Check if the file is an image (optional: you can add more validation for file types)
        if (in_array($imageType, ['image/jpeg', 'image/png', 'image/gif'])) {
            // Move the uploaded file to the desired folder
            if (move_uploaded_file($imageTmpName, $uploadDir . $imageNewName)) {
                // Insert product into the database
                $insertSql = "INSERT INTO products (name, category, description, price, stock, image) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertSql);
                $stmt->bind_param("ssssds", $name, $category, $description, $price, $stock, $imageNewName);

                if ($stmt->execute()) {
                    echo "Product added successfully!";
                    // Refresh the page to show the updated list
                    header('Location: inventory.php');
                    exit();
                } else {
                    echo "Error adding product: " . $stmt->error;
                }
            } else {
                echo "Failed to upload image.";
            }
        } else {
            echo "Invalid image format. Only JPG, PNG, and GIF files are allowed.";
        }
    } else {
        echo "No image uploaded or there was an error uploading the image.";
    }
}

// Handle delete product (and check if delete_id is passed)
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    $deleteSql = "DELETE FROM products WHERE product_ID = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Product deleted successfully!';
        header('Location: inventory.php');
        exit();
    } else {
        echo "Error deleting product: " . $stmt->error;
    }
}

// Initialize search and filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build the base query
$base_query = "SELECT * FROM products WHERE 1=1";
$params = array();
$types = "";

// Add search condition if search term is provided
if (!empty($search)) {
    $base_query .= " AND (product_ID LIKE ? OR name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Add category filter if selected
if (!empty($category_filter)) {
    $base_query .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

// Add pagination
$results_per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $results_per_page;

// Get total number of records for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*)", $base_query);
$stmt = $conn->prepare($count_query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_records = $total_result->fetch_row()[0];
$total_pages = ceil($total_records / $results_per_page);

// Add limit for pagination to the main query
$base_query .= " LIMIT ?, ?";
$params[] = $start_from;
$params[] = $results_per_page;
$types .= "ii";

// Prepare and execute the main query
$stmt = $conn->prepare($base_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch product data for modal if update_id is set
$product = null; // Initialize $product variable
if (isset($_GET['update_id'])) {
    $product_ID = $_GET['update_id'];

    // Fetch product data to populate the modal form
    $selectSql = "SELECT * FROM products WHERE product_ID = ?";
    $selectStmt = $conn->prepare($selectSql);
    $selectStmt->bind_param("i", $product_ID);
    $selectStmt->execute();
    $productResult = $selectStmt->get_result();

    if ($productResult->num_rows > 0) {
        $product = $productResult->fetch_assoc();
    }
}

// Handle update product form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product'])) {
    $product_ID = $_POST['product_ID'];
    $name = $_POST['name'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $imageTmpName = $_FILES['image']['tmp_name'];
        $imageName = $_FILES['image']['name'];
        $imageType = $_FILES['image']['type'];

        // Define the upload directory
        $uploadDir = '../uploads/';
        
        // Generate a unique name for the image
        $imageNewName = uniqid('product_', true) . '.' . pathinfo($imageName, PATHINFO_EXTENSION);

        // Check if file is an image
        if (in_array($imageType, ['image/jpeg', 'image/png', 'image/gif'])) {
            if (move_uploaded_file($imageTmpName, $uploadDir . $imageNewName)) {
                $updateSql = "UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ?, image = ? WHERE product_ID = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("ssssdsi", $name, $category, $description, $price, $stock, $imageNewName, $product_ID);

                if ($stmt->execute()) {
                    echo "Product updated successfully!";
                    header('Location: inventory.php');
                    exit();
                } else {
                    echo "Error updating product: " . $stmt->error;
                }
            } else {
                echo "Error uploading image.";
            }
        }
    } else {
        $updateSql = "UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ? WHERE product_ID = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ssssdi", $name, $category, $description, $price, $stock, $product_ID);
        
        if ($stmt->execute()) {
            echo "Product updated successfully!";
            header('Location: inventory.php');
            exit();
        } else {
            echo "Error updating product: " . $stmt->error;
        }
    }
}

?>