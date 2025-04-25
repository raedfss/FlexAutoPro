# ────────────────────────────────────────────────
# FlexAutoPro Dockerfile
# • يستخدم PHP 8.1 مع Apache
# • يثبت امتدادات MySQL و PostgreSQL
# • يضبط Apache ليقرأ المنفذ الذي يزوده Railway (متغيّر PORT)
# ────────────────────────────────────────────────

# 1) نبدأ بصورة PHP 8.1 مع Apache
FROM php:8.1-apache

# 2) نثبت مكتبات بناء PostgreSQL ثم امتدادات PDO و mysqli
RUN apt-get update \
 && apt-get install -y libpq-dev \
 && docker-php-ext-install pdo pdo_mysql pdo_pgsql mysqli \
 && rm -rf /var/lib/apt/lists/*

# 3) نفعل mod_rewrite (اختياري لو تستخدم روابط صديقة)
RUN a2enmod rewrite

# 4) نعرّف متغيّر البيئة PORT (Railway يزوده عادة بقيمة 9000)
ENV PORT 9000

# 5) نغيّر إعدادات Apache ليستمع على المنفذ $PORT بدل 80
RUN sed -i "s/80/${PORT}/g" \
    /etc/apache2/ports.conf \
    /etc/apache2/sites-available/*.conf

# 6) ننسخ كل ملفات مشروعك إلى مجلد الويب الافتراضي
COPY . /var/www/html/

# 7) إذا كان لديك مجلد uploads، نمنحه الأذونات الصحيحة
RUN if [ -d "/var/www/html/uploads" ]; then \
      chown -R www-data:www-data /var/www/html/uploads; \
    fi

# 8) نعلن للـ container أننا نستخدم المنفذ $PORT
EXPOSE ${PORT}

# 9) نشغّل Apache في المقدمة
CMD ["apache2-foreground"]
