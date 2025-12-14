# راهنمای مرجع استفاده

مرجع فرماند کامل و مستندات API برای MPM - مدیریت بسته های Mehr.

<!--html-ignore-->
## فهرست مطالب

- [فرمت های درخواست](#فرمت-های-درخواست)
- [فرماندهای درون ساخت](#فرماندهای-درون-ساخت)
- [مدیریت بسته](#مدیریت-بسته)
- [پروتکل پاسخ](#پروتکل-پاسخ)
- [فایل های تنظیمات](#فایل-های-تنظیمات)
- [فرماندهای سفارشی](#فرماندهای-سفارشی)
- [معماری](#معماری)
- [کارایی](#کارایی)
- [حل مسائل](#حل-مسائل)
<!--/html-ignore-->

---

## فرمت های درخواست

### حالت HTTP

**فرمت:**
```
GET /mpm.php/API_KEY/COMMAND/ARG0/ARG1/ARG2/...
```

**مثال ها:**
```bash
# دریافت کلید API
KEY=$(cat .config/key)

# نمایش فهرست فایل ها
curl "http://localhost/mpm.php/$KEY/ls"

# خواندن فایل
curl "http://localhost/mpm.php/$KEY/cat/README.md"

# نمایش متن با آرگومان های متعدد
curl "http://localhost/mpm.php/$KEY/echo/hello/world"

# مدیریت بسته ها
curl "http://localhost/mpm.php/$KEY/pkg/search/database"
curl "http://localhost/mpm.php/$KEY/pkg/add/users"
```

**پاسخ:**
- موفقیت: HTTP 200 با پاسخ متنی ساده
- خطا: HTTP 4xx/5xx با پیام خطا

### حالت CLI

**فرمت:**
```bash
php mpm.php COMMAND ARG0 ARG1 ARG2 ...
```

**مثال ها:**
```bash
# نمایش فهرست فایل ها
php mpm.php ls

# خواندن فایل
php mpm.php cat README.md

# نمایش متن با آرگومان های متعدد
php mpm.php echo hello world

# مدیریت بسته ها
php mpm.php pkg search database
php mpm.php pkg add users
```

**پاسخ:**
- موفقیت: خروجی به STDOUT، کد خروج 0
- خطا: خروجی به STDERR با پیشوند "Error: "، کد خروج 1

---

## فرماندهای درون ساخت

### ls [path]

نمایش محتویات پوشه.

**آرگومان ها:**
- `path` (اختیاری): پوشه ای برای نمایش (پیش فرض: پوشه جاری)

**مثال ها:**
```bash
# CLI
php mpm.php ls
php mpm.php ls app
php mpm.php ls app/packages

# HTTP
curl "http://localhost/mpm.php/$KEY/ls"
curl "http://localhost/mpm.php/$KEY/ls/app"
```

**خروجی:**
نام فایل های جدا شده با فاصله (بدون `.` و `..`)

**خطاها:**
- 404: پوشه پیدا نشد
- 400: نمی توان پوشه را خواند
- 403: اعتبارسنجی مسیر ناموفق (مسیر مطلق یا `..`)

---

### cat <file>

خواندن محتویات فایل.

**آرگومان ها:**
- `file` (اجباری): مسیر فایل

**مثال ها:**
```bash
# CLI
php mpm.php cat README.md
php mpm.php cat .config/key

# HTTP
curl "http://localhost/mpm.php/$KEY/cat/README.md"
```

**خروجی:**
محتویات فایل (حفظ قالب بندی و خطوط جدید)

**خطاها:**
- 400: آرگومان فایل وجود ندارد یا فایل نیست
- 404: فایل پیدا نشد
- 403: اعتبارسنجی مسیر ناموفق

---

### rm <file>

حذف فایل.

**آرگومان ها:**
- `file` (اجباری): مسیر فایل

**مثال ها:**
```bash
# CLI
php mpm.php rm temp.txt
php mpm.php rm logs/old.log

# HTTP
curl "http://localhost/mpm.php/$KEY/rm/temp.txt"
```

**خروجی:**
رشته خالی در صورت موفقیت (رفتار POSIX)

**خطاها:**
- 400: آرگومان فایل وجود ندارد، فایل نیست یا نمی توان حذف کرد
- 404: فایل پیدا نشد
- 403: اعتبارسنجی مسیر ناموفق

---

### mkdir <path>

ایجاد پوشه (بصورت بازگشتی).

**آرگومان ها:**
- `path` (اجباری): مسیر پوشه ای برای ایجاد

**مثال ها:**
```bash
# CLI
php mpm.php mkdir uploads
php mpm.php mkdir app/data/cache

# HTTP
curl "http://localhost/mpm.php/$KEY/mkdir/uploads"
```

**خروجی:**
رشته خالی در صورت موفقیت

**خطاها:**
- 400: آرگومان مسیر وجود ندارد، قبلا وجود دارد یا نمی توان ایجاد کرد
- 403: اعتبارسنجی مسیر ناموفق

---

### cp <src> <dst>

کپی فایل.

**آرگومان ها:**
- `src` (اجباری): مسیر فایل منبع
- `dst` (اجباری): مسیر فایل مقصد

**مثال ها:**
```bash
# CLI
php mpm.php cp config.json config.backup.json
php mpm.php cp README.md docs/README.md

# HTTP
curl "http://localhost/mpm.php/$KEY/cp/config.json/config.backup.json"
```

**خروجی:**
رشته خالی در صورت موفقیت

**خطاها:**
- 400: آرگومان ها وجود ندارند، منبع فایل نیست یا نمی توان کپی کرد
- 404: فایل منبع پیدا نشد
- 403: اعتبارسنجی مسیر ناموفق

---

### echo <text>...

نمایش آرگومان های متنی.

**آرگومان ها:**
- `text...` (اجباری): یک یا بیشتر آرگومان متنی

**مثال ها:**
```bash
# CLI
php mpm.php echo hello
php mpm.php echo hello world "from shell"

# HTTP
curl "http://localhost/mpm.php/$KEY/echo/hello"
curl "http://localhost/mpm.php/$KEY/echo/hello/world"
```

**خروجی:**
آرگومان های جدا شده با فاصله

---

### env [action] [name]

مدیریت متغیرهای محیطی.

**اقدام ها:**
- `list` (پیش فرض): نمایش تمام متغیرهای محیطی
- `get <name>`: دریافت مقدار متغیر خاص

**مثال ها:**
```bash
# CLI
php mpm.php env list
php mpm.php env get PATH
php mpm.php env get HOME

# HTTP
curl "http://localhost/mpm.php/$KEY/env/list"
curl "http://localhost/mpm.php/$KEY/env/get/PATH"
```

**خروجی:**
- `list`: یک متغیر در هر سطر (`KEY=value`)
- `get`: فقط مقدار متغیر

**خطاها:**
- 404: متغیر پیدا نشد یا اقدام نامعلوم
- 400: نام متغیر برای `get` وجود ندارد

---

## مدیریت بسته

### pkg add [PACKAGE...]

نصب بسته ها با حل خودکار وابستگی ها.

**آرگومان ها:**
- `PACKAGE...` (اجباری): یک یا بیشتر نام بسته

**مثال ها:**
```bash
# CLI
php mpm.php pkg add users
php mpm.php pkg add users auth database

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/add/users"
curl "http://localhost/mpm.php/$KEY/pkg/add/users/auth"
```

**فرایند:**
1. بازیابی پایگاه داده مخزن
2. حل وابستگی ها (DFS مرتب سازی توپولوژیکی)
3. دانلود تمام بسته ها با تایید checksum
4. استخراج تمام بسته ها به ریشه پروژه
5. ثبت بسته ها به طور اتمی

**خروجی:**
```
بسته های نصب شامل: dependency1, dependency2, users

در حال دانلود بسته ها...
تمام بسته ها دانلود و تایید شدند

در حال استخراج بسته ها...
استخراج شده dependency1 (1.0.0)
استخراج شده dependency2 (2.0.0)
استخراج شده users (3.0.0)

در حال ثبت بسته ها...

موفقیت آمیز نصب 3 بسته: dependency1, dependency2, users
```

**خطاها:**
- 404: بسته پیدا نشد
- 400: وابستگی دایره ای شناسایی شد
- 503: تمام آینه ها ناموفق بودند یا قفل نگه داشته شده است

---

### pkg del <PACKAGE>

حذف بسته (در صورتی که بسته های دیگر به آن وابسته باشند، شکست می خورد).

**آرگومان ها:**
- `PACKAGE` (اجباری): نام بسته

**مثال ها:**
```bash
# CLI
php mpm.php pkg del users

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/del/users"
```

**خروجی:**
```
بسته حذف شد: users (نسخه 1.0.0) - 15 فایل حذف شد، 3 پوشه خالی حذف شد
```

**خطاها:**
- 404: بسته نصب نشده است
- 400: بسته توسط بسته های دیگر لازم است
- 503: قفل نگه داشته شده است

---

### pkg upgrade [PACKAGE]

ارتقای تمام بسته ها یا بسته خاص به آخرین نسخه.

**آرگومان ها:**
- `PACKAGE` (اختیاری): نام بسته (اگر حذف شود، تمام را ارتقا دهد)

**مثال ها:**
```bash
# CLI
php mpm.php pkg upgrade           # ارتقای تمام
php mpm.php pkg upgrade users     # ارتقای خاص

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/upgrade"
curl "http://localhost/mpm.php/$KEY/pkg/upgrade/users"
```

**خروجی:**
```
ارتقای users از 1.0.0 به 1.1.0

در حال دانلود بروز رسانی بسته ها...
تمام بروز رسانی بسته ها دانلود و تایید شدند

در حال استخراج بروز رسانی بسته ها...
استخراج شده users ارتقا به 1.1.0

در حال ثبت بروز رسانی ها...

موفقیت آمیز ارتقای 1 بسته: users
```

---

### pkg update

تازه کردن کش مخزن (بازیابی پایگاه داده بسته های جدید).

**مثال ها:**
```bash
# CLI
php mpm.php pkg update

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/update"
```

**خروجی:**
```
کش مخزن تازه شد - 42 بسته دردسترس
```

---

### pkg list [FILTER]

نمایش بسته های نصب شده.

**آرگومان ها:**
- `FILTER` (اختیاری): فیلتر بر اساس نام بسته (غیر حساس به بزرگی و کوچکی حروف)

**مثال ها:**
```bash
# CLI
php mpm.php pkg list
php mpm.php pkg list auth

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/list"
curl "http://localhost/mpm.php/$KEY/pkg/list/auth"
```

**خروجی:**
```
بسته های نصب شده:

  users                 v1.0.0      نصب شده: 1403-11-25
  auth                  v2.0.0      نصب شده: 1403-11-25 (وابسته: users)
  database              v1.5.0      نصب شده: 1403-11-25 (وابسته: users)
```

---

### pkg search <KEYWORD>

جستجوی بسته های دردسترس.

**آرگومان ها:**
- `KEYWORD` (اجباری): واژه جستجو

**مثال ها:**
```bash
# CLI
php mpm.php pkg search database

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/search/database"
```

**خروجی:**
```
3 بسته پیدا شد:

  mysql-driver          v1.0.0      درایور پایگاه داده MySQL [نصب شده]
    اتصال MySQL برای برنامه ها

  postgres-driver       v1.0.0      درایور PostgreSQL
    سازنده پایگاه داده PostgreSQL

  database-tools        v2.0.0      ابزار مدیریت پایگاه داده
    ابزارهای عمومی و کمکی پایگاه داده
```

---

### pkg info <PACKAGE>

نمایش اطلاعات دقیق بسته.

**آرگومان ها:**
- `PACKAGE` (اجباری): نام بسته

**مثال ها:**
```bash
# CLI
php mpm.php pkg info users

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/info/users"
```

**خروجی:**
```
بسته: سیستم مدیریت کاربر
شناسه: users
آخرین: 1.0.0
توضیح: مدیریت کاربر کامل با احراز هویت
نویسنده: تیم Mehrnet
مجوز: MIT

نصب شده: v1.0.0 (در 1403-11-25 10:30:00)
وابستگی ها: هیچ

نسخه های دردسترس:
  v1.0.0 - منتشر شده: 1403-11-25
  v0.9.0 - منتشر شده: 1403-11-11 (نیاز دارد: auth-lib)
```

**خطاها:**
- 404: بسته پیدا نشد

---

### pkg unlock

حذف اجباری فایل قفل (برای بازیابی دستی).

**مثال ها:**
```bash
# CLI
php mpm.php pkg unlock

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/unlock"
```

**خروجی:**
رشته خالی در صورت موفقیت

**زمان استفاده:**
اگر یک عملیات بسته خراب شود، فایل قفل ممکن است باقی بماند. این فرمان آن را حذف می‌کند.

---

### pkg help

نمایش کمک مدیریت بسته.

**مثال ها:**
```bash
# CLI
php mpm.php pkg help

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/help"
```

---

### pkg version

نمایش نسخه مدیریت بسته.

**مثال ها:**
```bash
# CLI
php mpm.php pkg version

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/version"
```

---

## پروتکل پاسخ

### کدهای وضعیت HTTP

| کد | معنی |
|----|------|
| 200 | موفقیت |
| 400 | درخواست نامعتبر (آرگومان های نامعتبر، خطاهای عملیاتی) |
| 403 | ممنوع (کلید API نامعتبر، اعتبارسنجی مسیر ناموفق) |
| 404 | پیدا نشد (فرمند، بسته یا فایل پیدا نشد) |
| 503 | سرویس در دسترس نیست (تمام آینه ها ناموفق، قفل نگه داشته شده) |

### کدهای خروج CLI

| کد | معنی |
|----|------|
| 0 | موفقیت |
| 1 | خطا (هر نوع) |

### معنی شناسی POSIX

- فرماندهایی که خروجی دارند داده بر می گردانند
- فرماندهایی که خروجی ندارند رشته خالی برمی گردانند (اما همچنان 0/200 خروج دارند)
- خطاها استثنایی با کدهای مناسب پرتاب می کنند

---

## فایل های تنظیمات

### .config/key

فایل متنی ساده حاوی کلید API 64 کاراکتری هگزادسیمال.

**فرمت:**
```
abc123def456789...  (64 کاراکتر هگزادسیمال)
```

**تولید:**
```php
$key = bin2hex(random_bytes(32));
```

**امنیت:**
- به طور خودکار در اولین اجرا تولید می شود
- برای احراز هویت HTTP استفاده می شود
- به طور خودکار برای حالت CLI بارگذاری می شود
- مقایسه ایمن زمانی از طریق `hash_equals()`

---

### .config/repos.json

پیکربندی آینه های مخزن.

**فرمت:**
```json
{
  "main": [
    "https://primary-mirror.com/packages",
    "https://secondary-mirror.com/packages"
  ],
  "extra": [
    "https://extra-repo.com/packages"
  ]
}
```

**انتخاب آینه:**
- آینه ها به ترتیب آرایه در صورت شکست امتحان می شوند
- بدون تاخیر عقب نشینی
- اولین آینه موفق برنده می شود

---

### .config/packages.json

ثبت بسته های نصب شده.

**فرمت:**
```json
{
  "createdAt": "2025-01-15T10:00:00Z",
  "updatedAt": "2025-01-15T10:30:00Z",
  "packages": {
    "users": {
      "version": "1.0.0",
      "installed_at": "2025-01-15T10:30:00Z",
      "dependencies": [],
      "files": [
        "./app/packages/users/handler.php",
        "./app/packages/users/module.php"
      ],
      "download_url": "https://mirror.com/users-1.0.0.zip",
      "download_time": "2025-01-15T10:29:00Z",
      "checksum": "sha256:abc123...",
      "repository": "main"
    }
  }
}
```

**فرادادها:**
- `createdAt`: زمان ایجاد ثبت
- `updatedAt`: آخرین زمان تغییر
- `files`: آرایه مسیرهای فایل های نصب شده (برای حذف)
- `download_url`: آینه منبع استفاده شده
- `checksum`: checksum تایید شده

---

### .config/path.json

الگوهای کشف کننده فرمند.

**فرمت:**
```json
[
  "app/packages/[name]/handler.php",
  "bin/[name].php"
]
```

**تطابق الگو:**
- `[name]` با نام فرمند جایگزین می شود
- الگوها به ترتیب آرایه جستجو می شوند
- اولین تطابق برنده می شود

**مثال:**
فرمند `users` جستجو می کند:
1. `app/packages/users/handler.php`
2. `bin/users.php`

---

## فرماندهای سفارشی

### فرمت کننده

کننده ها فایل های PHP هستند که قابل فراخوانی بر می گردانند:

```php
<?php
return function(array $args): string {
    // پردازش آرگومان ها
    // بازگشت پاسخ یا پرتاب استثنا
    return "output";
};
```

### پردازش آرگومان

**حالت HTTP:**
```
GET /mpm.php/KEY/mycommand/action/arg1/arg2
                       ↓        ↓      ↓    ↓
                   فرمند   args[0] [1]  [2]
```

**حالت CLI:**
```bash
php mpm.php mycommand action arg1 arg2
                ↓        ↓      ↓    ↓
            فرمند   args[0] [1]  [2]
```

**کننده دریافت می کند:**
```php
function(array $args): string {
    $action = $args[0];  // 'action'
    $arg1 = $args[1];    // 'arg1'
    $arg2 = $args[2];    // 'arg2'
}
```

### مدیریت خطا

پرتاب استثناهای داخل کدهای وضعیت HTTP:

```php
// درخواست نامعتبر
throw new \RuntimeException('Invalid input', 400);

// پیدا نشد
throw new \RuntimeException('Item not found', 404);

// ممنوع
throw new \RuntimeException('Permission denied', 403);
```

**توجه:** حالت CLI تمام کدهای خطا را به کد خروج 1 تبدیل می کند.

### مثال کننده

```php
<?php
return function(array $args): string {
    $action = $args[0] ?? 'help';

    switch ($action) {
        case 'list':
            // خواندن داده ها
            $data = json_decode(
                file_get_contents('app/data/items.json'),
                true
            );
            return json_encode($data);

        case 'get':
            $id = $args[1] ?? null;
            if (!$id) {
                throw new \RuntimeException('شناسه لازم است', 400);
            }
            // واکشی آیتم
            return "Item: $id";

        case 'create':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('نام لازم است', 400);
            }
            // ایجاد آیتم
            return "Created: $name";

        case 'help':
            return "اقدام ها: list, get <id>, create <name>";

        default:
            throw new \RuntimeException("اقدام نامعلوم: $action", 404);
    }
};
```

---

## معماری

### ساختار فایل

```
.
├── mpm.php              # اجرایی اصلی (2300 سطر)
├── .config/               # پیکربندی (ایجاد خودکار)
│   ├── key                # کلید API
│   ├── repos.json         # آینه های مخزن
│   ├── packages.json      # بسته های نصب شده
│   └── path.json          # الگوهای کننده
├── .cache/                # کش (ایجاد خودکار)
│   ├── mpm.lock           # فایل قفل
│   └── *.zip              # بسته های دانلود شده
└── app/packages/          # بسته های نصب شده
    └── [package]/
        └── handler.php
```

### جریان اجرا

```
1. شناسایی Runtime (HTTP/CLI)
    ↓
2. مقدار دهی اولیه فایل های پیکربندی
    ↓
3. تجزیه درخواست (PATH_INFO یا argv)
    ↓
4. اعتبارسنجی کلید API
    ↓
5. اجرای فرمند
    ├─ فرمند درون ساخت
    ├─ مدیریت بسته
    └─ کننده سفارشی
    ↓
6. ارسال پاسخ (سرصفحه های HTTP یا STDOUT/STDERR)
```

### جریان مدیریت بسته

**نصب:**
```
1. حل وابستگی ها (DFS مرتب سازی توپولوژیکی)
    ↓
2. دانلود تمام بسته ها (با تایید checksum)
    ↓
3. استخراج تمام بسته ها (با حل تضاد)
    ↓
4. ثبت در .config/packages.json (اتمی)
```

**ویژگی های کلیدی:**
- **حل وابستگی:** پیچیدگی O(V + E)
- **شناسایی دایره ای:** جستجوهای مجموعه O(1)
- **تایید Checksum:** SHA256 با مقایسه ایمن زمانی
- **عقب نشینی آینه:** تلاش مجدد ترتیبی، بدون تاخیر
- **عملیات اتمی:** نصب تمام یا هیچ
- **حل تضاد:** فایل های موجود با `-2`، `-3` و غیره نام گذاری می شوند

### مدل امنیت

1. **احراز هویت:**
    - کلید API تصادفی 64 کاراکتری
    - مقایسه ایمن زمانی از طریق `hash_equals()`
    - بارگذاری خودکار در حالت CLI

2. **اعتبارسنجی مسیر:**
    - مسدود کردن مسیرهای مطلق (`/etc/passwd`)
    - مسدود کردن پیمایش دایرکتوری (`../../../`)
    - اعمال بر تمام عملیات فایل

3. **امنیت بسته:**
    - تایید checksum SHA256
    - شناسایی پیمایش Zip
    - اجرای HTTPS آینه

---

## کارایی

### معیارهای عملکرد

- **اجرای برنامه:** <1ms (اندازه گیری شده از طریق نمایه گری)
- **سربار سرور درون ساخت:** 1000ms+ (در تولید از سرور وب مناسب استفاده کنید)
- **دانلود بسته:** بستگی به سرعت شبکه و آینه دارد
- **حل وابستگی:** O(V + E) که V = بسته ها، E = وابستگی ها

### نکات بهینه سازی

1. **استفاده از سرور وب تولید** (Nginx, Apache)
2. **فعال کردن کش کد PHP** (OPcache)
3. **استفاده از CDN برای آینه های بسته**
4. **کاهش پیچیدگی کننده سفارشی**
5. **ذخیره سازی پایگاه داده بسته به طور محلی**

---

## حل مسائل

### "کلید API نامعتبر"

**HTTP 403**

**علت:** کلید API وجود ندارد یا نامعتبر است

**حل:**
```bash
# بررسی کلید
cat .config/key

# تولید مجدد کلید (حذف و دوباره اجرا کنید)
rm .config/key
php mpm.php ls
```

---

### "کلید API پیدا نشد" (CLI)

**کد خروج 1**

**علت:** فایل `.config/key` وجود ندارد

**حل:**
```bash
# مقدار دهی اولیه shell
php mpm.php ls
```

---

### "فرمند پیدا نشد"

**HTTP 404 / کد خروج 1**

**علت:** فایل کننده پیدا نشد

**حل:**
```bash
# بررسی الگوهای کننده
cat .config/path.json

# تایید وجود کننده
ls app/packages/[command]/handler.php
ls bin/[command].php
```

---

### "عملیات pkg دیگری در حال انجام است"

**HTTP 503 / کد خروج 1**

**علت:** فایل قفل وجود دارد

**حل:**
```bash
# منتظر تکمیل عملیات یا قفل اجباری باز کنید
php mpm.php pkg unlock

# یا به صورت دستی حذف کنید
rm .cache/mpm.lock
```

---

### "بسته پیدا نشد"

**HTTP 404 / کد خروج 1**

**علت:** بسته در مخزن نیست

**حل:**
```bash
# تازه کردن کش مخزن
php mpm.php pkg update

# جستجوی بسته
php mpm.php pkg search keyword
```

---

### "وابستگی دایره ای"

**HTTP 400 / کد خروج 1**

**علت:** چرخه وابستگی بسته (A → B → C → A)

**حل:**
بررسی وابستگی های بسته و حذف چرخه. این یک مسئله مخزن بسته است.

---

### "نمی توان حذف کرد - موارد دیگر نیاز دارند"

**HTTP 400 / کد خروج 1**

**علت:** بسته های دیگر به بسته هدف وابسته اند

**حل:**
```bash
# اولا بسته های وابسته را حذف کنید
php mpm.php pkg del dependent-package
php mpm.php pkg del target-package
```

---

### خطاهای اجازه

**علت:** دسترسی نقش های سیستم فایل

**حل:**
```bash
# رفع اجازه ها
chmod 755 .
chmod 644 mpm.php
chmod 755 .config
chmod 644 .config/*
chmod 755 .cache
```

---

<!--html-ignore-->
## همچنین ببینید

- **[راهنمای نصب](INSTALL_FA.md)** - تنظیمات و پیکربندی
- **[توسعه بسته](PACKAGES_FA.md)** - ایجاد بسته
- **[مخزن GitHub](https://github.com/mehrnet/mpm)** - کد منبع

---

برای پرسش ها یا مسائل، لطفا بازدید کنید: https://github.com/mehrnet/mpm/issues
<!--/html-ignore-->
