<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// التحقق من وجود جلسة مستخدم وتوجيهه للصفحة المناسبة
if (isset($_SESSION['email'])) {
    header("Location: home.php");
    exit;
}

// إحصائيات للواجهة الرئيسية (يمكن جلبها من قاعدة البيانات)
try {
    $stats = [
        'users' => 0,
        'services' => 0,
        'tickets' => 0
    ];

    // عدد المستخدمين
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['users'] = $stmt->fetchColumn();

    // عدد الخدمات المقدمة (إذا كان لديك جدول للخدمات)
    $stmt = $pdo->query("SELECT COUNT(*) FROM services");
    $stats['services'] = $stmt->fetchColumn();

    // عدد التذاكر
    $stmt = $pdo->query("SELECT COUNT(*) FROM tickets");
    $stats['tickets'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // إذا كان هناك خطأ في الاستعلام، نستخدم قيم افتراضية
    $stats = [
        'users' => 582,
        'services' => 12,
        'tickets' => 3256
    ];
}

// إعداد معلومات الصفحة
$page_title = "مرحبًا بك في FlexAuto";
$hide_title = true; // إخفاء العنوان الافتراضي

// تنسيقات CSS المخصصة للصفحة
$page_css = <<<CSS
.hero {
    background: linear-gradient(135deg, #004080, #001030);
    color: white;
    padding: 80px 20px;
    text-align: center;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    margin-bottom: 50px;
    position: relative;
    overflow: hidden;
}

.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('/assets/img/car-circuit.svg');
    background-size: cover;
    opacity: 0.1;
    z-index: 0;
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero h1 {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 20px;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}

.hero p {
    font-size: 1.2rem;
    margin-bottom: 30px;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.btn-cta {
    background: linear-gradient(135deg, #00d9ff, #0070cc);
    color: white;
    font-weight: bold;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-block;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    border: none;
}

.btn-cta:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
    background: linear-gradient(135deg, #00eaff, #0088ff);
}

.section-title {
    text-align: center;
    margin: 50px 0 30px;
    font-weight: bold;
    font-size: 2rem;
    position: relative;
    padding-bottom: 15px;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: linear-gradient(to right, #00d9ff, #0070cc);
    border-radius: 2px;
}

.map-container {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 40px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.map-container:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.map-title {
    margin-bottom: 15px;
    color: #00d9ff;
    font-weight: bold;
    display: flex;
    align-items: center;
}

.map-title i {
    margin-left: 10px;
    font-size: 1.2em;
}

.stats-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
    margin: 50px 0;
}

.stat-card {
    background: linear-gradient(135deg, #1a2e44, #0c1b2b);
    border-radius: 15px;
    padding: 25px;
    width: 220px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 15px;
    color: #00d9ff;
}

.stat-number {
    font-size: 2.2rem;
    font-weight: bold;
    margin-bottom: 10px;
    background: linear-gradient(135deg, #00d9ff, #0088ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat-title {
    color: #a0aec0;
    font-size: 1rem;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
    margin: 40px 0;
}

.service-card {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(to right, #00d9ff, #0070cc);
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.service-icon {
    font-size: 2.5rem;
    color: #00d9ff;
    margin-bottom: 15px;
}

.service-title {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 15px;
}

.service-desc {
    color: #a0aec0;
    margin-bottom: 15px;
}

.cta-section {
    background: linear-gradient(135deg, #004080, #001030);
    padding: 50px 20px;
    text-align: center;
    border-radius: 15px;
    margin: 50px 0;
    position: relative;
    overflow: hidden;
}

.cta-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('/assets/img/circuit-pattern.svg');
    opacity: 0.05;
    z-index: 0;
}

.cta-content {
    position: relative;
    z-index: 1;
}

.cta-title {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 20px;
    color: white;
}

.cta-text {
    color: #a0aec0;
    margin-bottom: 30px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

/* للشاشات الصغيرة */
@media (max-width: 768px) {
    .hero h1 {
        font-size: 2.2rem;
    }
    
    .hero p {
        font-size: 1rem;
    }
    
    .stats-container {
        flex-direction: column;
        align-items: center;
    }
    
    .stat-card {
        width: 100%;
        max-width: 300px;
    }
}
CSS;

// محتوى الصفحة
ob_start();
?>
<div class="hero">
    <div class="hero-content">
        <h1>ذكاء في الخدمة، احترافية في الأداء</h1>
        <p>نقدم لك أحدث الحلول التقنية والبرمجية لسيارتك – برمجة مفاتيح، تعديل وحدات التحكم ECU، إصلاح بيانات الحوادث، وخدمات متخصصة للورش والمراكز الفنية.</p>
        <a href="register.php" class="btn-cta">ابدأ الآن مجاناً <i class="fas fa-arrow-left"></i></a>
    </div>
</div>

<div class="container">
    <!-- إحصائيات -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?= number_format($stats['users']) ?>+</div>
            <div class="stat-title">عميل يثق بنا</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tools"></i></div>
            <div class="stat-number"><?= number_format($stats['services']) ?>+</div>
            <div class="stat-title">خدمة متخصصة</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-number"><?= number_format($stats['tickets']) ?>+</div>
            <div class="stat-title">طلب تم إنجازه</div>
        </div>
    </div>
    
    <!-- خدماتنا -->
    <h2 class="section-title">خدماتنا المتخصصة</h2>
    <div class="services-grid">
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-key"></i></div>
            <h3 class="service-title">برمجة المفاتيح</h3>
            <p class="service-desc">إنشاء وبرمجة مفاتيح بديلة للسيارات بمختلف أنواعها مع ضمان التوافق الكامل.</p>
        </div>
        
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-microchip"></i></div>
            <h3 class="service-title">برمجة ECU</h3>
            <p class="service-desc">تعديل وتحسين برمجيات وحدة التحكم الإلكترونية للسيارة لتحسين الأداء والكفاءة.</p>
        </div>
        
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-car-crash"></i></div>
            <h3 class="service-title">مسح بيانات الحوادث</h3>
            <p class="service-desc">إعادة ضبط وحدات الوسائد الهوائية بعد الحوادث ومسح بيانات SRS بشكل آمن وموثوق.</p>
        </div>
        
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-cogs"></i></div>
            <h3 class="service-title">خدمات الورش المتخصصة</h3>
            <p class="service-desc">حلول برمجية ودعم فني متكامل للورش ومراكز الصيانة المتخصصة وفنيي السيارات.</p>
        </div>
    </div>
    
    <!-- قسم CTA -->
    <div class="cta-section">
        <div class="cta-content">
            <h2 class="cta-title">احصل على دعم فني احترافي</h2>
            <p class="cta-text">سجل الآن واستفد من خدماتنا المتخصصة وفريق الدعم الفني المتاح على مدار الساعة</p>
            <a href="register.php" class="btn-cta">سجل حساب جديد</a>
        </div>
    </div>
    
    <!-- مواقع الفروع -->
    <h2 class="section-title">مواقع فروعنا</h2>
    <div class="map-container">
        <h3 class="map-title"><i class="fas fa-map-marker-alt"></i> الفرع الرئيسي – الزرقاء / المنطقة الحرة / شارع 20</h3>
        <iframe src="https://maps.google.com/maps?q=الزرقاء المنطقة الحرة&t=&z=15&ie=UTF8&iwloc=&output=embed" width="100%" height="350" style="border:0; border-radius:10px;" allowfullscreen="" loading="lazy"></iframe>
    </div>
    
    <div class="map-container">
        <h3 class="map-title"><i class="fas fa-map-marker-alt"></i> الفرع الثاني – عمان / القويسمة / مجمع عبود</h3>
        <iframe src="https://maps.google.com/maps?q=عمان القويسمة مجمع عبود&t=&z=15&ie=UTF8&iwloc=&output=embed" width="100%" height="350" style="border:0; border-radius:10px;" allowfullscreen="" loading="lazy"></iframe>
    </div>
</div>
<?php
$page_content = ob_get_clean();

// تضمين ملف القالب
require_once 'includes/layout.php';
?>