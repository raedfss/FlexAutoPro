<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// إعداد معلومات الصفحة
$page_title = "الاشتراك الاحترافي - قريباً";
$hide_title = true; // إخفاء العنوان الافتراضي في القالب

// تنسيقات CSS المخصصة للصفحة
$page_css = <<<CSS
.coming-soon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    text-align: center;
    min-height: 80vh;
    padding: 40px 20px;
    position: relative;
    overflow: hidden;
}

.coming-soon::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('/assets/img/circuit-pattern.svg');
    background-size: cover;
    opacity: 0.03;
    z-index: 0;
}

.cs-content {
    position: relative;
    z-index: 1;
    max-width: 800px;
}

.cs-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 20px;
    color: #00d9ff;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.cs-subtitle {
    font-size: 1.2rem;
    color: #cbd5e1;
    margin-bottom: 40px;
}

.cs-description {
    color: #a0aec0;
    margin-bottom: 30px;
    font-size: 1.1rem;
    line-height: 1.6;
}

.cs-illustration {
    max-width: 350px;
    margin-bottom: 40px;
    filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.3));
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%   { transform: translateY(0px) rotate(0deg); }
    25%  { transform: translateY(-10px) rotate(1deg); }
    50%  { transform: translateY(0px) rotate(0deg); }
    75%  { transform: translateY(10px) rotate(-1deg); }
    100% { transform: translateY(0px) rotate(0deg); }
}

.features-preview {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    margin: 20px 0 40px;
}

.feature-item {
    background: rgba(15, 23, 42, 0.5);
    border-radius: 12px;
    padding: 20px;
    width: 250px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(66, 135, 245, 0.1);
    backdrop-filter: blur(5px);
    transition: all 0.3s ease;
}

.feature-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    border-color: rgba(0, 217, 255, 0.3);
}

.feature-icon {
    font-size: 2rem;
    color: #00d9ff;
    margin-bottom: 15px;
}

.feature-title {
    font-weight: bold;
    color: #ffffff;
    margin-bottom: 10px;
}

.feature-desc {
    color: #94a3b8;
    font-size: 0.9rem;
}

.cs-cta {
    margin-top: 30px;
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    justify-content: center;
}

.cs-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 25px;
    background: linear-gradient(135deg, #00d9ff, #0070cc);
    color: white;
    border-radius: 30px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.cs-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    background: linear-gradient(135deg, #00eaff, #0088ff);
}

.cs-btn-outline {
    background: transparent;
    border: 2px solid #00d9ff;
    color: #00d9ff;
}

.cs-btn-outline:hover {
    background: rgba(0, 217, 255, 0.1);
}

.progress-container {
    width: 100%;
    max-width: 500px;
    margin: 40px auto 20px;
}

.progress-bar {
    height: 6px;
    background: rgba(15, 23, 42, 0.5);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    width: 75%;
    background: linear-gradient(to right, #00d9ff, #0088ff);
    border-radius: 3px;
    position: relative;
    animation: progress-animation 2s ease-out;
}

@keyframes progress-animation {
    from { width: 0; }
    to { width: 75%; }
}

.progress-text {
    display: flex;
    justify-content: space-between;
    color: #94a3b8;
    font-size: 0.9rem;
}

.notify-container {
    margin-top: 40px;
    width: 100%;
    max-width: 500px;
}

.notify-title {
    font-size: 1.2rem;
    color: #ffffff;
    margin-bottom: 15px;
    font-weight: bold;
}

.notify-form {
    display: flex;
    gap: 10px;
}

.notify-input {
    flex: 1;
    padding: 12px 20px;
    border-radius: 30px;
    border: 1px solid rgba(66, 135, 245, 0.2);
    background: rgba(15, 23, 42, 0.5);
    color: #ffffff;
    outline: none;
    transition: all 0.3s ease;
}

.notify-input:focus {
    border-color: #00d9ff;
    box-shadow: 0 0 0 3px rgba(0, 217, 255, 0.2);
}

.notify-btn {
    padding: 12px 25px;
    background: linear-gradient(135deg, #00d9ff, #0070cc);
    color: white;
    border-radius: 30px;
    border: none;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.notify-btn:hover {
    background: linear-gradient(135deg, #00eaff, #0088ff);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .cs-title {
        font-size: 2rem;
    }
    
    .cs-subtitle {
        font-size: 1.1rem;
    }
    
    .cs-illustration {
        max-width: 280px;
    }
    
    .notify-form {
        flex-direction: column;
    }
    
    .notify-btn {
        width: 100%;
    }
}
CSS;

// JavaScript المخصص للصفحة
$page_js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    // تحقق من صحة البريد الإلكتروني
    const notifyForm = document.getElementById('notify-form');
    const emailInput = document.getElementById('notify-email');
    
    if(notifyForm) {
        notifyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = emailInput.value.trim();
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            if(!isValid) {
                alert('يرجى إدخال بريد إلكتروني صحيح');
                return;
            }
            
            // عند النجاح نظهر رسالة تأكيد ونفرغ الحقل
            alert('تم التسجيل بنجاح! سنخبرك عند إطلاق الخدمة.');
            emailInput.value = '';
        });
    }
    
    // إضافة عداد تنازلي للإطلاق (مجرد محاكاة)
    const launchDate = new Date();
    launchDate.setDate(launchDate.getDate() + 30); // إطلاق بعد 30 يوم
    
    const countdownEl = document.getElementById('countdown');
    
    function updateCountdown() {
        const now = new Date();
        const diff = launchDate - now;
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        
        if(countdownEl) {
            countdownEl.textContent = days;
        }
    }
    
    updateCountdown();
});
JS;

// محتوى الصفحة
ob_start();
?>
<div class="coming-soon">
    <div class="cs-content">
        <svg class="cs-illustration" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
            <style>
                .stroke { fill:none; stroke:#00d9ff; stroke-width:8; stroke-linecap:round; stroke-linejoin:round; }
                .fill-primary { fill:#00d9ff; }
                .fill-dark { fill:#0f172a; }
                .fill-light { fill:#a0aec0; }
            </style>
            <circle cx="250" cy="250" r="200" class="fill-dark" />
            <path d="M180,170 L320,170 L320,330 L180,330 Z" class="stroke" />
            <rect x="200" y="130" width="100" height="40" rx="10" class="fill-primary" />
            <rect x="220" y="200" width="60" height="20" rx="5" class="fill-light" />
            <rect x="220" y="240" width="120" height="10" rx="5" class="fill-light" />
            <rect x="220" y="260" width="100" height="10" rx="5" class="fill-light" />
            <rect x="220" y="280" width="80" height="10" rx="5" class="fill-light" />
            <circle cx="380" cy="150" r="30" class="fill-primary" />
            <circle cx="120" cy="320" r="40" class="fill-primary" opacity="0.5" />
            <path d="M100,100 C150,80 200,120 250,100 S350,50 400,100" class="stroke" />
            <path d="M100,390 C150,370 200,410 250,390 S350,350 400,390" class="stroke" />
        </svg>
        
        <h1 class="cs-title">🚀 الاشتراك الاحترافي قادم قريباً</h1>
        <p class="cs-subtitle">نعمل على توفير تجربة برمجية متكاملة لورش السيارات والفنيين المحترفين</p>
        
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div class="progress-text">
                <span>اكتمال التطوير: 75%</span>
                <span>متبقي <span id="countdown">30</span> يوم للإطلاق</span>
            </div>
        </div>
        
        <div class="features-preview">
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-unlock-alt"></i></div>
                <div class="feature-title">وصول غير محدود</div>
                <div class="feature-desc">وصول كامل لجميع قواعد بيانات البرمجة لمختلف أنواع السيارات</div>
            </div>
            
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                <div class="feature-title">استجابة سريعة</div>
                <div class="feature-desc">أولوية في معالجة الطلبات ودعم فني متميز على مدار الساعة</div>
            </div>
            
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-percentage"></i></div>
                <div class="feature-title">خصومات حصرية</div>
                <div class="feature-desc">خصومات تصل إلى 30% على جميع خدمات البرمجة والتشخيص</div>
            </div>
        </div>
        
        <p class="cs-description">
            نعمل على تطوير نظام اشتراك متكامل يوفر لك أدوات متقدمة وميزات حصرية تساعدك في تقديم خدمات برمجة سيارات احترافية. 
            اشترك في القائمة البريدية لتكون أول من يعلم عند إطلاق الخدمة والحصول على عروض خاصة.
        </p>
        
        <div class="notify-container">
            <h3 class="notify-title">أول من يعلم عند الإطلاق</h3>
            <form id="notify-form" class="notify-form">
                <input type="email" id="notify-email" class="notify-input" placeholder="أدخل بريدك الإلكتروني" required>
                <button type="submit" class="notify-btn">إشعاري عند الإطلاق</button>
            </form>
        </div>
        
        <div class="cs-cta">
            <a href="services.php" class="cs-btn"><i class="fas fa-arrow-right"></i> العودة إلى الخدمات</a>
            <a href="mailto:raedfss@hotmail.com" class="cs-btn cs-btn-outline"><i class="fas fa-envelope"></i> استفسار عن الخدمة</a>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();

// تضمين ملف القالب
require_once 'includes/layout.php';
?>