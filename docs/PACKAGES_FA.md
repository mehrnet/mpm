# راهنمای توسعه بسته

راهنمای کامل برای ایجاد، تست و توزیع بسته های MPM (مدیریت بسته های Mehr).

<!--html-ignore-->
برای دستورالعمل های نصب و تنظیمات، ببینید [INSTALL_FA.md](INSTALL_FA.md) یا [USAGE_FA.md](USAGE_FA.md).

---

## فهرست مطالب

- [شروع سریع](#شروع-سریع)
- [ساختار بسته](#ساختار-بسته)
- [پیاده سازی کننده](#پیاده-سازی-کننده)
- [فرادادهای بسته](#فرادادهای-بسته)
- [تست بسته ها](#تست-بسته-ها)
- [توزیع](#توزیع)
- [بهترین عملکردها](#بهترین-عملکردها)
- [مثال ها](#مثال-ها)

---
<!--/html-ignore-->

## شروع سریع

بسته حداقل را در 3 مرحله ایجاد کنید:

```bash
# 1. ساختار بسته ایجاد کنید
mkdir -p mypackage
cd mypackage

# 2. کننده ایجاد کنید
cat > handler.php << 'EOF'
<?php
return function(array $args): string {
    return "سلام از mypackage!";
};
EOF

# 3. فرادادها ایجاد کنید
cat > package.json << 'EOF'
{
    "id": "mypackage",
    "name": "My Package",
    "version": "1.0.0",
    "description": "یک بسته ساده",
    "author": "نام شما",
    "license": "MIT",
    "dependencies": []
}
EOF

# تست کنید
cd ..
mkdir -p app/packages/mypackage
cp -r mypackage/* app/packages/mypackage/
php mpm.php mypackage
```

---

## ساختار بسته

### فایل های لازم

```
mypackage/
├── handler.php       # لازم: نقطه ورود
└── package.json      # لازم: فرادادها
```

### ساختار اختیاری

```
mypackage/
├── handler.php       # نقطه ورود (لازم)
├── package.json      # فرادادها (لازم)
├── lib/             # کد کتابخانه
│   ├── MyClass.php
│   └── helpers.php
├── data/            # فایل های داده
│   └── config.json
├── views/           # الگو ها
│   └── template.html
└── README.md        # مستندات بسته
```

### استخراج فایل

بسته ها به ریشه پروژه استخراج می شوند و مسیرها حفظ می شوند:

**بسته شامل:**
```
handler.php
module.php
lib/Database.php
data/schema.json
```

**استخراج به:**
```
./handler.php
./module.php
./lib/Database.php
./data/schema.json
```

برای ماژول های قالب Mehr، استفاده کنید:
```
app/packages/[name]/handler.php
app/packages/[name]/module.php
```

---

## پیاده سازی کننده

### کننده پایه

```php
<?php
return function(array $args): string {
    // اقدام واحد
    return "سلام، دنیا!";
};
```

### کننده چند اقدامی

```php
<?php
return function(array $args): string {
    $action = $args[0] ?? 'help';

    switch ($action) {
        case 'list':
            return handleList();

        case 'get':
            $id = $args[1] ?? null;
            if (!$id) {
                throw new \RuntimeException('شناسه لازم است', 400);
            }
            return handleGet($id);

        case 'create':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('نام لازم است', 400);
            }
            return handleCreate($name);

        case 'delete':
            $id = $args[1] ?? null;
            if (!$id) {
                throw new \RuntimeException('شناسه لازم است', 400);
            }
            return handleDelete($id);

        case 'help':
            return <<<'EOF'
اقدام های دردسترس:
  list              - نمایش تمام آیتم ها
  get <id>          - دریافت آیتم با شناسه
  create <name>     - ایجاد آیتم جدید
  delete <id>       - حذف آیتم
EOF;

        default:
            throw new \RuntimeException("اقدام نامعلوم: $action", 404);
    }
};

function handleList(): string {
    return "item1\nitem2\nitem3";
}

function handleGet(string $id): string {
    // بارگذاری از فایل، پایگاه داده، و غیره
    return "Item data for: $id";
}

function handleCreate(string $name): string {
    // ایجاد و ذخیره
    return "Created: $name";
}

function handleDelete(string $id): string {
    // حذف آیتم
    return "Deleted: $id";
}
```

### پردازش آرگومان

آرگومان ها در هر دو حالت HTTP و CLI یکسان اند:

**HTTP:**
```
GET /mpm.php/KEY/users/create/alice/alice@example.com
                    ↓      ↓      ↓         ↓
                فرمند args[0] args[1]  args[2]
```

**CLI:**
```bash
php mpm.php users create alice alice@example.com
               ↓      ↓      ↓         ↓
           فرمند args[0] args[1]  args[2]
```

**کننده:**
```php
function(array $args): string {
    $action = $args[0];    // 'create'
    $name = $args[1];      // 'alice'
    $email = $args[2];     // 'alice@example.com'
}
```

### عملیات فایل

کننده ها در دایرکتوری mpm.php اجرا می شوند. از مسیرهای نسبی استفاده کنید:

```php
<?php
return function(array $args): string {
    // خواندن فایل داده
    $data = json_decode(
        file_get_contents('app/data/items.json'),
        true
    );

    // تغییر داده ها
    $data['new_item'] = $args[0] ?? 'default';

    // بازنویسی
    file_put_contents(
        'app/data/items.json',
        json_encode($data, JSON_PRETTY_PRINT)
    );

    return "بروز رسانی شد";
};
```

### مدیریت خطا

```php
<?php
return function(array $args): string {
    $id = $args[0] ?? null;

    // اعتبارسنجی
    if (!$id) {
        throw new \RuntimeException('شناسه لازم است', 400);
    }

    if (!is_numeric($id)) {
        throw new \RuntimeException('شناسه باید عددی باشد', 400);
    }

    // عملیات فایل
    $file = "app/data/$id.json";
    if (!file_exists($file)) {
        throw new \RuntimeException('آیتم پیدا نشد', 404);
    }

    try {
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (!$data) {
            throw new \RuntimeException('JSON نامعتبر است', 400);
        }

        return json_encode($data);
    } catch (\Throwable $e) {
        throw new \RuntimeException(
            "خرابی در خواندن آیتم: {$e->getMessage()}",
            400
        );
    }
};
```

### فرمت های پاسخ

**متن ساده:**
```php
return "item1\nitem2\nitem3";
```

**JSON:**
```php
return json_encode([
    'status' => 'success',
    'items' => ['item1', 'item2', 'item3']
]);
```

**خالی (POSIX):**
```php
return '';  // موفقیت بدون خروجی
```

---

## فرادادهای بسته

### فرمت package.json

```json
{
    "id": "mypackage",
    "name": "My Package",
    "version": "1.0.0",
    "description": "توضیح بسته",
    "author": "نام شما",
    "license": "MIT",
    "dependencies": ["dependency1", "dependency2"]
}
```

### مشخصات فیلد

| فیلد | نوع | لازم | توضیح |
|------|------|------|--------|
| `id` | رشته | بله | کوچک، بدون فاصله، استفاده در URL ها |
| `name` | رشته | بله | نام قابل خواندن |
| `version` | رشته | بله | نسخه درجه بندی معنایی (X.Y.Z) |
| `description` | رشته | بله | توضیح مختصر |
| `author` | رشته | نه | نویسنده بسته |
| `license` | رشته | نه | شناسه مجوز (MIT, Apache-2.0, و غیره) |
| `dependencies` | آرایه | نه | آرایه شناسه های بسته |

### اعلان وابستگی

```json
{
    "id": "auth",
    "dependencies": ["users", "session"]
}
```

مدیریت بسته:
- وابستگی های انتقالی را خودکار حل می کند
- وابستگی های دایره ای را شناسایی می کند
- به ترتیب صحیح نصب می کند (وابستگی ها قبل از وابستگان)
- بسته های قبلا نصب شده را نادیده می گیرد

---

## تست بسته ها

### تست دستی (CLI)

```bash
# نصب دستی بسته
mkdir -p app/packages/mypackage
cp -r mypackage/* app/packages/mypackage/

# تست فرماند ها
php mpm.php mypackage
php mpm.php mypackage list
php mpm.php mypackage get 123
php mpm.php mypackage create "Test Item"
```

### تست دستی (HTTP)

```bash
# شروع سرور
php -S localhost:8000

# کلید API را دریافت کنید
KEY=$(cat .config/key)

# تست فرماند ها
curl "http://localhost:8000/mpm.php/$KEY/mypackage"
curl "http://localhost:8000/mpm.php/$KEY/mypackage/list"
curl "http://localhost:8000/mpm.php/$KEY/mypackage/get/123"
```

### چک لیست تست

- [ ] تمام اقدام ها به درستی کار می کنند
- [ ] مدیریت خطا کار می کند (ورودی نامعتبر)
- [ ] آرگومان های لازم اعتبارسنجی می شوند
- [ ] عملیات فایل موفق می شوند
- [ ] هر دو حالت CLI و HTTP یکسان کار می کنند
- [ ] اقدام کمک تمام فرماند ها را مستند می کند
- [ ] وابستگی ها در package.json اعلام می شوند

---

## توزیع

### ایجاد ZIP بسته

```bash
cd mypackage
zip -r ../mypackage-1.0.0.zip .

# تایید محتویات
unzip -l ../mypackage-1.0.0.zip
```

### محاسبه Checksum

```bash
sha256sum mypackage-1.0.0.zip
# Output: abc123def456...  mypackage-1.0.0.zip
```

### اسکریپت‌های ساخت خودکار

برای بسته‌بندی قابل استفاده مجدد، اسکریپت‌های shell ایجاد کنید که فرآیند ساخت را خودکار می‌کنند. این به ویژه برای بسته‌بندی دارایی‌های شخص ثالث مانند آیکون‌ها، فونت‌ها یا کتابخانه‌های JavaScript مفید است.

**ساختار پایه:**

```bash
#!/bin/sh
set -e

PKG="mypackage"
DES="توضیح بسته"
VER="1.0.0"

URL="https://example.com/source.zip"
OUT="${PKG}-${VER}.zip"
TMP="$(mktemp -d)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$TMP"
# دانلود و استخراج منبع
wget -q "$URL" -O src.zip
unzip -q src.zip

# بازسازی ساختار
mkdir -p "assets/category/${PKG}"
mv source-files/* "assets/category/${PKG}/"

# ایجاد zip بسته
zip -rq "$OUT" assets

# محاسبه متادیتا
CHECKSUM=$(sha256sum "$OUT" | cut -d' ' -f1)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
RELEASED=$(date +%Y-%m-%d)

# انتقال به مخزن
mv "$OUT" "${SCRIPT_DIR}/../main/"

# بروزرسانی database.json
DB="${SCRIPT_DIR}/../main/database.json"
jq --arg id "$PKG" \
   --arg name "$PKG" \
   --arg desc "$DES" \
   --arg ver "$VER" \
   --arg checksum "sha256:$CHECKSUM" \
   --argjson size "$SIZE" \
   --arg url "$OUT" \
   --arg released "$RELEASED" \
   '.packages[$id] = {
       name: $name,
       description: $desc,
       author: "mpm-bot",
       license: "MIT",
       versions: { ($ver): { version: $ver, dependencies: [], checksum: $checksum, size: $size, download_url: $url, released_at: $released } },
       latest: $ver
   }' "$DB" > "${DB}.tmp" && mv "${DB}.tmp" "$DB"

rm -rf "$TMP"
echo "[+] Built ${PKG} v${VER}"
```

**مثال: بسته آیکون (heroicons)**

```bash
#!/bin/sh
set -e

PKG="heroicons"
DES="مجموعه آیکون‌های SVG رایگان با کیفیت بالا برای توسعه UI."
VER="2.2.0"

URL="https://github.com/tailwindlabs/heroicons/archive/refs/tags/v${VER}.zip"
OUT="${PKG}-${VER}.zip"
TMP="$(mktemp -d)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$TMP"
wget -q "$URL" -O src.zip
unzip -q src.zip

mkdir -p "assets/icons/${PKG}"
mv "heroicons-${VER}/src/"* "assets/icons/${PKG}/"

zip -rq "$OUT" assets

CHECKSUM=$(sha256sum "$OUT" | cut -d' ' -f1)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
RELEASED=$(date +%Y-%m-%d)

mv "$OUT" "${SCRIPT_DIR}/../main/"

DB="${SCRIPT_DIR}/../main/database.json"
jq --arg id "$PKG" --arg name "$PKG" --arg desc "$DES" --arg ver "$VER" \
   --arg checksum "sha256:$CHECKSUM" --argjson size "$SIZE" \
   --arg url "$OUT" --arg released "$RELEASED" \
   '.packages[$id] = {
       name: $name, description: $desc, author: "mpm-bot", license: "MIT",
       versions: { ($ver): { version: $ver, dependencies: [], checksum: $checksum, size: $size, download_url: $url, released_at: $released } },
       latest: $ver
   }' "$DB" > "${DB}.tmp" && mv "${DB}.tmp" "$DB"

rm -rf "$TMP"
echo "[+] Built ${PKG} v${VER}"
```

**مثال: کتابخانه JavaScript (htmx)**

```bash
#!/bin/sh
set -e

PKG="htmx"
DES="ابزارهای قدرتمند برای HTML."
VER="2.0.4"

URL="https://unpkg.com/htmx.org@${VER}/dist/htmx.min.js"
OUT="${PKG}-${VER}.zip"
TMP="$(mktemp -d)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$TMP"
mkdir -p "assets/js"
wget -q "$URL" -O "assets/js/htmx.min.js"

zip -rq "$OUT" assets

CHECKSUM=$(sha256sum "$OUT" | cut -d' ' -f1)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
RELEASED=$(date +%Y-%m-%d)

mv "$OUT" "${SCRIPT_DIR}/../main/"

DB="${SCRIPT_DIR}/../main/database.json"
jq --arg id "$PKG" --arg name "$PKG" --arg desc "$DES" --arg ver "$VER" \
   --arg checksum "sha256:$CHECKSUM" --argjson size "$SIZE" \
   --arg url "$OUT" --arg released "$RELEASED" \
   '.packages[$id] = {
       name: $name, description: $desc, author: "mpm-bot", license: "BSD-2-Clause",
       versions: { ($ver): { version: $ver, dependencies: [], checksum: $checksum, size: $size, download_url: $url, released_at: $released } },
       latest: $ver
   }' "$DB" > "${DB}.tmp" && mv "${DB}.tmp" "$DB"

rm -rf "$TMP"
echo "[+] Built ${PKG} v${VER}"
```

**ساختار مخزن:**

```
mpm-repo/
├── scripts/
│   ├── heroicons.sh
│   ├── htmx.sh
│   ├── alpinejs.sh
│   └── ...
└── main/
    ├── database.json
    ├── heroicons-2.2.0.zip
    ├── htmx-2.0.4.zip
    └── ...
```

**ساخت همه بسته‌ها:**

```bash
for script in scripts/*.sh; do ./$script; done
```

### database.json مخزن

```json
{
  "version": 1,
  "packages": {
    "mypackage": {
      "name": "My Package",
      "description": "توضیح بسته",
      "author": "نام شما",
      "license": "MIT",
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
      "latest": "1.0.0"
    }
  }
}
```

فایل‌ها در زمان استخراج به صورت محلی ردیابی می‌شوند تا حذف تمیز انجام شود.

### میزبانی GitHub

```bash
# ساختار مخزن ایجاد کنید
mkdir -p my-packages/main
cd my-packages/main

# بسته و پایگاه داده اضافه کنید
cp /path/to/mypackage-1.0.0.zip .
cat > database.json << 'EOF'
{
  "version": 1,
  "packages": { ... }
}
EOF

# فشار به GitHub
cd ..
git init
git add .
git commit -m "Add mypackage"
git remote add origin https://github.com/user/my-packages.git
git push -u origin main

# کاربران تنظیم می کنند:
# "main": ["https://github.com/user/my-packages/raw/main/main"]
```

---

## بهترین عملکردها

### اعتبارسنجی ورودی

```php
// نادرست: بدون اعتبارسنجی
$id = $args[0];
return processId($id);

// درست: اعتبارسنجی و تمیز کردن
$id = $args[0] ?? null;
if (!$id || !is_numeric($id) || $id < 1) {
    throw new \RuntimeException('شناسه نامعتبر است', 400);
}
return processId((int)$id);
```

### امنیت

```php
// نادرست: دسترسی فایل دلخواه
$file = $args[0];
return file_get_contents($file);  // آسیب پذیر برای ../../../etc/passwd

// درست: محدود کردن به دایرکتوری امن
$file = basename($args[0] ?? '');  // حذف اجزای مسیر
$path = "app/data/$file";

if (!file_exists($path)) {
    throw new \RuntimeException('فایل پیدا نشد', 404);
}

return file_get_contents($path);
```

### پیام های خطا

```php
// نادرست: خطای عمومی
throw new \RuntimeException('خطا', 400);

// درست: خطای توصیفی
throw new \RuntimeException('شناسه کاربر باید عدد مثبت باشد', 400);
```

### سازمان بندی کد

```php
// نادرست: همه چیز در کننده
return function(array $args): string {
    // 500 سطر کد...
};

// درست: استفاده از دایرکتوری lib/
return function(array $args): string {
    require_once __DIR__ . '/lib/Manager.php';
    $manager = new Manager();
    return $manager->handle($args);
};
```

### وابستگی ها

```php
// درست: بررسی اگر کتابخانه های Composer در دسترس باشند
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    // استفاده از کتابخانه ها
} else {
    throw new \RuntimeException('وابستگی های Composer وجود ندارند', 500);
}
```

---

## مثال ها

### شمارنده ساده

```php
<?php
return function(array $args): string {
    $file = 'app/data/counter.txt';

    if (!file_exists($file)) {
        file_put_contents($file, '0');
    }

    $count = (int)file_get_contents($file);
    $count++;
    file_put_contents($file, (string)$count);

    return "تعداد: $count";
};
```

### CRUD JSON

```php
<?php
return function(array $args): string {
    $action = $args[0] ?? 'list';
    $file = 'app/data/items.json';

    // بارگذاری داده ها
    $items = [];
    if (file_exists($file)) {
        $items = json_decode(file_get_contents($file), true) ?? [];
    }

    switch ($action) {
        case 'list':
            return json_encode($items);

        case 'add':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('نام لازم است', 400);
            }
            $items[] = [
                'id' => count($items) + 1,
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s')
            ];
            file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));
            return "اضافه شد: $name";

        case 'get':
            $id = (int)($args[1] ?? 0);
            foreach ($items as $item) {
                if ($item['id'] === $id) {
                    return json_encode($item);
                }
            }
            throw new \RuntimeException('آیتم پیدا نشد', 404);

        default:
            throw new \RuntimeException('اقدام نامعلوم', 404);
    }
};
```

### ماژول قالب Mehr

```
mymodule/
├── app/
│   └── packages/
│       └── mymodule/
│           ├── handler.php       # کننده فرماند shell
│           ├── module.php        # پیاده سازی ماژول
│           ├── manifest.json     # Mehr manifest
│           └── migrations/       # انتقال پایگاه داده
│               └── 001_create_table.php
└── package.json                  # فرادادهای توزیع
```

**handler.php:**
```php
<?php
return function(array $args): string {
    require_once __DIR__ . '/module.php';
    $module = new MyModule();
    return $module->handleCommand($args);
};
```

**package.json:**
```json
{
    "id": "mymodule",
    "name": "My Module",
    "version": "1.0.0",
    "description": "ماژول قالب Mehr",
    "dependencies": ["core"]
}
```

---

<!--html-ignore-->
## همچنین ببینید

- **[راهنمای نصب](INSTALL_FA.md)** - تنظیم و ایجاد مخزن
- **[مرجع استفاده](USAGE_FA.md)** - مرجع فرماند
- **[بسته های نمونه](https://github.com/mehrnet/mpm-packages)** - مثال های کاری

---

برای پرسش ها یا مسائل، لطفا بازدید کنید: https://github.com/mehrnet/mpm/issues
<!--/html-ignore-->
