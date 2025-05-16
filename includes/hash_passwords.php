<?php
// Include the database connection
include('db-conn.php');

/**
 * Function to check if a password is hashed
 * This checks common patterns of unhashed passwords in your system
 */
function is_password_hashed($password) {
    // Check if it's a valid bcrypt hash (60 characters, starting with $2y$)
    if (strlen($password) == 60 && strpos($password, '$2y$') === 0) {
        return true;
    }
    
    // Add your known unhashed password patterns here
    $known_patterns = [
        '/^cafe-.*/',              // Matches 'cafe-cashier-1', 'cafe-manager-1', etc.
        '/^[0-9]{4,}$/',          // Matches numeric passwords
        '/^(cashier|manager|kitchen)[0-9]*$/' // Matches role-based passwords
    ];
    
    foreach ($known_patterns as $pattern) {
        if (preg_match($pattern, $password)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Function to hash a password if it's not already hashed
 */
function ensure_password_hashed($password) {
    if (!is_password_hashed($password)) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    return $password;
}

// Start transaction
$conn->begin_transaction();

try {
    // Select all users from the database
    $sql = "SELECT * FROM users";
    $result = $conn->query($sql);
    
    $updated_count = 0;
    $total_users = $result->num_rows;
    
    // Loop through all users and update unhashed passwords
    while ($row = $result->fetch_assoc()) {
        $current_password = $row['password'];
        
        // Only update if the password is not hashed
        if (!is_password_hashed($current_password)) {
            $hashed_password = ensure_password_hashed($current_password);
            
            // Update the user's password in the database
            $update_sql = "UPDATE users SET password = ? WHERE user_ID = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $hashed_password, $row['user_ID']);
            $stmt->execute();
            
            $updated_count++;
        }
    }
    
    // If everything is successful, commit the transaction
    $conn->commit();
    
    // Prepare the response message
    if ($updated_count > 0) {
        echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; border-radius: 5px; background-color: #f8f9fa;'>";
        echo "<h2 style='color: #6c4f3d;'>Password Update Summary</h2>";
        echo "<p>Total users checked: $total_users</p>";
        echo "<p>Passwords updated: $updated_count</p>";
        echo "<p style='color: #28a745;'><strong>✓ All unhashed passwords have been successfully updated!</strong></p>";
        echo "</div>";
    } else {
        echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; border-radius: 5px; background-color: #f8f9fa;'>";
        echo "<h2 style='color: #6c4f3d;'>Password Check Summary</h2>";
        echo "<p>Total users checked: $total_users</p>";
        echo "<p style='color: #28a745;'><strong>✓ All passwords are already properly hashed!</strong></p>";
        echo "</div>";
    }

} catch (Exception $e) {
    // If there's an error, rollback the transaction
    $conn->rollback();
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; border-radius: 5px; background-color: #fff3f3;'>";
    echo "<h2 style='color: #dc3545;'>Error</h2>";
    echo "<p>Failed to update passwords: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?> 