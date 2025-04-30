<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// إعداد معلومات الصفحة
$page_title = "خدماتنا في FlexAuto";
$hide_title = true; // إخفاء العنوان الافتراضي في القالب

// تنسيقات CSS المخصصة للصفحة
$page_css = <<<CSS
.services-header {
    background: linear-gradient(135deg, #004080, #001030);
    color: white;
    padding: 60px 20px;
    text-align: center;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    margin-bottom: 50px;
    position: relative;
    overflow: hidden;
}

.services-header::before {
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
}

.services-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 20px;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}

.services-header p {
    font-size: 1.1rem;
    margin-bottom: 0;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
    color: rgba(255, 255, 255, 0.9);
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 30px;
    margin: 40px 0;
}

.service-card {
    background: rgba(15, 23, 42, 0.5);
    border-radius: 16px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    height: 100%;
    border: 1px solid rgba(66, 135, 245, 0.1);
    backdrop-filter: blur(5px);
}

.service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(to bottom, #00d9ff, #0070cc);
    opacity: 0.8;
}

.service-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    background: rgba(15, 23, 42, 0.7);
}

.service-icon {
    font-size: 3rem;
    margin-bottom: 20px;
    color: #00d9ff;
    transition: all 0.3s ease;
}

.service-card:hover .service-icon {
    transform: scale(1.1);
    color: #00eaff;
}

.service-title {
    font-size: 1.4rem;
    font-weight: bold;
    margin-bottom: 15px;
    color: #ffffff;
    position: relative;
    padding-bottom: 12px;
}

.service-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 3px;
    background: linear-gradient(to right, #00d9ff, #0070cc);
    border-radius: 1.5px;
    transition: all 0.3s ease;
}

.service-card:hover .service-title::after {
    width: 60px;
}

.service-desc {
    color: #a0aec0;
    font-size: 1rem;
    line-height: 1.6;
    flex-grow: 1;
}

.features-list {
    margin-top: 15px;
    text-align: right;
    list-style: none;
    padding: 0;
}

.features-list li {
    padding: 8px 0;
    color: #cbd5e1;
    position: relative;
    padding-right: 22px;
}

.features-list li::before {
    content: '✓';
    position: absolute;
    right: 0;
    color: #00d9ff;
    font-weight: bold;
}

.service-btn {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 25px;
    background: linear-gradient(135deg, #00d9ff, #0070cc);
    color: white;
    border-radius: 30px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.service-btn:hover {
    background: linear-gradient(135deg, #00eaff, #0088ff);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
}

.contact-section {
    background: linear-gradient(135deg, #004080, #001030);
    padding: 50px 20px;
    text-align: center;
    border-radius: 15px;
    margin: 50px 0;
    position: relative;
    overflow: hidden;
}

.contact-section::before {
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

.contact-content {
    position: relative;
    z-index: 1;
}

.contact-title {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 20px;
    color: white;
}

.contact-text {
    color: #a0aec0;
    margin-bottom: 30px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

.btn-contact {
    background: linear-gradient(135deg, #00d9ff, #0070cc);
    color: white;
    font-weight: bold;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-block;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.btn-contact:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
    background: linear-gradient(135deg, #00eaff, #0088ff);
}

/* للشاشات الصغيرة */
@media (max-width: 768px) {
    .services-header h1 {
        font-size: 2rem;
    }
    
    .services-header p {
        font-size: 1rem;
    }
    
    .service-card {
        padding: 25px 20px;
    }
}
CSS;

// محتوى الصفحة
ob_start();
?>
<div class="services-header">
    <div class="header-content">
        <h1>خدماتنا التقنية المتقدمة</h1>
        <p>نقدم لك مجموعة متكاملة من الحلول البرمجية والتقنية لسيارتك مع ضمان الجودة والاحترافية في التنفيذ</p>
    </div>
</div>

<div class="container">
    <div class="services-grid">
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-key"></i></div>
            <h3 class="service-title">برمجة المفاتيح</h3>
            <p class="service-desc">استخراج وبرمجة مفاتيح السيارات عبر VIN، مع دعم للموديلات الحديثة من كيا وهيونداي والمزيد.</p>
            <ul class="features-list">
                <li>استخراج أكواد التشفير</li>
                <li>برمجة المفاتيح الذكية</li>
                <li>دعم جميع أنواع السيارات</li>
            </ul>
            <a href="key-code.php" class="service-btn">طلب الخدمة</a>
        </div>
        
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-car-crash"></i></div>
            <h3 class="service-title">مسح بيانات الحوادث</h3>
            <p class="service-desc">رفع ملف وحدة الوسادة الهوائية، ومعالجته تلقائيًا لمسح بيانات التصادم وإعادة الوحدة إلى وضع المصنع.</p>
            <ul class="features-list">
                <li>معالجة ملفات وحدة SRS</li>
                <li>إعادة ضبط الوسائد الهوائية</li>
                <li>إزالة أكواد الأعطال</li>
            </ul>
            <a href="airbag-reset.php" class="service-btn">طلب الخدمة</a>
        </div>
        
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-microchip"></i></div>
            <h3 class="service-title">تعديل برمجيات ECU</h3>
            <p class="service-desc">تعديل برمجيات وحدات التحكم بالمركبة: إزالة الأكواد، تعديل السرعة القصوى، فتح خصائص مغلقة والمزيد.</p>
            <ul class="features-list">
                <li>زيادة كفاءة المحرك</li>
                <li>تفعيل خصائص إضافية</li>
                <li>إزالة قيود المصنع</li>
            </ul>
            <a href="ecu-tuning.php" class="service-btn">طلب الخدمة</a>
        </div>
        
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-tools"></i></div>
            <h3 class="service-title">دعم البرمجة عن بعد</h3>
            <p class="service-desc">اتصال مباشر عبر أجهزة FlexAuto من خلال الإنترنت لتنفيذ البرمجة باستخدام أدوات المصنع الأصلية.</p>
            <ul class="features-list">
                <li>دعم أنظمة GDS, IDS</li>
                <li>دعم أنظمة Witech, ISTA</li>
                <li>تشخيص وبرمجة فوري</li>
            </ul>
            <a href="online-programming-ticket.php" class="service-btn">طلب الخدمة</a>
        </div>
        
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-database"></i></div>
            <h3 class="service-title">قاعدة بيانات VIN</h3>
            <p class="service-desc">خدمة فورية لاستخراج الأكواد البرمجية من قاعدة بياناتنا المتخصصة بناءً على رقم الهيكل (VIN).</p>
            <ul class="features-list">
                <li>أكواد مفاتيح جميع السيارات</li>
                <li>معلومات تفصيلية عن المركبة</li>
                <li>استجابة فورية (أقل من دقيقة)</li>
            </ul>
            <a href="vin-database.php" class="service-btn">استعلام VIN</a>
        </div>
        
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-user-shield"></i></div>
            <h3 class="service-title">اشتراكات مهنية</h3>
            <p class="service-desc">خطة اشتراك مرنة تتيح لك الوصول الكامل لجميع الخدمات مع سجل طلبات ودعم فني مباشر.</p>
            <ul class="features-list">
                <li>خصومات على جميع الخدمات</li>
                <li>أولوية في معالجة الطلبات</li>
                <li>وصول لقاعدة بيانات متقدمة</li>
            </ul>
            <a href="pro-subscription.php" class="service-btn">اشترك الآن</a>
        </div>
    </div>
    
    <!-- قسم التواصل -->
    <div class="contact-section">
        <div class="contact-content">
            <h2 class="contact-title">هل تحتاج إلى مساعدة؟</h2>
            <p class="contact-text">فريق الدعم الفني جاهز لمساعدتك في اختيار الخدمة المناسبة لاحتياجاتك أو الإجابة على أي استفسارات فنية</p>
            <a href="mailto:raedfss@hotmail.com" class="btn-contact"><i class="fas fa-envelope"></i> تواصل معنا</a>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();

// تضمين ملف القالب
require_once 'includes/layout.php';
?>