<?php
// index.php – صفحة التعريف العامة للزوار
$page_title = "مرحبًا بك في FlexAuto";
$page_css = <<<CSS
.hero {
    background: linear-gradient(to left, #004080, #003060);
    color: white;
    padding: 80px 20px;
    text-align: center;
}
.hero h1 {
    font-size: 3rem;
    font-weight: 700;
}
.hero p {
    font-size: 1.2rem;
    margin-bottom: 30px;
}
.section-title {
    text-align: center;
    margin: 50px 0 20px;
    font-weight: bold;
}
.map-container {
    margin-bottom: 40px;
}
CSS;

$page_content = <<<HTML
<div class="hero">
    <h1>مرحبًا بك في FlexAuto</h1>
    <p>نقدم لك خدمات متقدمة لورش صيانة السيارات – برمجة مفاتيح، تعديل برمجيات، مسح بيانات الحوادث والمزيد.</p>
    <a href="register.php" class="btn btn-light btn-lg">ابدأ الآن</a>
</div>

<div class="container">
    <h2 class="section-title">📍 مواقع فروعنا</h2>

    <div class="map-container">
        <h5>الفرع الأول – الزرقاء / المنطقة الحرة / شارع 20</h5>
        <iframe src="https://maps.google.com/maps?q=الزرقاء المنطقة الحرة&t=&z=15&ie=UTF8&iwloc=&output=embed" width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
    </div>

    <div class="map-container">
        <h5>الفرع الثاني – عمان / القويسمة / مجمع عبود</h5>
        <iframe src="https://maps.google.com/maps?q=عمان القويسمة مجمع عبود&t=&z=15&ie=UTF8&iwloc=&output=embed" width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
    </div>
</div>
HTML;

require_once 'includes/layout.php';
