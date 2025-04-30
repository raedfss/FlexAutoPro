<?php
// pro-subscription.php – صفحة الاشتراك الاحترافي تحت التطوير
$page_title = "الاشتراك الاحترافي - تحت التطوير";

$page_css = <<<CSS
.under-construction {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    text-align: center;
    min-height: 90vh;
    background-color: #f8f9fa;
    position: relative;
    overflow: hidden;
    padding: 40px 20px;
}

.under-construction svg {
    max-width: 300px;
    margin-bottom: 30px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%   { transform: translateY(0px); }
    50%  { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

.under-construction h1 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 15px;
    color: var(--primary);
}

.under-construction p {
    font-size: 1rem;
    color: #555;
    max-width: 600px;
}

.back-btn {
    margin-top: 30px;
}
CSS;

$page_content = <<<HTML
<div class="under-construction">
    <!-- صورة SVG المتحركة (مستخدمة سابقًا) -->
    <svg xmlns="http://www.w3.org/2000/svg" fill="#004080" height="100" viewBox="0 0 24 24" width="100">
        <path d="M0 0h24v24H0z" fill="none"/>
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 
        18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm1-13h-2v6h6v-2h-4z"/>
    </svg>

    <h1>🚀 الاشتراك الاحترافي (Pro Subscription)</h1>
    <p>نعمل حاليًا على تطوير نظام اشتراك متقدم لورش البرمجة الاحترافية، سيوفر مزايا حصرية وأدوات ذكية لتسهيل عملك اليومي.</p>
    <p class="text-muted mt-3">🛠️ هذه الخدمة تحت التطوير وسيتم إطلاقها قريبًا.</p>

    <a href="services.php" class="btn btn-primary back-btn">🔙 العودة إلى الخدمات</a>
</div>
HTML;

require_once 'includes/layout.php';
