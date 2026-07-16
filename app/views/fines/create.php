<?php
/**
 * Create Fine View
 *
 * Fine creation form.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Add Fine</h4>
        <p class="text-muted mb-0">Create a new fine for a member</p>
    </div>

    <a href="/fines" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="card dashboard-card">
    <div class="card-body">

        <form action="/fines" method="POST" id="fineForm">

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

                                <option value="<?php echo $member['id']; ?>"
                                    <?php echo (($member_id ?? '') == $member['id']) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($member['full_name']); ?>
                                    (<?php echo htmlspecialchars($member['member_no']); ?>)

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
                        <label for="fine_type" class="form-label">
                            Fine Type <span class="text-danger">*</span>
                        </label>

                        <select class="form-select" id="fine_type" name="fine_type" required>
                            <option value="">Select Fine Type</option>
                            <option value="Late Payment">Late Payment</option>
                            <option value="Meeting Absence">Meeting Absence</option>
                            <option value="Loan Default">Loan Default</option>
                            <option value="Rule Violation">Rule Violation</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="amount" class="form-label">
                            Fine Amount <span class="text-danger">*</span>
                        </label>

                        <div class="input-group">
                            <span class="input-group-text">TSh</span>

                            <input
                                type="number"
                                class="form-control"
                                id="amount"
                                name="amount"
                                step="0.01"
                                min="1"
                                required>
                        </div>
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="fine_date" class="form-label">
                            Fine Date <span class="text-danger">*</span>
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="fine_date"
                            name="fine_date"
                            value="<?php echo date('Y-m-d'); ?>"
                            required>
                    </div>
                </div>


                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="reason" class="form-label">
                            Reason <span class="text-danger">*</span>
                        </label>

                        <textarea
                            class="form-control"
                            id="reason"
                            name="reason"
                            rows="3"
                            maxlength="500"
                            required></textarea>
                    </div>
                </div>


                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="remarks" class="form-label">
                            Additional Remarks
                        </label>

                        <textarea
                            class="form-control"
                            id="remarks"
                            name="remarks"
                            rows="3"
                            maxlength="500"></textarea>
                    </div>
                </div>


                <!-- Fine Preview -->

                <div class="col-12">

                    <div class="alert alert-warning" id="finePreview" style="display:none;">

                        <h6 class="fw-bold">
                            Fine Summary
                        </h6>

                        <div class="row">

                            <div class="col-md-4">

                                <small class="text-muted">Fine Type</small>

                                <p class="fw-bold mb-0" id="previewType">-</p>

                            </div>

                            <div class="col-md-4">

                                <small class="text-muted">Amount</small>

                                <p class="fw-bold mb-0" id="previewAmount">
                                    0.00 TSh
                                </p>

                            </div>

                            <div class="col-md-4">

                                <small class="text-muted">Fine Date</small>

                                <p class="fw-bold mb-0" id="previewDate">-</p>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-12">

                    <hr>

                    <div class="d-flex gap-2">

                        <button type="submit" class="btn btn-primary">

                            <i class="fas fa-save me-1"></i>

                            Save Fine

                        </button>

                        <a href="/fines" class="btn btn-secondary">

                            Cancel

                        </a>

                    </div>

                </div>

            </div>

        </form>

    </div>
</div>


<script>

const fineType = document.getElementById('fine_type');
const amount = document.getElementById('amount');
const fineDate = document.getElementById('fine_date');

fineType.addEventListener('change', updatePreview);
amount.addEventListener('input', updatePreview);
fineDate.addEventListener('change', updatePreview);

function updatePreview(){

    if(fineType.value && amount.value){

        document.getElementById('previewType').textContent = fineType.value;

        document.getElementById('previewAmount').textContent =
            parseFloat(amount.value).toLocaleString(undefined,{
                minimumFractionDigits:2,
                maximumFractionDigits:2
            }) + " TSh";

        document.getElementById('previewDate').textContent = fineDate.value;

        document.getElementById('finePreview').style.display = "block";

    }else{

        document.getElementById('finePreview').style.display = "none";

    }

}


// Validation

document.getElementById('fineForm').addEventListener('submit', function(e){

    const member = document.getElementById('member_id');

    if(member && member.value === ""){

        alert("Please select a member.");

        e.preventDefault();

        return false;

    }

    if(fineType.value === ""){

        alert("Please select a fine type.");

        e.preventDefault();

        return false;

    }

    if(amount.value === "" || parseFloat(amount.value) <= 0){

        alert("Please enter a valid fine amount.");

        e.preventDefault();

        return false;

    }

    if(document.getElementById('reason').value.trim() === ""){

        alert("Please provide the reason for the fine.");

        e.preventDefault();

        return false;

    }

    return true;

});

</script>