<?php
/**
 * Dividend Report View
 * 
 * Printable dividend report with summary and details.
 */
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dividend Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: white;
        }
        .report-header {
            text-align: center;
            border-bottom: 2px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .report-header h1 {
            color: #667eea;
            margin: 0;
        }
        .report-header p {
            color: #666;
            margin: 5px 0;
        }
        .summary-section {
            margin: 20px 0;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card .label {
            color: #666;
            font-size: 12px;
        }
        .summary-card .value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .summary-card .value.success { color: #48bb78; }
        .summary-card .value.warning { color: #ed8936; }
        .summary-card .value.danger { color: #f56565; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        table th {
            background: #f2f2f2;
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        table td {
            padding: 8px 10px;
            border: 1px solid #ddd;
        }
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge.success { background: #c6f6d5; color: #22543d; }
        .status-badge.warning { background: #feebc8; color: #744210; }
        .status-badge.info { background: #bee3f8; color: #2a4365; }
        .report-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        .no-print {
            text-align: center;
            margin-top: 20px;
        }
        .no-print button {
            padding: 10px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 10px; }
            .summary-card { background: #f8f9fa !important; }
        }
    </style>
</head>
<body>
    <div class="report-header">
        <h1><?php echo APP_NAME; ?></h1>
        <h2>Dividend Report</h2>
        <p>Generated: <?php echo date('F d, Y H:i:s'); ?></p>
        <?php if (isset($filters['year']) && $filters['year']): ?>
        <p>Financial Year: <?php echo $filters['year']; ?></p>
        <?php endif; ?>
    </div>
    
    <div class="summary-section">
        <h3>Summary</h3>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Total Members</div>
                <div class="value"><?php echo number_format($summary['total_members'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Earnings</div>
                <div class="value"><?php echo number_format($summary['total_earned'] ?? 0, 2); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Paid Amount</div>
                <div class="value success"><?php echo number_format($summary['paid_amount'] ?? 0, 2); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Outstanding</div>
                <div class="value danger"><?php echo number_format($summary['outstanding_amount'] ?? 0, 2); ?></div>
            </div>
        </div>
    </div>
    
    <div class="details-section">
        <h3>Member Dividends</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Member No</th>
                    <th>Savings</th>
                    <th>Earned</th>
                    <th>Paid</th>
                    <th>Outstanding</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dividends as $index => $dividend): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($dividend['member_name']); ?></td>
                    <td><?php echo htmlspecialchars($dividend['member_no']); ?></td>
                    <td><?php echo number_format($dividend['savings_amount'], 2); ?></td>
                    <td><?php echo number_format($dividend['interest_earned'], 2); ?></td>
                    <td><?php echo number_format($dividend['total_paid'] ?? 0, 2); ?></td>
                    <td><?php echo number_format($dividend['outstanding'] ?? $dividend['interest_earned'], 2); ?></td>
                    <td>
                        <span class="status-badge <?php echo strtolower($dividend['status']); ?>">
                            <?php echo $dividend['status']; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="report-footer">
        <p>This is a computer-generated report. No signature required.</p>
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - All Rights Reserved</p>
    </div>
    
    <div class="no-print">
        <button onclick="window.print()">🖨️ Print Report</button>
        <button onclick="window.close()" style="background: #718096;">Close</button>
    </div>
</body>
</html>