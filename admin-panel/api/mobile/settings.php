<?php
/**
 * Mobile API - Settings & App Config
 * الإعدادات وإعدادات التطبيق
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../../includes/functions.php';

$action = $_GET['action'] ?? 'app';

switch ($action) {
    case 'app':
        getAppSettings();
        break;
    case 'about':
        getAbout();
        break;
    case 'terms':
        getTerms();
        break;
    case 'privacy':
        getPrivacy();
        break;
    case 'refund':
        getRefund();
        break;
    case 'contact':
        getContact();
        break;
    default:
        sendError('Invalid action', 400);
}

function tableExists($tableName)
{
    global $conn;

    $safeTableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTableName}'");
    return $result && $result->num_rows > 0;
}

function columnExists($tableName, $columnName)
{
    global $conn;

    if (!tableExists($tableName)) {
        return false;
    }

    $safeTableName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
    $safeColumnName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $columnName);

    if ($safeTableName === '' || $safeColumnName === '') {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$safeTableName}` LIKE '{$safeColumnName}'");
    return $result && $result->num_rows > 0;
}

function fetchKeyValueSettings($tableName, $keyColumn, $valueColumn)
{
    global $conn;

    if (!tableExists($tableName)) {
        return [];
    }

    $settings = [];
    $query = "SELECT `$keyColumn` AS setting_key, `$valueColumn` AS setting_value FROM `$tableName`";
    $result = $conn->query($query);
    if (!$result) {
        return [];
    }

    while ($row = $result->fetch_assoc()) {
        $key = (string) ($row['setting_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $settings[$key] = $row['setting_value'] ?? '';
    }

    return $settings;
}

function toBoolSetting($value, $default = false)
{
    if ($value === null) {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function normalizeAppFont($value)
{
    $normalized = strtolower(trim((string) $value));
    return $normalized === 'zain' ? 'zain' : 'cairo';
}

function normalizeLanguageCode($value)
{
    $lang = strtolower(trim((string) $value));
    $allowed = ['ar', 'en', 'ur'];
    return in_array($lang, $allowed, true) ? $lang : 'ar';
}

function resolveRequestedLanguage()
{
    $lang = $_GET['lang'] ?? '';

    if ($lang === '') {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($header !== '') {
            $lang = substr($header, 0, 2);
        }
    }

    return normalizeLanguageCode($lang);
}

function getDefaultContentPagesMap()
{
    return [
        'about' => [
            'ar' => [
                'title' => 'عن Darfix',
                'content' => "Darfix هو تطبيق سعودي رائد في مجال الخدمات المنزلية، نربط بين العملاء ومقدمي الخدمات المحترفين بسرعة وسهولة في مختلف مدن المملكة.\n\nنوفر لك تجربة موثوقة تشمل الحجز السريع، متابعة الطلب لحظياً، طرق دفع آمنة، ودعم متواصل لضمان أفضل تجربة خدمة." 
            ],
            'en' => [
                'title' => 'About Darfix',
                'content' => "Darfix is a leading Saudi home-services app that connects customers with trusted service providers quickly and reliably across the Kingdom.\n\nWe provide a smooth experience with fast booking, real-time order tracking, secure payments, and responsive support." 
            ],
            'ur' => [
                'title' => 'Darfix کے بارے میں',
                'content' => "Darfix سعودی عرب میں گھریلو خدمات کے لیے ایک نمایاں ایپ ہے جو صارفین کو قابلِ اعتماد سروس فراہم کنندگان سے تیزی اور آسانی کے ساتھ جوڑتی ہے۔\n\nہم تیز بکنگ، آرڈر کی لائیو ٹریکنگ، محفوظ ادائیگی اور مسلسل سپورٹ فراہم کرتے ہیں تاکہ بہترین تجربہ مل سکے۔" 
            ],
        ],
        'privacy' => [
            'ar' => [
                'title' => 'سياسة الخصوصية',
                'content' => "تطبيق Darfix يحترم خصوصيتك. نقوم بجمع البيانات التي تقدمها (الاسم، رقم الهاتف، البريد الإلكتروني، العناوين، تفاصيل الطلب، الصور) والبيانات الناتجة عن الاستخدام (الموقع عند طلب الخدمة، معلومات الجهاز، سجلات الاستخدام).\n\nنستخدم هذه البيانات لتقديم الخدمة، ربطك بمقدمي خدمات مناسبين، تنفيذ الطلبات، إرسال الإشعارات، منع الاحتيال، وتحسين التطبيق.\n\nنشارك البيانات فقط مع مقدمي الخدمات المعنيين ومع مزودي خدمات موثوقين مثل الدفع والرسائل والخرائط وفق ضوابط تعاقدية. لا نبيع بياناتك.\n\nيمكنك الوصول إلى بياناتك أو تحديثها أو حذف حسابك من داخل التطبيق (الإعدادات > حذف الحساب) أو عبر فريق الدعم. عند حذف الحساب نحذف أو نُجهّل بياناتك الشخصية، وقد نحتفظ ببعض السجلات للالتزامات القانونية أو المحاسبية أو الأمان.\n\nللاستفسارات: support@darfix.org." 
            ],
            'en' => [
                'title' => 'Privacy Policy',
                'content' => "Darfix respects your privacy. We collect data you provide (name, phone number, email, addresses, order details, photos) and data generated during use (location when you request a service, device info, usage logs).\n\nWe use this data to deliver services, match you with providers, process orders, send notifications, prevent fraud, and improve the app.\n\nWe share data only with relevant service providers and trusted vendors such as payment, messaging, and maps under contractual safeguards. We do not sell your data.\n\nYou can access, update, or delete your account from the app (Settings > Delete Account) or by contacting support. When you delete your account, we remove or anonymize personal data; some records may be retained for legal, accounting, or safety obligations.\n\nFor questions: support@darfix.org." 
            ],
            'ur' => [
                'title' => 'رازداری کی پالیسی',
                'content' => "Darfix respects your privacy. We collect data you provide (name, phone number, email, addresses, order details, photos) and data generated during use (location when you request a service, device info, usage logs).\n\nWe use this data to deliver services, match you with providers, process orders, send notifications, prevent fraud, and improve the app.\n\nWe share data only with relevant service providers and trusted vendors such as payment, messaging, and maps under contractual safeguards. We do not sell your data.\n\nYou can access, update, or delete your account from inside the app (Settings > Delete Account) or by contacting support. When you delete your account, we remove or anonymize personal data; some records may be retained for legal, accounting, or safety obligations.\n\nFor questions: support@darfix.org." 
            ],
        ],
        'terms' => [
            'ar' => [
                'title' => 'شروط الاستخدام',
                'content' => "باستخدامك لتطبيق Darfix، فإنك توافق على الالتزام بشروط الاستخدام.\n\n1) يجب استخدام التطبيق بطريقة قانونية وعدم إساءة الاستخدام.\n2) الأسعار والمواعيد تخضع لتأكيد مقدم الخدمة.\n3) يمكن إلغاء الطلب وفق سياسة الإلغاء المعتمدة.\n4) يحق للتطبيق تحديث هذه الشروط عند الحاجة." 
            ],
            'en' => [
                'title' => 'Terms of Use',
                'content' => "By using Darfix, you agree to comply with these terms.\n\n1) The app must be used lawfully and without abuse.\n2) Pricing and scheduling are subject to provider confirmation.\n3) Orders may be canceled according to the cancellation policy.\n4) We reserve the right to update these terms when needed." 
            ],
            'ur' => [
                'title' => 'استعمال کی شرائط',
                'content' => "Darfix ایپ استعمال کرنے سے آپ ان شرائط کی پابندی سے اتفاق کرتے ہیں۔\n\n1) ایپ کو قانونی طور پر اور بغیر غلط استعمال کے استعمال کیا جائے۔\n2) قیمت اور وقت سروس فراہم کنندہ کی تصدیق کے تابع ہیں۔\n3) آرڈر منسوخی پالیسی کے مطابق منسوخ کیا جا سکتا ہے۔\n4) ضرورت کے مطابق ان شرائط میں تبدیلی کا حق محفوظ ہے۔" 
            ],
        ],
        'refund' => [
            'ar' => [
                'title' => 'سياسة الاسترداد',
                'content' => "بعد تأكيد الطلب وبدء إجراءات التنفيذ، تصبح عملية الدفع غير قابلة للاسترداد.\n\nيمكن قبول طلبات الاسترداد فقط في الحالات التي يتعذر فيها تقديم الخدمة من طرفنا أو عند وجود خطأ في عملية الخصم.\n\nيتم تقديم طلب الاسترداد خلال 7 أيام عمل من تاريخ الدفع عبر فريق الدعم.",
            ],
            'en' => [
                'title' => 'Refund Policy',
                'content' => "Payments become non-refundable once the order is confirmed and processing starts.\n\nRefund requests are accepted only if the service cannot be delivered by us or if there is a billing error.\n\nPlease submit refund requests within 7 business days of payment via support.",
            ],
            'ur' => [
                'title' => 'واپسی کی پالیسی',
                'content' => "آرڈر کی تصدیق اور پراسیسنگ شروع ہونے کے بعد ادائیگی ناقابل واپسی ہو جاتی ہے۔\n\nواپسی کی درخواستیں صرف اس صورت میں قبول کی جائیں گی جب سروس فراہم نہ ہو سکے یا ادائیگی میں غلطی ہو۔\n\nبراہ کرم ادائیگی کے 7 کاروباری دنوں کے اندر سپورٹ سے رابطہ کریں۔",
            ],
        ],
    ];
}

function ensureContentPagesTable()
{
    global $conn;

    if (tableExists('app_content_pages')) {
        return;
    }

    $sql = "CREATE TABLE `app_content_pages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `page_key` VARCHAR(50) NOT NULL,
        `language_code` VARCHAR(5) NOT NULL,
        `title` VARCHAR(255) DEFAULT NULL,
        `content` LONGTEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_page_lang` (`page_key`, `language_code`),
        KEY `idx_page_key` (`page_key`),
        KEY `idx_language_code` (`language_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql);
}

function getLegacyPageContent($pageKey)
{
    global $conn;

    if (!tableExists('settings')) {
        return '';
    }

    $columnMap = [
        'about' => 'about_us',
        'terms' => 'terms_and_conditions',
        'privacy' => 'privacy_policy',
        'refund' => 'refund_policy',
    ];

    $column = $columnMap[$pageKey] ?? '';
    if ($column === '' || !columnExists('settings', $column)) {
        return '';
    }

    $query = "SELECT `$column` AS content FROM settings WHERE id = 1 LIMIT 1";
    $result = $conn->query($query);

    if (!$result) {
        return '';
    }

    $row = $result->fetch_assoc();
    return trim((string) ($row['content'] ?? ''));
}

function seedDefaultContentPages()
{
    global $conn;

    if (!tableExists('app_content_pages')) {
        return;
    }

    $countResult = $conn->query("SELECT COUNT(*) AS total FROM app_content_pages");
    $count = 0;
    if ($countResult) {
        $countRow = $countResult->fetch_assoc();
        $count = (int) ($countRow['total'] ?? 0);
    }

    if ($count > 0) {
        return;
    }

    $defaults = getDefaultContentPagesMap();

    foreach ($defaults as $pageKey => $languages) {
        $legacyArabic = getLegacyPageContent($pageKey);

        foreach ($languages as $langCode => $payload) {
            $title = trim((string) ($payload['title'] ?? ''));
            $content = trim((string) ($payload['content'] ?? ''));

            if ($langCode === 'ar' && $legacyArabic !== '') {
                $content = $legacyArabic;
            }

            $stmt = $conn->prepare("INSERT INTO app_content_pages (page_key, language_code, title, content) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('ssss', $pageKey, $langCode, $title, $content);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

function ensureContentPagesReady()
{
    static $isReady = false;

    if ($isReady) {
        return;
    }

    ensureContentPagesTable();
    seedDefaultContentPages();

    $isReady = true;
}

function fetchPageContent($pageKey, $languageCode)
{
    global $conn;

    ensureContentPagesReady();

    $defaults = getDefaultContentPagesMap();
    $safePageKey = isset($defaults[$pageKey]) ? $pageKey : 'about';
    $requestedLanguage = normalizeLanguageCode($languageCode);

    if (tableExists('app_content_pages')) {
        $stmt = $conn->prepare("SELECT title, content FROM app_content_pages WHERE page_key = ? AND language_code = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $safePageKey, $requestedLanguage);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                return [
                    'title' => (string) ($row['title'] ?? ''),
                    'content' => (string) ($row['content'] ?? ''),
                    'language' => $requestedLanguage,
                ];
            }
        }

        if ($requestedLanguage !== 'ar') {
            $fallbackLang = 'ar';
            $stmt = $conn->prepare("SELECT title, content FROM app_content_pages WHERE page_key = ? AND language_code = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $safePageKey, $fallbackLang);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if ($row) {
                    return [
                        'title' => (string) ($row['title'] ?? ''),
                        'content' => (string) ($row['content'] ?? ''),
                        'language' => $fallbackLang,
                    ];
                }
            }
        }
    }

    $fallbackLanguage = isset($defaults[$safePageKey][$requestedLanguage])
        ? $requestedLanguage
        : 'ar';

    $payload = $defaults[$safePageKey][$fallbackLanguage];

    return [
        'title' => (string) ($payload['title'] ?? ''),
        'content' => (string) ($payload['content'] ?? ''),
        'language' => $fallbackLanguage,
    ];
}

function firstNonEmpty(array $values, $fallback = '')
{
    foreach ($values as $value) {
        $text = trim((string) $value);
        if ($text !== '') {
            return $text;
        }
    }

    return $fallback;
}

function getLocalizedSettingValue(array $settings, $baseKey, $languageCode, $default = '')
{
    $lang = normalizeLanguageCode($languageCode);

    $candidates = [
        $settings[$baseKey . '_' . $lang] ?? '',
        $settings[$baseKey . '_ar'] ?? '',
        $settings[$baseKey . '_en'] ?? '',
        $settings[$baseKey . '_ur'] ?? '',
        $settings[$baseKey] ?? '',
    ];

    return firstNonEmpty($candidates, $default);
}

function buildAboutMeta(array $settings, $languageCode)
{
    $defaultIcons = [1 => '⭐', 2 => '💰', 3 => '⚡', 4 => '🎧', 5 => '🛡️'];
    $features = [];

    for ($index = 1; $index <= 5; $index++) {
        $title = getLocalizedSettingValue($settings, "about_feature_{$index}_title", $languageCode, '');
        $description = getLocalizedSettingValue($settings, "about_feature_{$index}_description", $languageCode, '');

        if ($title === '' && $description === '') {
            continue;
        }

        $icon = firstNonEmpty(
            [$settings["about_feature_{$index}_icon"] ?? '', $defaultIcons[$index] ?? '⭐'],
            '⭐'
        );

        $features[] = [
            'icon' => $icon,
            'title' => $title,
            'description' => $description,
        ];
    }

    return [
        'why_title' => getLocalizedSettingValue($settings, 'about_why_title', $languageCode, ''),
        'stats' => [
            'happy_clients' => firstNonEmpty([$settings['about_stat_happy_clients'] ?? ''], '50,000+'),
            'service_providers' => firstNonEmpty([$settings['about_stat_service_providers'] ?? ''], '2,500+'),
            'completed_orders' => firstNonEmpty([$settings['about_stat_completed_orders'] ?? ''], '100,000+'),
        ],
        'features' => $features,
    ];
}

function buildShareMeta(array $settings, $languageCode)
{
    $benefits = [];
    for ($index = 1; $index <= 3; $index++) {
        $title = getLocalizedSettingValue($settings, "share_benefit_{$index}_title", $languageCode, '');
        $subtitle = getLocalizedSettingValue($settings, "share_benefit_{$index}_subtitle", $languageCode, '');
        if ($title === '' && $subtitle === '') {
            continue;
        }

        $benefits[] = [
            'title' => $title,
            'subtitle' => $subtitle,
        ];
    }

    return [
        'reward_amount' => firstNonEmpty([
            $settings['referral_reward_amount'] ?? '',
            $settings['share_reward_amount'] ?? '',
        ], '50'),
        'reward_reason' => getLocalizedSettingValue($settings, 'share_reward_reason', $languageCode, ''),
        'invite_subtitle' => getLocalizedSettingValue($settings, 'share_invite_subtitle', $languageCode, ''),
        'invite_message' => getLocalizedSettingValue($settings, 'share_invite_message', $languageCode, ''),
        'program_title' => getLocalizedSettingValue($settings, 'share_program_title', $languageCode, ''),
        'link_base' => firstNonEmpty([$settings['share_link_base'] ?? ''], ''),
        'benefits' => $benefits,
    ];
}

function buildHelpCenterMeta(array $settings, $languageCode)
{
    $faqCountRaw = (int) ($settings['help_faq_count'] ?? 4);
    if ($faqCountRaw < 1) {
        $faqCountRaw = 1;
    }
    if ($faqCountRaw > 8) {
        $faqCountRaw = 8;
    }

    $faqs = [];
    for ($index = 1; $index <= $faqCountRaw; $index++) {
        $question = firstNonEmpty([
            getLocalizedSettingValue($settings, "help_faq_{$index}_question", $languageCode, ''),
            getLocalizedSettingValue($settings, "faq_{$index}_question", $languageCode, ''),
        ], '');
        $answer = firstNonEmpty([
            getLocalizedSettingValue($settings, "help_faq_{$index}_answer", $languageCode, ''),
            getLocalizedSettingValue($settings, "faq_{$index}_answer", $languageCode, ''),
        ], '');

        if ($question === '' && $answer === '') {
            continue;
        }

        $faqs[] = [
            'question' => $question,
            'answer' => $answer,
        ];
    }

    return [
        'banner_text' => getLocalizedSettingValue($settings, 'help_banner_text', $languageCode, ''),
        'faqs' => $faqs,
    ];
}

/**
 * Get app settings
 */
function getAppSettings()
{
    $language = resolveRequestedLanguage();
    $legacySettings = fetchKeyValueSettings('settings', 'key', 'value');
    $appSettings = fetchKeyValueSettings('app_settings', 'setting_key', 'setting_value');
    $settings = array_merge($legacySettings, $appSettings);
    $aboutMeta = buildAboutMeta($settings, $language);
    $shareMeta = buildShareMeta($settings, $language);
    $helpCenterMeta = buildHelpCenterMeta($settings, $language);

    $appLogoPath = trim((string) ($settings['app_logo'] ?? ''));
    $providerLogoPath = trim((string) ($settings['provider_app_logo'] ?? ''));
    $appLogoUrl = $appLogoPath !== '' ? imageUrl($appLogoPath) : null;
    $providerLogoUrl = $providerLogoPath !== '' ? imageUrl($providerLogoPath) : null;

    $oneSignalAppId = trim((string) ($settings['onesignal_app_id'] ?? $settings['one_signal_app_id'] ?? ''));
    if ($oneSignalAppId === '') {
        $oneSignalAppId = trim((string) (getenv('ONESIGNAL_APP_ID') ?: getenv('ONE_SIGNAL_APP_ID') ?: ''));
    }
    if ($oneSignalAppId === '' && defined('ONESIGNAL_APP_ID')) {
        $oneSignalAppId = trim((string) ONESIGNAL_APP_ID);
    }

    sendSuccess([
        'app_name' => getLocalizedSettingValue($settings, 'app_name', $language, 'Darfix'),
        'app_version' => $settings['app_version'] ?? '1.0.0',
        'min_version' => $settings['min_version'] ?? '1.0.0',
        'force_update' => toBoolSetting($settings['force_update'] ?? null, false),
        'maintenance_mode' => toBoolSetting($settings['maintenance_mode'] ?? null, false),
        'support_phone' => $settings['support_phone'] ?? '+966501234567',
        'support_email' => $settings['support_email'] ?? 'support@ertah.app',
        'support_address' => firstNonEmpty([
            $settings['support_address'] ?? '',
            $settings['address'] ?? '',
        ], 'الرياض، المملكة العربية السعودية'),
        'app_logo' => $appLogoUrl,
        'provider_app_logo' => $providerLogoUrl,
        'spare_parts_min_order_with_installation' => (float) ($settings['spare_parts_min_order_with_installation'] ?? 0),
        'whatsapp' => $settings['whatsapp'] ?? '+966501234567',
        'facebook' => $settings['facebook'] ?? '',
        'twitter' => $settings['twitter'] ?? '',
        'instagram' => $settings['instagram'] ?? '',
        'referral_reward_amount' => $shareMeta['reward_amount'],
        'share_reward_reason' => $shareMeta['reward_reason'],
        'about_meta' => $aboutMeta,
        'share_meta' => $shareMeta,
        'help_center_meta' => $helpCenterMeta,
        'language' => $language,
        'app_font' => normalizeAppFont($settings['app_font'] ?? 'cairo'),
        'available_fonts' => ['cairo', 'zain'],
        'onesignal_app_id' => $oneSignalAppId,
        'darfix_ai' => [
            'enabled' => toBoolSetting($settings['darfix_ai_enabled'] ?? null, true),
        ],
        'darfix_ai_enabled' => toBoolSetting($settings['darfix_ai_enabled'] ?? null, true),
    ]);
}

/**
 * Get about us
 */
function getAbout()
{
    $language = resolveRequestedLanguage();
    $payload = fetchPageContent('about', $language);

    sendSuccess([
        'content' => $payload['content'],
        'title' => $payload['title'],
        'language' => $payload['language'],
        'requested_language' => $language,
    ]);
}

/**
 * Get terms and conditions
 */
function getTerms()
{
    $language = resolveRequestedLanguage();
    $payload = fetchPageContent('terms', $language);

    sendSuccess([
        'content' => $payload['content'],
        'title' => $payload['title'],
        'language' => $payload['language'],
        'requested_language' => $language,
    ]);
}

/**
 * Get privacy policy
 */
function getPrivacy()
{
    $language = resolveRequestedLanguage();
    $payload = fetchPageContent('privacy', $language);

    sendSuccess([
        'content' => $payload['content'],
        'title' => $payload['title'],
        'language' => $payload['language'],
        'requested_language' => $language,
    ]);
}

/**
 * Get refund policy
 */
function getRefund()
{
    $language = resolveRequestedLanguage();
    $payload = fetchPageContent('refund', $language);

    sendSuccess([
        'content' => $payload['content'],
        'title' => $payload['title'],
        'language' => $payload['language'],
        'requested_language' => $language,
    ]);
}

/**
 * Get contact info
 */
function getContact()
{
    $defaults = [
        'phone' => '+966501234567',
        'email' => 'info@ertah.app',
        'whatsapp' => '+966501234567',
        'address' => 'الرياض، المملكة العربية السعودية',
    ];
    $legacySettings = fetchKeyValueSettings('settings', 'key', 'value');
    $appSettings = fetchKeyValueSettings('app_settings', 'setting_key', 'setting_value');
    $settings = array_merge($legacySettings, $appSettings);

    $contact = [
        'phone' => firstNonEmpty([
            $settings['support_phone'] ?? '',
            $settings['phone'] ?? '',
        ], $defaults['phone']),
        'email' => firstNonEmpty([
            $settings['support_email'] ?? '',
            $settings['email'] ?? '',
        ], $defaults['email']),
        'support_phone' => firstNonEmpty([
            $settings['support_phone'] ?? '',
            $settings['phone'] ?? '',
        ], $defaults['phone']),
        'support_email' => firstNonEmpty([
            $settings['support_email'] ?? '',
            $settings['email'] ?? '',
        ], $defaults['email']),
        'whatsapp' => firstNonEmpty([$settings['whatsapp'] ?? ''], $defaults['whatsapp']),
        'address' => firstNonEmpty([
            $settings['support_address'] ?? '',
            $settings['address'] ?? '',
        ], $defaults['address']),
    ];

    sendSuccess($contact);
}
