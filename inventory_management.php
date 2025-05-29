<?php
/**
 * صفحة إدارة المستودعات الرئيسية - نظام FlexAuto
 * تعرض جميع المستودعات (warehouse_1 إلى warehouse_30) مع إمكانية الوصول إليها
 * الملف: inventory_management.php
 */

// بدء الجلسة والتحقق من الحماية والصلاحيات
session_start();
require_once __DIR__ . '/includes/db.php';

// ===============================
// فحص الحماية والصلاحيات
// ===============================

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// تعيين user_role افتراضياً إذا لم يكن موجود
if (!isset($_SESSION['user_role'])) {
    $_SESSION['user_role'] = 'user';
}

// فحص الصلاحيات - يجب أن يكون owner أو staff مع inventory_access
$user_role = $_SESSION['user_role'];
$inventory_access = $_SESSION['inventory_access'] ?? 0;

if (!($user_role === 'owner' || ($user_role === 'staff' && $inventory_access == 1) || $user_role === 'admin')) {
    // إعادة توجيه للصفحة الرئيسية مع رسالة خطأ
    $_SESSION['error_message'] = "⛔ ليس لديك صلاحية للوصول لإدارة المستودعات";
    header("Location: index.php");
    exit;
}

// إعدادات الصفحة
$page_title = 'إدارة المستودعات';
$display_title = 'إدارة المستودع - Inventory Management';

// معلومات المستخدم
$username = $_SESSION['username'] ?? 'مستخدم';
$user_email = $_SESSION['email'] ?? '';

// ===============================
// دوال مساعدة للصفحة
// ===============================

/**
 * فحص وجود ملف المستودع
 * @param int $warehouse_number رقم المستودع
 * @return bool
 */
function warehouseFileExists($warehouse_number) {
    return file_exists(__DIR__ . "/warehouse_{$warehouse_number}.php");
}

/**
 * الحصول على إحصائيات المستودع من قاعدة البيانات
 * @param PDO $pdo اتصال قاعدة البيانات
 * @param int $warehouse_id معرف المستودع
 * @return array
 */
function getWarehouseStats($pdo, $warehouse_id) {
    try {
        // إنشاء جدول المستودعات إذا لم يكن موجوداً
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS warehouse_inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                warehouse_id INT NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                item_code VARCHAR(100),
                quantity INT DEFAULT 0,
                unit_price DECIMAL(10,2) DEFAULT 0.00,
                category VARCHAR(100),
                description TEXT,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_warehouse_id (warehouse_id)
            )
        ");

        // جلب إحصائيات المستودع
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_items,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(quantity * unit_price), 0) as total_value,
                MAX(last_updated) as last_update
            FROM warehouse_inventory 
            WHERE warehouse_id = ?
        ");
        $stmt->execute([$warehouse_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_items' => $stats['total_items'] ?? 0,
            'total_quantity' => $stats['total_quantity'] ?? 0,
            'total_value' => $stats['total_value'] ?? 0,
            'last_update' => $stats['last_update'] ?? null,
            'exists' => warehouseFileExists($warehouse_id)
        ];
        
    } catch (PDOException $e) {
        error_log("خطأ في جلب إحصائيات المستودع {$warehouse_id}: " . $e->getMessage());
        return [
            'total_items' => 0,
            'total_quantity' => 0,
            'total_value' => 0,
            'last_update' => null,
            'exists' => warehouseFileExists($warehouse_id)
        ];
    }
}

// ===============================
// جلب بيانات المستودعات
// ===============================

$warehouses_data = [];
$total_warehouses = 30; // إجمالي عدد المستودعات المدعومة

for ($i = 1; $i <= $total_warehouses; $i++) {
    $warehouse_stats = getWarehouseStats($pdo, $i);
    $warehouses_data[] = [
        'id' => $i,
        'name' => "المستودع رقم {$i}",
        'name_en' => "Warehouse {$i}",
        'file_exists' => $warehouse_stats['exists'],
        'total_items' => $warehouse_stats['total_items'],
        'total_quantity' => $warehouse_stats['total_quantity'],
        'total_value' => $warehouse_stats['total_value'],
        'last_update' => $warehouse_stats['last_update']
    ];
}

// حساب الإحصائيات العامة
$active_warehouses = count(array_filter($warehouses_data, function($w) { return $w['file_exists']; }));
$total_items_all = array_sum(array_column($warehouses_data, 'total_items'));
$total_value_all = array_sum(array_column($warehouses_data, 'total_value'));

// ===============================
// CSS مخصص للصفحة
// ===============================
$page_css = <<<CSS
/* حاوي الصفحة الرئيسي */
.inventory-container {
    background: rgba(0, 0, 0, 0.75);
    padding: 30px;
    border-radius: 15px;
    margin: 20px auto;
    max-width: 1400px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* رأس الصفحة */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid rgba(0, 255, 255, 0.3);
    flex-wrap: wrap;
    gap: 20px;
}

.page-title {
    color: #00ffff;
    font-size: 28px;
    margin: 0;
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-subtitle {
    color: #a8d8ff;
    font-size: 16px;
    margin: 5px 0 0 0;
}

/* الإحصائيات العامة */
.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(0, 255, 255, 0.3);
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #00ffff;
    margin-bottom: 5px;
}

.stat-label {
    color: #a8d8ff;
    font-size: 14px;
}

/* أزرار التحكم */
.action-buttons {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.btn-primary {
    background: linear-gradient(135deg, #1e90ff, #00bfff);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #63b3ed, #4da6d9);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(30, 144, 255, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, #28a745, #218838);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #34ce57, #28a745);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #333;
}

.btn-disabled {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: #aaa;
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-disabled:hover {
    transform: none;
    box-shadow: none;
}

/* شبكة المستودعات */
.warehouses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.warehouse-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.warehouse-card:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(0, 255, 255, 0.3);
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
}

.warehouse-card.active {
    border-color: rgba(40, 167, 69, 0.5);
    background: rgba(40, 167, 69, 0.1);
}

.warehouse-card.inactive {
    border-color: rgba(108, 117, 125, 0.3);
    background: rgba(108, 117, 125, 0.05);
}

.warehouse-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.warehouse-name {
    font-size: 18px;
    font-weight: bold;
    color: #00ffff;
    margin: 0;
}

.warehouse-name-en {
    font-size: 14px;
    color: #a8d8ff;
    margin: 2px 0 0 0;
}

.warehouse-status {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.status-inactive {
    background: rgba(108, 117, 125, 0.2);
    color: #6c757d;
}

.warehouse-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
}

.stat-value {
    font-size: 16px;
    font-weight: bold;
    color: #ffffff;
    margin-bottom: 3px;
}

.stat-label-small {
    font-size: 12px;
    color: #a8d8ff;
}

.warehouse-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

/* رسائل التنبيه */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-info {
    background: rgba(23, 162, 184, 0.15);
    color: #17a2b8;
    border-color: #17a2b8;
}

.alert-warning {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
    border-color: #ffc107;
}

.alert-success {
    background: rgba(40, 167, 69, 0.15);
    color: #28a745;
    border-color: #28a745;
}

/* حالة فارغة */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

/* شريط البحث والفلترة */
.search-filter-bar {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.search-input {
    flex: 1;
    min-width: 200px;
    padding: 10px 15px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.3);
    color: #ffffff;
    font-size: 14px;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #00ffff;
    background: rgba(0, 0, 0, 0.5);
    box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.filter-select {
    padding: 8px 12px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.3);
    color: #ffffff;
    font-size: 14px;
    min-width: 120px;
}

/* التصميم المتجاوب */
@media (max-width: 768px) {
    .inventory-container {
        padding: 20px;
        margin: 10px;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .page-title {
        font-size: 24px;
    }
    
    .stats-overview {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .warehouses-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .warehouse-stats {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .warehouse-actions {
        flex-direction: column;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .search-filter-bar {
        flex-direction: column;
        gap: 10px;
    }
    
    .search-input {
        min-width: 100%;
    }
}

/* تأثيرات إضافية */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* تحسينات بصرية */
.warehouse-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #00ffff, transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.warehouse-card:hover::before {
    opacity: 1;
}

.last-update {
    font-size: 12px;
    color: #64748b;
    margin-top: 10px;
    text-align: center;
    font-style: italic;
}
CSS;

// ===============================
// محتوى الصفحة
// ===============================
ob_start();
?>

<div class="inventory-container fade-in">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <div>
            <h2 class="page-title">
                🏪 إدارة المستودع - Inventory Management
            </h2>
            <p class="page-subtitle">
                إدارة شاملة لجميع المستودعات - مرحباً <?php echo htmlspecialchars($username); ?>
            </p>
        </div>
        
        <?php if ($user_role === 'owner' || $user_role === 'admin'): ?>
        <div class="action-buttons">
            <button class="btn btn-success" id="addWarehouseBtn" title="قريباً - إضافة مستودع جديد">
                ➕ إضافة مستودع جديد
            </button>
            <button class="btn btn-warning" onclick="refreshPage()" title="تحديث البيانات">
                🔄 تحديث البيانات
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- الإحصائيات العامة -->
    <div class="stats-overview">
        <div class="stat-card">
            <span class="stat-icon">🏪</span>
            <div class="stat-number"><?php echo $active_warehouses; ?></div>
            <div class="stat-label">مستودعات نشطة</div>
        </div>
        
        <div class="stat-card">
            <span class="stat-icon">📦</span>
            <div class="stat-number"><?php echo number_format($total_items_all); ?></div>
            <div class="stat-label">إجمالي الأصناف</div>
        </div>
        
        <div class="stat-card">
            <span class="stat-icon">💰</span>
            <div class="stat-number"><?php echo number_format($total_value_all, 2); ?> ر.س</div>
            <div class="stat-label">إجمالي القيمة</div>
        </div>
        
        <div class="stat-card">
            <span class="stat-icon">👤</span>
            <div class="stat-number"><?php echo htmlspecialchars($user_role); ?></div>
            <div class="stat-label">صلاحيتك</div>
        </div>
    </div>
    
    <!-- رسائل تنبيهية -->
    <?php if ($active_warehouses === 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>تنبيه:</strong> لا توجد مستودعات نشطة حالياً. يمكنك إنشاء ملفات المستودعات المطلوبة أو التواصل مع المطور.
            </div>
        </div>
    <?php elseif ($active_warehouses < 5): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>معلومة:</strong> لديك <?php echo $active_warehouses; ?> مستودعات نشطة من أصل <?php echo $total_warehouses; ?> مستودع متاح.
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>ممتاز:</strong> النظام يعمل بكفاءة مع <?php echo $active_warehouses; ?> مستودع نشط.
            </div>
        </div>
    <?php endif; ?>
    
    <!-- شريط البحث والفلترة -->
    <div class="search-filter-bar">
        <input type="text" 
               class="search-input" 
               id="searchInput" 
               placeholder="🔍 البحث في المستودعات... (اسم المستودع أو الرقم)">
        
        <select class="filter-select" id="statusFilter">
            <option value="">جميع المستودعات</option>
            <option value="active">المستودعات النشطة فقط</option>
            <option value="inactive">المستودعات غير النشطة</option>
        </select>
        
        <button class="btn btn-primary btn-sm" onclick="clearFilters()">
            🗑️ مسح الفلاتر
        </button>
    </div>
    
    <!-- شبكة المستودعات -->
    <div class="warehouses-grid" id="warehousesGrid">
        <?php foreach ($warehouses_data as $warehouse): ?>
        <div class="warehouse-card <?php echo $warehouse['file_exists'] ? 'active' : 'inactive'; ?>" 
             data-warehouse-id="<?php echo $warehouse['id']; ?>"
             data-status="<?php echo $warehouse['file_exists'] ? 'active' : 'inactive'; ?>"
             data-name="<?php echo strtolower($warehouse['name'] . ' ' . $warehouse['name_en']); ?>">
            
            <div class="warehouse-header">
                <div>
                    <h3 class="warehouse-name"><?php echo htmlspecialchars($warehouse['name']); ?></h3>
                    <p class="warehouse-name-en"><?php echo htmlspecialchars($warehouse['name_en']); ?></p>
                </div>
                <span class="warehouse-status <?php echo $warehouse['file_exists'] ? 'status-active' : 'status-inactive'; ?>">
                    <?php echo $warehouse['file_exists'] ? '✅ نشط' : '⏸️ غير نشط'; ?>
                </span>
            </div>
            
            <div class="warehouse-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($warehouse['total_items']); ?></div>
                    <div class="stat-label-small">عدد الأصناف</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($warehouse['total_quantity']); ?></div>
                    <div class="stat-label-small">إجمالي الكمية</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($warehouse['total_value'], 0); ?> ر.س</div>
                    <div class="stat-label-small">القيمة الإجمالية</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value">
                        <?php if ($warehouse['last_update']): ?>
                            <?php echo date('Y-m-d', strtotime($warehouse['last_update'])); ?>
                        <?php else: ?>
                            لا يوجد
                        <?php endif; ?>
                    </div>
                    <div class="stat-label-small">آخر تحديث</div>
                </div>
            </div>
            
            <div class="warehouse-actions">
                <?php if ($warehouse['file_exists']): ?>
                    <button class="btn btn-primary btn-sm" 
                            onclick="openWarehouse(<?php echo $warehouse['id']; ?>)">
                        🚪 دخول المستودع
                    </button>
                    <button class="btn btn-success btn-sm" 
                            onclick="viewStats(<?php echo $warehouse['id']; ?>)">
                        📊 الإحصائيات
                    </button>
                <?php else: ?>
                    <button class="btn btn-disabled btn-sm" disabled>
                        🔒 غير متاح
                    </button>
                    <?php if ($user_role === 'owner' || $user_role === 'admin'): ?>
                    <button class="btn btn-warning btn-sm" 
                            onclick="createWarehouse(<?php echo $warehouse['id']; ?>)" 
                            title="إنشاء ملف المستودع">
                        ⚙️ إنشاء
                    </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($warehouse['last_update']): ?>
            <div class="last-update">
                آخر نشاط: <?php echo date('Y-m-d H:i', strtotime($warehouse['last_update'])); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- حالة عدم وجود نتائج -->
    <div class="empty-state" id="noResults" style="display: none;">
        <i class="fas fa-search"></i>
        <h3>لا توجد نتائج</h3>
        <p>لم يتم العثور على مستودعات تطابق البحث أو الفلاتر المحددة</p>
    </div>
    
    <!-- زر العودة -->
    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="btn btn-primary">
            🏠 العودة للصفحة الرئيسية
        </a>
    </div>
    
    <!-- ملاحظات مفيدة -->
    <div style="margin-top: 30px; padding: 20px; background: rgba(255, 255, 255, 0.05); border-radius: 10px; border-left: 3px solid #00ffff;">
        <h4 style="color: #00ffff; margin-bottom: 10px;">💡 ملاحظات مهمة:</h4>
        <ul style="color: #a8d8ff; line-height: 1.6;">
            <li><strong>المستودعات النشطة:</strong> هي التي لها ملفات فعلية في النظام</li>
            <li><strong>إنشاء مستودع:</strong> يتطلب صلاحيات مدير أو مالك</li>
            <li><strong>البحث:</strong> يمكنك البحث بالاسم أو الرقم</li>
            <li><strong>التحديث:</strong> البيانات تتحدث تلقائياً كل دقيقة</li>
        </ul>
    </div>
</div>

<script>
// ===============================
// JavaScript للتفاعل مع الصفحة
// ===============================

// متغيرات عامة
let allWarehouseCards = [];

// تهيئة الصفحة
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
    setupSearch();
    setupAutoRefresh();
});

// تهيئة عناصر الصفحة
function initializePage() {
    allWarehouseCards = Array.from(document.querySelectorAll('.warehouse-card'));
    
    // إضافة تأثيرات الحركة للبطاقات
    allWarehouseCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = `all 0.6s ease ${index * 0.1}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
    });
}

// إعداد وظائف البحث
function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(performSearch, 300));
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', performSearch);
    }
}

// تنفيذ البحث والفلترة
function performSearch() {
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    
    let visibleCount = 0;
    
    allWarehouseCards.forEach(card => {
        const name = card.dataset.name;
        const status = card.dataset.status;
        const warehouseId = card.dataset.warehouseId;
        
        // فحص البحث النصي
        const matchesSearch = !searchTerm || 
            name.includes(searchTerm) || 
            warehouseId.includes(searchTerm);
        
        // فحص فلتر الحالة
        const matchesStatus = !statusFilter || status === statusFilter;
        
        // إظهار أو إخفاء البطاقة
        if (matchesSearch && matchesStatus) {
            card.style.display = 'block';
            card.style.animation = `fadeIn 0.3s ease ${visibleCount * 0.05}s both`;
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // إظهار رسالة عدم وجود نتائج
    const noResults = document.getElementById('noResults');
    const grid = document.getElementById('warehousesGrid');
    
    if (noResults && grid) {
        if (visibleCount === 0) {
            noResults.style.display = 'block';
            grid.style.display = 'none';
        } else {
            noResults.style.display = 'none';
            grid.style.display = 'grid';
        }
    }
}

// مسح الفلاتر
function clearFilters() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) searchInput.value = '';
    if (statusFilter) statusFilter.value = '';
    
    performSearch();
}

// وظيفة debounce لتحسين الأداء
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// فتح المستودع
function openWarehouse(warehouseId) {
    // إظهار حالة التحميل
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ جاري التحميل...';
    button.disabled = true;
    
    // التوجه إلى صفحة المستودع
    setTimeout(() => {
        window.location.href = `warehouse_${warehouseId}.php`;
    }, 500);
}

// عرض إحصائيات المستودع
function viewStats(warehouseId) {
    alert(`📊 إحصائيات المستودع رقم ${warehouseId}\n\nهذه الميزة قيد التطوير...`);
}

// إنشاء مستودع جديد (للمديرين فقط)
function createWarehouse(warehouseId) {
    if (confirm(`هل تريد إنشاء المستودع رقم ${warehouseId}؟\n\nسيتم إنشاء ملف warehouse_${warehouseId}.php`)) {
        alert('ميزة إنشاء المستودعات تحت التطوير...\nيرجى التواصل مع المطور.');
    }
}

// تحديث الصفحة
function refreshPage() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ جاري التحديث...';
    button.disabled = true;
    
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// إعداد التحديث التلقائي
function setupAutoRefresh() {
    // تحديث تلقائي كل 5 دقائق
    setInterval(() => {
        fetch(window.location.href, { 
            method: 'HEAD' 
        }).then(() => {
            console.log('تم فحص التحديثات...');
        }).catch(error => {
            console.error('خطأ في فحص التحديثات:', error);
        });
    }, 300000); // 5 دقائق
}

// زر إضافة مستودع جديد
document.getElementById('addWarehouseBtn')?.addEventListener('click', function() {
    alert('🚧 ميزة إضافة مستودع جديد قيد التطوير...\n\nقريباً ستتمكن من:\n• إنشاء مستودعات جديدة\n• تخصيص إعدادات المستودع\n• إدارة الصلاحيات');
});

// اختصارات لوحة المفاتيح
document.addEventListener('keydown', function(event) {
    // منع التنفيذ أثناء الكتابة في حقول الإدخال
    if (event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT') {
        return;
    }
    
    // العودة للصفحة الرئيسية بالضغط على Escape
    if (event.key === 'Escape') {
        window.location.href = 'index.php';
    }
    
    // التركيز على البحث بالضغط على Ctrl+F
    if (event.ctrlKey && event.key === 'f') {
        event.preventDefault();
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    // تحديث الصفحة بالضغط على F5 أو Ctrl+R
    if (event.key === 'F5' || (event.ctrlKey && event.key === 'r')) {
        event.preventDefault();
        refreshPage();
    }
    
    // مسح الفلاتر بالضغط على Ctrl+D
    if (event.ctrlKey && event.key === 'd') {
        event.preventDefault();
        clearFilters();
    }
});

// تحسين الأداء - إدارة الذاكرة
window.addEventListener('beforeunload', function() {
    // تنظيف المتغيرات
    allWarehouseCards = null;
});

// رسائل ترحيبية للمستخدم
window.addEventListener('load', function() {
    // عرض رسالة ترحيب بسيطة
    const activeCount = <?php echo $active_warehouses; ?>;
    if (activeCount > 0) {
        console.log(`🎉 مرحباً! لديك ${activeCount} مستودع نشط`);
    }
});
</script>

<?php
$page_content = ob_get_clean();

// تضمين القالب الرئيسي
include __DIR__ . '/includes/layout.php';
?>