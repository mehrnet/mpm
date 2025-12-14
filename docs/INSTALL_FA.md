# راهنمای نصب و تنظیمات

راهنمای کامل برای نصب، تنظیم و شروع استفاده از MPM - مدیریت بسته های Mehr.

<!--html-ignore-->

## فهرست مطالب

- [نصب](#نصب)
- [اولین اجرا](#اولین-اجرا)
- [پیکربندی](#پیکربندی)
- [شروع کار](#شروع-کار)
- [توسعه بسته](#توسعه-بسته)
- [تنظیم مخزن](#تنظیم-مخزن)
- [استقرار](#استقرار)

<!--/html-ignore-->

---

## نصب

### نصب سریع

```bash
# دانلود mpm.php
wget https://raw.githubusercontent.com/mehrnet/mpm/main/mpm.php

# تعیین دسترسی ها
chmod 644 mpm.php

# تست نصب
php mpm.php echo "Hello, World!"
```

### نصب دستی

1. فایل `mpm.php` را از مخزن دانلود کنید
2. آن را در ریشه پروژه یا دایرکتوری سرور وب قرار دهید
3. اطمینان حاصل کنید که PHP 7.0+ نصب است
4. تایید کنید که افزونه `ZipArchive` فعال است (برای مدیریت بسته)

### الزامات

- **PHP**: نسخه 7.0 یا بالاتر
- **افزونه ها**: `ZipArchive` (برای مدیریت بسته)
- **دسترسی ها**: دسترسی خواندن/نوشتن به دایرکتوری پروژه

## اولین اجرا

در اولین اجرا، MPM به طور خودکار فایل های پیکربندی را تولید می کند:

```bash
# هر فرمندی را برای مقدار دهی اولیه اجرا کنید
php mpm.php ls
```

این فایل های زیر را ایجاد می کند:

```
.config/
├── key              # کلید API (64 کاراکتر هگزادسیمال)
├── repos.json       # پیکربندی آینه های مخزن
├── packages.json    # ثبت بسته های نصب شده
└── path.json        # الگوهای کننده فرماند
```

### کلید API

کلید API شما در اولین دسترسی HTTP نمایش داده می شود یا به فایل `.config/key` در اولین اجرای CLI ذخیره می شود:

```bash
# مشاهده کلید API شما
cat .config/key
```

**مهم:** کلید API خود را محفوظ نگه دارید. هر کسی با دسترسی می تواند فرماند اجرا کند.

## پیکربندی

### آینه های مخزن

برای سفارشی کردن مخازن بسته، `.config/repos.json` را ویرایش کنید:

```json
{
  "main": [
    "https://raw.githubusercontent.com/mehrnet/mpm-repo/refs/heads/main/main"
  ]
}
```

هر ورودی یک URL کامل به پوشه مخزن است که شامل `database.json` و فایل‌های ZIP بسته می‌باشد. آینه‌ها به ترتیب در صورت شکست امتحان می‌شوند.

### الگوهای کننده فرماند

برای سفارشی کردن محلی جستجوی shell برای فرماندهای سفارشی، `.config/path.json` را ویرایش کنید:

```json
[
  "app/packages/[name]/handler.php",
  "bin/[name].php",
  "custom/handlers/[name].php"
]
```

جانشین `[name]` با نام فرمند می شود.

### پیکربندی خاص محیط

برای محیط های مختلف، می توانید:

1. **دایرکتوری های پیکربندی مختلف استفاده کنید:**
    ```php
    // اصلاح ثابت ها در mpm.php (توصیه نشده است)
    const DIR_CONFIG = '.config';
    ```

2. **Symlink پیکربندی:**
    ```bash
    ln -s .config.production .config
    ```

3. **متغیرهای محیطی:**
    دسترسی از طریق `php mpm.php env list`

## شروع کار

### حالت CLI (توصیه برای توسعه)

```bash
# نمایش فهرست فایل ها
php mpm.php ls

# خواندن محتویات فایل
php mpm.php cat mpm.php | head -20

# ایجاد دایرکتوری
php mpm.php mkdir tmp

# کپی فایل ها
php mpm.php cp mpm.php shell.backup.php

# مدیریت بسته ها
php mpm.php pkg search database
php mpm.php pkg add users
php mpm.php pkg list
php mpm.php pkg upgrade
```

### حالت HTTP (تولید)

1. **سرور توسعه PHP را شروع کنید (تست کردن):**
    ```bash
    php -S localhost:8000
    ```

2. **کلید API خود را دریافت کنید:**
    ```bash
    KEY=$(cat .config/key)
    ```

3. **فرماند ها را اجرا کنید:**
    ```bash
    # نمایش فهرست فایل ها
    curl "http://localhost:8000/mpm.php/$KEY/ls"

    # خواندن فایل
    curl "http://localhost:8000/mpm.php/$KEY/cat/README.md"

    # مدیریت بسته ها
    curl "http://localhost:8000/mpm.php/$KEY/pkg/list"
    curl "http://localhost:8000/mpm.php/$KEY/pkg/add/users"
    ```

### سرور وب تولید

برای تولید، از سرور وب مناسب استفاده کنید:

**Nginx:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/shell;
    index mpm.php;

    location / {
        try_files $uri $uri/ /mpm.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index mpm.php;
        include fastcgi_params;
    }
}
```

**Apache (.htaccess):**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ mpm.php/$1 [L,QSA]
```

## توسعه بسته

### ایجاد بسته

1. **ساختار بسته ایجاد کنید:**
    ```
    mypackage/
    ├── handler.php       # لازم: نقطه ورود
    ├── package.json      # لازم: فرادادها
    ├── lib/             # اختیاری: کد کتابخانه
    └── data/            # اختیاری: فایل های داده
    ```

2. **handler.php بنویسید:**
    ```php
    <?php
    return function(array $args): string {
        $action = $args[0] ?? 'help';

        switch ($action) {
            case 'list':
                return "Item 1\nItem 2\nItem 3";

            case 'create':
                $name = $args[1] ?? null;
                if (!$name) {
                    throw new \RuntimeException('نام لازم است', 400);
                }
                return "Created: $name";

            default:
                return "Actions: list, create";
        }
    };
    ```

3. **package.json ایجاد کنید:**
    ```json
    {
        "id": "mypackage",
        "name": "My Package",
        "version": "1.0.0",
        "description": "Package description",
        "author": "Your Name",
        "license": "MIT",
        "dependencies": []
    }
    ```

### فیلدهای فرادادهای بسته

| فیلد | نوع | لازم | توضیح |
|-----|------|------|--------|
| `id` | رشته | بله | کوچک، بدون فاصله، استفاده شده در URL ها |
| `name` | رشته | بله | نام قابل خواندن |
| `version` | رشته | بله | نسخه درجه بندی معنایی (X.Y.Z) |
| `description` | رشته | بله | توضیح مختصر |
| `author` | رشته | نه | نویسنده بسته |
| `license` | رشته | نه | شناسه مجوز (MIT, Apache-2.0, و غیره) |
| `dependencies` | آرایه | نه | آرایه شناسه های بسته |

### تست بسته شما

**حالت CLI:**
```bash
# نصب دستی
mkdir -p app/packages/mypackage
cp -r mypackage/* app/packages/mypackage/

# تست فرماند ها
php mpm.php mypackage list
php mpm.php mypackage create item-name
```

**حالت HTTP:**
```bash
KEY=$(cat .config/key)
curl "http://localhost:8000/mpm.php/$KEY/mypackage/list"
curl "http://localhost:8000/mpm.php/$KEY/mypackage/create/item-name"
```

### بهترین عملکردها

1. **اعتبارسنجی ورودی:**
    ```php
    $id = $args[0] ?? null;
    if (!$id || !is_numeric($id)) {
        throw new \RuntimeException('شناسه نامعتبر است', 400);
    }
    ```

2. **مدیریت خطا:**
    ```php
    try {
        $data = json_decode(file_get_contents('data.json'), true);
        if (!$data) {
            throw new \RuntimeException('JSON نامعتبر است', 400);
        }
    } catch (\Throwable $e) {
        throw new \RuntimeException("Error: {$e->getMessage()}", 400);
    }
    ```

3. **امنیت:**
    ```php
    // نادرست: استفاده مستقیم از ورودی کاربر
    $file = $args[0];
    return file_get_contents($file);  // آسیب پذیر برای پیمایش مسیر

    // درست: اعتبار سنجی و محدود کردن مسیرها
    $file = basename($args[0] ?? '');  // حذف اجزای مسیر
    $path = "app/data/$file";
    if (!file_exists($path)) {
        throw new \RuntimeException('فایل پیدا نشد', 404);
    }
    return file_get_contents($path);
    ```

## تنظیم مخزن

### ایجاد مخزن بسته

1. **ساختار مخزن ایجاد کنید:**
    ```
    my-repo/
    └── main/
        ├── database.json
        ├── mypackage-1.0.0.zip
        └── otherapkg-1.0.0.zip
    ```

2. **ZIP بسته بسازید:**
    ```bash
    cd mypackage
    zip -r ../my-repo/main/mypackage-1.0.0.zip .
    ```

3. **Checksum محاسبه کنید:**
    ```bash
    sha256sum my-repo/main/mypackage-1.0.0.zip
    # Output: abc123def456...  mypackage-1.0.0.zip
    ```

4. **database.json ایجاد کنید:**
    ```json
    {
      "version": 1,
      "packages": {
        "mypackage": {
          "name": "My Package",
          "description": "Package description",
          "versions": {
            "1.0.0": {
              "version": "1.0.0",
              "dependencies": [],
              "checksum": "sha256:abc123def456...",
              "size": 2048,
              "download_url": "mypackage-1.0.0.zip",
              "released_at": "2025-01-15"
            }
          },
          "latest": "1.0.0",
          "author": "Your Name",
          "license": "MIT"
        }
      }
    }
    ```

5. **مخزن را میزبانی کنید:**
    - آپلود به GitHub: `https://github.com/user/repo/raw/main/main/`
    - استفاده از CDN یا میزبانی استاتیک
    - اطمینان حاصل کنید HTTPS فعال است

6. **پوسته را برای استفاده از مخزن شما تنظیم کنید:**
    `.config/repos.json` را ویرایش کنید:
    ```json
    {
      "main": ["https://github.com/user/repo/raw/main/main"]
    }
    ```

### مثال میزبانی GitHub

```bash
# مخزن ایجاد کنید
mkdir -p my-packages/main
cd my-packages

# بسته ها اضافه کنید
cp /path/to/mypackage-1.0.0.zip main/
cat > main/database.json << 'EOF'
{
  "version": 1,
  "packages": { ... }
}
EOF

# به GitHub فشار دهید
git init
git add .
git commit -m "Add packages"
git remote add origin https://github.com/user/my-packages.git
git push -u origin main

# کاربران تنظیم می کنند: https://github.com/user/my-packages/raw/main/main
```

## استقرار

### توسعه

```bash
# سرور درون ساخت PHP را اجرا کنید
php -S localhost:8000

# فرماند ها را تست کنید
php mpm.php pkg list
curl "http://localhost:8000/mpm.php/$(cat .config/key)/pkg/list"
```

### Staging/تولید

1. **سرور وب مناسب استفاده کنید** (Nginx, Apache, Caddy)
2. **HTTPS فعال کنید** برای امنیت کلید API
3. **دسترسی های محدود تعیین کنید:**
    ```bash
    chmod 644 mpm.php
    chmod 700 .config
    chmod 600 .config/key
    ```

4. **PHP-FPM را پیکربندی کنید** برای عملکرد بهتر
5. **نظارت و ثبت را راه اندازی کنید**
6. **پشتیبان گیری منظم** از دایرکتوری `.config/`

### استقرار Docker

```dockerfile
FROM php:8.1-fpm

# نصب افزونه ها
RUN docker-php-ext-install zip

# کپی shell
COPY mpm.php /var/www/html/

# تعیین دسترسی ها
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html
```

### تقویت امنیت

1. **محدود کردن دسترسی کلید API:**
    ```bash
    chmod 600 .config/key
    chown www-data:www-data .config/key
    ```

2. **استفاده از کلیدهای خاص محیط**
3. **فعال کردن HTTPS فقط** در تولید
4. **محدود کردن نرخ** در سطح سرور وب
5. **نظارت بر تلاش های احراز هویت ناموفق**
6. **به روز رسانی های امنیتی منظم**

## حل مسائل

### مسائل عام

**خطاهای "اجازه رد شد":**
```bash
# رفع دسترسی ها
chmod 755 .
chmod 644 mpm.php
chmod -R 755 .config
```

**"ZipArchive پیدا نشد":**
```bash
# نصب افزونه
sudo apt-get install php-zip  # Debian/Ubuntu
sudo yum install php-zip       # CentOS/RHEL
```

**"کلید API پیدا نشد" (حالت CLI):**
```bash
# تولید کلید به صورت دستی
php mpm.php ls  # این .config/key را ایجاد می کند
```

**مسائل فایل قفل:**
```bash
# اگر گیر کرد قفل اجباری باز کنید
php mpm.php pkg unlock
```

<!--html-ignore-->

## مراحل بعدی

- [راهنمای مرجع استفاده](USAGE_FA.md) را برای مستندات فرماند کامل بخوانید
- [توسعه بسته](PACKAGES_FA.md) را برای ایجاد بسته پیشرفته ببینید
- [بسته های نمونه](https://github.com/mehrnet/mpm-packages) را بررسی کنید

---

برای پرسش ها یا مسائل، لطفا بازدید کنید: https://github.com/mehrnet/mpm

<!--/html-ignore-->
