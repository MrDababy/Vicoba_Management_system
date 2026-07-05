<?php
/**
 * Receipt View
 * 
 * Printable receipt for savings transactions.
 */
?>

<div class="receipt-wrapper">
    <div class="receipt-container">
        <div class="text-center mb-4">
            <h2 class="fw-bold" style="color: #667eea;"><?php echo APP_NAME; ?></h2>
            <p class="text-muted small">Community Banking Management System</p>
            <hr>
            <h4 class="fw-bold">SAVINGS RECEIPT</h4>
        </div>
        
        <div class="receipt-details">
            <div class="row g-2">
                <div class="col-6">
                    <p class="text-muted small mb-0">Receipt No:</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($transaction['receipt_no']); ?></p>
                </div>
                <div class="col-6 text-end">
                    <p class="text-muted small mb-0">Date:</p>
                    <p class="fw-bold"><?php echo date('F d, Y H:i', strtotime($transaction['transaction_date'])); ?></p>
                </div>
                <div class="col-12">
                    <p class="text-muted small mb-0">Member:</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($transaction['member_name']); ?></p>
                </div>
                <div class="col-12">
                    <p class="text-muted small mb-0">Member No:</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($transaction['member_no']); ?></p>
                </div>
            </div>
            
            <hr>
            
            <div class="row g-2">
                <div class="col-6">
                    <p class="text-muted small mb-0">Transaction Type:</p>
                    <p class="fw-bold <?php echo strtolower($transaction['transaction_type']); ?>">
                        <?php echo htmlspecialchars($transaction['transaction_type']); ?>
                    </p>
                </div>
                <div class="col-6 text-end">
                    <p class="text-muted small mb-0">Amount:</p>
                    <p class="fw-bold amount <?php echo strtolower($transaction['transaction_type']); ?>">
                        <?php echo number_format($transaction['amount'], 2); ?> TSh
                    </p>
                </div>
                <div class="col-12">
                    <p class="text-muted small mb-0">Balance After:</p>
                    <p class="fw-bold"><?php echo number_format($transaction['balance_after'], 2); ?> TSh</p>
                </div>
                <div class="col-12">
                    <p class="text-muted small mb-0">Payment Mode:</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($transaction['transaction_mode']); ?></p>
                </div>
                <?php if (!empty($transaction['reference_no'])): ?>
                <div class="col-12">
                    <p class="text-muted small mb-0">Reference No:</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($transaction['reference_no']); ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($transaction['description'])): ?>
                <div class="col-12">
                    <p class="text-muted small mb-0">Description:</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($transaction['description']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <hr>
            
            <div class="text-center">
                <p class="fw-bold" style="color: #667eea;">Thank you for saving with us!</p>
                <p class="text-muted small">This is a computer-generated receipt. No signature required.</p>
                <p class="text-muted small">Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4 no-print">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print Receipt
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            <i class="fas fa-times me-1"></i> Close
        </button>
    </div>
</div>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    padding: 20px;
}

.receipt-wrapper {
    max-width: 500px;
    margin: 0 auto;
}

.receipt-container {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.amount {
    font-size: 24px;
}

.amount.deposit {
    color: #48bb78;
}

.amount.withdrawal {
    color: #f56565;
}

.deposit {
    color: #48bb78;
}

.withdrawal {
    color: #f56565;
}

@media print {
    body {
        background: white;
        padding: 0;
    }
    .receipt-container {
        box-shadow: none;
        padding: 20px;
    }
    .no-print {
        display: none;
    }
    .receipt-wrapper {
        max-width: 100%;
    }
}
</style>