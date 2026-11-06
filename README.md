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

### .env

```env
WP_REPO_PROVIDER=github          # github | gitlab | gitea
WP_REPO_GITHUB_TOKEN=...         # فقط خواندنی (fine-grained token)
WP_REPO_GITHUB_WEBHOOK_SECRET=...
WP_REPO_REGISTRATION_TOKEN=...   # اختیاری، برای POST /sites/register
WP_REPO_DISK=local               # دیسک خصوصی
```

برای GitLab/Gitea: `WP_REPO_GITLAB_TOKEN`, `WP_REPO_GITEA_API`, `WP_REPO_GITEA_TOKEN` و secret متناظر.

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

## راه‌اندازی Webhook

آدرس: `https://apachish.ir/api/wp-updates/webhook/{github|gitlab|gitea}`

- GitHub: Content type `application/json`، Secret = `WP_REPO_GITHUB_WEBHOOK_SECRET`، رویدادهای *Releases* (و در صورت نیاز *Pushes*، که فقط تگ‌ها پردازش می‌شوند).
- GitLab: Secret token، رویدادهای *Releases events* و *Tag push events*.
- Gitea/Forgejo: Secret، رویداد *Release* و *Push*.

مهم: نسخه‌ای که ثبت می‌شود همان `Version:` داخل هدر فایل اصلی پلاگین/`style.css` است (چون وردپرس همان را مقایسه می‌کند)؛ تگ و هدر را هم‌نسخه نگه دارید.

## استفاده

1. **Packages → Add package:** `slug` باید دقیقاً نام پوشه‌ی پلاگین/قالب در وردپرس باشد. `repo` به شکل `owner/repo` است.
2. با Release جدید، نسخه خودکار ساخته می‌شود. دستی: `php artisan wp-repo:sync my-plugin`.
3. **Sites → Add site:** دامنه (و برای push، مسیر نصب وردپرس). کلید API فقط یک بار نمایش داده می‌شود.

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
| POST | `/webhook/{provider}` | امضا اعتبارسنجی می‌شود، Job صف می‌خورد |
| GET | `/check?slug=&type=&version=` | هدرها: `X-Site-Domain`, `X-Site-Key` |
| GET | `/download/{version}` | فقط با لینک signed |
| POST | `/sites/register` | هدر `X-Registration-Token` |

## امنیت

- توکن گیت read-only و فقط در `.env`. هرگز در لاگ یا پاسخ نمی‌آید.
- Webhook با `hash_equals`. کلید سایت‌ها فقط به شکل sha256 ذخیره می‌شود.
- استخراج zip در برابر Zip Slip، symlink و حجم بیش‌ازحد محافظت شده است.
- لینک دانلود signed و کوتاه‌عمر؛ mu-plugin قبل از نصب checksum را بررسی می‌کند.
- پشت Cloudflare/پروکسی، `TrustProxies` را درست تنظیم کنید وگرنه اعتبارسنجی لینک signed (که به host/scheme وابسته است) رد می‌شود.

## تست

```bash
composer install && vendor/bin/pest
```
