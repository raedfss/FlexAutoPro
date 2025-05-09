<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// استخدام user_role بدلاً من user_type (هذا هو سبب الخطأ)
$username = $_SESSION['username'] ?? '';
$email = $_SESSION['email'] ?? '';
$user_role = $_SESSION['user_role'] ?? 'user';

// إعداد متغيرات الصفحة
$page_title = "حجز تذكرة برمجة أونلاين";
$success_message = '';
$error_messages = [];

// التحقق من اكتمال الملف الشخصي (سنقوم بتنفيذ فحص بسيط)
$profile_complete = false;
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/includes/db.php';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($user['username']) && !empty($user['email'])) {
            $profile_complete = true;
        }
    } catch (PDOException $e) {
        // لا نفعل شيئًا، سنفترض أن الملف الشخصي غير مكتمل
        error_log("Online Ticket Profile Check Error: " . $e->getMessage());
    }
}

// بدء محتوى الصفحة
ob_start();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>حجز تذكرة برمجة أونلاين | FlexAuto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            color: white;
            background-color: #1a1f2e;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* خلفية SVG متحركة */
        .svg-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            opacity: 0.5;
        }

        .svg-object {
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        header {
            background-color: rgba(0, 0, 0, 0.85);
            padding: 18px 20px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #00ffff;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(0, 255, 255, 0.3);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.4);
        }

        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
            width: 100%;
        }

        .container {
            background: rgba(0, 0, 0, 0.6);
            width: 100%;
            max-width: 800px;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(66, 135, 245, 0.2);
            margin: 0 auto;
            transition: all 0.3s ease;
        }

        .container:hover {
            box-shadow: 0 0 40px rgba(0, 255, 255, 0.2);
        }

        h1 {
            font-size: 28px;
            margin-bottom: 15px;
            color: #fff;
            text-align: center;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }

        .role {
            font-size: 16px;
            margin-bottom: 25px;
            color: #a0d0ff;
            text-align: center;
        }

        .form-style {
            text-align: right;
            margin-top: 20px;
        }

        .form-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px dashed rgba(66, 135, 245, 0.3);
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .section-title {
            font-size: 18px;
            color: #00ffff;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .section-title::before {
            content: '';
            display: inline-block;
            width: 5px;
            height: 18px;
            background-color: #00ffff;
            margin-left: 8px;
            border-radius: 3px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4287f5;
        }

        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        input[type="time"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid #3a4052;
            background-color: rgba(30, 35, 50, 0.8);
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="tel"]:focus,
        input[type="email"]:focus,
        input[type="date"]:focus,
        input[type="time"]:focus,
        select:focus,
        textarea:focus {
            border-color: #4287f5;
            box-shadow: 0 0 8px rgba(66, 135, 245, 0.5);
            outline: none;
        }

        .required::after {
            content: ' *';
            color: #ff6b6b;
        }

        .optional {
            font-size: 13px;
            color: #a0a0a0;
            margin-right: 5px;
            font-weight: normal;
        }

        .input-hint {
            font-size: 12px;
            color: #a0d0ff;
            margin-top: -15px;
            margin-bottom: 15px;
            display: block;
        }

        .input-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .input-group > div {
            flex: 1;
            min-width: 250px;
        }

        input[type="submit"] {
            background: linear-gradient(135deg, #1e90ff, #4287f5);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: block;
            margin: 25px auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        input[type="submit"]:hover {
            background: linear-gradient(135deg, #4287f5, #63b3ed);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
        }

        .logout {
            text-align: center;
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .logout a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 20px;
            border: 1px solid rgba(255, 107, 107, 0.4);
            border-radius: 5px;
            transition: all 0.3s;
            display: inline-block;
        }

        .logout a:hover {
            background-color: rgba(255, 107, 107, 0.1);
            border-color: rgba(255, 107, 107, 0.6);
        }
        
        .file-upload-section {
            margin-bottom: 20px;
        }
        
        .file-input-container {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 8px;
            background-color: rgba(30, 35, 50, 0.5);
            border: 1px dashed #3a4052;
            transition: all 0.3s ease;
        }
        
        .file-input-container:hover {
            border-color: #4287f5;
            background-color: rgba(30, 35, 50, 0.7);
        }
        
        .file-input {
            display: block;
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border-radius: 5px;
            background-color: rgba(20, 25, 40, 0.8);
            color: white;
            border: 1px solid #2a3040;
            cursor: pointer;
        }
        
        .file-info {
            font-size: 12px;
            color: #a0d0ff;
            margin-top: 5px;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .form-note {
            background-color: rgba(66, 135, 245, 0.1);
            border-right: 3px solid #4287f5;
            padding: 10px 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-size: 14px;
        }

        .home-link {
            background-color: rgba(0, 150, 136, 0.1) !important;
            color: #00ffaa !important;
            border: 1px solid rgba(0, 150, 136, 0.4) !important;
        }

        .home-link:hover {
            background-color: rgba(0, 150, 136, 0.2) !important;
            border-color: rgba(0, 150, 136, 0.6) !important;
        }

        footer {
            background-color: rgba(0, 0, 0, 0.9);
            color: #eee;
            text-align: center;
            padding: 20px;
            width: 100%;
            margin-top: auto;
        }

        .footer-highlight {
            font-size: 18px;
            font-weight: bold;
            color: #00ffff;
            margin-bottom: 10px;
        }
        
        .checkbox-container {
            margin-bottom: 20px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding: 10px;
            background-color: rgba(30, 35, 50, 0.5);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .checkbox-item:hover {
            background-color: rgba(30, 35, 50, 0.7);
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-top: 3px;
            margin-left: 10px;
            width: 18px;
            height: 18px;
            accent-color: #4287f5;
        }
        
        .checkbox-item label {
            color: #f0f0f0;
            font-weight: normal;
            margin-bottom: 0;
        }
        
        .test-connection-btn {
            background: linear-gradient(135deg, #00c853, #009624);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            margin: 10px 0;
            transition: all 0.3s ease;
        }
        
        .test-connection-btn:hover {
            background: linear-gradient(135deg, #00e676, #00c853);
            transform: translateY(-2px);
        }
        
        .test-result {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            display: none;
        }
        
        .test-success {
            background-color: rgba(0, 200, 83, 0.1);
            border: 1px solid rgba(0, 200, 83, 0.4);
            color: #00c853;
        }
        
        .test-error {
            background-color: rgba(255, 82, 82, 0.1);
            border: 1px solid rgba(255, 82, 82, 0.4);
            color: #ff5252;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }
        
        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }
        
        .alert i {
            margin-left: 10px;
            font-size: 20px;
        }
        
        .timezone-select {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            background-color: rgba(30, 35, 50, 0.8);
            color: white;
            border: 1px solid #3a4052;
        }

        @media (max-width: 768px) {
            main {
                padding: 20px 15px;
            }
            
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            input[type="submit"] {
                width: 100%;
            }

            .input-group {
                flex-direction: column;
                gap: 0;
            }

            .logout {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

<div class="svg-background">
    <embed type="image/svg+xml" src="admin/admin_background.svg" class="svg-object">
</div>

<header>
    FlexAuto - حجز تذكرة برمجة أونلاين
</header>

<main>
    <div class="container">
        <h1>مرحبًا <?= htmlspecialchars($username) ?>!</h1>
        <div class="role">🧾 يمكنك هنا حجز تذكرة برمجة أونلاين وتحديد الوقت المناسب للجلسة</div>

        <!-- إضافة تنبيه عن أهمية اكتمال الملف الشخصي -->
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>ملاحظة هامة:</strong> يجب إكمال بيانات الملف الشخصي الخاص بك للحصول على الخدمة بشكل كامل. يرجى التأكد من <a href="profile.php" style="color: #ffc107; text-decoration: underline;">تحديث ملفك الشخصي</a> قبل المتابعة.
            </div>
        </div>

        <form method="POST" action="ticket_submit.php" class="form-style" enctype="multipart/form-data">
            
            <!-- بيانات الورشة -->
            <div class="form-section">
                <h3 class="section-title">🏪 بيانات الورشة</h3>
                
                <div class="input-group">
                    <div>
                        <label class="required">اسم الورشة:</label>
                        <input type="text" name="shop_name" required placeholder="مثال: ورشة الأمل للصيانة">
                    </div>
                    
                    <div>
                        <label class="required">رقم الهاتف:</label>
                        <input type="tel" name="phone" required placeholder="مثال: 0777123456 أو +962777123456" 
                               pattern="^(\+)?\d{10,15}$">
                    </div>
                </div>
                
                <div class="input-group">
                    <div>
                        <label class="required">المدينة:</label>
                        <input type="text" name="city" required placeholder="مثال: عمان">
                    </div>
                    
                    <div>
                        <label class="required">الدولة:</label>
                        <select name="country" required>
                            <option value="">-- اختر الدولة --</option>
                            <option value="Jordan">الأردن</option>
                            <option value="Saudi Arabia">السعودية</option>
                            <option value="UAE">الإمارات</option>
                            <option value="Qatar">قطر</option>
                            <option value="Kuwait">الكويت</option>
                            <option value="Bahrain">البحرين</option>
                            <option value="Oman">عمان</option>
                            <option value="Egypt">مصر</option>
                            <option value="Iraq">العراق</option>
                            <option value="Lebanon">لبنان</option>
                            <option value="Syria">سوريا</option>
                            <option value="Palestine">فلسطين</option>
                            <option value="Other">دولة أخرى</option>
                        </select>
                    </div>
                </div>
                
                <label>البريد الإلكتروني: <span class="optional">(مُسجل)</span></label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly>
            </div>
            
            <!-- بيانات السيارة -->
            <div class="form-section">
                <h3 class="section-title">🚘 بيانات السيارة</h3>

                <label class="required">نوع السيارة:</label>
                <input type="text" name="car_type" required placeholder="مثال: مرسيدس E300 موديل 2023">

                <div class="input-group">
                    <div>
                        <label class="required">رقم الشاسيه (VIN):</label>
                        <input type="text" name="vin" required placeholder="أدخل رقم الشاسيه المكون من 17 خانة"
                               pattern=".{17,17}" title="يجب أن يتكون رقم الشاسيه من 17 خانة بالضبط">
                        <span class="input-hint">يوجد على لوحة البيانات أسفل الزجاج الأمامي أو على باب السائق</span>
                    </div>
                    
                    <div>
                        <label class="required">سنة الصنع:</label>
                        <input type="text" name="year" required placeholder="مثال: 2023" pattern="[0-9]{4}">
                    </div>
                </div>
                
                <label class="required">نوع وحدة التحكم (ECU Type):</label>
                <select name="ecu_type" required>
                    <option value="">-- اختر نوع وحدة التحكم --</option>
                    <option value="ECU">وحدة التحكم في المحرك (ECU)</option>
                    <option value="TCU">وحدة التحكم في ناقل الحركة (TCU)</option>
                    <option value="BCM">وحدة التحكم في الهيكل (BCM)</option>
                    <option value="ICU">وحدة التحكم في الأدوات (ICU)</option>
                    <option value="ABS">وحدة التحكم في الفرامل (ABS)</option>
                    <option value="SRS">وحدة التحكم في الوسائد الهوائية (SRS)</option>
                    <option value="Other">أخرى</option>
                </select>
            </div>

            <!-- نوع الخدمة المطلوبة -->
            <div class="form-section">
                <h3 class="section-title">🛠️ نوع الخدمة المطلوبة</h3>

                <label class="required">اختر الخدمة المطلوبة:</label>
                <select name="service_type" required>
                    <option value="">-- اختر الخدمة --</option>
                    <option value="ecu_programming">برمجة كمبيوتر رئيسي</option>
                    <option value="unit_initialization">تهيئة وحدات جديدة</option>
                    <option value="security_unlock">فك حماية</option>
                    <option value="diagnosis">تشخيص عطل محدد</option>
                    <option value="flash_rw">قراءة أو كتابة ملفات (Read/Write Flash)</option>
                    <option value="other">أخرى</option>
                </select>

                <label class="required">وصف مفصل للمشكلة أو الخدمة المطلوبة:</label>
                <textarea name="description" rows="6" required placeholder="اكتب هنا وصف مفصل للخدمة التي تحتاجها أو المشكلة التي تواجهها. كلما كانت المعلومات أكثر دقة، كلما كان الحل أسرع وأفضل."></textarea>
            </div>
            
            <!-- جدولة الموعد -->
            <div class="form-section">
                <h3 class="section-title">📅 جدولة موعد الجلسة</h3>
                
                <div class="form-note">
                    يرجى اختيار التاريخ والوقت المناسب لك. سيتم التواصل معك لتأكيد الموعد أو تغييره إذا لزم الأمر.
                </div>
                
                <div class="input-group">
                    <div>
                        <label class="required">التاريخ المفضل:</label>
                        <input type="date" name="preferred_date" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div>
                        <label class="required">الوقت المفضل:</label>
                        <input type="time" name="preferred_time" required>
                    </div>
                </div>
                
                <div>
                    <label class="required">المنطقة الزمنية:</label>
                    <select name="timezone" class="timezone-select" required>
                        <option value="">-- اختر المنطقة الزمنية --</option>
                        <option value="Asia/Amman">توقيت الأردن/فلسطين (GMT+3)</option>
                        <option value="Asia/Riyadh">توقيت السعودية/الخليج (GMT+3)</option>
                        <option value="Asia/Baghdad">توقيت العراق (GMT+3)</option>
                        <option value="Africa/Cairo">توقيت مصر (GMT+2)</option>
                        <option value="Africa/Casablanca">توقيت المغرب (GMT+1)</option>
                        <option value="Europe/Istanbul">توقيت تركيا (GMT+3)</option>
                    </select>
                </div>
            </div>
            
            <!-- ملفات وصور -->
            <div class="form-section">
                <h3 class="section-title">📂 ملفات وصور</h3>
                
                <div class="form-note">
                    تحميل الملفات والصور سيساعد فريقنا على فهم احتياجاتك بشكل أفضل وتوفير الخدمة المناسبة بسرعة أكبر.
                </div>
                
                <div class="file-upload-section">
                    <div class="file-input-container">
                        <label>📊 تحميل ملف DTC أو Log (اختياري):</label>
                        <input type="file" name="dtc_file" class="file-input" accept=".txt,.log,.csv,.xml">
                        <div class="file-info">صيغ الملفات المقبولة: .txt, .log, .csv, .xml (الحجم الأقصى: 10 ميجابايت)</div>
                    </div>
                    
                    <div class="file-input-container">
                        <label>🖼️ تحميل صور (اختياري):</label>
                        <input type="file" name="images[]" class="file-input" accept="image/*" multiple>
                        <div class="file-info">يمكنك تحميل أكثر من صورة (الحد الأقصى: 5 صور، 2 ميجابايت لكل صورة)</div>
                    </div>
                </div>
            </div>
            
            <!-- التحقق من جاهزية الورشة -->
            <div class="form-section">
                <h3 class="section-title">🔍 التحقق من الجاهزية</h3>
                
                <div class="checkbox-container">
                    <div class="checkbox-item">
                        <input type="checkbox" id="check_scanner" name="has_scanner" required>
                        <label for="check_scanner">أمتلك جهاز فحص متوافق مع (J2534 أو OBDLink أو ماستر فلكس أو D-PDU)</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="check_internet" name="has_internet" required>
                        <label for="check_internet">الكمبيوتر متصل بالإنترنت السلكي أو WiFi مستقر</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="check_ignition" name="keeps_ignition" required>
                        <label for="check_ignition">يمكنني إبقاء السيارة بوضع تشغيل (Ignition ON) طوال مدة الجلسة</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="check_disconnect" name="wont_disconnect" required>
                        <label for="check_disconnect">أتفهم أنه يجب عدم فصل الجهاز خلال الجلسة البرمجية</label>
                    </div>
                </div>
                
                <button type="button" id="test_connection" class="test-connection-btn">
                    ⚡ اختبار سرعة الاتصال والجاهزية
                </button>
                
                <div id="test_result" class="test-result">
                    <!-- هنا ستظهر نتيجة الاختبار -->
                </div>
            </div>
            
            <!-- التعهد القانوني -->
            <div class="form-section">
                <h3 class="section-title">📜 إقرار الاستخدام المهني</h3>
                
                <div class="form-note">
                    يرجى قراءة التعهد بعناية والموافقة عليه قبل إرسال الطلب:
                </div>
                
                <div class="checkbox-item">
                    <input type="checkbox" id="legal_agreement" name="legal_agreement" required>
                    <label for="legal_agreement">
                        أقر بأن هذه الخدمة موجهة للاستخدام المهني فقط، وأنني مسؤول عن أي نتائج ناتجة عن الإعداد الخاطئ أو التوصيل السيء. كما أتعهد بالالتزام بتعليمات الفريق الفني خلال الجلسة، وعدم فصل الأجهزة دون إذن منهم.
                    </label>
                </div>
            </div>

            <input type="submit" value="📨 إرسال طلب البرمجة">
        </form>

        <div class="logout">
            <a href="index.php" class="home-link">🏠 الرئيسية</a>
            <a href="my_tickets.php">📋 تذاكري السابقة</a>
            <a href="logout.php">🔓 تسجيل الخروج</a>
        </div>
    </div>
</main>

<footer>
    <div class="footer-highlight">ذكاءٌ في الخدمة، سرعةٌ في الاستجابة، جودةٌ بلا حدود.</div>
    <div>Smart service, fast response, unlimited quality.</div>
    <div style="margin-top: 8px;">📧 raedfss@hotmail.com | ☎️ +962796519007</div>
    <div style="margin-top: 5px;">&copy; <?= date('Y') ?> FlexAuto. جميع الحقوق محفوظة.</div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // اختبار الاتصال والجاهزية
    const testButton = document.getElementById('test_connection');
    const testResult = document.getElementById('test_result');
    
    if (testButton) {
        testButton.addEventListener('click', function() {
            // تغيير حالة الزر لإظهار التحميل
            testButton.disabled = true;
            testButton.innerHTML = '⏳ جاري الاختبار...';
            
            // محاكاة اختبار الاتصال (في الواقع يمكن استبداله باختبار حقيقي)
            setTimeout(function() {
                // اختبار سرعة الإنترنت
                const connectionSpeed = Math.random() * 100;
                
                if (connectionSpeed > 30) {
                    // سرعة جيدة
                    testResult.className = 'test-result test-success';
                    testResult.innerHTML = `
                        <strong>✅ الاتصال جيد!</strong>
                        <p>سرعة الإنترنت: ${connectionSpeed.toFixed(2)} Mbps</p>
                        <p>جهاز الفحص: تم اكتشافه</p>
                        <p>الاتصال مستقر ومناسب للبرمجة عن بعد</p>
                    `;
                } else {
                    // سرعة منخفضة
                    testResult.className = 'test-result test-error';
                    testResult.innerHTML = `
                        <strong>⚠️ تنبيه!</strong>
                        <p>سرعة الإنترنت: ${connectionSpeed.toFixed(2)} Mbps (منخفضة)</p>
                        <p>ننصح باستخدام اتصال أسرع للحصول على أفضل تجربة برمجة عن بعد</p>
                        <p>تأكد من الاتصال بشبكة مستقرة قبل بدء الجلسة</p>
                    `;
                }
                
                // إظهار النتيجة
                testResult.style.display = 'block';
                
                // إعادة الزر إلى حالته الطبيعية
                testButton.disabled = false;
                testButton.innerHTML = '⚡ اختبار سرعة الاتصال والجاهزية';
            }, 2000);
        });
    }
    
    // التحقق من رقم الشاصي VIN
    const vinInput = document.querySelector('input[name="vin"]');
    if (vinInput) {
        vinInput.addEventListener('input', function() {
            // تحويل إلى أحرف كبيرة
            this.value = this.value.toUpperCase();
            
            // التحقق من الطول
            if (this.value.length === 17) {
                // التحقق من التنسيق
                const vinPattern = /^[A-HJ-NPR-Z0-9]{17}$/;
                if (vinPattern.test(this.value)) {
                    this.style.borderColor = '#00c853';
                    this.style.boxShadow = '0 0 8px rgba(0, 200, 83, 0.5)';
                } else {
                    this.style.borderColor = '#ff5252';
                    this.style.boxShadow = '0 0 8px rgba(255, 82, 82, 0.5)';
                }
            } else {
                // إعادة تعيين التنسيق
                this.style.borderColor = '#3a4052';
                this.style.boxShadow = 'none';
            }
        });
    }
    
    // منع إرسال النموذج إذا لم تكن الحقول المطلوبة مكتملة
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(event) {
            // التحقق من الحقول المطلوبة
            const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
            let hasError = false;
            
            requiredFields.forEach(field => {
                if (!field.value) {
                    field.style.borderColor = '#ff5252';
                    hasError = true;
                }
            });
            
            // التحقق من رقم الشاصي
            if (vinInput && vinInput.value.length !== 17) {
                vinInput.style.borderColor = '#ff5252';
                hasError = true;
            }
            
            // التحقق من الموافقة على التعهد
            const legalAgreement = document.getElementById('legal_agreement');
            if (legalAgreement && !legalAgreement.checked) {
                const legalContainer = legalAgreement.closest('.checkbox-item');
                if (legalContainer) {
                    legalContainer.style.backgroundColor = 'rgba(255, 82, 82, 0.1)';
                    legalContainer.style.borderColor = 'rgba(255, 82, 82, 0.4)';
                }
                hasError = true;
            }
            
            if (hasError) {
                event.preventDefault();
                alert('يرجى إكمال جميع الحقول المطلوبة والتحقق من صحة البيانات قبل الإرسال.');
            }
        });
    }
});
</script>

</body>
</html>
<?php
// استخدام المحتوى المخزن
$page_content = ob_get_clean();

// تضمين قالب التصميم
include __DIR__ . '/includes/layout.php';
?>