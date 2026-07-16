<?php
/**
 * Create Savings View
 *
 * Savings deposit form.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Add Savings</h4>
        <p class="text-muted mb-0">Record a new savings deposit</p>
    </div>

    <a href="/savings" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="card dashboard-card">
    <div class="card-body">

        <form action="/savings" method="POST" id="savingForm">

            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="row g-4">

                <?php if ($auth->isAdmin()): ?>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="member_id" class="form-label">
                            Member <span class="text-danger">*</span>
                        </label>

                        <select class="form-select" id="member_id" name="member_id" required>

                            <option value="">Select Member</option>

                            <?php foreach ($members as $member): ?>

                                <option
                                    value="<?php echo $member['id']; ?>"
                                    <?php echo (($member_id ?? '') == $member['id']) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($member['full_name']); ?>
                                    (<?php echo htmlspecialchars($member['member_no']); ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>
                </div>

                <?php else: ?>

                    <input type="hidden"
                           name="member_id"
                           value="<?php echo $member_id; ?>">

                <?php endif; ?>


                <div class="col-md-6">

                    <div class="mb-3">

                        <label for="amount" class="form-label">
                            Deposit Amount <span class="text-danger">*</span>
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">TSh</span>

                            <input
                                type="number"
                                class="form-control"
                                id="amount"
                                name="amount"
                                min="1"
                                step="0.01"
                                required>

                        </div>

                        <small class="text-muted">
                            Enter the savings amount to deposit.
                        </small>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="mb-3">

                        <label for="deposit_date" class="form-label">
                            Deposit Date <span class="text-danger">*</span>
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="deposit_date"
                            name="deposit_date"
                            value="<?php echo date('Y-m-d'); ?>"
                            required>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="mb-3">

                        <label for="payment_method" class="form-label">
                            Payment Method
                        </label>

                        <select
                            class="form-select"
                            id="payment_method"
                            name="payment_method">

                            <option value="Cash">Cash</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Bank">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>

                        </select>

                    </div>

                </div>


                <div class="col-md-12">

                    <div class="mb-3">

                        <label for="remarks" class="form-label">
                            Remarks
                        </label>

                        <textarea
                            class="form-control"
                            id="remarks"
                            name="remarks"
                            rows="3"
                            maxlength="500"></textarea>

                    </div>

                </div>


                <!-- Deposit Preview -->

                <div class="col-12">

                    <div class="alert alert-success" id="savingPreview" style="display:none;">

                        <h6 class="fw-bold">
                            Deposit Preview
                        </h6>

                        <div class="row">

                            <div class="col-md-6">

                                <small class="text-muted">
                                    Deposit Amount
                                </small>

                                <p class="fw-bold mb-0" id="previewAmount">
                                    0.00 TSh
                                </p>

                            </div>

                            <div class="col-md-6">

                                <small class="text-muted">
                                    Payment Method
                                </small>

                                <p class="fw-bold mb-0" id="previewMethod">
                                    Cash
                                </p>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-12">

                    <hr>

                    <div class="d-flex gap-2">

                        <button type="submit" class="btn btn-primary">

                            <i class="fas fa-save me-1"></i>

                            Save Deposit

                        </button>

                        <a href="/savings" class="btn btn-secondary">
                            Cancel
                        </a>

                    </div>

                </div>

            </div>

        </form>

    </div>
</div>


<script>

const amountInput = document.getElementById('amount');
const methodInput = document.getElementById('payment_method');

amountInput.addEventListener('input', updatePreview);
methodInput.addEventListener('change', updatePreview);

function updatePreview(){

    const amount = parseFloat(amountInput.value) || 0;

    if(amount > 0){

        document.getElementById('previewAmount').textContent =
            amount.toLocaleString(undefined,{
                minimumFractionDigits:2,
                maximumFractionDigits:2
            }) + ' TSh';

        document.getElementById('previewMethod').textContent =
            methodInput.value;

        document.getElementById('savingPreview').style.display = 'block';

    }else{

        document.getElementById('savingPreview').style.display = 'none';

    }

}


// Form Validation

document.getElementById('savingForm').addEventListener('submit', function(e){

    const member = document.getElementById('member_id');

    if(member && member.value === ''){

        e.preventDefault();

        alert('Please select a member.');

        return false;

    }

    if(amountInput.value === '' || parseFloat(amountInput.value) <= 0){

        e.preventDefault();

        alert('Please enter a valid savings amount.');

        amountInput.focus();

        return false;

    }

    return true;

});

</script>