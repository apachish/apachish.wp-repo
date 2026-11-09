# apachish/wp-repo

پکیج لاراول برای رساندن پلاگین‌ها و قالب‌های وردپرسِ نگهداری‌شده در **گیت پرایوت** به سایت‌های وردپرسی، بدون آپلود دستی.

دو روش تحویل دارد:

1. **Pull (آپدیت از پیشخوان وردپرس):** یک mu-plugin روی سایت وردپرسی از API لاراول نسخه جدید را می‌پرسد و آپدیتر خود وردپرس نصب می‌کند.
2. **Push (روی همان سرور):** پنل یا Artisan، zip را مستقیم داخل `wp-content` سایت می‌گذارد (با بکاپ و rollback).

```
git tag/release ──webhook──▶ Laravel (SyncPackageVersion)
                                │  دانلود با توکن read-only، ساخت zip استاندارد، ذخیره + checksum
                                ▼
 WP site (mu-plugin) ──GET /check──▶ نسخه جدید + لینک دانلود signed و کوتاه‌عمر
 Panel / wp-repo:deploy ──────────▶ جایگزینی مستقیم پوشه در wp-content
```

## نصب (پروژه اصلی)

```jsonc
// composer.json
"repositories": [{ "type": "path", "url": "packages_apachish/wp-repo" }]
```

```bash
composer require apachish/wp-repo:@dev livewire/livewire
php artisan migrate
php artisan vendor:publish --tag=wp-repo-config
```

اگر مایگریشن را publish می‌کنید: `'load_migrations' => false` بگذارید.

### اعتبارنامه‌ی گیت (بدون .env)

هیچ توکن یا secret ای در config/.env نیست. هر پروژه در پنل (**Git connections**) اتصال گیت خودش را می‌سازد؛ توکن و secret رمزنگاری‌شده (`encrypted` cast) در دیتابیس ذخیره می‌شوند و هرگز به مرورگر برنگردانده یا serialize نمی‌شوند. فقط اگر مایل بودید: `WP_REPO_DISK` و `WP_REPO_REGISTRATION_TOKEN` در `.env` هستند.

### صف و زمان‌بند

```bash
php artisan queue:work --timeout=600     # زیر Supervisor
* * * * * php artisan schedule:run       # prune هفتگی + check-sites روزانه
```

### Gate و پنل

پنل روی `/wp-repo` است و Gate با نام `manage-wp-repo` دارد. پیش‌فرض: کاربرانی که `is_admin` دارند. در `AuthServiceProvider` بازنویسی کنید:

```php
Gate::define('manage-wp-repo', fn ($user) => $user->hasRole('super-admin'));
```

منوی سایدبار: `@include('wp-repo::partials.sidebar')`. در Tailwind مسیر `./packages_apachish/wp-repo/resources/views/**/*.blade.php` را به `content` اضافه کنید. layout از `config('wp-repo.panel.layout')` می‌آید (پیش‌فرض `layouts.app`، با `{{ $slot }}`).

### Multi-tenant

`tenancy.resolver` را روی یک کلاس invokable بگذارید که `project_id` فعلی (خروجی `ResolveCurrentProject`) را برمی‌گرداند. بدون resolver و بدون binding با نام `current_project` هیچ scope‌ای اعمال نمی‌شود. پکیج‌هایی با `project_id = null` برای همه پروژه‌ها دیده می‌شوند.

## اتصال گیت هر پروژه

**Git connections ← Add**: نام، سرویس (GitHub/GitLab/Gitea)، توکن **فقط خواندنی** و برای سرور اختصاصی، آدرس API (فقط https).

| سرویس | توکن | دسترسی |
|---|---|---|
| GitHub | Fine-grained token | فقط ریپوهای لازم، **Contents: Read-only** |
| GitLab | Project/Group access token | Reporter + `read_api` + `read_repository` |
| Gitea/Forgejo | Application token | `read:repository` |

بعد از ذخیره، یک بار **Webhook secret** و **Webhook URL** (به شکل `/api/wp-updates/webhook/c/{uuid}`) نمایش داده می‌شود؛ secret دوباره نشان داده نمی‌شود (در صورت گم شدن «Secret جدید» بسازید). در گیت: Content type = `application/json`، همان secret، رویداد *Releases* (و در صورت نیاز *Pushes* برای تگ‌ها؛ در GitLab: *Release events* و *Tag push events*).

هر webhook فقط پکیج‌های همان اتصال را همگام می‌کند، پس secret یک پروژه به پکیج‌های پروژه‌ی دیگر دسترسی ندارد.

مهم: نسخه‌ی ثبت‌شده همان `Version:` داخل هدر فایل اصلی پلاگین/`style.css` است؛ تگ و هدر را هم‌نسخه نگه دارید.

## استفاده

1. **Git connections:** اتصال بسازید (بالا).
2. **Packages ← Add package:** `slug` دقیقاً نام پوشه‌ی پلاگین/قالب در وردپرس است، `repo` به شکل `owner/repo` و یک Git connection انتخاب کنید.
3. با Release جدید نسخه خودکار ساخته می‌شود. دستی: `php artisan wp-repo:sync my-plugin`.
4. **Sites ← Add site:** دامنه (و برای push، مسیر نصب وردپرس). کلید API فقط یک بار نمایش داده می‌شود.

### حالت Pull (mu-plugin)

```bash
php artisan vendor:publish --tag=wp-repo-mu-plugin   # → stubs/apachish-wp-updater.php
```

فایل را در `wp-content/mu-plugins/` سایت وردپرسی بگذارید (می‌توانید هنگام ساخت سایت جدید خودکار کپی شود) و در `wp-config.php`:

```php
define('APACHISH_SITE_KEY', 'wpr_...');
define('APACHISH_PACKAGES', ['my-plugin' => 'plugin', 'my-theme' => 'theme']);
```

سایت را می‌توانید خودکار ثبت کنید:
`curl -X POST https://apachish.ir/api/wp-updates/sites/register -H "X-Registration-Token: ..." -d domain=example.com`

اگر slug پلاگین با پلاگینی در wordpress.org یکی است، هدر `Update URI: https://apachish.ir` را در فایل اصلی پلاگین بگذارید تا وردپرس آن را از wordpress.org آپدیت نکند.

### حالت Push (روی همین سرور)

در config حتماً `wp.allowed_roots` را تنظیم کنید؛ بدون آن push غیرفعال است:

```php
'wp' => ['allowed_roots' => ['/var/www/sites'], 'chown' => 'www-data:www-data'],
```

```bash
php artisan wp-repo:deploy my-plugin --site=example.com
php artisan wp-repo:deploy my-plugin --all --ver=1.4.0
```

(گزینه‌ی `--ver` است چون `--version` در Artisan رزرو شده.) قبل از هر جایگزینی یک بکاپ zip در `storage/app/wp-repo/backups` گرفته می‌شود و در خطا برمی‌گردد. کاربر queue worker باید روی `wp-content` سایت‌ها دسترسی نوشتن داشته باشد.

### Entitlement (پکیج‌های پولی)

برای `is_paid`، کلاسی بنویسید که `EntitlementChecker` را پیاده کند (مثلاً از سیستم اشتراک شما بپرسد) و در `config('wp-repo.entitlement')` بگذارید:

```php
public function allows(WpSite $site, WpPackage $package): bool
{
    return Subscription::activeFor($site->project_id, $package->slug);
}
```

## API

| متد | مسیر | توضیح |
|---|---|---|
| POST | `/webhook/c/{uuid}` | امضا با secret همان اتصال اعتبارسنجی می‌شود، Job صف می‌خورد |
| GET | `/check?slug=&type=&version=` | هدرها: `X-Site-Domain`, `X-Site-Key` |
| GET | `/download/{version}` | فقط با لینک signed |
| POST | `/sites/register` | هدر `X-Registration-Token` |

## امنیت

- توکن و secret گیت per-project، رمزنگاری‌شده در دیتابیس، `hidden` در مدل و هرگز در لاگ/پاسخ.
- آدرس API سفارشی باید https باشد و به IP عمومی resolve شود (جلوگیری از SSRF توسط tenant)؛ برای Git داخلی `allow_private_hosts` را فقط در نصب‌های غیرمشترک روشن کنید.
- Webhook با `hash_equals`. کلید سایت‌ها فقط به شکل sha256 ذخیره می‌شود.
- استخراج zip در برابر Zip Slip، symlink و حجم بیش‌ازحد محافظت شده است.
- لینک دانلود signed و کوتاه‌عمر؛ mu-plugin قبل از نصب checksum را بررسی می‌کند.
- پشت Cloudflare/پروکسی، `TrustProxies` را درست تنظیم کنید وگرنه اعتبارسنجی لینک signed (که به host/scheme وابسته است) رد می‌شود.

## تست

```bash
composer install && vendor/bin/pest
```
