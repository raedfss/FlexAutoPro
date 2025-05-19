// إخفاء التنبيهات تلقائيًا بعد 5 ثوانٍ
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = "opacity 0.5s ease-out";
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// منع الضغط على زر الإرسال أكثر من مرة
document.addEventListener('submit', function (e) {
    const form = e.target;
    const button = form.querySelector('button[type="submit"]');
    if (button) {
        button.disabled = true;
        button.innerText = '... جارٍ الإرسال';
    }
});
