<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من صلاحية الأدمن
if (!isset($_SESSION['username']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

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
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$total = count($tickets);
$reviewed = count(array_filter($tickets, fn($t) => isset($t['is_seen']) && $t['is_seen']));
$pending = $total - $reviewed;

// إعداد معلومات الصفحة
$page_title = "إدارة التذاكر";
$hide_title = true; // إخفاء العنوان الافتراضي في القالب

// تنسيقات CSS المخصصة للصفحة
$page_css = <<<CSS
.dashboard-header {
    background: linear-gradient(135deg, #004080, #001030);
    color: white;
    padding: 40px 20px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('/assets/img/circuit-pattern.svg');
    background-size: cover;
    opacity: 0.05;
    z-index: 0;
}

.header-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.page-title {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-desc {
    margin: 5px 0 0 0;
    color: rgba(255, 255, 255, 0.8);
}

.export-btn {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-radius: 8px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.export-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}

.export-btn i {
    font-size: 1.1rem;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(15, 23, 42, 0.5);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(66, 135, 245, 0.1);
    backdrop-filter: blur(5px);
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    opacity: 0.8;
}

.stat-card.primary::before {
    background: linear-gradient(to bottom, #00d9ff, #0070cc);
}

.stat-card.success::before {
    background: linear-gradient(to bottom, #00ff88, #00cc70);
}

.stat-card.warning::before {
    background: linear-gradient(to bottom, #ffbb00, #ff8800);
}

.stat-icon {
    width: 50px;
    height: 50px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.5rem;
    border-radius: 12px;
}

.stat-icon.primary {
    background: rgba(0, 217, 255, 0.15);
    color: #00d9ff;
}

.stat-icon.success {
    background: rgba(0, 255, 136, 0.15);
    color: #00ff88;
}

.stat-icon.warning {
    background: rgba(255, 187, 0, 0.15);
    color: #ffbb00;
}

.stat-content h3 {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 0;
}

.stat-content p {
    margin: 5px 0 0 0;
    color: #94a3b8;
    font-size: 0.9rem;
}

.filters-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.search-input {
    padding: 12px 15px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(66, 135, 245, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
    transition: all 0.3s ease;
    width: 100%;
    padding-right: 40px;
    position: relative;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2300d9ff' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
}

.search-input:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 217, 255, 0.3);
    border-color: #00d9ff;
}

.filter-select {
    padding: 12px 15px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(66, 135, 245, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
    transition: all 0.3s ease;
    width: 100%;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2300d9ff' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: left 15px center;
    padding-left: 40px;
}

.filter-select:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 217, 255, 0.3);
    border-color: #00d9ff;
}

.table-container {
    background: rgba(15, 23, 42, 0.5);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(66, 135, 245, 0.1);
    backdrop-filter: blur(5px);
}

.tickets-table {
    width: 100%;
    border-collapse: collapse;
}

.tickets-table th {
    background: rgba(15, 23, 42, 0.8);
    color: #cbd5e1;
    font-weight: 600;
    padding: 15px;
    text-align: right;
    font-size: 0.9rem;
    border-bottom: 1px solid rgba(66, 135, 245, 0.2);
}

.tickets-table td {
    padding: 15px;
    border-bottom: 1px solid rgba(30, 41, 59, 0.5);
    color: #e2e8f0;
    font-size: 0.95rem;
}

.tickets-table tr:last-child td {
    border-bottom: none;
}

.tickets-table tr {
    transition: all 0.3s ease;
}

.tickets-table tr:hover {
    background: rgba(30, 41, 59, 0.5);
}

.tickets-table tr.reviewed {
    background: rgba(0, 255, 136, 0.05);
}

.tickets-table tr.pending {
    background: rgba(255, 187, 0, 0.05);
}

.tickets-table tr.reviewed:hover {
    background: rgba(0, 255, 136, 0.1);
}

.tickets-table tr.pending:hover {
    background: rgba(255, 187, 0, 0.1);
}

.status-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-badge.success {
    background: rgba(0, 255, 136, 0.15);
    color: #00ff88;
}

.status-badge.warning {
    background: rgba(255, 187, 0, 0.15);
    color: #ffbb00;
}

.service-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    background: rgba(0, 217, 255, 0.15);
    color: #00d9ff;
}

.action-btn {
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    margin-left: 5px;
}

.action-btn.review {
    background: linear-gradient(135deg, #00ff88, #00cc70);
    color: #fff;
}

.action-btn.review:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 10px rgba(0, 255, 136, 0.3);
}

.action-btn.view {
    background: linear-gradient(135deg, #00d9ff, #0070cc);
    color: #fff;
}

.action-btn.view:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 10px rgba(0, 217, 255, 0.3);
}

.action-btn.disabled {
    background: #64748b;
    color: #e2e8f0;
    cursor: not-allowed;
}

.empty-state {
    padding: 40px;
    text-align: center;
}

.empty-state-icon {
    font-size: 3rem;
    color: #64748b;
    margin-bottom: 20px;
}

.empty-state-title {
    font-size: 1.5rem;
    color: #e2e8f0;
    margin-bottom: 10px;
}

.empty-state-text {
    color: #94a3b8;
}

.chassis-number {
    font-family: 'Courier New', monospace;
    letter-spacing: 1px;
}

.placeholder-shimmer {
    background: linear-gradient(90deg, rgba(30, 41, 59, 0.5) 0%, rgba(30, 41, 59, 0.7) 50%, rgba(30, 41, 59, 0.5) 100%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 4px;
    height: 15px;
    width: 100%;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

@media (max-width: 992px) {
    .filters-container {
        grid-template-columns: 1fr;
    }
    
    .tickets-table {
        display: block;
        overflow-x: auto;
    }
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }
}
CSS;

// JavaScript المخصص للصفحة
$page_js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('ticketSearch');
    const statusFilter = document.getElementById('statusFilter');
    
    if(searchInput && statusFilter) {
        // تصفية التذاكر عند البحث
        searchInput.addEventListener('input', filterTickets);
        
        // تصفية التذاكر عند تغيير الحالة
        statusFilter.addEventListener('change', filterTickets);
        
        function filterTickets() {
            const keyword = searchInput.value.toLowerCase();
            const status = statusFilter.value;
            const rows = document.querySelectorAll('#ticketsTable tbody tr');
            
            rows.forEach(row => {
                const textContent = row.textContent.toLowerCase();
                const isReviewed = row.classList.contains('reviewed');
                let visible = textContent.includes(keyword);
                
                if (status === 'reviewed' && !isReviewed) visible = false;
                if (status === 'pending' && isReviewed) visible = false;
                
                row.style.display = visible ? '' : 'none';
            });
            
            // عرض رسالة إذا لم يتم العثور على نتائج
            const visibleRows = document.querySelectorAll('#ticketsTable tbody tr[style=""]').length;
            const emptyState = document.getElementById('emptyState');
            
            if(emptyState) {
                if(visibleRows === 0) {
                    emptyState.style.display = 'block';
                } else {
                    emptyState.style.display = 'none';
                }
            }
        }
    }
});
JS;

// محتوى الصفحة
ob_start();
?>
<div class="dashboard-header">
    <div class="header-content">
        <div class="title-section">
            <h1 class="page-title"><i class="fas fa-clipboard-list"></i> إدارة التذاكر</h1>
            <p class="page-desc">عرض ومتابعة جميع الطلبات الواردة من العملاء</p>
        </div>
        <a href="export_tickets.php" class="export-btn">
            <i class="fas fa-file-export"></i> تصدير البيانات
        </a>
    </div>
</div>

<div class="container">
    <div class="stats-container">
        <div class="stat-card primary">
            <div class="stat-icon primary">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= $total ?></h3>
                <p>إجمالي التذاكر</p>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= $reviewed ?></h3>
                <p>تمت المراجعة</p>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?= $pending ?></h3>
                <p>قيد الانتظار</p>
            </div>
        </div>
    </div>
    
    <div class="filters-container">
        <input type="text" id="ticketSearch" class="search-input" placeholder="البحث في التذاكر... (رقم، عميل، سيارة، إلخ)">
        <select id="statusFilter" class="filter-select">
            <option value="all">جميع التذاكر</option>
            <option value="reviewed">تمت المراجعة</option>
            <option value="pending">قيد الانتظار</option>
        </select>
    </div>
    
    <div class="table-container">
        <table id="ticketsTable" class="tickets-table">
            <thead>
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
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-clipboard-check"></i></div>
                                <h3 class="empty-state-title">لا توجد تذاكر حالياً</h3>
                                <p class="empty-state-text">ستظهر هنا جميع طلبات العملاء عند استلامها</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $row): ?>
                        <?php
                        $is_seen = isset($row['is_seen']) && $row['is_seen'];
                        $row_class = $is_seen ? 'reviewed' : 'pending';
                        $status_text = $is_seen ? 'تمت المراجعة' : 'قيد الانتظار';
                        $status_class = $is_seen ? 'success' : 'warning';
                        $status_icon = $is_seen ? 'check-circle' : 'clock';
                        
                        // تحضير أزرار الإجراءات
                        $action_btn = $is_seen
                            ? '<button class="action-btn disabled"><i class="fas fa-check"></i> تم</button>'
                            : '<a href="?mark_seen=' . $row['id'] . '" class="action-btn review"><i class="fas fa-check"></i> مراجعة</a>';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td>FLEX-<?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['username'] ?? 'زائر') ?></td>
                            <td><?= htmlspecialchars($row['phone'] ?? $row['phone_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['car_type'] ?? '-') ?></td>
                            <td><span class="chassis-number"><?= htmlspecialchars($row['chassis'] ?? $row['vin'] ?? '-') ?></span></td>
                            <td><span class="service-badge"><?= htmlspecialchars($row['service_type'] ?? $row['request_type'] ?? 'خدمة') ?></span></td>
                            <td><span class="status-badge <?= $status_class ?>"><i class="fas fa-<?= $status_icon ?>"></i> <?= $status_text ?></span></td>
                            <td>
                                <?= $action_btn ?>
                                <a href="ticket_details.php?id=<?= $row['id'] ?>" class="action-btn view"><i class="fas fa-eye"></i> عرض</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div id="emptyState" class="empty-state" style="display:none; margin-top:20px;">
        <div class="empty-state-icon"><i class="fas fa-search"></i></div>
        <h3 class="empty-state-title">لم يتم العثور على نتائج</h3>
        <p class="empty-state-text">لا توجد تذاكر تطابق معايير البحث الحالية</p>
    </div>
</div>
<?php
$page_content = ob_get_clean();

// تضمين ملف القالب
require_once __DIR__ . '/includes/layout.php';
?>