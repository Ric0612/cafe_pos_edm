<?php
session_start();
include('../includes/db-conn.php');
require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

// Check if user is logged in and has manager role
if (!isset($_SESSION['user_ID']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

// Initialize date variables first
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Handle PDF Export
if (isset($_GET['export_pdf']) && $_GET['export_pdf'] === 'true') {
    // Start output buffering
    ob_start();
    
    // Create new PDF document
    class MYPDF extends TCPDF {
        public function Header() {
            $imageFile = K_PATH_IMAGES.'cafe-logo.jpg';
            $this->Image($imageFile, 15, 10, 30, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            $this->SetFont('helvetica', 'B', 20);
            $this->Cell(0, 30, 'Café POS System Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
            $this->Ln(20);
        }

        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    // Clear any previous output
    ob_clean();

    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Café POS System');
    $pdf->SetTitle('Report - ' . date('Y-m-d'));

    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add report metadata
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Add date range to the report
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Report Period: ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)), 0, 1, 'R');
    $pdf->Ln(5);

    // Add report content
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Inventory Report', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Add inventory table
    $inventory_content = '<table border="1" cellpadding="4">
        <tr style="background-color: #f4e1c1;">
            <th>Product Name</th>
            <th>Category</th>
            <th>Current Stock</th>
            <th>Unit Price</th>
            <th>Total Value</th>
        </tr>';
    
    $inventory_report = getInventoryReport($conn, $start_date, $end_date);
    while ($row = mysqli_fetch_assoc($inventory_report)) {
        $inventory_content .= '<tr>
            <td>'.htmlspecialchars($row['name']).'</td>
            <td>'.htmlspecialchars($row['category']).'</td>
            <td>'.htmlspecialchars($row['current_stock']).'</td>
            <td>PHP '.number_format($row['price'], 2).'</td>
            <td>PHP '.number_format($row['inventory_value'], 2).'</td>
        </tr>';
    }
    $inventory_content .= '</table>';
    $pdf->writeHTML($inventory_content, true, false, true, false, '');

    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Sales Report', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Add sales table
    $sales_content = '<table border="1" cellpadding="4">
        <tr style="background-color: #f4e1c1;">
            <th>Cashier Name</th>
            <th>Total Transactions</th>
            <th>Total Sales</th>
            <th>Average Transaction</th>
            <th>Discounted Sales</th>
        </tr>';
    
    $sales_report = getSalesReport($conn, $start_date, $end_date);
    while ($row = mysqli_fetch_assoc($sales_report)) {
        $sales_content .= '<tr>
            <td>'.htmlspecialchars($row['cashier_name']).'</td>
            <td>'.htmlspecialchars($row['total_transactions']).'</td>
            <td>PHP '.number_format($row['total_sales'], 2).'</td>
            <td>PHP '.number_format($row['average_transaction_value'], 2).'</td>
            <td>'.htmlspecialchars($row['discounted_transactions']).'</td>
        </tr>';
    }
    $sales_content .= '</table>';
    $pdf->writeHTML($sales_content, true, false, true, false, '');

    // Clean output buffer
    ob_end_clean();

    // Output PDF
    $pdf->Output('cafe_report_'.date('Y-m-d').'.pdf', 'D');
    exit();
}

// Function to get inventory report with more details
function getInventoryReport($conn, $start_date = null, $end_date = null) {
    $query = "SELECT 
                p.product_ID,
                p.name,
                p.category,
                p.stock as current_stock,
                p.price,
                COALESCE(
                    (
                        SELECT CONCAT(
                            DATE_FORMAT(so.delivery_date, '%Y-%m-%d %H:%i:%s'),
                            ' (Delivery: +',
                            so.quantity,
                            ')'
                        )
                        FROM supply_orders so
                        WHERE so.product_id = p.product_ID 
                        AND so.status = 'delivered'
                        AND DATE(so.delivery_date) BETWEEN COALESCE(?, DATE_SUB(CURDATE(), INTERVAL 30 DAY)) 
                        AND COALESCE(?, CURDATE())
                        ORDER BY so.delivery_date DESC
                        LIMIT 1
                    ),
                    (
                        SELECT CONCAT(
                            DATE_FORMAT(alp.timestamp, '%Y-%m-%d %H:%i:%s'),
                            ' (',
                            CASE 
                                WHEN alp.action = 'update' THEN CONCAT(alp.old_value, ' → ', alp.new_value)
                                WHEN alp.action = 'add' THEN CONCAT('Added ', alp.new_value)
                                ELSE alp.action
                            END,
                            ')'
                        )
                        FROM audit_logs_products alp 
                        WHERE alp.product_ID = p.product_ID 
                        AND alp.field_changed = 'stock'
                        AND DATE(alp.timestamp) BETWEEN COALESCE(?, DATE_SUB(CURDATE(), INTERVAL 30 DAY)) 
                        AND COALESCE(?, CURDATE())
                        ORDER BY alp.timestamp DESC 
                        LIMIT 1
                    )
                ) as last_stock_update,
                (p.stock * p.price) as inventory_value,
                (
                    SELECT 
                        COALESCE(
                            SUM(
                                CASE 
                                    WHEN alp.action = 'update' AND alp.field_changed = 'stock' 
                                    THEN CAST(alp.new_value AS SIGNED) - CAST(alp.old_value AS SIGNED)
                                    WHEN alp.action = 'add' AND alp.field_changed = 'stock'
                                    THEN CAST(alp.new_value AS SIGNED)
                                    ELSE 0 
                                END
                            ), 0
                        ) +
                        COALESCE(
                            (
                                SELECT SUM(so.quantity)
                                FROM supply_orders so
                                WHERE so.product_id = p.product_ID 
                                AND so.status = 'delivered'
                                AND DATE(so.delivery_date) BETWEEN COALESCE(?, DATE_SUB(CURDATE(), INTERVAL 30 DAY)) 
                                AND COALESCE(?, CURDATE())
                            ), 0
                        )
                    FROM audit_logs_products alp 
                    WHERE alp.product_ID = p.product_ID
                    AND alp.field_changed = 'stock'
                    AND DATE(alp.timestamp) BETWEEN COALESCE(?, DATE_SUB(CURDATE(), INTERVAL 30 DAY)) 
                    AND COALESCE(?, CURDATE())
                ) as total_stock_changes
              FROM products p
              GROUP BY p.product_ID
              ORDER BY p.category, p.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssss", 
        $start_date, $end_date,  // For supply_orders in last_stock_update
        $start_date, $end_date,  // For audit_logs in last_stock_update
        $start_date, $end_date,  // For supply_orders in total_stock_changes
        $start_date, $end_date   // For audit_logs in total_stock_changes
    );
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}

// Function to get detailed sales report
function getSalesReport($conn, $start_date = null, $end_date = null) {
    // If no dates provided, default to last 30 days
    if (!$start_date) {
        $start_date = date('Y-m-d', strtotime('-30 days'));
    }
    if (!$end_date) {
        $end_date = date('Y-m-d');
    }

    $query = "SELECT 
                u.name,
                u.role,
                DATE(t.transaction_date) as sale_date,
                COUNT(DISTINCT t.transaction_id) as daily_transactions,
                SUM(t.total_amount) as daily_sales,
                SUM(t.vat_amount) as daily_vat,
                SUM(t.discount_amount) as daily_discounts,
                COUNT(DISTINCT CASE WHEN t.discount_type != 'none' THEN t.transaction_id END) as daily_discounted_transactions,
                AVG(t.total_amount) as daily_average_transaction
              FROM users u
              LEFT JOIN transactions t ON t.cashier_id = u.user_ID 
                AND DATE(t.transaction_date) BETWEEN ? AND ?
              WHERE u.role IN ('manager', 'cashier')
              GROUP BY u.user_ID, u.name, u.role, DATE(t.transaction_date)
              ORDER BY u.name, sale_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}

// Get total inventory value
function getTotalInventoryValue($conn) {
    $query = "SELECT SUM(stock * price) as total_value FROM products";
    $result = mysqli_query($conn, $query);
    return $result->fetch_assoc()['total_value'] ?? 0;
}

// Get reports with date filtering
$inventory_report = getInventoryReport($conn, $start_date, $end_date);
$sales_report = getSalesReport($conn, $start_date, $end_date);
$total_inventory_value = getTotalInventoryValue($conn);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Cafe POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <style>
        .report-controls {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .date-filter {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
        }
        .date-filter .form-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include('../includes/sidebar.php'); ?>
        
        <!-- Page content wrapper -->
        <div id="page-content-wrapper">
            <!-- Top navigation -->
            <?php include('../includes/nav.php'); ?>
            
            <!-- Page content -->
            <div class="container-fluid px-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="mt-4">Reports Dashboard</h1>
                </div>

                <!-- Controls Section -->
                <div class="report-controls d-flex justify-content-between align-items-center">
                    <!-- Date Filter -->
                    <div class="date-filter">
                        <form class="d-flex align-items-center gap-3">
                            <div class="form-group">
                                <label class="me-2">From:</label>
                                <input type="date" class="form-control form-control-sm" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="form-group">
                                <label class="me-2">To:</label>
                                <input type="date" class="form-control form-control-sm" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        </form>
                    </div>

                    <!-- Export Buttons -->
                    <div class="export-buttons">
                        <a href="?export_pdf=true<?php echo isset($_GET['start_date']) ? '&start_date='.$_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date='.$_GET['end_date'] : ''; ?>" class="btn btn-danger btn-sm btn-export">
                            <i class="fas fa-file-pdf"></i> Export to PDF
                        </a>
                        <button class="btn btn-success btn-sm btn-export" onclick="exportTableToExcel('inventoryTable', 'inventory_report')">
                            <i class="fas fa-file-excel"></i> Export Inventory
                        </button>
                        <button class="btn btn-success btn-sm btn-export" onclick="exportTableToExcel('salesTable', 'sales_report')">
                            <i class="fas fa-file-excel"></i> Export Sales
                        </button>
                    </div>
                </div>

                <!-- Inventory Report -->
                <div class="report-section">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h2 class="mb-0">Inventory Report</h2>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body">
                                            <h5>Total Inventory Value</h5>
                                            <h3>PHP <?php echo number_format($total_inventory_value, 2); ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped" id="inventoryTable">
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Current Stock</th>
                                            <th>Unit Price</th>
                                            <th>Total Value</th>
                                            <th>Last Stock Update</th>
                                            <th>Stock Changes</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = mysqli_fetch_assoc($inventory_report)) : ?>
                                        <tr class="<?php echo $row['current_stock'] <= 10 ? 'table-warning' : ''; ?>">
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                                            <td><?php echo htmlspecialchars($row['current_stock']); ?></td>
                                            <td>PHP <?php echo number_format($row['price'], 2); ?></td>
                                            <td>PHP <?php echo number_format($row['inventory_value'], 2); ?></td>
                                            <td><?php echo $row['last_stock_update'] ?? 'N/A'; ?></td>
                                            <td>
                                                <?php 
                                                    $stock_changes = $row['total_stock_changes'];
                                                    if ($stock_changes > 0) {
                                                        echo "<span class='text-success'>+$stock_changes</span>";
                                                    } elseif ($stock_changes < 0) {
                                                        echo "<span class='text-danger'>$stock_changes</span>";
                                                    } else {
                                                        echo "No changes";
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($row['current_stock'] == 0): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif ($row['current_stock'] <= 10): ?>
                                                    <span class="badge bg-warning text-dark">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Report -->
                <div class="report-section">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h2 class="mb-0">Sales Report (Last 30 Days)</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table" id="salesTable">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Date</th>
                                            <th>Transactions</th>
                                            <th>Daily Sales</th>
                                            <th>Avg Transaction</th>
                                            <th>Discounted Sales</th>
                                            <th>VAT</th>
                                            <th>Discounts</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $current_user = '';
                                        $user_total_sales = 0;
                                        $user_total_transactions = 0;
                                        $grand_total_sales = 0;
                                        $grand_total_transactions = 0;
                                        $row_count = 0;

                                        while ($row = mysqli_fetch_assoc($sales_report)) :
                                            // If we're starting a new user, print the previous user's totals
                                            if ($current_user !== '' && $current_user !== $row['name']) {
                                                // Print user totals
                                                echo '<tr class="table-info fw-bold">
                                                    <td>' . htmlspecialchars($current_user) . ' Total</td>
                                                    <td></td>
                                                    <td></td>
                                                    <td>' . $user_total_transactions . '</td>
                                                    <td>PHP ' . number_format($user_total_sales, 2) . '</td>
                                                    <td colspan="4"></td>
                                                </tr>';
                                                // Reset user totals
                                                $user_total_sales = 0;
                                                $user_total_transactions = 0;
                                                $row_count = 0;
                                            }

                                            $current_user = $row['name'];
                                            $row_count++;
                                            
                                            // Add to totals
                                            $user_total_sales += $row['daily_sales'];
                                            $user_total_transactions += $row['daily_transactions'];
                                            $grand_total_sales += $row['daily_sales'];
                                            $grand_total_transactions += $row['daily_transactions'];

                                            // Alternate row colors within each user's section
                                            $row_class = $row_count % 2 === 0 ? 'table-light' : '';
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $row['role'] === 'manager' ? 'bg-primary' : 'bg-success'; 
                                                ?>">
                                                    <?php echo ucfirst(htmlspecialchars($row['role'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['sale_date'] ? date('M d, Y', strtotime($row['sale_date'])) : 'No sales'; ?></td>
                                            <td><?php echo $row['daily_transactions'] ?? 0; ?></td>
                                            <td>PHP <?php echo number_format($row['daily_sales'] ?? 0, 2); ?></td>
                                            <td>PHP <?php echo number_format($row['daily_average_transaction'] ?? 0, 2); ?></td>
                                            <td><?php echo $row['daily_discounted_transactions'] ?? 0; ?></td>
                                            <td>PHP <?php echo number_format($row['daily_vat'] ?? 0, 2); ?></td>
                                            <td>PHP <?php echo number_format($row['daily_discounts'] ?? 0, 2); ?></td>
                                        </tr>
                                        <?php endwhile; 
                                        
                                        // Print last user's totals
                                        if ($current_user !== '') {
                                            echo '<tr class="table-info fw-bold">
                                                <td>' . htmlspecialchars($current_user) . ' Total</td>
                                                <td></td>
                                                <td></td>
                                                <td>' . $user_total_transactions . '</td>
                                                <td>PHP ' . number_format($user_total_sales, 2) . '</td>
                                                <td colspan="4"></td>
                                            </tr>';
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-primary fw-bold">
                                            <td>Grand Total</td>
                                            <td></td>
                                            <td></td>
                                            <td><?php echo $grand_total_transactions; ?></td>
                                            <td>PHP <?php echo number_format($grand_total_sales, 2); ?></td>
                                            <td colspan="4"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#inventoryTable').DataTable({
                "pageLength": 25,
                "order": [[1, "asc"]]
            });
            $('#salesTable').DataTable({
                "pageLength": 25,
                "order": [[2, "desc"]]
            });
        });

        function exportTableToExcel(tableID, filename = '') {
            var downloadLink;
            var dataType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById(tableID);
            var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            filename = filename?filename+'.xls':'excel_data.xls';
            
            downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            
            if(navigator.msSaveOrOpenBlob){
                var blob = new Blob(['\ufeff', tableHTML], {
                    type: dataType
                });
                navigator.msSaveOrOpenBlob( blob, filename);
            } else {
                downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                downloadLink.download = filename;
                downloadLink.click();
            }
        }
    </script>
</body>
</html> 