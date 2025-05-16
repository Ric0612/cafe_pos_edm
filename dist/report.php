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
                (
                    SELECT alp.timestamp 
                    FROM audit_logs_products alp 
                    WHERE alp.product_ID = p.product_ID 
                    AND alp.field_changed = 'stock'
                    " . ($start_date && $end_date ? "AND DATE(alp.timestamp) BETWEEN '$start_date' AND '$end_date'" : "") . "
                    ORDER BY alp.timestamp DESC 
                    LIMIT 1
                ) as last_stock_update,
                (
                    SELECT alp.new_value 
                    FROM audit_logs_products alp 
                    WHERE alp.product_ID = p.product_ID 
                    AND alp.field_changed = 'stock'
                    " . ($start_date && $end_date ? "AND DATE(alp.timestamp) BETWEEN '$start_date' AND '$end_date'" : "") . "
                    ORDER BY alp.timestamp DESC 
                    LIMIT 1
                ) as last_stock_change,
                (p.stock * p.price) as inventory_value,
                (
                    SELECT SUM(CASE 
                        WHEN alp.action = 'update' AND alp.field_changed = 'stock' 
                        THEN CAST(alp.new_value AS SIGNED) - CAST(alp.old_value AS SIGNED)
                        WHEN alp.action = 'add' AND alp.field_changed = 'stock'
                        THEN CAST(alp.new_value AS SIGNED)
                        ELSE 0 
                    END)
                    FROM audit_logs_products alp 
                    WHERE alp.product_ID = p.product_ID
                    " . ($start_date && $end_date ? "AND DATE(alp.timestamp) BETWEEN '$start_date' AND '$end_date'" : "") . "
                ) as stock_changes
              FROM products p
              GROUP BY p.product_ID
              ORDER BY p.category, p.name";
    
    $result = mysqli_query($conn, $query);
    return $result;
}

// Function to get detailed sales report
function getSalesReport($conn, $start_date = null, $end_date = null) {
    $where_clause = "";
    if ($start_date && $end_date) {
        $where_clause = " WHERE DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'";
    }

    $query = "SELECT 
                u.name as cashier_name,
                COUNT(DISTINCT t.transaction_id) as total_transactions,
                SUM(t.total_amount) as total_sales,
                SUM(t.vat_amount) as total_vat,
                SUM(t.discount_amount) as total_discounts,
                COUNT(DISTINCT CASE WHEN t.discount_type != 'none' THEN t.transaction_id END) as discounted_transactions,
                AVG(t.total_amount) as average_transaction_value
              FROM transactions t
              JOIN users u ON t.cashier_id = u.user_ID
              $where_clause
              GROUP BY u.user_ID, u.name
              ORDER BY total_sales DESC";
    
    $result = mysqli_query($conn, $query);
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
                                            <th>Last Stock Change</th>
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
                                            <td><?php echo $row['last_stock_update'] ? date('Y-m-d H:i:s', strtotime($row['last_stock_update'])) : 'N/A'; ?></td>
                                            <td><?php echo $row['last_stock_change'] ?? 'N/A'; ?></td>
                                            <td>
                                                <?php 
                                                    $stock_changes = $row['stock_changes'];
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
                            <h2 class="mb-0">Sales Report</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="salesTable">
                                    <thead>
                                        <tr>
                                            <th>Cashier Name</th>
                                            <th>Total Transactions</th>
                                            <th>Total Sales</th>
                                            <th>Average Transaction</th>
                                            <th>Discounted Sales</th>
                                            <th>Total VAT</th>
                                            <th>Total Discounts</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $grand_total_sales = 0;
                                        $grand_total_transactions = 0;
                                        while ($row = mysqli_fetch_assoc($sales_report)) : 
                                            $grand_total_sales += $row['total_sales'];
                                            $grand_total_transactions += $row['total_transactions'];
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['cashier_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['total_transactions']); ?></td>
                                            <td>PHP <?php echo number_format($row['total_sales'], 2); ?></td>
                                            <td>PHP <?php echo number_format($row['average_transaction_value'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($row['discounted_transactions']); ?></td>
                                            <td>PHP <?php echo number_format($row['total_vat'], 2); ?></td>
                                            <td>PHP <?php echo number_format($row['total_discounts'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-info">
                                            <td><strong>Grand Total</strong></td>
                                            <td><strong><?php echo $grand_total_transactions; ?></strong></td>
                                            <td><strong>PHP <?php echo number_format($grand_total_sales, 2); ?></strong></td>
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