# 1) نستخدم صورة PHP 8.1 مع Apache
FROM php:8.1-apache

# 2) نثبت libpq-dev لتطوير PostgreSQL، ثم نثبت امتدادات PDO لكل من MySQL و PostgreSQL
RUN apt-get update \
 && apt-get install -y libpq-dev \
 && docker-php-ext-install pdo pdo_mysql pdo_pgsql mysqli \
 && rm -rf /var/lib/apt/lists/*

# 3) نفعّل mod_rewrite إذا كنت تستخدمه في روابط صديقة (اختياري)
RUN a2enmod rewrite

# 4) ننسخ كل ملفات المشروع إلى مجلد الويب الافتراضي
COPY . /var/www/html/

# 5) (اختياري) نمنح الأذونات لمجلد uploads إن وجِد
RUN if [ -d "/var/www/html/uploads" ]; then chown -R www-data:www-data /var/www/html/uploads; fi

# 6) نُعلِن عن البورت 80 داخل الحاوية
EXPOSE 80

# 7) نُشغّل Apache في المقدمة
CMD ["apache2-foreground"]

