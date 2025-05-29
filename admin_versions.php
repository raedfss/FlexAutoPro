<?php
/**
 * صفحة عرض الإصدارات للمستخدمين - نظام FlexAuto
 * يعرض آخر التحديثات والإصدارات للمستخدمين العاديين
 * الملف: version.php
 */

// بدء الجلسة والتحقق من تسجيل الدخول
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// إعدادات الصفحة
$page_title = 'آخر التحديثات والإصدارات';
$display_title = 'آخر التحديثات والتعديلات - FlexAuto';

// معلومات المستخدم وضمان وجود user_role
$username = $_SESSION['username'] ?? 'مستخدم';

// التحقق من وجود user_role وتعيين قيمة افتراضية
if (!isset($_SESSION['user_role'])) {
    $_SESSION['user_role'] = 'user';
}
$user_role = $_SESSION['user_role'];

// ===============================
// جلب الإصدارات من قاعدة البيانات
// ===============================
try {
    // إنشاء جدول الإصدارات إذا لم يكن موجوداً (للتأكد)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            version_number VARCHAR(20) NOT NULL,
            release_date DATE NOT NULL,
            version_type ENUM('Major', 'Minor', 'Patch') NOT NULL DEFAULT 'Minor',
            status ENUM('Stable', 'Latest', 'Beta', 'Alpha') NOT NULL DEFAULT 'Stable',
            summary TEXT NOT NULL,
            details TEXT NOT NULL,
            files_changed TEXT,
            git_commands TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // جلب آخر 10 إصدارات
    $stmt = $pdo->query("
        SELECT * FROM versions 
        ORDER BY 
            CAST(SUBSTRING_INDEX(version_number, '.', 1) AS UNSIGNED) DESC,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(version_number, '.', 2), '.', -1) AS UNSIGNED) DESC,
            CAST(SUBSTRING_INDEX(version_number, '.', -1) AS UNSIGNED) DESC
        LIMIT 10
    ");
    $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب آخر إصدار مستقر
    $stmt = $pdo->query("
        SELECT * FROM versions 
        WHERE status IN ('Latest', 'Stable')
        ORDER BY 
            CAST(SUBSTRING_INDEX(version_number, '.', 1) AS UNSIGNED) DESC,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(version_number, '.', 2), '.', -1) AS UNSIGNED) DESC,
            CAST(SUBSTRING_INDEX(version_number, '.', -1) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $latest_version = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("خطأ في جلب الإصدارات: " . $e->getMessage());
    $versions = [];
    $latest_version = null;
}

// ===============================
// وظيفة تحويل التفاصيل إلى HTML
// ===============================
function formatDetails($details) {
    // تحويل النقاط المبدوءة بـ * إلى قائمة HTML
    $lines = explode("\n", $details);
    $formatted = [];
    $inList = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            if ($inList) {
                $formatted[] = '</ul>';
                $inList = false;
            }
            $formatted[] = '<br>';
            continue;
        }
        
        if (strpos($line, '*') === 0) {
            if (!$inList) {
                $formatted[] = '<ul class="details-list">';
                $inList = true;
            }
            $formatted[] = '<li>' . htmlspecialchars(ltrim($line, '* ')) . '</li>';
        } else {
            if ($inList) {
                $formatted[] = '</ul>';
                $inList = false;
            }
            $formatted[] = '<p>' . htmlspecialchars($line) . '</p>';
        }
    }
    
    if ($inList) {
        $formatted[] = '</ul>';
    }
    
    return implode('', $formatted);
}

// ===============================
// CSS خاص بالصفحة
// ===============================
$page_css = <<<CSS
/* حاوي الصفحة الرئيسي */
.versions-display-container {
    background: rgba(0, 0, 0, 0.75);
    padding: 30px;
    border-radius: 15px;
    margin: 20px auto;
    max-width: 1000px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* رأس الصفحة */
.page-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid rgba(0, 255, 255, 0.3);
}

.page-header h1 {
    color: #00ffff;
    font-size: 32px;
    margin: 0 0 10px 0;
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
}

.page-header .subtitle {
    color: #a8d8ff;
    font-size: 16px;
    margin: 0;
}

/* آخر إصدار - بطاقة مميزة */
.latest-version-card {
    background: linear-gradient(135deg, rgba(0, 255, 255, 0.15), rgba(30, 144, 255, 0.15));
    border: 2px solid #00ffff;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.latest-version-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(0, 255, 255, 0.1) 0%, transparent 70%);
    animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.latest-version-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
}

.latest-version-number {
    font-size: 28px;
    font-weight: bold;
    color: #00ffff;
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.8);
}

.latest-badge {
    background: linear-gradient(135deg, #ff6b6b, #ff8787);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
    animation: glow 2s ease-in-out infinite alternate;
}

@keyframes glow {
    from { box-shadow: 0 0 5px rgba(255, 107, 107, 0.5); }
    to { box-shadow: 0 0 20px rgba(255, 107, 107, 0.8); }
}

.latest-version-content {
    position: relative;
    z-index: 1;
}

.latest-version-date {
    color: #a8d8ff;
    font-size: 14px;
    margin-bottom: 10px;
}

.latest-version-summary {
    font-size: 16px;
    line-height: 1.6;
    margin-bottom: 15px;
}

/* قائمة الإصدارات */
.versions-list {
    display: grid;
    gap: 20px;
}

.version-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.version-card:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(0, 255, 255, 0.3);
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
}

.version-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.version-number {
    font-size: 20px;
    font-weight: bold;
    color: #00ffff;
}

.version-meta {
    display: flex;
    gap: 10px;
    align-items: center;
}

.version-date {
    color: #a8d8ff;
    font-size: 14px;
}

/* شارات الحالة */
.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-major { 
    background: linear-gradient(135deg, #00d9ff, #0099cc); 
    color: white; 
}
.badge-minor { 
    background: linear-gradient(135deg, #ffc107, #e6ac00); 
    color: #333; 
}
.badge-patch { 
    background: linear-gradient(135deg, #6c757d, #545b62); 
    color: white; 
}
.badge-stable { 
    background: linear-gradient(135deg, #28a745, #1e7e34); 
    color: white; 
}
.badge-latest { 
    background: linear-gradient(135deg, #ff6b6b, #ee5253); 
    color: white; 
}
.badge-beta { 
    background: linear-gradient(135deg, #ffc107, #e6ac00); 
    color: #333; 
}
.badge-alpha { 
    background: linear-gradient(135deg, #6f42c1, #5a32a3); 
    color: white; 
}

/* محتوى الإصدار */
.version-summary {
    font-size: 16px;
    line-height: 1.6;
    margin-bottom: 15px;
    color: #e2e8f0;
}

.version-details {
    display: none;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.version-details.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 500px;
    }
}

.details-list {
    padding-right: 20px;
    margin: 15px 0;
}

.details-list li {
    margin-bottom: 8px;
    line-height: 1.5;
    color: #cbd5e0;
}

.details-list li::marker {
    color: #00ffff;
}

/* أزرار التحكم */
.toggle-details-btn {
    background: linear-gradient(135deg, #1e90ff, #0070cc);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.toggle-details-btn:hover {
    background: linear-gradient(135deg, #63b3ed, #4da6d9);
    transform: translateY(-2px);
}

.back-btn {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    margin-top: 30px;
}

.back-btn:hover {
    background: linear-gradient(135deg, #545b62, #495057);
    transform: translateY(-2px);
    color: white;
    text-decoration: none;
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

.search-group {
    flex: 1;
    min-width: 200px;
}

.search-input {
    width: 100%;
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

.filter-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-select {
    padding: 8px 12px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.3);
    color: #ffffff;
    font-size: 14px;
    min-width: 120px;
    transition: all 0.3s ease;
}

.filter-select:focus {
    outline: none;
    border-color: #00ffff;
    background: rgba(0, 0, 0, 0.5);
}

.clear-filters-btn {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.clear-filters-btn:hover {
    background: linear-gradient(135deg, #545b62, #495057);
    transform: translateY(-1px);
}

.results-count {
    color: #a8d8ff;
    font-size: 14px;
    margin-bottom: 15px;
    text-align: center;
}

.no-results {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

.no-results i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}
.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #64748b;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #94a3b8;
}

/* التصميم المتجاوب */
@media (max-width: 768px) {
    .versions-display-container {
        padding: 20px;
        margin: 10px;
    }
    
    .page-header h1 {
        font-size: 24px;
    }
    
    .latest-version-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .latest-version-number {
        font-size: 24px;
    }
    
    .version-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .version-meta {
        justify-content: center;
    }
    
    /* تحسينات للبحث والفلترة على الهواتف */
    .search-filter-bar {
        flex-direction: column;
        gap: 15px;
    }
    
    .search-group {
        min-width: 100%;
    }
    
    .filter-group {
        justify-content: center;
        width: 100%;
    }
    
    .filter-select {
        min-width: 100px;
        flex: 1;
    }
    
    .clear-filters-btn {
        width: 100%;
        margin-top: 10px;
    }
}

/* تحسينات بصرية إضافية */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.sparkle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: #00ffff;
    border-radius: 50%;
    animation: sparkle 2s linear infinite;
}

@keyframes sparkle {
    0% { opacity: 0; transform: scale(0); }
    50% { opacity: 1; transform: scale(1); }
    100% { opacity: 0; transform: scale(0); }
}
CSS;

// ===============================
// محتوى الصفحة
// ===============================
ob_start();
?>

<div class="versions-display-container fade-in">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <h1>🚀 آخر التحديثات والإصدارات</h1>
        <p class="subtitle">تابع آخر التطويرات والتحسينات في نظام FlexAuto</p>
    </div>
    
    <!-- آخر إصدار مميز -->
    <?php if ($latest_version): ?>
        <div class="latest-version-card">
            <div class="sparkle" style="top: 20%; left: 10%; animation-delay: 0s;"></div>
            <div class="sparkle" style="top: 60%; right: 15%; animation-delay: 0.5s;"></div>
            <div class="sparkle" style="bottom: 30%; left: 70%; animation-delay: 1s;"></div>
            
            <div class="latest-version-header">
                <div class="latest-version-number">
                    📦 الإصدار <?php echo htmlspecialchars($latest_version['version_number']); ?>
                </div>
                <div class="latest-badge">
                    أحدث إصدار
                </div>
            </div>
            
            <div class="latest-version-content">
                <div class="latest-version-date">
                    📅 تاريخ الإصدار: <?php echo date('Y-m-d', strtotime($latest_version['release_date'])); ?>
                </div>
                
                <div class="latest-version-summary">
                    <?php echo htmlspecialchars($latest_version['summary']); ?>
                </div>
                
                <div class="version-meta">
                    <span class="badge badge-<?php echo strtolower($latest_version['version_type']); ?>">
                        <?php echo htmlspecialchars($latest_version['version_type']); ?>
                    </span>
                    <span class="badge badge-<?php echo strtolower($latest_version['status']); ?>">
                        <?php echo htmlspecialchars($latest_version['status']); ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- قائمة جميع الإصدارات -->
    <?php if (empty($versions)): ?>
        <div class="empty-state">
            <i class="fas fa-code-branch"></i>
            <h3>لا توجد إصدارات متاحة</h3>
            <p>سيتم عرض الإصدارات هنا عند إضافتها</p>
        </div>
    <?php else: ?>
        <h2 style="color: #00ffff; margin-bottom: 20px; text-align: center;">
            📋 سجل جميع الإصدارات
        </h2>
        
        <!-- شريط البحث والفلترة -->
        <div class="search-filter-bar">
            <div class="search-group">
                <input type="text" 
                       class="search-input" 
                       id="searchInput" 
                       placeholder="🔍 البحث في الإصدارات... (رقم الإصدار، الملخص، التفاصيل)">
            </div>
            
            <div class="filter-group">
                <select class="filter-select" id="typeFilter">
                    <option value="">جميع الأنواع</option>
                    <option value="major">إصدار رئيسي</option>
                    <option value="minor">إصدار ثانوي</option>
                    <option value="patch">إصلاح</option>
                </select>
                
                <select class="filter-select" id="statusFilter">
                    <option value="">جميع الحالات</option>
                    <option value="stable">مستقر</option>
                    <option value="latest">أحدث</option>
                    <option value="beta">تجريبي</option>
                    <option value="alpha">تطويري</option>
                </select>
                
                <button class="clear-filters-btn" id="clearFilters">
                    🗑️ مسح الفلاتر
                </button>
            </div>
        </div>
        
        <!-- عداد النتائج -->
        <div class="results-count" id="resultsCount">
            عرض جميع الإصدارات (<?php echo count($versions); ?>)
        </div>
        
        <!-- رسالة عدم وجود نتائج -->
        <div class="no-results" id="noResults" style="display: none;">
            <i class="fas fa-search"></i>
            <h3>لا توجد نتائج</h3>
            <p>لم يتم العثور على إصدارات تطابق البحث أو الفلاتر المحددة</p>
        </div>
        
        <div class="versions-list" id="versionsList">
            <?php foreach ($versions as $index => $version): ?>
                <div class="version-card" 
                     style="animation-delay: <?php echo $index * 0.1; ?>s;"
                     data-version="<?php echo strtolower($version['version_number']); ?>"
                     data-type="<?php echo strtolower($version['version_type']); ?>"
                     data-status="<?php echo strtolower($version['status']); ?>"
                     data-summary="<?php echo strtolower($version['summary']); ?>"
                     data-details="<?php echo strtolower($version['details']); ?>"
                     data-date="<?php echo $version['release_date']; ?>">
                    <div class="version-header">
                        <div class="version-number">
                            🔖 الإصدار <?php echo htmlspecialchars($version['version_number']); ?>
                        </div>
                        <div class="version-meta">
                            <span class="version-date">
                                <?php echo date('Y-m-d', strtotime($version['release_date'])); ?>
                            </span>
                            <span class="badge badge-<?php echo strtolower($version['version_type']); ?>">
                                <?php echo htmlspecialchars($version['version_type']); ?>
                            </span>
                            <span class="badge badge-<?php echo strtolower($version['status']); ?>">
                                <?php echo htmlspecialchars($version['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="version-summary">
                        <?php echo htmlspecialchars($version['summary']); ?>
                    </div>
                    
                    <?php if (!empty($version['details'])): ?>
                        <button class="toggle-details-btn" onclick="toggleDetails(<?php echo $version['id']; ?>)">
                            📄 عرض التفاصيل
                        </button>
                        
                        <div class="version-details" id="details-<?php echo $version['id']; ?>">
                            <h4 style="color: #00ffff; margin-bottom: 10px;">📝 تفاصيل الإصدار:</h4>
                            <div class="details-content">
                                <?php echo formatDetails($version['details']); ?>
                            </div>
                            
                            <?php if (!empty($version['files_changed'])): ?>
                                <h4 style="color: #00ffff; margin: 20px 0 10px 0;">📁 الملفات المتغيرة:</h4>
                                <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; font-family: monospace; font-size: 14px;">
                                    <?php echo nl2br(htmlspecialchars($version['files_changed'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($version['git_commands']) && $user_role === 'admin'): ?>
                                <h4 style="color: #00ffff; margin: 20px 0 10px 0;">⚡ أوامر Git:</h4>
                                <div style="background: rgba(0,0,0,0.5); padding: 15px; border-radius: 8px; font-family: monospace; font-size: 14px; border-left: 3px solid #00ffff;">
                                    <?php echo nl2br(htmlspecialchars($version['git_commands'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- زر العودة مع ملاحظة الاختصارات -->
    <div style="text-align: center;">
        <a href="index.php" class="back-btn">
            🏠 العودة للصفحة الرئيسية
        </a>
        
        <!-- ملاحظة الاختصارات -->
        <div style="margin-top: 20px; font-size: 14px; color: #64748b; text-align: center;">
            <strong>💡 اختصارات مفيدة:</strong><br>
            <span style="margin: 0 10px;">🔍 Ctrl+F للبحث</span>
            <span style="margin: 0 10px;">📄 Ctrl+D لإظهار/إخفاء جميع التفاصيل</span>
            <span style="margin: 0 10px;">🗑️ Ctrl+R لمسح الفلاتر</span>
            <span style="margin: 0 10px;">🏠 Esc للعودة</span>
        </div>
    </div>
</div>

<script>
// ===============================
// JavaScript للتفاعل مع الصفحة
// ===============================

// متغيرات عامة للبحث والفلترة
let allVersionCards = [];
let sparkleAnimationId = null;
let sparkleCount = 0;
const MAX_SPARKLES = 3; // حد أقصى للـ sparkles لتحسين الأداء

// تبديل عرض تفاصيل الإصدار
function toggleDetails(versionId) {
    const detailsDiv = document.getElementById(`details-${versionId}`);
    const button = event.target;
    
    if (detailsDiv.classList.contains('show')) {
        // إخفاء التفاصيل
        detailsDiv.classList.remove('show');
        button.innerHTML = '📄 عرض التفاصيل';
        button.style.background = 'linear-gradient(135deg, #1e90ff, #0070cc)';
    } else {
        // إظهار التفاصيل
        detailsDiv.classList.add('show');
        button.innerHTML = '📁 إخفاء التفاصيل';
        button.style.background = 'linear-gradient(135deg, #6c757d, #495057)';
    }
}

// وظائف البحث والفلترة
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const clearFiltersBtn = document.getElementById('clearFilters');
    
    // جمع جميع بطاقات الإصدارات
    allVersionCards = Array.from(document.querySelectorAll('.version-card'));
    
    // إضافة مستمعات الأحداث
    if (searchInput) {
        searchInput.addEventListener('input', debounce(performSearch, 300));
    }
    
    if (typeFilter) {
        typeFilter.addEventListener('change', performSearch);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', performSearch);
    }
    
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', clearAllFilters);
    }
}

// تنفيذ البحث والفلترة
function performSearch() {
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const typeFilter = document.getElementById('typeFilter')?.value || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    
    let visibleCount = 0;
    
    allVersionCards.forEach(card => {
        const version = card.dataset.version;
        const type = card.dataset.type;
        const status = card.dataset.status;
        const summary = card.dataset.summary;
        const details = card.dataset.details;
        
        // فحص البحث النصي
        const matchesSearch = !searchTerm || 
            version.includes(searchTerm) || 
            summary.includes(searchTerm) || 
            details.includes(searchTerm);
        
        // فحص فلتر النوع
        const matchesType = !typeFilter || type === typeFilter;
        
        // فحص فلتر الحالة
        const matchesStatus = !statusFilter || status === statusFilter;
        
        // إظهار أو إخفاء البطاقة
        if (matchesSearch && matchesType && matchesStatus) {
            card.style.display = 'block';
            card.style.animation = `fadeIn 0.3s ease ${visibleCount * 0.05}s both`;
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // تحديث عداد النتائج
    updateResultsCount(visibleCount);
}

// تحديث عداد النتائج
function updateResultsCount(count) {
    const resultsCount = document.getElementById('resultsCount');
    const noResults = document.getElementById('noResults');
    const versionsList = document.getElementById('versionsList');
    
    if (resultsCount) {
        if (count === allVersionCards.length) {
            resultsCount.textContent = `عرض جميع الإصدارات (${count})`;
        } else {
            resultsCount.textContent = `عرض ${count} من ${allVersionCards.length} إصدار`;
        }
    }
    
    // إظهار أو إخفاء رسالة عدم وجود نتائج
    if (noResults && versionsList) {
        if (count === 0) {
            noResults.style.display = 'block';
            versionsList.style.display = 'none';
        } else {
            noResults.style.display = 'none';
            versionsList.style.display = 'grid';
        }
    }
}

// مسح جميع الفلاتر
function clearAllFilters() {
    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) searchInput.value = '';
    if (typeFilter) typeFilter.value = '';
    if (statusFilter) statusFilter.value = '';
    
    performSearch();
    
    // تأثير بصري لزر المسح
    const clearBtn = document.getElementById('clearFilters');
    if (clearBtn) {
        clearBtn.style.transform = 'scale(0.95)';
        setTimeout(() => {
            clearBtn.style.transform = 'scale(1)';
        }, 150);
    }
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

// إدارة الـ sparkles بكفاءة أكبر
function createOptimizedSparkle(container) {
    if (sparkleCount >= MAX_SPARKLES) return;
    
    const sparkle = document.createElement('div');
    sparkle.className = 'sparkle';
    sparkle.style.top = Math.random() * 80 + '%';
    sparkle.style.left = Math.random() * 80 + '%';
    sparkle.style.animationDelay = '0s';
    
    container.appendChild(sparkle);
    sparkleCount++;
    
    // إزالة النجمة بعد انتهاء الحركة
    setTimeout(() => {
        if (sparkle.parentNode) {
            sparkle.parentNode.removeChild(sparkle);
            sparkleCount--;
        }
    }, 2000);
}

// تأثيرات بصرية عند التحميل مع تحسينات الأداء
document.addEventListener('DOMContentLoaded', function() {
    // تهيئة البحث والفلترة
    initializeSearch();
    
    // إضافة تأثيرات للبطاقات
    const versionCards = document.querySelectorAll('.version-card');
    
    // مراقب التقاطع لتأثيرات الحركة
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });
    
    // تطبيق المراقب على البطاقات
    versionCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = `all 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
    });
    
    // إضافة تأثيرات لمعان محسنة للبطاقة الرئيسية
    const latestCard = document.querySelector('.latest-version-card');
    if (latestCard) {
        const sparkleInterval = setInterval(() => {
            // استخدام requestAnimationFrame لتحسين الأداء
            requestAnimationFrame(() => {
                createOptimizedSparkle(latestCard);
            });
        }, 4000); // تقليل التكرار من 3 ثواني إلى 4 ثواني
        
        // توقيف الـ sparkles عندما لا تكون مرئية
        const sparkleObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) {
                    clearInterval(sparkleInterval);
                }
            });
        });
        
        sparkleObserver.observe(latestCard);
    }
});

// اختصارات لوحة المفاتيح محسنة
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
    
    // مسح الفلاتر بالضغط على Ctrl+R
    if (event.ctrlKey && event.key === 'r') {
        event.preventDefault();
        clearAllFilters();
    }
    
    // تبديل جميع التفاصيل بالضغط على Ctrl+D
    if (event.ctrlKey && event.key === 'd') {
        event.preventDefault();
        const detailsElements = document.querySelectorAll('.version-details:not([style*="display: none"])');
        const buttons = document.querySelectorAll('.toggle-details-btn');
        
        // فقط البطاقات المرئية
        const visibleCards = Array.from(document.querySelectorAll('.version-card')).filter(card => 
            card.style.display !== 'none'
        );
        
        let allVisible = true;
        visibleCards.forEach(card => {
            const details = card.querySelector('.version-details');
            if (details && !details.classList.contains('show')) {
                allVisible = false;
            }
        });
        
        visibleCards.forEach(card => {
            const details = card.querySelector('.version-details');
            const button = card.querySelector('.toggle-details-btn');
            
            if (details && button) {
                if (allVisible) {
                    // إخفاء الكل
                    details.classList.remove('show');
                    button.innerHTML = '📄 عرض التفاصيل';
                    button.style.background = 'linear-gradient(135deg, #1e90ff, #0070cc)';
                } else {
                    // إظهار الكل
                    details.classList.add('show');
                    button.innerHTML = '📁 إخفاء التفاصيل';
                    button.style.background = 'linear-gradient(135deg, #6c757d, #495057)';
                }
            }
        });
    }
});

// تحسين الأداء - تأخير تحميل الصور إن وجدت
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('img[data-src]');
    if (images.length > 0) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    }
});

// تنظيف الذاكرة عند مغادرة الصفحة
window.addEventListener('beforeunload', function() {
    if (sparkleAnimationId) {
        cancelAnimationFrame(sparkleAnimationId);
    }
    
    // إزالة جميع مستمعات الأحداث
    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) searchInput.removeEventListener('input', performSearch);
    if (typeFilter) typeFilter.removeEventListener('change', performSearch);
    if (statusFilter) statusFilter.removeEventListener('change', performSearch);
});
</script>

<?php
$page_content = ob_get_clean();

// تضمين القالب الرئيسي
include __DIR__ . '/includes/layout.php';
?>