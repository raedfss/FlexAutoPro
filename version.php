<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سجل الإصدارات | FlexAuto</title>
    <style>
        body {
            background-color: #1a1f2e;
            color: white;
            margin: 0; 
            padding: 20px;
            font-family: 'Segoe UI', sans-serif;
        }
        a {
            color: #00ffff;
            text-decoration: none;
        }
        h1 {
            margin-bottom: 20px;
        }
        ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 30px;
        }
        li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        .version-title {
            font-weight: bold;
            color: #00ffff;
        }
        .highlight {
            background-color: rgba(0, 255, 255, 0.15);
            border-radius: 5px;
            padding: 5px;
            display: inline-block;
            margin-right: 5px;
        }
    </style>
</head>
<body>

    <h1>سجل الإصدارات</h1>
    <li>
    <span class="version-title">v1.1.0</span> – 
    <span class="highlight">نسخة مستقرة</span>
    اعتماد النسخة الحالية كنقطة استقرار رئيسية تشمل:
    تنظيم الكود وتحسين نماذج التذاكر، صفحة ECU الجديدة، التحقق من إدخال البيانات، تصميم موحد،
    واستعداد للميزات القادمة (مثل الدفع، لوحة الإدارة).
</li>


    <ul>
        <li>
            <span class="version-title">v1.02</span> –
            <span class="highlight">أحدث إصدار</span>
            تحديث صفحة <code>key-code.php</code> بالكامل:
            إعادة تنظيم الكود، تحسين التصميم والرسائل الظاهرة، منع إرسال النموذج إلا بعد اكتمال البيانات المطلوبة،
            عرض رقم الطلب والشاسيه بطريقة احترافية، وتصحيح رابط العودة للصفحة الرئيسية إلى <code>home.php</code>.
        </li>
        <li>
            <span class="version-title">v1.01</span> –
            تحسين التوجيه إلى <code>my_tickets.php</code> بدلًا من <code>tickets.php</code> للمستخدم العادي، 
            وإضافة زر “آخر التحديثات والتعديلات” في الصفحة الرئيسية.
        </li>
        <li>
            <span class="version-title">v1.0</span> –
            الإصدار الأولي للموقع (الوظائف الأساسية مكتملة، بداية الاستقرار).
        </li>
        <li>
            <span class="version-title">v0.9</span> –
            مرحلة Beta لاختبار الميزات قبل الإصدار النهائي، وإصلاح مجموعة من الأخطاء.
        </li>
        <li>
            <span class="version-title">v0.7</span> –
            تحسينات عامة في التصميم وإضافة إشعارات تنبيه للمستخدم، وإصلاح بعض الأخطاء في النموذج الأولي.
        </li>
        <li>
            <span class="version-title">v0.5</span> –
            نموذج أولي (Prototype): إمكانية إرسال تذكرة وتخزينها في قاعدة البيانات.
        </li>
        <li>
            <span class="version-title">v0.4</span> –
            تطوير واجهات أولية (Home, Login) وربطها بقاعدة البيانات للتحقق من المستخدم.
        </li>
        <li>
            <span class="version-title">v0.2</span> –
            إنشاء صفحات التسجيل وتسجيل الدخول، مع تكوين الجداول الأساسية (Users, Tickets).
        </li>
        <li>
            <span class="version-title">v0.1</span> –
            مرحلة بدء المشروع: تهيئة بيئة التطوير (XAMPP) وإنشاء الهيكلية الأولية للملفات.
        </li>
    </ul>

    <p>
        <a href="home.php">عودة للصفحة الرئيسية</a>
    </p>

</body>
</html>
