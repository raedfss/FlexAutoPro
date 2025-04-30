<?php
// tickets.php - إدارة التذاكر للمشرف فقط

require_once 'includes/header.php'; // يتضمن session_start

// السماح فقط للمشرف بالدخول
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';

$page_title = "إدارة التذاكر";

$page_css = <<<CSS
.ticket-row.reviewed { background-color: #e7f9f1; }
.ticket-row.pending  { background-color: #fff3cd; }
CSS;

// تحديث حالة التذكرة
if (isset($_GET['mark_seen'])) {
    $id = intval($_GET['mark_seen']);
    $stmt = $pdo->prepare("UPDATE tickets SET is_seen = 1 WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: tickets.php");
    exit;
}

// جلب التذاكر
$stmt = $pdo->query("SELECT * FROM tickets ORDER BY created_at DESC");
$tickets = $stmt->fetchAll();

// إحصائيات
$total = count($tickets);
$reviewed = count(array_filter($tickets, fn($t) => $t['is_seen']));
$pending = $total - $reviewed;

$page_content = <<<HTML
<div class="container py-5">

    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2 fw-bold text-primary">
                <i class="fas fa-clipboard-list me-2"></i> إدارة التذاكر
            </h1>
            <p class="text-muted">عرض ومتابعة جميع الطلبات الواردة من الزبائن</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="export_tickets.php" class="btn btn-secondary mt-3">
                <i class="fas fa-file-export me-1"></i> تصدير البيانات
            </a>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fas fa-ticket-alt fa-2x me-3"></i>
                    <div>
                        <h3 class="fw-bold mb-0">{$total}</h3>
                        <p class="mb-0">إجمالي التذاكر</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <h3 class="fw-bold mb-0">{$reviewed}</h3>
                        <p class="mb-0">تمت المراجعة</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fas fa-clock fa-2x me-3"></i>
                    <div>
                        <h3 class="fw-bold mb-0">{$pending}</h3>
                        <p class="mb-0">قيد الانتظار</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- البحث والتصفية -->
    <div class="row mb-4">
        <div class="col-md-8">
            <input type="text" id="ticketSearch" class="form-control" placeholder="🔎 البحث في التذاكر...">
        </div>
        <div class="col-md-4">
            <select id="statusFilter" class="form-select">
                <option value="all">جميع التذاكر</option>
                <option value="reviewed">تمت المراجعة</option>
                <option value="pending">قيد الانتظار</option>
            </select>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="ticketsTable">
                <thead class="table-light">
                    <tr>
                        <th>رقم</th>
                        <th>العميل</th>
                        <th>الهاتف</th>
                        <th>السيارة</th>
                        <th>الشاسيه</th>
                        <th>الخدمة</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
HTML;

foreach ($tickets as $row) {
    $id = $row['id'];
    $status = $row['is_seen'] ? 'تمت المراجعة' : 'قيد المراجعة';
    $badge = $row['is_seen'] ? 'success' : 'warning text-dark';
    $stateClass = $row['is_seen'] ? 'reviewed' : 'pending';
    $actionBtn = $row['is_seen']
        ? "<button class='btn btn-sm btn-secondary' disabled><i class='fas fa-check'></i> تم</button>"
        : "<a href='?mark_seen={$id}' class='btn btn-sm btn-success'><i class='fas fa-check'></i> مراجعة</a>";

    $page_content .= <<<HTML
<tr class="ticket-row {$stateClass}">
    <td>FLEX-{$id}</td>
    <td>{$row['username']}</td>
    <td>{$row['phone_number']}</td>
    <td>{$row['car_type']}</td>
    <td class="font-monospace">{$row['vin']}</td>
    <td><span class="badge bg-primary px-2">{$row['request_type']}</span></td>
    <td><span class="badge bg-{$badge}">{$status}</span></td>
    <td>
        {$actionBtn}
        <a href="ticket_details.php?id={$id}" class="btn btn-sm btn-info">عرض</a>
    </td>
</tr>
HTML;
}

$page_content .= <<<HTML
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('ticketSearch').addEventListener('input', filterTickets);
document.getElementById('statusFilter').addEventListener('change', filterTickets);

function filterTickets() {
    const keyword = document.getElementById('ticketSearch').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#ticketsTable tbody tr');

    rows.forEach(row => {
        const match = row.textContent.toLowerCase().includes(keyword);
        const isReviewed = row.classList.contains('reviewed');
        let visible = match;

        if (status === 'reviewed' && !isReviewed) visible = false;
        if (status === 'pending' && isReviewed) visible = false;

        row.style.display = visible ? '' : 'none';
    });
}
</script>
HTML;

require_once 'includes/layout.php';
