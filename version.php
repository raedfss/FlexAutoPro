<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// إعدادات الصفحة
$page_title = "سجل الإصدارات";
$hide_title = false;

// تحديد التنسيقات الخاصة بالصفحة
$page_css = '
<style>
    .changelog-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .changelog-header {
        margin-bottom: 30px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 15px;
    }
    
    .changelog-header h1 {
        color: #00d9ff;
        font-size: 28px;
        margin-bottom: 10px;
    }
    
    .changelog-header p {
        color: #a0aec0;
        font-size: 16px;
    }
    
    .version-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .version-item {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        position: relative;
    }
    
    .version-item:last-child {
        border-bottom: none;
    }
    
    .version-title {
        font-weight: bold;
        font-size: 20px;
        color: #00d9ff;
        margin-right: 5px;
    }
    
    .version-date {
        font-size: 14px;
        color: #a0aec0;
        margin-right: 10px;
    }
    
    .version-tag {
        background-color: rgba(0, 217, 255, 0.15);
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
        margin-right: 10px;
        color: #00d9ff;
        text-transform: uppercase;
    }
    
    .version-tag.stable {
        background-color: rgba(0, 255, 136, 0.15);
        color: #00ff88;
    }
    
    .version-tag.latest {
        background-color: rgba(255, 204, 0, 0.15);
        color: #ffcc00;
    }
    
    .version-tag.beta {
        background-color: rgba(255, 107, 107, 0.15);
        color: #ff6b6b;
    }
    
    .version-tag.alpha {
        background-color: rgba(148, 82, 255, 0.15);
        color: #9452ff;
    }
    
    .version-tag.security {
        background-color: rgba(255, 0, 0, 0.15);
        color: #ff5555;
    }
    
    .version-summary {
        margin-top: 10px;
        font-size: 16px;
        line-height: 1.7;
        color: #f8fafc;
    }
    
    .version-details {
        margin-top: 15px;
        padding-right: 20px;
    }
    
    .version-details ul {
        list-style-type: disc;
        padding-right: 20px;
        margin-top: 10px;
    }
    
    .version-details li {
        margin-bottom: 8px;
        line-height: 1.6;
        color: #e2e8f0;
    }
    
    .version-files {
        margin-top: 15px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .file-tag {
        background-color: #1a2234;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 13px;
        color: #cbd5e0;
        display: inline-block;
        border: 1px solid #2d3748;
    }
    
    .file-tag code {
        background-color: rgba(0, 0, 0, 0.2);
        padding: 2px 5px;
        border-radius: 3px;
        font-family: Consolas, monospace;
        font-size: 12px;
    }
    
    .back-link {
        margin-top: 30px;
        text-align: center;
    }
    
    .back-link a {
        display: inline-block;
        background-color: #1e293b;
        color: #f8fafc;
        padding: 10px 20px;
        border-radius: 5px;
        transition: all 0.3s;
        text-decoration: none;
        border: 1px solid #2d3748;
    }
    
    .back-link a:hover {
        background-color: #2d3748;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    
    .version-badge {
        position: absolute;
        left: 0;
        top: 5px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #0f172a;
        border: 2px solid #00d9ff;
        color: #00d9ff;
        font-weight: bold;
        font-size: 18px;
    }
    
    .version-badge.major {
        background: rgba(0, 217, 255, 0.1);
        border-color: #00d9ff;
        color: #00d9ff;
    }
    
    .version-badge.minor {
        background: rgba(255, 204, 0, 0.1);
        border-color: #ffcc00;
        color: #ffcc00;
    }
    
    .version-badge.patch {
        background: rgba(148, 82, 255, 0.1);
        border-color: #9452ff;
        color: #9452ff;
    }
    
    .git-command {
        background-color: #0f172a;
        border-radius: 6px;
        border: 1px solid #2d3748;
        padding: 15px;
        margin-top: 15px;
        font-family: Consolas, monospace;
        position: relative;
    }
    
    .git-command code {
        color: #e2e8f0;
        display: block;
        line-height: 1.5;
        white-space: pre;
        font-size: 14px;
        direction: ltr;
        text-align: left;
    }
    
    .git-command .code-label {
        position: absolute;
        top: -10px;
        right: 10px;
        background-color: #0f172a;
        padding: 2px 8px;
        font-size: 12px;
        color: #a0aec0;
        border-radius: 4px;
        border: 1px solid #2d3748;
    }
    
    .git-command .copy-btn {
        position: absolute;
        top: 5px;
        left: 5px;
        background-color: transparent;
        border: none;
        color: #a0aec0;
        cursor: pointer;
        font-size: 14px;
        transition: color 0.3s;
    }
    
    .git-command .copy-btn:hover {
        color: #00d9ff;
    }
    
    .security-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        direction: rtl;
    }
    
    .security-table th, .security-table td {
        padding: 8px 12px;
        text-align: right;
        border: 1px solid #2d3748;
    }
    
    .security-table th {
        background-color: rgba(0, 217, 255, 0.1);
        color: #00d9ff;
        font-weight: bold;
    }
    
    .security-table td {
        color: #e2e8f0;
    }
    
    .security-table tr:nth-child(even) {
        background-color: rgba(255, 255, 255, 0.03);
    }
    
    @media (max-width: 768px) {
        .version-badge {
            position: static;
            margin-bottom: 10px;
        }
        
        .version-item {
            padding-right: 0;
        }
    }
</style>';

// محتوى الصفحة
ob_start();
?>

<div class="changelog-container">
    <div class="changelog-header">
        <h1>سجل الإصدارات - مشروع فلكس أوتو</h1>
        <p>تتبع تقدم المشروع والتحسينات المضافة في كل إصدار</p>
    </div>
    
    <ul class="version-list">
        <!-- إضافة الإصدار الجديد 1.1.2 -->
        <li class="version-item">
            <div class="version-badge patch">1.1</div>
            <span class="version-title">v1.1.2</span>
            <span class="version-date">1 May 2025</span>
            <span class="version-tag latest">أحدث إصدار</span>
            <span class="version-tag security">تحديث أمني</span>

            <div class="version-summary">
                تحديث شامل للأمان وسد الثغرات المحتملة في جميع نماذج النظام، وتحسين تجربة المستخدم للعملاء والإدارة.
            </div>

            <div class="version-details">
                <ul>
                    <li>إنشاء صفحات التسجيل وتسجيل الدخول الأساسية.</li>
                    <li>تكوين الجداول الأساسية (Users, Tickets) في قاعدة البيانات.</li>
                    <li>تطوير الوظائف الأساسية للتعامل مع بيانات المستخدم.</li>
                </ul>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge minor">0.1</div>
            <span class="version-title">v0.1.0</span>
            <span class="version-date">15 فبراير 2025</span>
            <span class="version-tag alpha">بدء المشروع</span>
            
            <div class="version-summary">
                مرحلة بدء المشروع وتهيئة بيئة التطوير.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>تهيئة بيئة التطوير باستخدام XAMPP.</li>
                    <li>إنشاء الهيكلية الأولية للملفات والمجلدات.</li>
                    <li>وضع خطة العمل وتحديد المتطلبات الأساسية للمشروع.</li>
                </ul>
            </div>
        </li>
    </ul>
    
    <div class="back-link">
        <a href="home.php">العودة للصفحة الرئيسية</a>
    </div>
</div>

<script>
    // دالة نسخ أوامر Git إلى الحافظة
    function copyToClipboard(button) {
        const gitCommandElement = button.nextElementSibling;
        const textArea = document.createElement('textarea');
        textArea.value = gitCommandElement.textContent;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        button.textContent = 'تم النسخ!';
        setTimeout(() => {
            button.textContent = 'نسخ';
        }, 2000);
    }
    
    // دالة إخفاء رسائل النجاح والفشل بعد فترة
    document.addEventListener('DOMContentLoaded', function() {
        // إخفاء رسائل النجاح والفشل بعد 5 ثوانٍ
        setTimeout(function() {
            const messages = document.querySelectorAll('.alert-success, .alert-danger, .alert-warning, .alert-info');
            messages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 500);
            });
        }, 5000);
    });
</script>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/includes/layout.php';
?>تعزيز حماية النظام ضد هجمات CSRF و XSS و SQL Injection لجميع نماذج الإدخال.</li>
                    <li>تحسين التحقق من صحة البيانات باستخدام Sanitization و Validation للمدخلات.</li>
                    <li>تطبيق حماية ملفات إضافية لمنع تنفيذ أو رفع ملفات ضارة محتملة.</li>
                    <li>إضافة إشعارات نجاح وفشل ديناميكية داخل النماذج تختفي تلقائيًا بعد 5 ثوانٍ.</li>
                    <li>تطبيق <code>htmlspecialchars()</code> لحماية جميع البيانات المعروضة.</li>
                    <li>استخدام <code>password_hash()</code> و <code>password_verify()</code> لتشفير كلمات المرور بشكل آمن.</li>
                    <li>حذف الملفات المؤقتة مثل <code>reset_users.php</code> و <code>change_admin_password.php</code> بعد الاستخدام.</li>
                    <li>حذف الملف <code>setup_database.php</code> واستبداله بـ <code>setup_database_fixed.php</code> ثم حذفه.</li>
                </ul>
                
                <table class="security-table">
                    <tr>
                        <th>الملف</th>
                        <th>نوع الحماية المضافة</th>
                    </tr>
                    <tr>
                        <td>ticket_submit.php</td>
                        <td>حماية CSRF + Sanitization + تأكيد صلاحية البيانات</td>
                    </tr>
                    <tr>
                        <td>edit_ticket.php</td>
                        <td>حماية الجلسة + تأكيد ملكية التذكرة + XSS Filter</td>
                    </tr>
                    <tr>
                        <td>ecu-tuning.php</td>
                        <td>حماية الملفات + XSS + CSRF</td>
                    </tr>
                    <tr>
                        <td>airbag_reset.php</td>
                        <td>حماية رفع الملفات + منع تنفيذ الملفات الضارة</td>
                    </tr>
                    <tr>
                        <td>admin_tickets.php</td>
                        <td>حماية من CSRF عند استخدام mark_seen و cancel_ticket</td>
                    </tr>
                    <tr>
                        <td>login.php / register.php</td>
                        <td>Sanitization + حماية SQL Injection + فلترة البريد</td>
                    </tr>
                    <tr>
                        <td>vin-database.php</td>
                        <td>حماية كاملة من XSS + CSRF + Regex مخصص لـ VIN</td>
                    </tr>
                </table>
            </div>

            <div class="version-files">
                <span class="file-tag">تحديث <code>ticket_submit.php</code></span>
                <span class="file-tag">تحديث <code>edit_ticket.php</code></span>
                <span class="file-tag">تحديث <code>ecu-tuning.php</code></span>
                <span class="file-tag">تحديث <code>airbag_reset.php</code></span>
                <span class="file-tag">تحديث <code>admin_tickets.php</code></span>
                <span class="file-tag">تحديث <code>login.php</code></span>
                <span class="file-tag">تحديث <code>register.php</code></span>
                <span class="file-tag">تحديث <code>vin-database.php</code></span>
                <span class="file-tag">حذف <code>setup_database.php</code></span>
                <span class="file-tag">حذف <code>reset_users.php</code></span>
                <span class="file-tag">حذف <code>change_admin_password.php</code></span>
            </div>

            <div class="git-command">
                <span class="code-label">أوامر Git</span>
                <button class="copy-btn" onclick="copyToClipboard(this)">نسخ</button>
                <code>cd D:\Projects\FlexAutoPro
git add .
git commit -m "🔒 v1.1.2: تعزيز أمان النظام، سد الثغرات، وتحسين تجربة المستخدم"
git tag -a v1.1.2 -m "تحديث أمني شامل v1.1.2"
git push origin main
git push origin v1.1.2</code>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge patch">1.1</div>
            <span class="version-title">v1.1.1</span>
            <span class="version-date">1 May 2025</span>

            <div class="version-summary">
                تحسينات في صفحة <code>admin_versions.php</code> وظهور صفحة سجل الإصدارات من قاعدة البيانات تلقائيًا.
            </div>

            <div class="version-details">
                <ul>
                    <li>إنشاء صفحة <code>admin_versions.php</code> لإدارة الإصدارات من خلال لوحة التحكم.</li>
                    <li>إضافة صفحة <code>get_version.php</code> لدعم التعديل السريع عبر واجهة المستخدم.</li>
                    <li>تحسين تخزين الإصدارات وربطها تلقائيًا بصفحة <code>version.php</code>.</li>
                    <li>عرض سجل الإصدارات ديناميكيًا بدلًا من الكتابة اليدوية (للاستخدام مستقبلاً).</li>
                </ul>
            </div>

            <div class="version-files">
                <span class="file-tag">إضافة <code>admin_versions.php</code></span>
                <span class="file-tag">إضافة <code>get_version.php</code></span>
                <span class="file-tag">تحسين <code>version.php</code></span>
            </div>

            <div class="git-command">
                <span class="code-label">أوامر Git</span>
                <button class="copy-btn" onclick="copyToClipboard(this)">نسخ</button>
                <code>cd D:\Projects\FlexAutoPro
git add .
git commit -m "📦 v1.1.1: إضافة إدارة الإصدارات ديناميكيًا وتحسين التصميم العام"
git tag -a v1.1.1 -m "أحدث إصدار 1.1.1"
git push origin main
git push origin v1.1.1</code>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge major">1.1</div>
            <span class="version-title">v1.1.0</span>
            <span class="version-date">1 مايو 2025</span>
            <span class="version-tag stable">نسخة مستقرة</span>
            
            <div class="version-summary">
                اعتماد النسخة الحالية كنقطة استقرار رئيسية مع تطبيق أفضل ممارسات هندسة البرمجيات وتحضير المشروع للميزات المستقبلية.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>إعادة هيكلة شاملة للكود مع تطبيق نمط MVC بشكل جزئي لتحسين قابلية الصيانة.</li>
                    <li>تطوير وتحسين نموذج تعديل ECU الجديد مع تحقق كامل من الإدخالات.</li>
                    <li>توحيد واجهة المستخدم باستخدام نظام تصميم متناسق عبر جميع الصفحات.</li>
                    <li>تحسين أمان التطبيق وتطبيق أفضل ممارسات OWASP للحماية من هجمات SQL Injection و XSS.</li>
                    <li>تحضير البنية التحتية لإضافة ميزات الدفع ولوحة الإدارة المتكاملة.</li>
                </ul>
            </div>
            
            <div class="version-files">
                <span class="file-tag">تحسين <code>ecu-tuning.php</code></span>
                <span class="file-tag">إضافة <code>includes/forms/ecu-tuning-form.php</code></span>
                <span class="file-tag">تحديث <code>assets/css/style.css</code></span>
            </div>
            
            <div class="git-command">
                <span class="code-label">أوامر Git</span>
                <button class="copy-btn" onclick="copyToClipboard(this)">نسخ</button>
                <code>cd D:\Projects\FlexAutoPro
git add .
git commit -m "🔖 v1.1.0: إصدار مستقر مع تنظيم شامل، تحسين ECU، بنية تصميم موحدة"
git tag -a v1.1.0 -m "إصدار مستقر 1.1.0"
git push origin main
git push origin v1.1.0</code>
            </div>
        </li>
        
        <!-- باقي الإصدارات السابقة -->
        <li class="version-item">
            <div class="version-badge minor">1.0</div>
            <span class="version-title">v1.0.2</span>
            <span class="version-date">25 أبريل 2025</span>
            
            <div class="version-summary">
                تحديث شامل لصفحة <code>key-code.php</code> مع تحسينات كبيرة في واجهة المستخدم والتحقق من البيانات.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>إعادة تنظيم الكود مع فصل عرض البيانات عن منطق المعالجة.</li>
                    <li>تحسين التصميم البصري والرسائل الظاهرة للمستخدم.</li>
                    <li>تنفيذ التحقق من البيانات على جانب العميل والخادم لمنع الإرسال غير المكتمل.</li>
                    <li>تحسين طريقة عرض بيانات الطلب (رقم الطلب والشاسيه) بطريقة احترافية.</li>
                    <li>تصحيح رابط العودة ليشير إلى <code>home.php</code> بدلاً من الصفحة الرئيسية.</li>
                </ul>
            </div>
            
            <div class="version-files">
                <span class="file-tag">تحديث <code>key-code.php</code></span>
                <span class="file-tag">تعديل <code>assets/js/form-validation.js</code></span>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge patch">1.0</div>
            <span class="version-title">v1.0.1</span>
            <span class="version-date">20 أبريل 2025</span>
            
            <div class="version-summary">
                تحسينات في التوجيه وإضافة زر "آخر التحديثات" للمستخدمين.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>تحسين نظام التوجيه لتوجيه المستخدم العادي إلى <code>my_tickets.php</code> بدلاً من <code>tickets.php</code>.</li>
                    <li>إضافة زر "آخر التحديثات والتعديلات" في الصفحة الرئيسية للوصول السريع إلى سجل الإصدارات.</li>
                    <li>إصلاح أخطاء متفرقة في واجهة المستخدم.</li>
                </ul>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge major">1.0</div>
            <span class="version-title">v1.0.0</span>
            <span class="version-date">15 أبريل 2025</span>
            <span class="version-tag stable">إصدار مستقر</span>
            
            <div class="version-summary">
                الإصدار الأولي المستقر للموقع مع اكتمال جميع الوظائف الأساسية.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>إطلاق جميع الوظائف الأساسية للمستخدمين: التسجيل، تسجيل الدخول، إنشاء التذاكر، متابعة الحالة.</li>
                    <li>تكامل نظام إدارة التذاكر مع واجهة مستخدم سهلة الاستخدام.</li>
                    <li>استكمال نظام الإشعارات وتتبع حالة الطلبات.</li>
                    <li>اختبار شامل للتطبيق وإصلاح جميع المشكلات المعروفة.</li>
                </ul>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge minor">0.9</div>
            <span class="version-title">v0.9.0</span>
            <span class="version-date">5 أبريل 2025</span>
            <span class="version-tag beta">نسخة بيتا</span>
            
            <div class="version-summary">
                مرحلة بيتا النهائية مع اختبار كامل للميزات واستعداد للإصدار المستقر.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>اختبار كامل لجميع وظائف التطبيق في بيئات متعددة.</li>
                    <li>إصلاح مجموعة من الأخطاء المكتشفة أثناء الاختبار.</li>
                    <li>تحسين أداء النظام وتجربة المستخدم.</li>
                </ul>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge patch">0.7</div>
            <span class="version-title">v0.7.0</span>
            <span class="version-date">25 مارس 2025</span>
            
            <div class="version-summary">
                تحسينات عامة في التصميم وإضافة نظام الإشعارات.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>تحسين التصميم العام للصفحات بألوان وخطوط متناسقة.</li>
                    <li>إضافة نظام إشعارات وتنبيهات المستخدم.</li>
                    <li>إصلاح أخطاء متعددة في النموذج الأولي.</li>
                </ul>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge minor">0.5</div>
            <span class="version-title">v0.5.0</span>
            <span class="version-date">15 مارس 2025</span>
            <span class="version-tag alpha">نموذج أولي</span>
            
            <div class="version-summary">
                نموذج أولي عملي مع إمكانية إرسال التذاكر وتخزينها.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>تطوير نظام أساسي لإرسال التذاكر.</li>
                    <li>تنفيذ قاعدة البيانات لتخزين طلبات المستخدمين.</li>
                    <li>إنشاء الهيكل الأساسي لكيفية معالجة وتتبع الطلبات.</li>
                </ul>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge patch">0.4</div>
            <span class="version-title">v0.4.0</span>
            <span class="version-date">5 مارس 2025</span>
            
            <div class="version-summary">
                تطوير واجهات أولية وربطها بقاعدة البيانات.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>تطوير واجهات Home و Login الأولية.</li>
                    <li>ربط النظام بقاعدة البيانات للتحقق من المستخدم.</li>
                    <li>إنشاء نظام جلسات أساسي للحفاظ على حالة تسجيل الدخول.</li>
                </ul>
            </div>
        </li>
        
        <li class="version-item">
            <div class="version-badge patch">0.2</div>
            <span class="version-title">v0.2.0</span>
            <span class="version-date">25 فبراير 2025</span>
            
            <div class="version-summary">
                إنشاء صفحات التسجيل وتكوين الجداول الأساسية.
            </div>
            
            <div class="version-details">
                <ul>
                    <li>