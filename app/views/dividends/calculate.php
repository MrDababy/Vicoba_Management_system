<?php
/**
 * Dividend Calculation View
 * 
 * Form for calculating and distributing dividends.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Calculate Dividends</h4>
        <p class="text-muted mb-0">Distribute annual profits to members</p>
    </div>
    <a href="/dividends" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="card dashboard-card">
    <div class="card-body">
        <?php if ($is_calculated): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> Dividends for <?php echo $year; ?> have already been calculated.
            Recalculating will overwrite existing records.
        </div>
        <?php endif; ?>
        
        <form action="/dividends/process-calculate" method="POST" id="dividendForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="year" class="form-label">Financial Year <span class="text-danger">*</span></label>
                        <select class="form-select" id="year" name="year" required>
                            <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $year ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="total_profit" class="form-label">Total Annual Profit <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">TSh</span>
                            <input type="number" class="form-control" id="total_profit" name="total_profit" 
                                   value="<?php echo $annual_profit; ?>" step="0.01" min="0.01" required>
                        </div>
                        <small class="text-muted">Auto-calculated from loan interest and fines income</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="interest_percentage" class="form-label">Distribution Percentage <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="interest_percentage" name="interest_percentage" 
                                   value="70" min="1" max="100" required>
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Percentage of profit to distribute to members</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Quick Preview</h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <small class="text-muted">Active Members</small>
                                    <p class="fw-bold"><?php echo number_format($member_count); ?></p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Annual Profit</small>
                                    <p class="fw-bold"><?php echo number_format($annual_profit, 2); ?></p>
                                </div>
                            </div>
                            <div id="previewSection">
                                <hr>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted">Distributable Amount</small>
                                        <p class="fw-bold text-success" id="previewDistributable">0.00</p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Average Share</small>
                                        <p class="fw-bold text-primary" id="previewAverage">0.00</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <h6 class="fw-bold mb-3">Scenario Analysis</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Distribution %</th>
                                    <th>Distributable Amount</th>
                                    <th>Retained Earnings</th>
                                    <th>Rate on Savings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scenarios as $scenario): ?>
                                <tr>
                                    <td><?php echo $scenario['percentage']; ?>%</td>
                                    <td><?php echo number_format($scenario['distributable_amount'], 2); ?></td>
                                    <td><?php echo number_format($scenario['retained_earnings'], 2); ?></td>
                                    <td><?php echo number_format($scenario['rate'], 2); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="col-12">
                    <hr>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" <?php echo $is_calculated ? 'onclick="return confirm(\'This will overwrite existing dividend records. Continue?\')"' : ''; ?>>
                            <i class="fas fa-calculator me-1"></i> 
                            <?php echo $is_calculated ? 'Recalculate Dividends' : 'Calculate Dividends'; ?>
                        </button>
                        <a href="/dividends" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Real-time preview update
document.getElementById('total_profit')?.addEventListener('input', updatePreview);
document.getElementById('interest_percentage')?.addEventListener('input', updatePreview);

function updatePreview() {
    const profit = parseFloat(document.getElementById('total_profit').value) || 0;
    const percentage = parseFloat(document.getElementById('interest_percentage').value) || 0;
    const memberCount = <?php echo $member_count; ?>;
    
    const distributable = profit * (percentage / 100);
    const average = memberCount > 0 ? distributable / memberCount : 0;
    
    document.getElementById('previewDistributable').textContent = distributable.toFixed(2);
    document.getElementById('previewAverage').textContent = average.toFixed(2);
}

// Initial preview
updatePreview();

// Form validation
document.getElementById('dividendForm')?.addEventListener('submit', function(e) {
    const profit = parseFloat(document.getElementById('total_profit').value);
    const percentage = parseFloat(document.getElementById('interest_percentage').value);
    const year = document.getElementById('year').value;
    
    if (!year) {
        e.preventDefault();
        alert('Please select a financial year.');
        return false;
    }
    
    if (!profit || profit <= 0) {
        e.preventDefault();
        alert('Please enter a valid total profit greater than zero.');
        return false;
    }
    
    if (!percentage || percentage < 1 || percentage > 100) {
        e.preventDefault();
        alert('Please enter a distribution percentage between 1 and 100.');
        return false;
    }
    
    return true;
});
</script>