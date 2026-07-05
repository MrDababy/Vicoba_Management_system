<?php
/**
 * Create Loan View
 * 
 * Loan application form.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Apply for Loan</h4>
        <p class="text-muted mb-0">Submit a new loan application</p>
    </div>
    <a href="/loans" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="card dashboard-card">
    <div class="card-body">
        <form action="/loans" method="POST" id="loanForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row g-4">
                <?php if ($auth->isAdmin()): ?>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="member_id" class="form-label">Member <span class="text-danger">*</span></label>
                        <select class="form-select" id="member_id" name="member_id" required>
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>" <?php echo ($member_id ?? '') == $member['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['full_name']); ?> (<?php echo $member['member_no']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php else: ?>
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                <?php endif; ?>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="loan_type_id" class="form-label">Loan Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="loan_type_id" name="loan_type_id" required>
                            <option value="">Select Loan Type</option>
                            <?php foreach ($loan_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" 
                                    data-rate="<?php echo $type['default_rate']; ?>"
                                    data-min="<?php echo $type['min_amount']; ?>"
                                    data-max="<?php echo $type['max_amount']; ?>">
                                <?php echo htmlspecialchars($type['name']); ?>
                                (Rate: <?php echo $type['default_rate']; ?>%, 
                                Min: <?php echo number_format($type['min_amount'], 2); ?>, 
                                Max: <?php echo number_format($type['max_amount'], 2); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Loan Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">TSh</span>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0.01" required>
                        </div>
                        <small class="text-muted" id="amountHelp"></small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="duration_months" class="form-label">Duration (Months) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="duration_months" name="duration_months" 
                               min="1" max="60" value="12" required>
                        <small class="text-muted">1-60 months</small>
                    </div>
                </div>
                
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" maxlength="500"></textarea>
                    </div>
                </div>
                
                <!-- Loan Calculation Preview -->
                <div class="col-12">
                    <div class="alert alert-info" id="loanPreview" style="display: none;">
                        <h6 class="fw-bold">Loan Preview</h6>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <small class="text-muted">Interest Rate:</small>
                                <p class="fw-bold" id="previewRate">0%</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Monthly Payment:</small>
                                <p class="fw-bold" id="previewMonthly">0.00 TSh</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Total Interest:</small>
                                <p class="fw-bold" id="previewInterest">0.00 TSh</p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Total Repayable:</small>
                                <p class="fw-bold" id="previewTotal">0.00 TSh</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <hr>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Submit Application
                        </button>
                        <a href="/loans" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Loan type change handler
document.getElementById('loan_type_id')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const rate = parseFloat(selectedOption.dataset.rate) || 0;
    const minAmount = parseFloat(selectedOption.dataset.min) || 0;
    const maxAmount = parseFloat(selectedOption.dataset.max) || 0;
    
    document.getElementById('previewRate').textContent = rate + '%';
    document.getElementById('amountHelp').textContent = 
        `Min: ${minAmount.toFixed(2)}, Max: ${maxAmount.toFixed(2)} TSh`;
    
    // Set min/max for amount
    const amountInput = document.getElementById('amount');
    amountInput.min = minAmount;
    amountInput.max = maxAmount;
    
    updatePreview();
});

// Amount and duration change handlers
document.getElementById('amount')?.addEventListener('input', updatePreview);
document.getElementById('duration_months')?.addEventListener('input', updatePreview);

function updatePreview() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const duration = parseInt(document.getElementById('duration_months').value) || 0;
    const rate = parseFloat(document.getElementById('previewRate').textContent) || 0;
    
    if (amount > 0 && duration > 0) {
        // Calculate using loan calculator
        const monthlyRate = (rate / 100) / 12;
        let monthlyPayment = 0;
        
        if (monthlyRate == 0) {
            monthlyPayment = amount / duration;
        } else {
            monthlyPayment = amount * (monthlyRate * Math.pow(1 + monthlyRate, duration)) / 
                            (Math.pow(1 + monthlyRate, duration) - 1);
        }
        
        const totalPayment = monthlyPayment * duration;
        const totalInterest = totalPayment - amount;
        
        document.getElementById('previewMonthly').textContent = monthlyPayment.toFixed(2) + ' TSh';
        document.getElementById('previewInterest').textContent = totalInterest.toFixed(2) + ' TSh';
        document.getElementById('previewTotal').textContent = totalPayment.toFixed(2) + ' TSh';
        document.getElementById('loanPreview').style.display = 'block';
    } else {
        document.getElementById('loanPreview').style.display = 'none';
    }
}

// Form validation
document.getElementById('loanForm')?.addEventListener('submit', function(e) {
    const memberId = document.getElementById('member_id');
    const loanTypeId = document.getElementById('loan_type_id');
    const amount = document.getElementById('amount');
    const duration = document.getElementById('duration_months');
    
    if (memberId && !memberId.value) {
        e.preventDefault();
        alert('Please select a member.');
        return false;
    }
    
    if (!loanTypeId.value) {
        e.preventDefault();
        alert('Please select a loan type.');
        return false;
    }
    
    if (!amount.value || parseFloat(amount.value) <= 0) {
        e.preventDefault();
        alert('Please enter a valid loan amount.');
        return false;
    }
    
    if (!duration.value || parseInt(duration.value) < 1) {
        e.preventDefault();
        alert('Please enter a valid duration.');
        return false;
    }
    
    // Check min/max
    const selectedOption = loanTypeId.options[loanTypeId.selectedIndex];
    const min = parseFloat(selectedOption.dataset.min) || 0;
    const max = parseFloat(selectedOption.dataset.max) || 0;
    const amt = parseFloat(amount.value);
    
    if (amt < min) {
        e.preventDefault();
        alert(`Minimum loan amount is ${min.toFixed(2)} TSh.`);
        return false;
    }
    
    if (amt > max) {
        e.preventDefault();
        alert(`Maximum loan amount is ${max.toFixed(2)} TSh.`);
        return false;
    }
    
    return true;
});
</script>