<?php
// services.php – صفحة عرض الخدمات العامة
$page_title = "خدماتنا في FlexAuto";
$page_css = <<<CSS
.services-section {
    padding: 60px 20px;
    background-color: #f9fafb;
}
.service-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    transition: transform 0.3s ease;
    text-align: center;
}
.service-card:hover {
    transform: translateY(-5px);
}
.service-icon {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 15px;
}
.service-title {
    font-weight: bold;
    font-size: 20px;
    margin-bottom: 10px;
}
.service-desc {
    color: #555;
    font-size: 15px;
}
CSS;

$page_content = <<<HTML
<div class="services-section container">
    <h2 class="text-center mb-5">🚗 خدماتنا التقنية المتقدمة</h2>
    <div class="row">
        <div class="col-md-4">
            <div class="service-card">
                <i class="fas fa-key service-icon"></i>
                <div class="service-title">برمجة المفاتيح</div>
                <div class="service-desc">استخراج وبرمجة مفاتيح السيارات عبر VIN، مع دعم للموديلات الحديثة من كيا وهيونداي والمزيد.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="service-card">
                <i class="fas fa-car-crash service-icon"></i>
                <div class="service-title">مسح بيانات الحوادث (Airbag)</div>
                <div class="service-desc">رفع ملف وحدة الوسادة الهوائية، ومعالجته تلقائيًا لمسح بيانات التصادم وإعادة الوحدة إلى وضع المصنع.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="service-card">
                <i class="fas fa-microchip service-icon"></i>
                <div class="service-title">تعديل برمجيات ECU</div>
                <div class="service-desc">تعديل برمجيات وحدات التحكم بالمركبة: إزالة الأكواد، تعديل السرعة القصوى، فتح خصائص مغلقة والمزيد.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="service-card">
                <i class="fas fa-tools service-icon"></i>
                <div class="service-title">دعم البرمجة عن بعد</div>
                <div class="service-desc">اتصال مباشر عبر أجهزة FlexAuto من خلال الإنترنت لتنفيذ البرمجة باستخدام أدوات المصنع الأصلية مثل GDS, IDS, Witech, ISTA.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="service-card">
                <i class="fas fa-database service-icon"></i>
                <div class="service-title">قاعدة بيانات VIN</div>
                <div class="service-desc">خدمة فورية لاستخراج الأكواد البرمجية من قاعدة بياناتنا المتخصصة بناءً على رقم الهيكل (VIN).</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="service-card">
                <i class="fas fa-user-shield service-icon"></i>
                <div class="service-title">اشتراكات مهنية</div>
                <div class="service-desc">خطة اشتراك مرنة تتيح لك الوصول الكامل لجميع الخدمات مع سجل طلبات ودعم فني مباشر.</div>
            </div>
        </div>
    </div>
</div>
HTML;

require_once 'includes/layout.php';
