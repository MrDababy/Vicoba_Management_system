<?php
/**
 * Create Repayment View
 *
 * Record a loan repayment.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h4 class="fw-bold mb-1">
            Record Loan Repayment
        </h4>

        <p class="text-muted mb-0">
            Record a member's loan repayment transaction
        </p>

    </div>

    <a href="<?= BASE_URL ?>/repayments"
       class="btn btn-secondary btn-sm">

        <i class="fas fa-arrow-left me-1"></i>

        Back

    </a>

</div>

<div class="card dashboard-card">

    <div class="card-body">

        <form action="<?= BASE_URL ?>/repayments"
              method="POST"
              id="repaymentForm">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= $csrf_token; ?>">

            <div class="row g-4">

                <!-- Member -->

                <?php if($auth->hasRole(['Admin','Treasurer'])): ?>

                <div class="col-md-6">

                    <div class="mb-3">

                        <label class="form-label">

                            Member

                            <span class="text-danger">*</span>

                        </label>

                        <select class="form-select"
                                id="member_id"
                                name="member_id"
                                required>

                            <option value="">
                                Select Member
                            </option>

                            <?php foreach($members as $member): ?>

                            <option value="<?= $member['id']; ?>">

                                <?= htmlspecialchars($member['full_name']); ?>

                                (<?= $member['member_no']; ?>)

                            </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <?php else: ?>

                <input type="hidden"
                       name="member_id"
                       value="<?= $member_id; ?>">

                <?php endif; ?>

                <!-- Loan -->

                <div class="col-md-6">

                    <div class="mb-3">

                        <label class="form-label">

                            Loan

                            <span class="text-danger">*</span>

                        </label>

                        <select class="form-select"
                                id="loan_id"
                                name="loan_id"
                                required>

                            <option value="">
                                Select Loan
                            </option>

                            <?php foreach($loans as $loan): ?>

                            <option value="<?= $loan['id']; ?>"

                                data-balance="<?= $loan['remaining_balance']; ?>"

                                data-principal="<?= $loan['principal_balance']; ?>"

                                data-interest="<?= $loan['interest_balance']; ?>">

                                <?= htmlspecialchars($loan['loan_no']); ?>

                                -

                                <?= htmlspecialchars($loan['member_name']); ?>

                            </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <!-- Outstanding Balance -->

                <div class="col-md-4">

                    <div class="mb-3">

                        <label class="form-label">

                            Outstanding Balance

                        </label>

                        <div class="input-group">

                            <span class="input-group-text">

                                TSh

                            </span>

                            <input type="text"
                                   class="form-control"
                                   id="outstanding_balance"
                                   readonly>

                        </div>

                    </div>

                </div>

                <!-- Principal Due -->

                <div class="col-md-4">

                    <div class="mb-3">

                        <label class="form-label">

                            Principal Due

                        </label>

                        <div class="input-group">

                            <span class="input-group-text">

                                TSh

                            </span>

                            <input type="text"
                                   class="form-control"
                                   id="principal_due"
                                   readonly>

                        </div>

                    </div>

                </div>

                <!-- Interest Due -->

                <div class="col-md-4">

                    <div class="mb-3">

                        <label class="form-label">

                            Interest Due

                        </label>

                        <div class="input-group">

                            <span class="input-group-text">

                                TSh

                            </span>

                            <input type="text"
                                   class="form-control"
                                   id="interest_due"
                                   readonly>

                        </div>

                    </div>

                </div>

                <!-- Payment Amount -->

                <div class="col-md-6">

                    <div class="mb-3">

                        <label class="form-label">

                            Payment Amount

                            <span class="text-danger">*</span>

                        </label>

                        <div class="input-group">

                            <span class="input-group-text">

                                TSh

                            </span>

                            <input type="number"
                                   class="form-control"
                                   id="payment_amount"
                                   name="payment_amount"
                                   step="0.01"
                                   min="0.01"
                                   required>

                        </div>

                    </div>

                </div>

                <!-- Payment Method -->

                <div class="col-md-6">

                    <div class="mb-3">

                        <label class="form-label">

                            Payment Method

                            <span class="text-danger">*</span>

                        </label>

                        <select class="form-select"
                                id="payment_method"
                                name="payment_method"
                                required>

                            <option value="">
                                Select Method
                            </option>

                            <option value="Cash">
                                Cash
                            </option>

                            <option value="Bank">
                                Bank Transfer
                            </option>

                            <option value="Mobile Money">
                                Mobile Money
                            </option>

                            <option value="Cheque">
                                Cheque
                            </option>

                        </select>

                    </div>

                </div>

                <!-- Payment Date -->

                <div class="col-md-6">

                    <div class="mb-3">

                        <label class="form-label">

                            Payment Date

                            <span class="text-danger">*</span>

                        </label>

                        <input type="date"
                               class="form-control"
                               id="payment_date"
                               name="payment_date"
                               value="<?= date('Y-m-d'); ?>"
                               required>

                    </div>

                </div>

                <!-- Transaction Reference -->

                <div class="col-md-6">

                    <div class="mb-3">

                        <label class="form-label">

                            Transaction Reference

                        </label>

                        <input type="text"
                               class="form-control"
                               id="reference_no"
                               name="reference_no"
                               maxlength="100"
                               placeholder="Optional">

                    </div>

                </div>

                <!-- Remarks -->

                <div class="col-12">

                    <div class="mb-3">

                        <label class="form-label">

                            Remarks

                        </label>

                        <textarea class="form-control"
                                  id="remarks"
                                  name="remarks"
                                  rows="4"
                                  maxlength="500"
                                  placeholder="Additional payment notes..."></textarea>

                    </div>

                </div>

                                <!-- Payment Summary Preview -->

                <div class="col-12">

                    <div class="alert alert-info"
                         id="paymentPreview"
                         style="display:none;">

                        <h5 class="fw-bold mb-3">

                            <i class="fas fa-receipt me-2"></i>

                            Payment Summary

                        </h5>

                        <div class="row g-3">

                            <div class="col-md-3">

                                <div class="border rounded p-3 text-center">

                                    <small class="text-muted d-block">

                                        Amount Paid

                                    </small>

                                    <h5 class="fw-bold text-primary mb-0"
                                        id="previewAmount">

                                        0.00 TSh

                                    </h5>

                                </div>

                            </div>

                            <div class="col-md-3">

                                <div class="border rounded p-3 text-center">

                                    <small class="text-muted d-block">

                                        Principal Paid

                                    </small>

                                    <h5 class="fw-bold text-success mb-0"
                                        id="previewPrincipal">

                                        0.00 TSh

                                    </h5>

                                </div>

                            </div>

                            <div class="col-md-3">

                                <div class="border rounded p-3 text-center">

                                    <small class="text-muted d-block">

                                        Interest Paid

                                    </small>

                                    <h5 class="fw-bold text-warning mb-0"
                                        id="previewInterest">

                                        0.00 TSh

                                    </h5>

                                </div>

                            </div>

                            <div class="col-md-3">

                                <div class="border rounded p-3 text-center">

                                    <small class="text-muted d-block">

                                        Remaining Balance

                                    </small>

                                    <h5 class="fw-bold text-danger mb-0"
                                        id="previewBalance">

                                        0.00 TSh

                                    </h5>

                                </div>

                            </div>

                        </div>

                        <hr>

                        <div class="row">

                            <div class="col-md-6">

                                <table class="table table-sm mb-0">

                                    <tr>

                                        <th width="50%">

                                            Loan Number

                                        </th>

                                        <td id="summaryLoan">

                                            -

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>

                                            Member

                                        </th>

                                        <td id="summaryMember">

                                            -

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>

                                            Payment Method

                                        </th>

                                        <td id="summaryMethod">

                                            -

                                        </td>

                                    </tr>

                                </table>

                            </div>

                            <div class="col-md-6">

                                <table class="table table-sm mb-0">

                                    <tr>

                                        <th width="50%">

                                            Payment Date

                                        </th>

                                        <td id="summaryDate">

                                            <?= date('d M Y'); ?>

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>

                                            Transaction Ref

                                        </th>

                                        <td id="summaryReference">

                                            -

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>

                                            Status

                                        </th>

                                        <td>

                                            <span class="badge bg-success">

                                                Ready to Save

                                            </span>

                                        </td>

                                    </tr>

                                </table>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Form Buttons -->

                <div class="col-12">

                    <hr>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                        <div>

                            <button type="submit"
                                    class="btn btn-primary">

                                <i class="fas fa-save me-2"></i>

                                Save Repayment

                            </button>

                            <button type="reset"
                                    class="btn btn-outline-warning">

                                <i class="fas fa-rotate-left me-2"></i>

                                Reset

                            </button>

                        </div>

                        <div>

                            <a href="<?= BASE_URL ?>/repayments"
                               class="btn btn-secondary">

                                <i class="fas fa-times me-2"></i>

                                Cancel

                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </form>

    </div>

</div>
<script>

const loanSelect = document.getElementById('loan_id');
const amountInput = document.getElementById('payment_amount');
const methodSelect = document.getElementById('payment_method');
const dateInput = document.getElementById('payment_date');
const referenceInput = document.getElementById('reference_no');

const outstandingInput = document.getElementById('outstanding_balance');
const principalInput = document.getElementById('principal_due');
const interestInput = document.getElementById('interest_due');

const preview = document.getElementById('paymentPreview');

loanSelect?.addEventListener('change', loadLoanDetails);
amountInput?.addEventListener('input', updatePreview);
methodSelect?.addEventListener('change', updatePreview);
dateInput?.addEventListener('change', updatePreview);
referenceInput?.addEventListener('input', updatePreview);

/*=========================================
=           Load Loan Details             =
=========================================*/

function loadLoanDetails()
{
    const option = loanSelect.options[loanSelect.selectedIndex];

    if(!option.value)
    {
        outstandingInput.value = '';
        principalInput.value = '';
        interestInput.value = '';
        preview.style.display = 'none';
        return;
    }

    const balance = parseFloat(option.dataset.balance) || 0;
    const principal = parseFloat(option.dataset.principal) || 0;
    const interest = parseFloat(option.dataset.interest) || 0;

    outstandingInput.value = balance.toFixed(2);
    principalInput.value = principal.toFixed(2);
    interestInput.value = interest.toFixed(2);

    amountInput.max = balance;

    updatePreview();
}

/*=========================================
=       Update Payment Preview            =
=========================================*/

function updatePreview()
{
    const payment = parseFloat(amountInput.value) || 0;

    const principalDue = parseFloat(principalInput.value) || 0;
    const interestDue = parseFloat(interestInput.value) || 0;
    const outstanding = parseFloat(outstandingInput.value) || 0;

    if(payment <= 0 || outstanding <= 0)
    {
        preview.style.display='none';
        return;
    }

    let interestPaid = Math.min(payment, interestDue);
    let principalPaid = payment - interestPaid;

    if(principalPaid > principalDue)
        principalPaid = principalDue;

    const remaining =
        outstanding - payment;

    document.getElementById('previewAmount').textContent =
        payment.toLocaleString(undefined,{
            minimumFractionDigits:2
        }) + ' TSh';

    document.getElementById('previewPrincipal').textContent =
        principalPaid.toLocaleString(undefined,{
            minimumFractionDigits:2
        }) + ' TSh';

    document.getElementById('previewInterest').textContent =
        interestPaid.toLocaleString(undefined,{
            minimumFractionDigits:2
        }) + ' TSh';

    document.getElementById('previewBalance').textContent =
        Math.max(remaining,0).toLocaleString(undefined,{
            minimumFractionDigits:2
        }) + ' TSh';

    const loanOption =
        loanSelect.options[loanSelect.selectedIndex];

    document.getElementById('summaryLoan').textContent =
        loanOption.text;

    const member =
        loanOption.text.split('-')[1] ?? '';

    document.getElementById('summaryMember').textContent =
        member.trim();

    document.getElementById('summaryMethod').textContent =
        methodSelect.value || '-';

    document.getElementById('summaryDate').textContent =
        dateInput.value || '-';

    document.getElementById('summaryReference').textContent =
        referenceInput.value || '-';

    preview.style.display='block';
}

/*=========================================
=         Form Validation                 =
=========================================*/

document.getElementById('repaymentForm')
.addEventListener('submit',function(e){

    const payment =
        parseFloat(amountInput.value)||0;

    const outstanding =
        parseFloat(outstandingInput.value)||0;

    if(!loanSelect.value)
    {
        e.preventDefault();
        alert('Please select a loan.');
        return;
    }

    if(payment<=0)
    {
        e.preventDefault();
        alert('Enter a valid payment amount.');
        return;
    }

    if(payment>outstanding)
    {
        e.preventDefault();
        alert('Payment cannot exceed outstanding balance.');
        return;
    }

    if(!methodSelect.value)
    {
        e.preventDefault();
        alert('Please select a payment method.');
        return;
    }

});

/*=========================================
=         Reset Preview                   =
=========================================*/

document.getElementById('repaymentForm')
.addEventListener('reset',function(){

    setTimeout(function(){

        outstandingInput.value='';
        principalInput.value='';
        interestInput.value='';

        preview.style.display='none';

    },50);

});

/*=========================================
=      Format Numbers While Typing        =
=========================================*/

amountInput?.addEventListener('blur',function(){

    if(this.value!='')
    {
        this.value=parseFloat(this.value).toFixed(2);
    }

});

</script>
<style>

/*=========================================
=            Dashboard Card               =
=========================================*/

.dashboard-card{
    border:none;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 3px 15px rgba(0,0,0,.06);
    animation:fadeIn .35s ease;
}

/*=========================================
=              Form Controls              =
=========================================*/

.form-control,
.form-select{
    border-radius:8px;
    transition:all .25s ease;
}

.form-control:focus,
.form-select:focus{
    box-shadow:0 0 0 .15rem rgba(13,110,253,.15);
}

.input-group-text{
    background:#fff;
    border-right:0;
}

.input-group .form-control{
    border-left:0;
}

/*=========================================
=          Payment Preview Card           =
=========================================*/

#paymentPreview{
    border:none;
    border-left:5px solid #0d6efd;
    border-radius:12px;
}

#paymentPreview .border{
    border-radius:10px !important;
    transition:.3s;
    background:#fff;
}

#paymentPreview .border:hover{
    transform:translateY(-2px);
    box-shadow:0 3px 10px rgba(0,0,0,.08);
}

/*=========================================
=              Tables                     =
=========================================*/

.table-sm th{
    font-weight:600;
    width:45%;
}

.table-sm td{
    font-weight:500;
}

/*=========================================
=              Buttons                    =
=========================================*/

.btn{
    border-radius:8px;
}

.btn-primary{
    padding:.55rem 1.2rem;
}

.btn-secondary,
.btn-outline-warning{
    padding:.55rem 1.2rem;
}

/*=========================================
=             Required Field              =
=========================================*/

.text-danger{
    font-weight:bold;
}

/*=========================================
=          Summary Values                 =
=========================================*/

#previewAmount{
    color:#0d6efd;
}

#previewPrincipal{
    color:#198754;
}

#previewInterest{
    color:#fd7e14;
}

#previewBalance{
    color:#dc3545;
}

/*=========================================
=              Animation                  =
=========================================*/

@keyframes fadeIn{

    from{

        opacity:0;
        transform:translateY(8px);

    }

    to{

        opacity:1;
        transform:translateY(0);

    }

}

/*=========================================
=             Mobile Layout               =
=========================================*/

@media (max-width:992px){

    .dashboard-card{

        margin-bottom:1rem;

    }

}

@media (max-width:768px){

    #paymentPreview .col-md-3{

        margin-bottom:12px;

    }

    .d-flex.justify-content-between{

        flex-direction:column;
        align-items:flex-start !important;
        gap:10px;

    }

    .btn{

        width:100%;

    }

}

@media (max-width:576px){

    .table-sm th,
    .table-sm td{

        font-size:13px;

    }

}

/*=========================================
=              Dark Theme                 =
=========================================*/

[data-bs-theme="dark"] .dashboard-card{

    background:#2d2d3f;

}

[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select{

    background:#3d3d5f;
    color:#fff;
    border-color:#555;

}

[data-bs-theme="dark"] .input-group-text{

    background:#3d3d5f;
    color:#fff;
    border-color:#555;

}

[data-bs-theme="dark"] #paymentPreview{

    background:#34344d;
    color:#fff;
    border-left-color:#4dabf7;

}

[data-bs-theme="dark"] #paymentPreview .border{

    background:#3d3d5f;
    border-color:#555 !important;

}

[data-bs-theme="dark"] .table{

    color:#fff;

}

[data-bs-theme="dark"] .table td,
[data-bs-theme="dark"] .table th{

    border-color:#555;

}

[data-bs-theme="dark"] .modal-content{

    background:#2d2d3f;
    color:#fff;

}

</style>