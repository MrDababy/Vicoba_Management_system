<?php
/**
 * Loan Repayments List View
 *
 * Displays a paginated list of loan repayments with
 * statistics, search and filter options.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Loan Repayments</h4>
        <p class="text-muted mb-0">Manage all loan repayment transactions</p>
    </div>

    <div class="d-flex gap-2">

        <button class="btn btn-outline-primary btn-sm"
            onclick="window.location.href='<?= BASE_URL ?>/repayments/export'">
            <i class="fas fa-file-export me-1"></i>
            Export
        </button>

        <?php if ($auth->hasRole(['Admin','Treasurer'])): ?>

        <a href="<?= BASE_URL ?>/repayments/create"
           class="btn btn-primary btn-sm">

            <i class="fas fa-money-check-dollar me-1"></i>
            Record Repayment

        </a>

        <?php endif; ?>

    </div>
</div>

<!-- Statistics Cards -->

<div class="row g-3 mb-4">

    <div class="col-md-3 col-6">

        <div class="stat-card bg-light rounded-3 p-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <p class="text-muted small mb-0">
                        Total Repayments
                    </p>

                    <h5 class="fw-bold mb-0">

                        <?=
                        number_format($stats['total'] ?? 0)
                        ?>

                    </h5>

                </div>

                <div class="stat-icon primary">
                    <i class="fas fa-money-bill-wave"></i>
                </div>

            </div>

        </div>

    </div>

    <div class="col-md-3 col-6">

        <div class="stat-card bg-light rounded-3 p-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <p class="text-muted small mb-0">
                        Today's Collection
                    </p>

                    <h5 class="fw-bold mb-0">

                        <?= number_format($stats['today'] ?? 0,2) ?>

                    </h5>

                </div>

                <div class="stat-icon success">
                    <i class="fas fa-calendar-day"></i>
                </div>

            </div>

        </div>

    </div>

    <div class="col-md-3 col-6">

        <div class="stat-card bg-light rounded-3 p-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <p class="text-muted small mb-0">
                        This Month
                    </p>

                    <h5 class="fw-bold mb-0">

                        <?= number_format($stats['month'] ?? 0,2) ?>

                    </h5>

                </div>

                <div class="stat-icon info">
                    <i class="fas fa-chart-line"></i>
                </div>

            </div>

        </div>

    </div>

    <div class="col-md-3 col-6">

        <div class="stat-card bg-light rounded-3 p-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <p class="text-muted small mb-0">
                        Outstanding Balance
                    </p>

                    <h5 class="fw-bold mb-0">

                        <?= number_format($stats['balance'] ?? 0,2) ?>

                    </h5>

                </div>

                <div class="stat-icon warning">
                    <i class="fas fa-wallet"></i>
                </div>

            </div>

        </div>

    </div>

</div>

<!-- Search & Filters -->

<div class="card dashboard-card mb-4">

    <div class="card-body">

        <form method="GET"
              action="<?= BASE_URL ?>/repayments"
              id="searchForm"
              class="row g-3">

            <div class="col-md-4">

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>

                    <input
                        type="text"
                        class="form-control"
                        name="search"
                        placeholder="Search member or loan number..."
                        value="<?= htmlspecialchars($filters['search'] ?? '') ?>">

                </div>

            </div>

            <div class="col-md-2">

                <select
                    class="form-select"
                    name="payment_method">

                    <option value="">
                        All Methods
                    </option>

                    <option value="Cash"
                        <?= ($filters['payment_method'] ?? '')=='Cash' ? 'selected':'' ?>>
                        Cash
                    </option>

                    <option value="Mobile Money"
                        <?= ($filters['payment_method'] ?? '')=='Mobile Money' ? 'selected':'' ?>>
                        Mobile Money
                    </option>

                    <option value="Bank"
                        <?= ($filters['payment_method'] ?? '')=='Bank' ? 'selected':'' ?>>
                        Bank
                    </option>

                </select>

            </div>

            <div class="col-md-2">

                <input
                    type="date"
                    class="form-control"
                    name="date_from"
                    value="<?= $filters['date_from'] ?? '' ?>">

            </div>

            <div class="col-md-2">

                <input
                    type="date"
                    class="form-control"
                    name="date_to"
                    value="<?= $filters['date_to'] ?? '' ?>">

            </div>

            <div class="col-md-2">

                <button
                    class="btn btn-primary w-100">

                    <i class="fas fa-filter me-1"></i>

                    Filter

                </button>

            </div>

            <div class="col-12">

                <a href="<?= BASE_URL ?>/repayments"
                   class="btn btn-secondary btn-sm">

                    <i class="fas fa-undo me-1"></i>

                    Reset

                </a>

            </div>

        </form>

    </div>

</div>
<!-- Repayments Table -->

<div class="card dashboard-card">

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead class="table-light">

                    <tr>

                        <th style="width:60px;">#</th>

                        <th>Member</th>

                        <th>Loan No.</th>

                        <th>Amount Paid</th>

                        <th>Principal</th>

                        <th>Interest</th>

                        <th>Balance</th>

                        <th>Method</th>

                        <th>Payment Date</th>

                        <th style="width:130px;">Actions</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($repayments)): ?>

                    <tr>

                        <td colspan="10" class="text-center py-5">

                            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3 d-block"></i>

                            <h6 class="text-muted">
                                No repayments found
                            </h6>

                            <?php if ($auth->hasRole(['Admin','Treasurer'])): ?>

                            <a href="<?= BASE_URL ?>/repayments/create"
                               class="btn btn-primary btn-sm mt-2">

                                <i class="fas fa-plus me-1"></i>

                                Record First Repayment

                            </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php else: ?>

                <?php foreach ($repayments as $index => $repayment): ?>

                    <tr>

                        <td>

                            <?= $pagination['from'] + $index; ?>

                        </td>

                        <td>

                            <div class="d-flex align-items-center">

                                <div class="avatar-sm me-2">

                                    <?= strtoupper(substr($repayment['member_name'] ?? 'U',0,1)); ?>

                                </div>

                                <div>

                                    <div class="fw-semibold small">

                                        <?= htmlspecialchars($repayment['member_name']); ?>

                                    </div>

                                    <small class="text-muted">

                                        Member

                                    </small>

                                </div>

                            </div>

                        </td>

                        <td>

                            <span class="fw-semibold">

                                <?= htmlspecialchars($repayment['loan_number']); ?>

                            </span>

                        </td>

                        <td>

                            <span class="fw-bold text-success">

                                <?= number_format($repayment['amount_paid'],2); ?>

                            </span>

                        </td>

                        <td>

                            <?= number_format($repayment['principal_amount'],2); ?>

                        </td>

                        <td>

                            <?= number_format($repayment['interest_amount'],2); ?>

                        </td>

                        <td>

                            <?php

                            $balance = $repayment['remaining_balance'];

                            ?>

                            <?php if($balance<=0): ?>

                                <span class="badge bg-success">

                                    Cleared

                                </span>

                            <?php else: ?>

                                <span class="text-danger fw-bold">

                                    <?= number_format($balance,2); ?>

                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php

                            $methodClass='secondary';

                            if($repayment['payment_method']=='Cash')
                                $methodClass='success';

                            if($repayment['payment_method']=='Mobile Money')
                                $methodClass='primary';

                            if($repayment['payment_method']=='Bank')
                                $methodClass='info';

                            ?>

                            <span class="badge bg-<?= $methodClass; ?>">

                                <?= htmlspecialchars($repayment['payment_method']); ?>

                            </span>

                        </td>

                        <td>

                            <?= date('M d, Y',strtotime($repayment['payment_date'])); ?>

                            <br>

                            <small class="text-muted">

                                <?= date('h:i A',strtotime($repayment['payment_date'])); ?>

                            </small>

                        </td>

                        <td>

                            <div class="btn-group btn-group-sm">

                                <a href="<?= BASE_URL ?>/repayments/<?= $repayment['id']; ?>"

                                   class="btn btn-outline-primary"

                                   title="View">

                                    <i class="fas fa-eye"></i>

                                </a>

                                <?php if($auth->hasRole(['Admin','Treasurer'])): ?>

                                <a href="<?= BASE_URL ?>/repayments/<?= $repayment['id']; ?>/edit"

                                   class="btn btn-outline-warning"

                                   title="Edit">

                                    <i class="fas fa-edit"></i>

                                </a>

                                <?php endif; ?>

                                <?php if($auth->isAdmin()): ?>

                                <button

                                    class="btn btn-outline-danger"

                                    onclick="deleteRepayment(
                                        <?= $repayment['id']; ?>,
                                        '<?= addslashes($repayment['member_name']); ?>'
                                    )"

                                    title="Delete">

                                    <i class="fas fa-trash"></i>

                                </button>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <?php if(!empty($repayments)): ?>

        <div class="card-footer bg-transparent">

            <?= \App\Helpers\Pagination::render(
                $pagination,
                '/repayments',
                $filters
            ); ?>

        </div>

        <?php endif; ?>

    </div>

</div>
<!-- Delete Repayment Modal -->

<div class="modal fade" id="deleteModal" tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    Delete Repayment

                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">

                </button>

            </div>

            <div class="modal-body">

                <p>

                    Are you sure you want to delete this repayment for

                    <strong id="deleteRepaymentName"></strong>?

                </p>

                <p class="text-danger small">

                    This action cannot be undone.

                </p>

                <form id="deleteForm"
                      method="POST">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= $csrf_token; ?>">

                </form>

            </div>

            <div class="modal-footer">

                <button class="btn btn-secondary"
                        data-bs-dismiss="modal">

                    Cancel

                </button>

                <button class="btn btn-danger"
                        onclick="confirmDelete()">

                    Delete

                </button>

            </div>

        </div>

    </div>

</div>

<script>

/*==================================
=            PER PAGE              =
==================================*/

document.getElementById('perPageSelect')?.addEventListener('change', function () {

    const url = new URL(window.location.href);

    url.searchParams.set('per_page', this.value);

    window.location.href = url.toString();

});


/*==================================
=         DELETE REPAYMENT         =
==================================*/

let deleteId = null;

function deleteRepayment(id, memberName)
{

    deleteId = id;

    document.getElementById('deleteRepaymentName').textContent = memberName;

    document.getElementById('deleteForm').action =
        '<?= BASE_URL ?>/repayments/' + id + '/delete';

    new bootstrap.Modal(
        document.getElementById('deleteModal')
    ).show();

}

function confirmDelete()
{

    document.getElementById('deleteForm').submit();

}


/*==================================
=        AUTO FILTER CHANGE        =
==================================*/

document.querySelectorAll(

    '#searchForm select,' +
    '#searchForm input[type="date"]'

).forEach(function(el){

    el.addEventListener('change', function(){

        document.getElementById('searchForm').submit();

    });

});


/*==================================
=        SEARCH ON ENTER           =
==================================*/

document.querySelector(

    'input[name="search"]'

)?.addEventListener('keypress', function(e){

    if(e.key === 'Enter')
    {

        e.preventDefault();

        document.getElementById('searchForm').submit();

    }

});


/*==================================
=          TABLE HOVER             =
==================================*/

document.querySelectorAll('tbody tr').forEach(function(row){

    row.addEventListener('mouseenter', function(){

        this.classList.add('table-active');

    });

    row.addEventListener('mouseleave', function(){

        this.classList.remove('table-active');

    });

});


/*==================================
=         TOOLTIP SUPPORT          =
==================================*/

var tooltipTriggerList = [].slice.call(

    document.querySelectorAll('[title]')

);

tooltipTriggerList.map(function (tooltipTriggerEl) {

    return new bootstrap.Tooltip(tooltipTriggerEl);

});


/*==================================
=       REFRESH STATISTICS         =
==================================*/

function refreshStatistics()
{

    fetch("<?= BASE_URL ?>/repayments/statistics")

    .then(response => response.json())

    .then(data => {

        console.log(data);

    })

    .catch(error => {

        console.log(error);

    });

}

// Refresh every 5 minutes
setInterval(refreshStatistics,300000);

</script>
<style>

/*===============================
=            Avatar             =
===============================*/

.avatar-sm{
    width:40px;
    height:40px;
    border-radius:50%;
    background:linear-gradient(135deg,#667eea,#764ba2);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:600;
    flex-shrink:0;
    font-size:15px;
}

/*===============================
=        Statistics Cards       =
===============================*/

.stat-card{
    transition:.3s;
    border:none;
}

.stat-card:hover{
    transform:translateY(-3px);
    box-shadow:0 5px 20px rgba(0,0,0,.08);
}

/*===============================
=        Statistics Icons       =
===============================*/

.stat-icon{
    width:45px;
    height:45px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
}

.stat-icon.primary{
    background:rgba(102,126,234,.12);
    color:#667eea;
}

.stat-icon.success{
    background:rgba(72,187,120,.12);
    color:#48bb78;
}

.stat-icon.info{
    background:rgba(66,153,225,.12);
    color:#4299e1;
}

.stat-icon.warning{
    background:rgba(237,137,54,.12);
    color:#ed8936;
}

/*===============================
=         Dashboard Card        =
===============================*/

.dashboard-card{
    border:none;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 3px 15px rgba(0,0,0,.05);
}

/*===============================
=            Table              =
===============================*/

.table{
    margin-bottom:0;
}

.table th{
    white-space:nowrap;
    font-size:14px;
    font-weight:600;
    vertical-align:middle;
}

.table td{
    vertical-align:middle;
    font-size:14px;
}

.table tbody tr{
    transition:.2s;
}

.table tbody tr:hover{
    background:#f8f9fa;
}

/*===============================
=            Badges             =
===============================*/

.badge{
    padding:.45rem .7rem;
    font-weight:500;
    font-size:12px;
}

/*===============================
=       Button Group            =
===============================*/

.btn-group .btn{
    border-radius:6px !important;
}

.btn-group .btn:not(:last-child){
    margin-right:4px;
}

/*===============================
=      Search Controls          =
===============================*/

.form-control,
.form-select{

    border-radius:8px;

}

.input-group-text{

    background:#fff;

}

/*===============================
=         Card Footer           =
===============================*/

.card-footer{

    border-top:1px solid #eee;

}

/*===============================
=      Responsive Layout        =
===============================*/

@media(max-width:992px){

.table{

font-size:13px;

}

.table td,
.table th{

white-space:nowrap;

}

}

@media(max-width:768px){

.stat-card{

margin-bottom:10px;

}

.btn-group{

display:flex;

}

.btn-group .btn{

padding:.25rem .5rem;

}

}

@media(max-width:576px){

.avatar-sm{

width:35px;
height:35px;

}

.table{

font-size:12px;

}

}

/*===============================
=         Dark Theme            =
===============================*/

[data-bs-theme="dark"] .dashboard-card{

background:#2d2d3f;

}

[data-bs-theme="dark"] .stat-card{

background:#34344d !important;

}

[data-bs-theme="dark"] .table{

color:#e2e8f0;

}

[data-bs-theme="dark"] .table-light{

background:#3d3d5f;

}

[data-bs-theme="dark"] .table-light th{

background:#3d3d5f;

color:#fff;

}

[data-bs-theme="dark"] tbody tr:hover{

background:#454565;

}

[data-bs-theme="dark"] .card-footer{

border-top:1px solid #555;

}

[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select{

background:#3d3d5f;
border-color:#555;
color:#fff;

}

[data-bs-theme="dark"] .input-group-text{

background:#3d3d5f;
border-color:#555;
color:#fff;

}

[data-bs-theme="dark"] .modal-content{

background:#2d2d3f;
color:#fff;

}

[data-bs-theme="dark"] .stat-icon.primary{

background:rgba(102,126,234,.2);

}

[data-bs-theme="dark"] .stat-icon.success{

background:rgba(72,187,120,.2);

}

[data-bs-theme="dark"] .stat-icon.info{

background:rgba(66,153,225,.2);

}

[data-bs-theme="dark"] .stat-icon.warning{

background:rgba(237,137,54,.2);

}

/*===============================
=         Animations            =
===============================*/

.dashboard-card{

animation:fadeIn .4s ease;

}

@keyframes fadeIn{

from{

opacity:0;
transform:translateY(10px);

}

to{

opacity:1;
transform:translateY(0);

}

}

/*===============================
=       Currency Amounts        =
===============================*/

.text-success{

font-weight:600;

}

.text-danger{

font-weight:600;

}

</style>