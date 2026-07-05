<?php
/**
 * Savings Form Partial
 * 
 * Reusable form for creating and editing savings transactions.
 * 
 * @var array $transaction Transaction data (null for create)
 * @var bool $is_edit Whether this is an edit form
 * @var array $members List of members
 * @var array $transaction_types Transaction types
 * @var array $transaction_modes Transaction modes
 * @var string $csrf_token CSRF token
 */
?>

<form action="<?php echo $is_edit ? '/savings/' . $transaction['id'] : '/savings'; ?>" 
      method="POST" id="savingsForm">
    
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <?php if ($is_edit): ?>
    <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>
    
    <div class="row g-4">
        <!-- Member Selection -->
        <div class="col-md-6">
            <div class="mb-3">
                <label for="member_id" class="form-label">Member <span class="text-danger">*</span></label>
                <select class="form-select" id="member_id" name="member_id" required>
                    <option value="">Select Member</option>
                    <?php foreach ($members as $member): ?>
                    <option value="<?php echo $member['id']; ?>" 
                            <?php echo ($transaction['member_id'] ?? '') == $member['id'] ? 'selected' : ''; ?>
                            data-balance="<?php echo $is_edit ? $transaction['balance_after'] : 0; ?>">
                        <?php echo htmlspecialchars($member['full_name']); ?> (<?php echo $member['member_no']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Current Balance Display -->
            <div class="mb-3">
                <label class="form-label">Current Balance</label>
                <div class="p-3 bg-light rounded-3">
                    <h5 class="fw-bold mb-0" id="currentBalanceDisplay">
                        <?php echo number_format($is_edit ? $transaction['balance_after'] : 0, 2); ?> TSh
                    </h5>
                </div>
            </div>
        </div>
        
        <!-- Transaction Details -->
        <div class="col-md-6">
            <div class="mb-3">
                <label for="transaction_type" class="form-label">Transaction Type <span class="text-danger">*</span></label>
                <select class="form-select" id="transaction_type" name="transaction_type" required>
                    <option value="">Select Type</option>
                    <?php foreach ($transaction_types as $type): ?>
                    <option value="<?php echo $type; ?>" <?php echo ($transaction['transaction_type'] ?? '') == $type ? 'selected' : ''; ?>>
                        <?php echo $type; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">TSh</span>
                    <input type="number" class="form-control" id="amount" name="amount" 
                           value="<?php echo $transaction['amount'] ?? ''; ?>" 
                           step="0.01" min="0.01" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="transaction_mode" class="form-label">Payment Mode <span class="text-danger">*</span></label>
                <select class="form-select" id="transaction_mode" name="transaction_mode" required>
                    <option value="">Select Mode</option>
                    <?php foreach ($transaction_modes as $mode): ?>
                    <option value="<?php echo $mode; ?>" <?php echo ($transaction['transaction_mode'] ?? '') == $mode ? 'selected' : ''; ?>>
                        <?php echo $mode; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Additional Details -->
        <div class="col-md-6">
            <div class="mb-3">
                <label for="transaction_date" class="form-label">Transaction Date <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control" id="transaction_date" name="transaction_date" 
                       value="<?php echo date('Y-m-d\TH:i', strtotime($transaction['transaction_date'] ?? 'now')); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="reference_no" class="form-label">Reference Number</label>
                <input type="text" class="form-control" id="reference_no" name="reference_no" 
                       value="<?php echo htmlspecialchars($transaction['reference_no'] ?? ''); ?>" maxlength="100">
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="500"><?php echo htmlspecialchars($transaction['description'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <?php if ($is_edit): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> Changing the amount or type will automatically recalculate the running balance.
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Submit Buttons -->
        <div class="col-12">
            <hr>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> 
                    <?php echo $is_edit ? 'Update Transaction' : 'Record Savings'; ?>
                </button>
                <a href="/savings" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<script>
// Member selection - fetch current balance
document.getElementById('member_id')?.addEventListener('change', function() {
    const memberId = this.value;
    const display = document.getElementById('currentBalanceDisplay');
    
    if (memberId) {
        fetch(`/savings/balance?member_id=${memberId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                display.textContent = data.data.balance.toFixed(2) + ' TSh';
            }
        })
        .catch(() => {
            display.textContent = 'Error fetching balance';
        });
    } else {
        display.textContent = '0.00 TSh';
    }
});

// Form validation
document.getElementById('savingsForm')?.addEventListener('submit', function(e) {
    const type = document.getElementById('transaction_type').value;
    const amount = parseFloat(document.getElementById('amount').value);
    
    if (!type) {
        e.preventDefault();
        alert('Please select a transaction type.');
        return false;
    }
    
    if (!amount || amount <= 0) {
        e.preventDefault();
        alert('Please enter a valid amount greater than 0.');
        return false;
    }
    
    // For withdrawal, check if balance is sufficient
    if (type === 'Withdrawal' && !<?php echo $is_edit ? 'true' : 'false'; ?>) {
        const memberId = document.getElementById('member_id').value;
        if (memberId) {
            // We'll check on the server side
            return true;
        }
    }
    
    return true;
});
</script>