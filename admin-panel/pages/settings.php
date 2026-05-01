<?php
/**
 * صفحة إعدادات التطبيق
 * App Settings Page
 */

require_once '../init.php';
requireLogin();

$pageTitle = 'إعدادات التطبيق';
$pageSubtitle = 'إدارة ميزات وخصائص التطبيق';

$homeSectionIconFields = [
    'home_section_icon_most_requested' => 'الأكثر طلباً',
    'home_section_icon_services' => 'الخدمات',
    'home_section_icon_spare_parts' => 'قطع غيار مع التركيب',
    'home_section_icon_latest_offers' => 'أحدث العروض',
];

$brandingLogoFields = [
    'app_logo' => 'شعار تطبيق العميل',
    'provider_app_logo' => 'شعار تطبيق مقدم الخدمة',
];

$homeSectionSettingsConfig = [
    'how_it_works' => [
        'label' => 'طريقة عمل Darfix',
        'default_visible' => 1,
        'default_order' => 1,
    ],
    'services' => [
        'label' => 'الخدمات',
        'default_visible' => 1,
        'default_order' => 2,
    ],
    'most_requested_services' => [
        'label' => 'الأكثر طلباً',
        'default_visible' => 1,
        'default_order' => 3,
    ],
    'ad_banner' => [
        'label' => 'البنر الإعلاني',
        'default_visible' => 1,
        'default_order' => 4,
    ],
    'spare_parts' => [
        'label' => 'قطع الغيار',
        'default_visible' => 1,
        'default_order' => 5,
    ],
    'stores' => [
        'label' => 'المتاجر',
        'default_visible' => 1,
        'default_order' => 6,
    ],
    'offers' => [
        'label' => 'أحدث العروض',
        'default_visible' => 1,
        'default_order' => 7,
    ],
];

$homeFeedLimitsConfig = [
    'home_limit_how_it_works_steps' => [
        'label' => 'عدد خطوات "كيف يعمل DarFix"',
        'default' => 4,
        'min' => 0,
        'max' => 10,
        'help' => '0 = إخفاء قسم كيف يعمل DarFix.',
    ],
    'home_limit_banners' => [
        'label' => 'عدد بنرات السلايدر في الرئيسية',
        'default' => 5,
        'min' => 0,
        'max' => 20,
        'help' => '0 = إخفاء السلايدر بالكامل.',
    ],
    'home_limit_categories' => [
        'label' => 'عدد الفئات (الخدمات) في الرئيسية',
        'default' => 8,
        'min' => 0,
        'max' => 30,
        'help' => 'يتحكم في شبكة الفئات الظاهرة للمستخدم.',
    ],
    'home_limit_most_requested_services' => [
        'label' => 'عدد الخدمات الأكثر طلباً',
        'default' => 4,
        'min' => 0,
        'max' => 30,
        'help' => '0 = إخفاء قسم الأكثر طلباً.',
    ],
    'home_limit_spare_parts' => [
        'label' => 'عدد قطع الغيار المعروضة',
        'default' => 4,
        'min' => 0,
        'max' => 30,
        'help' => '0 = إخفاء قسم قطع الغيار.',
    ],
    'home_limit_stores' => [
        'label' => 'عدد المتاجر المعروضة',
        'default' => 5,
        'min' => 0,
        'max' => 30,
        'help' => '0 = إخفاء قسم المتاجر.',
    ],
    'home_limit_offers' => [
        'label' => 'عدد العروض المعروضة',
        'default' => 5,
        'min' => 0,
        'max' => 30,
        'help' => '0 = إخفاء قسم العروض.',
    ],
    'home_limit_cities' => [
        'label' => 'عدد المدن المرسلة للتطبيق',
        'default' => 200,
        'min' => 0,
        'max' => 2000,
        'help' => 'ينصح بعدد متوسط لتسريع التحميل.',
    ],
];

$howItWorksStepsConfig = [
    1 => [
        'default_image' => 'https://iili.io/fxvIxea.png',
        'titles' => [
            'ar' => 'احجز الخدمة',
            'en' => 'Book Service',
            'ur' => 'سروس بک کریں',
        ],
        'subtitles' => [
            'ar' => 'التي تحتاجها',
            'en' => 'That you need',
            'ur' => 'جس کی آپ کو ضرورت ہے',
        ],
    ],
    2 => [
        'default_image' => 'https://iili.io/fxvg2TP.png',
        'titles' => [
            'ar' => 'استقبل العروض',
            'en' => 'Receive Offers',
            'ur' => 'پیشکشیں وصول کریں',
        ],
        'subtitles' => [
            'ar' => 'من مقدمي الخدمات',
            'en' => 'From service providers',
            'ur' => 'سروس فراہم کنندگان سے',
        ],
    ],
    3 => [
        'default_image' => 'https://iili.io/fxv4PvR.png',
        'titles' => [
            'ar' => 'اختر الأفضل',
            'en' => 'Choose Best',
            'ur' => 'بہترین کا انتخاب کریں',
        ],
        'subtitles' => [
            'ar' => 'السعر والتقييم',
            'en' => 'Price and Rating',
            'ur' => 'قیمت اور درجہ بندی',
        ],
    ],
    4 => [
        'default_image' => 'https://iili.io/fxWOm92.png',
        'titles' => [
            'ar' => 'تنفيذ الخدمة',
            'en' => 'Execute Service',
            'ur' => 'سروس انجام دیں',
        ],
        'subtitles' => [
            'ar' => 'بجودة عالية',
            'en' => 'High Quality',
            'ur' => 'اعلی معیار',
        ],
    ],
];

$defaultServiceCountryOptions = [
    'SA' => 'السعودية',
    'AE' => 'الإمارات',
    'KW' => 'الكويت',
    'QA' => 'قطر',
    'BH' => 'البحرين',
    'OM' => 'عُمان',
    'EG' => 'مصر',
    'JO' => 'الأردن',
    'IQ' => 'العراق',
    'MA' => 'المغرب',
];
$serviceCountryOptions = getServiceCountryOptions($defaultServiceCountryOptions);

$contentLanguages = [
    'ar' => 'العربية',
    'en' => 'English',
    'ur' => 'اردو',
];

$contentPages = [
    'about' => [
        'label' => 'عن التطبيق',
        'titles' => [
            'ar' => 'عن Darfix',
            'en' => 'About Darfix',
            'ur' => 'Darfix کے بارے میں',
        ],
    ],
    'privacy' => [
        'label' => 'سياسة الخصوصية',
        'titles' => [
            'ar' => 'سياسة الخصوصية',
            'en' => 'Privacy Policy',
            'ur' => 'رازداری کی پالیسی',
        ],
    ],
    'terms' => [
        'label' => 'شروط الاستخدام',
        'titles' => [
            'ar' => 'شروط الاستخدام',
            'en' => 'Terms of Use',
            'ur' => 'استعمال کی شرائط',
        ],
    ],
    'refund' => [
        'label' => 'سياسة الاسترداد',
        'titles' => [
            'ar' => 'سياسة الاسترداد',
            'en' => 'Refund Policy',
            'ur' => 'واپسی کی پالیسی',
        ],
    ],
];

$aboutFeatureDefaults = [
    1 => [
        'icon' => '⭐',
        'titles' => [
            'ar' => 'جودة عالية',
            'en' => 'High Quality',
            'ur' => 'اعلی معیار',
        ],
        'descriptions' => [
            'ar' => 'نضمن لك أفضل جودة في الخدمات المنزلية',
            'en' => 'We guarantee top quality home services.',
            'ur' => 'ہم گھریلو خدمات میں بہترین معیار کی ضمانت دیتے ہیں۔',
        ],
    ],
    2 => [
        'icon' => '💰',
        'titles' => [
            'ar' => 'أسعار منافسة',
            'en' => 'Competitive Prices',
            'ur' => 'مسابقتی قیمتیں',
        ],
        'descriptions' => [
            'ar' => 'أفضل الأسعار في السوق السعودي',
            'en' => 'Best value in the local market.',
            'ur' => 'مارکیٹ میں بہترین قیمتیں۔',
        ],
    ],
    3 => [
        'icon' => '⚡',
        'titles' => [
            'ar' => 'خدمة سريعة',
            'en' => 'Fast Service',
            'ur' => 'تیز سروس',
        ],
        'descriptions' => [
            'ar' => 'استجابة فورية وتنفيذ سريع',
            'en' => 'Fast response and quick execution.',
            'ur' => 'فوری رسپانس اور تیز عمل درآمد۔',
        ],
    ],
    4 => [
        'icon' => '🎧',
        'titles' => [
            'ar' => 'دعم 24/7',
            'en' => '24/7 Support',
            'ur' => '24/7 سپورٹ',
        ],
        'descriptions' => [
            'ar' => 'فريق دعم متاح على مدار الساعة',
            'en' => 'Support team available around the clock.',
            'ur' => 'سپورٹ ٹیم ہر وقت دستیاب ہے۔',
        ],
    ],
    5 => [
        'icon' => '🛡️',
        'titles' => [
            'ar' => 'ضمان شامل',
            'en' => 'Comprehensive Warranty',
            'ur' => 'جامع وارنٹی',
        ],
        'descriptions' => [
            'ar' => 'ضمان جودة الخدمة مع المتابعة',
            'en' => 'Service quality guarantee with follow-up.',
            'ur' => 'سروس کے معیار کی ضمانت اور فالو اپ۔',
        ],
    ],
];

$shareBenefitDefaults = [
    1 => [
        'titles' => [
            'ar' => 'احصل على 50 ريال لكل صديق',
            'en' => 'Get 50 SAR for each friend',
            'ur' => 'ہر دوست پر 50 ریال حاصل کریں',
        ],
        'subtitles' => [
            'ar' => 'عند تسجيله لأول مرة',
            'en' => 'When they register for the first time',
            'ur' => 'جب وہ پہلی بار رجسٹر کرے',
        ],
    ],
    2 => [
        'titles' => [
            'ar' => 'شارك الرابط بسهولة',
            'en' => 'Share your link easily',
            'ur' => 'اپنا لنک آسانی سے شیئر کریں',
        ],
        'subtitles' => [
            'ar' => 'واتساب، إيميل أو أي تطبيق',
            'en' => 'WhatsApp, email, or any app',
            'ur' => 'واٹس ایپ، ای میل یا کسی بھی ایپ سے',
        ],
    ],
    3 => [
        'titles' => [
            'ar' => 'رصيد فوري في المحفظة',
            'en' => 'Instant wallet credit',
            'ur' => 'والٹ میں فوری کریڈٹ',
        ],
        'subtitles' => [
            'ar' => 'يضاف تلقائياً بعد تحقق الشروط',
            'en' => 'Added automatically when conditions are met',
            'ur' => 'شرائط پوری ہونے پر خودکار طور پر شامل ہوگا',
        ],
    ],
];

$helpFaqDefaults = [
    1 => [
        'questions' => [
            'ar' => 'كيف يمكنني طلب خدمة؟',
            'en' => 'How can I book a service?',
            'ur' => 'میں سروس کیسے بُک کر سکتا ہوں؟',
        ],
        'answers' => [
            'ar' => 'يمكنك طلب الخدمة من الصفحة الرئيسية ثم اختيار الموعد المناسب.',
            'en' => 'Choose your service from home, then select the suitable time.',
            'ur' => 'ہوم اسکرین سے سروس منتخب کریں اور مناسب وقت چنیں۔',
        ],
    ],
    2 => [
        'questions' => [
            'ar' => 'كيف يمكنني الدفع؟',
            'en' => 'How can I pay?',
            'ur' => 'میں ادائیگی کیسے کر سکتا ہوں؟',
        ],
        'answers' => [
            'ar' => 'يمكنك الدفع عبر البطاقة أو المحفظة أو Apple Pay حسب المتاح.',
            'en' => 'Pay by card, wallet, or Apple Pay based on availability.',
            'ur' => 'کارڈ، والٹ یا ایپل پے کے ذریعے ادائیگی کریں۔',
        ],
    ],
    3 => [
        'questions' => [
            'ar' => 'هل يمكنني إلغاء الطلب؟',
            'en' => 'Can I cancel my order?',
            'ur' => 'کیا میں آرڈر منسوخ کر سکتا ہوں؟',
        ],
        'answers' => [
            'ar' => 'نعم، يمكنك الإلغاء حسب سياسة الإلغاء داخل التطبيق.',
            'en' => 'Yes, according to the cancellation policy in the app.',
            'ur' => 'جی ہاں، ایپ کی منسوخی پالیسی کے مطابق۔',
        ],
    ],
    4 => [
        'questions' => [
            'ar' => 'كيف أتواصل مع الفني؟',
            'en' => 'How do I contact the technician?',
            'ur' => 'میں ٹیکنیشن سے کیسے رابطہ کروں؟',
        ],
        'answers' => [
            'ar' => 'من صفحة تتبع الطلب ستجد أزرار الاتصال والدردشة.',
            'en' => 'Use call/chat actions from the order tracking screen.',
            'ur' => 'آرڈر ٹریکنگ اسکرین سے کال/چیٹ کے بٹن استعمال کریں۔',
        ],
    ],
];

const DARFIX_AI_DEFAULT_ENDPOINT = 'https://api.us-west-2.modal.direct/v1/chat/completions';
const DARFIX_AI_DEFAULT_MODEL = 'zai-org/GLM-5-FP8';
const DARFIX_AI_DEFAULT_API_KEY = 'modalresearch_yjGu-_89u70CljD8gI2xuUP7gDQIa-Y63uojEtC9Tso';
const DARFIX_AI_DEFAULT_MAX_TOKENS = 500;
const DARFIX_SMS_SENDER_ID = 'Darfix';

function normalizeArabicDigitsToEnglish($value)
{
    return strtr((string) $value, [
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
    ]);
}

function sanitizeFixedOtp($value, $fallback = '1234')
{
    $normalized = normalizeArabicDigitsToEnglish((string) $value);
    $digits = preg_replace('/\D+/', '', $normalized) ?? '';

    if (strlen($digits) !== 4) {
        return $fallback;
    }

    return $digits;
}

function normalizeSmsSenderIdValue($value, $fallback = DARFIX_SMS_SENDER_ID)
{
    $senderId = trim((string) $value);
    $compact = strtolower(preg_replace('/[\s_\-]+/', '', $senderId) ?? '');

    if ($senderId === '' || in_array($compact, ['ertah', 'ertahapp', 'ertahsms'], true)) {
        return $fallback;
    }

    return $senderId;
}

ensureContentPagesTable();
seedContentPagesDefaults($contentPages, $contentLanguages);
$contentPagesData = loadContentPagesFromDatabase($contentPages, $contentLanguages);

// حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    $allowedAppFonts = ['cairo', 'zain'];
    $allowedCountryCodes = array_keys($serviceCountryOptions);

    if (!isset($settings['supported_countries']) || !is_array($settings['supported_countries'])) {
        $settings['supported_countries'] = [];
    }

    $settings['supported_countries'] = array_values(array_unique(array_filter(array_map(
        function ($value) use ($allowedCountryCodes) {
            $normalized = strtoupper(trim((string) $value));
            return in_array($normalized, $allowedCountryCodes, true) ? $normalized : '';
        },
        $settings['supported_countries']
    ))));

    if (isset($settings['app_font'])) {
        $requestedFont = strtolower(trim((string) $settings['app_font']));
        $settings['app_font'] = in_array($requestedFont, $allowedAppFonts, true) ? $requestedFont : 'cairo';
    }

    if (isset($settings['whatsapp'])) {
        $whatsappRaw = trim((string) $settings['whatsapp']);
        $whatsappRaw = preg_replace('/\s+/', '', $whatsappRaw);

        if ($whatsappRaw === '') {
            $settings['whatsapp'] = '';
        } elseif (strpos($whatsappRaw, '+') === 0) {
            $digits = preg_replace('/\D+/', '', substr($whatsappRaw, 1));
            $settings['whatsapp'] = $digits === '' ? '' : '+' . $digits;
        } else {
            $settings['whatsapp'] = preg_replace('/\D+/', '', $whatsappRaw);
        }
    }

    if (isset($settings['myfatoorah_enabled'])) {
        $enabledRaw = strtolower(trim((string) $settings['myfatoorah_enabled']));
        $settings['myfatoorah_enabled'] = in_array($enabledRaw, ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }

    if (isset($settings['darfix_ai_enabled'])) {
        $enabledRaw = strtolower(trim((string) $settings['darfix_ai_enabled']));
        $settings['darfix_ai_enabled'] = in_array($enabledRaw, ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }

    $smsEnabledRaw = strtolower(trim((string) ($settings['sms_enabled'] ?? '0')));
    $settings['sms_enabled'] = in_array($smsEnabledRaw, ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    $settings['fixed_otp'] = sanitizeFixedOtp($settings['fixed_otp'] ?? '1234');

    if (isset($settings['myfatoorah_base_url'])) {
        $baseUrl = trim((string) $settings['myfatoorah_base_url']);
        if ($baseUrl === '') {
            $baseUrl = (string) (defined('MYFATOORAH_BASE_URL') ? MYFATOORAH_BASE_URL : 'https://api-sa.myfatoorah.com');
        }
        $settings['myfatoorah_base_url'] = rtrim($baseUrl, '/');
    }

    if (array_key_exists('myfatoorah_token', $settings)) {
        $tokenInput = trim((string) $settings['myfatoorah_token']);
        if ($tokenInput === '') {
            $existingToken = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'myfatoorah_token' LIMIT 1");
            $settings['myfatoorah_token'] = trim((string) ($existingToken['setting_value'] ?? ''));
        } else {
            $settings['myfatoorah_token'] = $tokenInput;
        }
    }

    if (array_key_exists('sms_api_key', $settings)) {
        $keyInput = trim((string) $settings['sms_api_key']);
        if ($keyInput === '') {
            $existingKey = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'sms_api_key' LIMIT 1");
            $settings['sms_api_key'] = trim((string) ($existingKey['setting_value'] ?? ''));
        } else {
            $settings['sms_api_key'] = $keyInput;
        }
    }

    if (array_key_exists('sms_api_secret', $settings)) {
        $secretInput = trim((string) $settings['sms_api_secret']);
        if ($secretInput === '') {
            $existingSecret = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'sms_api_secret' LIMIT 1");
            $settings['sms_api_secret'] = trim((string) ($existingSecret['setting_value'] ?? ''));
        } else {
            $settings['sms_api_secret'] = $secretInput;
        }
    }

    if (array_key_exists('darfix_ai_api_key', $settings)) {
        $keyInput = trim((string) $settings['darfix_ai_api_key']);
        if ($keyInput === '') {
            $existingKey = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'darfix_ai_api_key' LIMIT 1");
            $settings['darfix_ai_api_key'] = trim((string) ($existingKey['setting_value'] ?? ''));
        } else {
            $settings['darfix_ai_api_key'] = $keyInput;
        }
    }

    if (isset($settings['sms_sender_id'])) {
        $settings['sms_sender_id'] = normalizeSmsSenderIdValue($settings['sms_sender_id']);
    }

    if (isset($settings['sms_api_url'])) {
        $smsUrl = trim((string) $settings['sms_api_url']);
        if ($smsUrl === '') {
            $smsUrl = 'https://api-sms.4jawaly.com/api/v1/account/area/sms/v2/send';
        }
        $settings['sms_api_url'] = $smsUrl;
    }

    if (isset($settings['darfix_ai_model'])) {
        $model = trim((string) $settings['darfix_ai_model']);
        $settings['darfix_ai_model'] = $model !== '' ? $model : DARFIX_AI_DEFAULT_MODEL;
    }

    if (isset($settings['darfix_ai_endpoint'])) {
        $endpoint = trim((string) $settings['darfix_ai_endpoint']);
        $settings['darfix_ai_endpoint'] = $endpoint !== '' ? $endpoint : DARFIX_AI_DEFAULT_ENDPOINT;
    }

    if (isset($settings['darfix_ai_system_prompt'])) {
        $settings['darfix_ai_system_prompt'] = trim((string) $settings['darfix_ai_system_prompt']);
    }

    if (isset($settings['darfix_ai_max_tokens'])) {
        $maxTokensRaw = normalizeArabicDigitsToEnglish((string) $settings['darfix_ai_max_tokens']);
        $maxTokens = (int) preg_replace('/[^\d]/', '', $maxTokensRaw);
        if ($maxTokens < 64) {
            $maxTokens = DARFIX_AI_DEFAULT_MAX_TOKENS;
        }
        if ($maxTokens > 4000) {
            $maxTokens = 4000;
        }
        $settings['darfix_ai_max_tokens'] = (string) $maxTokens;
    }

    $settings['support_phone'] = trim((string) ($settings['support_phone'] ?? conf('support_phone', '+966501234567')));
    $settings['support_email'] = trim((string) ($settings['support_email'] ?? conf('support_email', 'support@ertah.app')));
    $settings['support_address'] = trim((string) ($settings['support_address'] ?? conf('support_address', conf('address', 'الرياض، المملكة العربية السعودية'))));

    $helpFaqCountRaw = normalizeArabicDigitsToEnglish((string) ($settings['help_faq_count'] ?? conf('help_faq_count', '4')));
    $helpFaqCount = (int) preg_replace('/[^\d]/', '', $helpFaqCountRaw);
    if ($helpFaqCount < 1) {
        $helpFaqCount = 1;
    }
    if ($helpFaqCount > 8) {
        $helpFaqCount = 8;
    }
    $settings['help_faq_count'] = (string) $helpFaqCount;

    $rewardAmountRaw = normalizeArabicDigitsToEnglish((string) ($settings['referral_reward_amount'] ?? conf('referral_reward_amount', '50')));
    $rewardAmount = preg_replace('/[^0-9.]/', '', $rewardAmountRaw) ?? '';
    if ($rewardAmount === '') {
        $rewardAmount = '50';
    }
    $settings['referral_reward_amount'] = $rewardAmount;

    $pointsPerCurrencyRaw = normalizeArabicDigitsToEnglish((string) ($settings['points_per_currency_unit'] ?? conf('points_per_currency_unit', '10')));
    $pointsPerCurrency = preg_replace('/[^0-9.]/', '', $pointsPerCurrencyRaw) ?? '';
    if ($pointsPerCurrency === '' || (float) $pointsPerCurrency <= 0) {
        $pointsPerCurrency = '10';
    }
    $settings['points_per_currency_unit'] = $pointsPerCurrency;

    foreach ($homeSectionSettingsConfig as $sectionKey => $sectionMeta) {
        $visibleKey = 'home_section_visible_' . $sectionKey;
        $orderKey = 'home_section_order_' . $sectionKey;

        $visibleRaw = strtolower(trim((string) ($settings[$visibleKey] ?? $sectionMeta['default_visible'])));
        $settings[$visibleKey] = in_array($visibleRaw, ['0', 'false', 'no', 'off'], true) ? '0' : '1';

        $order = (int) ($settings[$orderKey] ?? $sectionMeta['default_order']);
        if ($order < 1) {
            $order = (int) $sectionMeta['default_order'];
        }
        if ($order > 99) {
            $order = 99;
        }
        $settings[$orderKey] = (string) $order;
    }

    foreach ($homeFeedLimitsConfig as $limitKey => $limitMeta) {
        $rawValue = normalizeArabicDigitsToEnglish((string) ($settings[$limitKey] ?? $limitMeta['default']));
        $parsed = (int) preg_replace('/[^\-\d]/', '', $rawValue);
        $min = (int) ($limitMeta['min'] ?? 0);
        $max = (int) ($limitMeta['max'] ?? 9999);

        if ($parsed < $min) {
            $parsed = $min;
        }
        if ($parsed > $max) {
            $parsed = $max;
        }

        $settings[$limitKey] = (string) $parsed;
    }

    $howItWorksInput = $_POST['how_it_works'] ?? [];
    foreach ($howItWorksStepsConfig as $stepNumber => $stepMeta) {
        $stepInput = $howItWorksInput[$stepNumber] ?? [];

        foreach ($contentLanguages as $langCode => $langLabel) {
            $titleSettingKey = "home_how_it_works_step_{$stepNumber}_title_{$langCode}";
            $subtitleSettingKey = "home_how_it_works_step_{$stepNumber}_subtitle_{$langCode}";

            $settings[$titleSettingKey] = trim((string) ($stepInput['title_' . $langCode] ?? ($stepMeta['titles'][$langCode] ?? '')));
            $settings[$subtitleSettingKey] = trim((string) ($stepInput['subtitle_' . $langCode] ?? ($stepMeta['subtitles'][$langCode] ?? '')));
        }
    }

    foreach ($settings as $key => $value) {
        if (is_array($value)) {
            $value = implode(',', array_filter(array_map('trim', $value)));
        }

        if ($key === 'supported_countries' && $value === '') {
            $value = 'SA';
        }

        // التحقق مما إذا كان الإعداد موجوداً
        $exists = db()->fetch("SELECT 1 FROM app_settings WHERE setting_key = ?", [$key]);

        if ($exists) {
            db()->update('app_settings', ['setting_value' => $value], 'setting_key = :key', ['key' => $key]);
        } else {
            db()->insert('app_settings', ['setting_key' => $key, 'setting_value' => $value, 'description' => '']);
        }
    }

    foreach ($homeSectionIconFields as $key => $label) {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
            setFlashMessage('error', "تعذر رفع أيقونة \"$label\"");
            redirect('settings.php');
        }

        $upload = uploadFile($_FILES[$key], 'home_sections');
        if (!$upload['success']) {
            setFlashMessage('error', "فشل رفع أيقونة \"$label\": " . ($upload['message'] ?? 'خطأ غير معروف'));
            redirect('settings.php');
        }

        $exists = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            db()->update('app_settings', ['setting_value' => $upload['path']], 'setting_key = :key', ['key' => $key]);
        } else {
            db()->insert('app_settings', ['setting_key' => $key, 'setting_value' => $upload['path'], 'description' => 'Home section icon']);
        }

        $oldPath = $exists['setting_value'] ?? '';
        if (!empty($oldPath) && $oldPath !== $upload['path'] && !filter_var($oldPath, FILTER_VALIDATE_URL)) {
            deleteFile($oldPath);
        }
    }

    foreach ($brandingLogoFields as $key => $label) {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
            setFlashMessage('error', "تعذر رفع شعار \"$label\"");
            redirect('settings.php');
        }

        $upload = uploadFile($_FILES[$key], 'branding');
        if (!$upload['success']) {
            setFlashMessage('error', "فشل رفع شعار \"$label\": " . ($upload['message'] ?? 'خطأ غير معروف'));
            redirect('settings.php');
        }

        $exists = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            db()->update('app_settings', ['setting_value' => $upload['path']], 'setting_key = :key', ['key' => $key]);
        } else {
            db()->insert('app_settings', ['setting_key' => $key, 'setting_value' => $upload['path'], 'description' => 'App branding logo']);
        }

        $oldPath = $exists['setting_value'] ?? '';
        if (!empty($oldPath) && $oldPath !== $upload['path'] && !filter_var($oldPath, FILTER_VALIDATE_URL)) {
            deleteFile($oldPath);
        }
    }

    foreach ($howItWorksStepsConfig as $stepNumber => $stepMeta) {
        $imageKey = "home_how_it_works_step_{$stepNumber}_image";
        if (!isset($_FILES[$imageKey]) || $_FILES[$imageKey]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($_FILES[$imageKey]['error'] !== UPLOAD_ERR_OK) {
            setFlashMessage('error', "تعذر رفع صورة الخطوة رقم {$stepNumber}");
            redirect('settings.php');
        }

        $upload = uploadFile($_FILES[$imageKey], 'home_how_it_works');
        if (!$upload['success']) {
            setFlashMessage('error', "فشل رفع صورة الخطوة رقم {$stepNumber}: " . ($upload['message'] ?? 'خطأ غير معروف'));
            redirect('settings.php');
        }

        $exists = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = ?", [$imageKey]);
        if ($exists) {
            db()->update('app_settings', ['setting_value' => $upload['path']], 'setting_key = :key', ['key' => $imageKey]);
        } else {
            db()->insert('app_settings', ['setting_key' => $imageKey, 'setting_value' => $upload['path'], 'description' => 'How it works step image']);
        }

        $oldPath = $exists['setting_value'] ?? '';
        if (!empty($oldPath) && $oldPath !== $upload['path'] && !filter_var($oldPath, FILTER_VALIDATE_URL)) {
            deleteFile($oldPath);
        }
    }

    $contentInput = $_POST['content_pages'] ?? [];
    foreach ($contentPages as $pageKey => $pageMeta) {
        foreach ($contentLanguages as $langCode => $langLabel) {
            $inputData = $contentInput[$pageKey][$langCode] ?? [];
            $fallbackTitle = $contentPagesData[$pageKey][$langCode]['title'] ?? ($pageMeta['titles'][$langCode] ?? $pageMeta['label']);
            $fallbackContent = $contentPagesData[$pageKey][$langCode]['content'] ?? '';

            $title = trim((string) ($inputData['title'] ?? $fallbackTitle));
            $content = trim((string) ($inputData['content'] ?? $fallbackContent));

            $existing = db()->fetch("SELECT id FROM app_content_pages WHERE page_key = ? AND language_code = ? LIMIT 1", [$pageKey, $langCode]);

            if ($existing) {
                db()->update(
                    'app_content_pages',
                    [
                        'title' => $title,
                        'content' => $content,
                    ],
                    'id = :id',
                    ['id' => $existing['id']]
                );
            } else {
                db()->insert('app_content_pages', [
                    'page_key' => $pageKey,
                    'language_code' => $langCode,
                    'title' => $title,
                    'content' => $content,
                ]);
            }
        }
    }

    logActivity('update_settings', 'settings', 0);
    setFlashMessage('success', 'تم حفظ الإعدادات بنجاح');
    redirect('settings.php');
}

// جلب جميع الإعدادات
$allSettings = db()->fetchAll("SELECT * FROM app_settings");
$config = [];
foreach ($allSettings as $s) {
    $config[$s['setting_key']] = $s['setting_value'];
}

// دالة مساعدة للحصول على قيمة الإعداد
function conf($key, $default = '')
{
    global $config;
    return $config[$key] ?? $default;
}

function maskSecretValue($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (strlen($value) <= 8) {
        return str_repeat('*', strlen($value));
    }

    return substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4);
}

function getServiceCountryOptions(array $fallback)
{
    $tableCheck = db()->fetch("SHOW TABLES LIKE 'countries'");
    if (!$tableCheck) {
        return $fallback;
    }

    $rows = db()->fetchAll("SELECT code, name_ar, name_en FROM countries WHERE is_active = 1 ORDER BY name_ar ASC");
    if (empty($rows)) {
        return $fallback;
    }

    $options = [];
    foreach ($rows as $row) {
        $code = strtoupper(trim((string) ($row['code'] ?? '')));
        if ($code === '') {
            continue;
        }
        $label = trim((string) ($row['name_ar'] ?: ($row['name_en'] ?: $code)));
        $options[$code] = $label;
    }

    if (empty($options)) {
        return $fallback;
    }

    foreach ($fallback as $code => $label) {
        if (!isset($options[$code])) {
            $options[$code] = $label;
        }
    }

    ksort($options);
    return $options;
}

function contentPagesTableExists()
{
    return (bool) db()->fetch("SHOW TABLES LIKE 'app_content_pages'");
}

function ensureContentPagesTable()
{
    if (contentPagesTableExists()) {
        return;
    }

    db()->query("
        CREATE TABLE IF NOT EXISTS app_content_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_key VARCHAR(50) NOT NULL,
            language_code VARCHAR(5) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            content LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_page_lang (page_key, language_code),
            KEY idx_page_key (page_key),
            KEY idx_language_code (language_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function settingsColumnExists($columnName)
{
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $columnName);
    if ($safeColumn === '') {
        return false;
    }

    if (!db()->fetch("SHOW TABLES LIKE 'settings'")) {
        return false;
    }

    return (bool) db()->fetch("SHOW COLUMNS FROM settings LIKE '{$safeColumn}'");
}

function getDefaultContentPagesMap()
{
    return [
        'about' => [
            'ar' => [
                'title' => 'عن Darfix',
                'content' => "Darfix هو تطبيق سعودي رائد في مجال الخدمات المنزلية، نربط بين العملاء ومقدمي الخدمات المحترفين بسرعة وسهولة في مختلف مدن المملكة.\n\nنوفر لك تجربة موثوقة تشمل الحجز السريع، متابعة الطلب لحظياً، طرق دفع آمنة، ودعم متواصل لضمان أفضل تجربة خدمة.",
            ],
            'en' => [
                'title' => 'About Darfix',
                'content' => "Darfix is a leading Saudi home-services app that connects customers with trusted service providers quickly and reliably across the Kingdom.\n\nWe provide a smooth experience with fast booking, real-time order tracking, secure payments, and responsive support.",
            ],
            'ur' => [
                'title' => 'Darfix کے بارے میں',
                'content' => "Darfix سعودی عرب میں گھریلو خدمات کے لیے ایک نمایاں ایپ ہے جو صارفین کو قابلِ اعتماد سروس فراہم کنندگان سے تیزی اور آسانی کے ساتھ جوڑتی ہے۔\n\nہم تیز بکنگ، آرڈر کی لائیو ٹریکنگ، محفوظ ادائیگی اور مسلسل سپورٹ فراہم کرتے ہیں تاکہ بہترین تجربہ مل سکے۔",
            ],
        ],
        'privacy' => [
            'ar' => [
                'title' => 'سياسة الخصوصية',
                'content' => "تطبيق Darfix يحترم خصوصيتك. نقوم بجمع البيانات التي تقدمها (الاسم، رقم الهاتف، البريد الإلكتروني، العناوين، تفاصيل الطلب، الصور) والبيانات الناتجة عن الاستخدام (الموقع عند طلب الخدمة، معلومات الجهاز، سجلات الاستخدام).\n\nنستخدم هذه البيانات لتقديم الخدمة، ربطك بمقدمي خدمات مناسبين، تنفيذ الطلبات، إرسال الإشعارات، منع الاحتيال، وتحسين التطبيق.\n\nنشارك البيانات فقط مع مقدمي الخدمات المعنيين ومع مزودي خدمات موثوقين مثل الدفع والرسائل والخرائط وفق ضوابط تعاقدية. لا نبيع بياناتك.\n\nيمكنك الوصول إلى بياناتك أو تحديثها أو حذف حسابك من داخل التطبيق (الإعدادات > حذف الحساب) أو عبر فريق الدعم. عند حذف الحساب نحذف أو نُجهّل بياناتك الشخصية، وقد نحتفظ ببعض السجلات للالتزامات القانونية أو المحاسبية أو الأمان.\n\nللاستفسارات: support@darfix.org.",
            ],
            'en' => [
                'title' => 'Privacy Policy',
                'content' => "Darfix respects your privacy. We collect data you provide (name, phone number, email, addresses, order details, photos) and data generated during use (location when you request a service, device info, usage logs).\n\nWe use this data to deliver services, match you with providers, process orders, send notifications, prevent fraud, and improve the app.\n\nWe share data only with relevant service providers and trusted vendors such as payment, messaging, and maps under contractual safeguards. We do not sell your data.\n\nYou can access, update, or delete your account from the app (Settings > Delete Account) or by contacting support. When you delete your account, we remove or anonymize personal data; some records may be retained for legal, accounting, or safety obligations.\n\nFor questions: support@darfix.org.",
            ],
            'ur' => [
                'title' => 'رازداری کی پالیسی',
                'content' => "Darfix respects your privacy. We collect data you provide (name, phone number, email, addresses, order details, photos) and data generated during use (location when you request a service, device info, usage logs).\n\nWe use this data to deliver services, match you with providers, process orders, send notifications, prevent fraud, and improve the app.\n\nWe share data only with relevant service providers and trusted vendors such as payment, messaging, and maps under contractual safeguards. We do not sell your data.\n\nYou can access, update, or delete your account from the app (Settings > Delete Account) or by contacting support. When you delete your account, we remove or anonymize personal data; some records may be retained for legal, accounting, or safety obligations.\n\nFor questions: support@darfix.org.",
            ],
        ],
        'terms' => [
            'ar' => [
                'title' => 'شروط الاستخدام',
                'content' => "باستخدامك لتطبيق Darfix، فإنك توافق على الالتزام بشروط الاستخدام.\n\n1) يجب استخدام التطبيق بطريقة قانونية وعدم إساءة الاستخدام.\n2) الأسعار والمواعيد تخضع لتأكيد مقدم الخدمة.\n3) يمكن إلغاء الطلب وفق سياسة الإلغاء المعتمدة.\n4) يحق للتطبيق تحديث هذه الشروط عند الحاجة.",
            ],
            'en' => [
                'title' => 'Terms of Use',
                'content' => "By using Darfix, you agree to comply with these terms.\n\n1) The app must be used lawfully and without abuse.\n2) Pricing and scheduling are subject to provider confirmation.\n3) Orders may be canceled according to the cancellation policy.\n4) We reserve the right to update these terms when needed.",
            ],
            'ur' => [
                'title' => 'استعمال کی شرائط',
                'content' => "Darfix ایپ استعمال کرنے سے آپ ان شرائط کی پابندی سے اتفاق کرتے ہیں۔\n\n1) ایپ کو قانونی طور پر اور بغیر غلط استعمال کے استعمال کیا جائے۔\n2) قیمت اور وقت سروس فراہم کنندہ کی تصدیق کے تابع ہیں۔\n3) آرڈر منسوخی پالیسی کے مطابق منسوخ کیا جا سکتا ہے۔\n4) ضرورت کے مطابق ان شرائط میں تبدیلی کا حق محفوظ ہے۔",
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

function getLegacyContentForPage($pageKey)
{
    $columnMap = [
        'about' => 'about_us',
        'privacy' => 'privacy_policy',
        'terms' => 'terms_and_conditions',
        'refund' => 'refund_policy',
    ];

    $column = $columnMap[$pageKey] ?? '';
    if ($column === '' || !settingsColumnExists($column)) {
        return '';
    }

    $query = "SELECT `{$column}` AS content FROM settings WHERE id = 1 LIMIT 1";
    $row = db()->fetch($query);
    return trim((string) ($row['content'] ?? ''));
}

function seedContentPagesDefaults(array $contentPages, array $contentLanguages)
{
    if (!contentPagesTableExists()) {
        return;
    }

    $countRow = db()->fetch("SELECT COUNT(*) AS total FROM app_content_pages");
    $count = (int) ($countRow['total'] ?? 0);
    if ($count > 0) {
        return;
    }

    $defaults = getDefaultContentPagesMap();

    foreach ($contentPages as $pageKey => $pageMeta) {
        $legacyArabicContent = getLegacyContentForPage($pageKey);

        foreach ($contentLanguages as $langCode => $langLabel) {
            $defaultData = $defaults[$pageKey][$langCode] ?? [
                'title' => $pageMeta['titles'][$langCode] ?? $pageMeta['label'],
                'content' => '',
            ];

            $title = trim((string) ($defaultData['title'] ?? ''));
            $content = trim((string) ($defaultData['content'] ?? ''));

            if ($langCode === 'ar' && $legacyArabicContent !== '') {
                $content = $legacyArabicContent;
            }

            db()->insert('app_content_pages', [
                'page_key' => $pageKey,
                'language_code' => $langCode,
                'title' => $title,
                'content' => $content,
            ]);
        }
    }
}

function loadContentPagesFromDatabase(array $contentPages, array $contentLanguages)
{
    $defaults = getDefaultContentPagesMap();
    $result = [];

    foreach ($contentPages as $pageKey => $pageMeta) {
        foreach ($contentLanguages as $langCode => $langLabel) {
            $defaultData = $defaults[$pageKey][$langCode] ?? [
                'title' => $pageMeta['titles'][$langCode] ?? $pageMeta['label'],
                'content' => '',
            ];

            $result[$pageKey][$langCode] = [
                'title' => (string) ($defaultData['title'] ?? ''),
                'content' => (string) ($defaultData['content'] ?? ''),
            ];
        }
    }

    if (!contentPagesTableExists()) {
        return $result;
    }

    $rows = db()->fetchAll("SELECT page_key, language_code, title, content FROM app_content_pages");

    foreach ($rows as $row) {
        $pageKey = (string) ($row['page_key'] ?? '');
        $langCode = (string) ($row['language_code'] ?? '');

        if (!isset($result[$pageKey][$langCode])) {
            continue;
        }

        $result[$pageKey][$langCode] = [
            'title' => (string) ($row['title'] ?? ''),
            'content' => (string) ($row['content'] ?? ''),
        ];
    }

    return $result;
}

$selectedCountriesRaw = conf('supported_countries', 'SA');
$selectedServiceCountries = array_filter(
    array_map('trim', explode(',', strtoupper((string) $selectedCountriesRaw)))
);
if (empty($selectedServiceCountries)) {
    $selectedServiceCountries = ['SA'];
}
foreach ($selectedServiceCountries as $selectedCountryCode) {
    if (!isset($serviceCountryOptions[$selectedCountryCode])) {
        $serviceCountryOptions[$selectedCountryCode] = $selectedCountryCode;
    }
}

$homeSectionsFormState = [];
foreach ($homeSectionSettingsConfig as $sectionKey => $sectionMeta) {
    $visibleKey = 'home_section_visible_' . $sectionKey;
    $orderKey = 'home_section_order_' . $sectionKey;

    $visibleRaw = strtolower(trim((string) conf($visibleKey, (string) $sectionMeta['default_visible'])));
    $isVisible = !in_array($visibleRaw, ['0', 'false', 'no', 'off'], true);

    $orderValue = (int) conf($orderKey, (string) $sectionMeta['default_order']);
    if ($orderValue < 1) {
        $orderValue = (int) $sectionMeta['default_order'];
    }

    $homeSectionsFormState[] = [
        'key' => $sectionKey,
        'label' => $sectionMeta['label'],
        'visible_key' => $visibleKey,
        'order_key' => $orderKey,
        'is_visible' => $isVisible,
        'order' => $orderValue,
    ];
}

usort($homeSectionsFormState, function ($a, $b) {
    if ($a['order'] === $b['order']) {
        return strcmp($a['label'], $b['label']);
    }
    return $a['order'] <=> $b['order'];
});

$homeFeedLimitsFormState = [];
foreach ($homeFeedLimitsConfig as $limitKey => $limitMeta) {
    $value = (int) conf($limitKey, (string) ($limitMeta['default'] ?? 0));
    $min = (int) ($limitMeta['min'] ?? 0);
    $max = (int) ($limitMeta['max'] ?? 9999);

    if ($value < $min) {
        $value = $min;
    }
    if ($value > $max) {
        $value = $max;
    }

    $homeFeedLimitsFormState[] = [
        'key' => $limitKey,
        'label' => (string) ($limitMeta['label'] ?? $limitKey),
        'value' => $value,
        'min' => $min,
        'max' => $max,
        'help' => (string) ($limitMeta['help'] ?? ''),
    ];
}

$howItWorksFormState = [];
foreach ($howItWorksStepsConfig as $stepNumber => $stepMeta) {
    $imageKey = "home_how_it_works_step_{$stepNumber}_image";
    $languagesState = [];

    foreach ($contentLanguages as $langCode => $langLabel) {
        $titleKey = "home_how_it_works_step_{$stepNumber}_title_{$langCode}";
        $subtitleKey = "home_how_it_works_step_{$stepNumber}_subtitle_{$langCode}";

        $languagesState[$langCode] = [
            'label' => $langLabel,
            'title_value' => conf($titleKey, (string) ($stepMeta['titles'][$langCode] ?? '')),
            'subtitle_value' => conf($subtitleKey, (string) ($stepMeta['subtitles'][$langCode] ?? '')),
        ];
    }

    $howItWorksFormState[] = [
        'step_number' => $stepNumber,
        'image_key' => $imageKey,
        'image_value' => conf($imageKey, (string) ($stepMeta['default_image'] ?? '')),
        'languages' => $languagesState,
    ];
}

$myFatoorahEnabled = conf('myfatoorah_enabled', '1');
$myFatoorahBaseUrl = conf(
    'myfatoorah_base_url',
    (string) (defined('MYFATOORAH_BASE_URL') ? MYFATOORAH_BASE_URL : 'https://api-sa.myfatoorah.com')
);

$storedMyFatoorahToken = trim((string) conf('myfatoorah_token', ''));
$envMyFatoorahToken = trim((string) (defined('MYFATOORAH_TOKEN') ? MYFATOORAH_TOKEN : ''));
$effectiveMyFatoorahToken = $storedMyFatoorahToken !== '' ? $storedMyFatoorahToken : $envMyFatoorahToken;
$myFatoorahTokenSource = $storedMyFatoorahToken !== '' ? 'قاعدة البيانات (app_settings)' : ($envMyFatoorahToken !== '' ? 'متغيرات البيئة (ENV)' : 'غير مُعد');
$myFatoorahTokenMasked = maskSecretValue($effectiveMyFatoorahToken);

$smsApiUrl = conf('sms_api_url', 'https://api-sms.4jawaly.com/api/v1/account/area/sms/v2/send');
$smsApiKeyStored = trim((string) conf('sms_api_key', ''));
$smsApiSecretStored = trim((string) conf('sms_api_secret', ''));
$smsSenderIdStored = normalizeSmsSenderIdValue(conf('sms_sender_id', conf('whatsapp_sender', DARFIX_SMS_SENDER_ID)));
$smsApiKeyFallback = trim((string) conf('whatsapp_api_key', ''));
$smsApiSecretFallback = trim((string) conf('whatsapp_api_secret', ''));
$smsApiKeyEffective = $smsApiKeyStored !== '' ? $smsApiKeyStored : $smsApiKeyFallback;
$smsApiSecretEffective = $smsApiSecretStored !== '' ? $smsApiSecretStored : $smsApiSecretFallback;
$smsApiKeyMasked = maskSecretValue($smsApiKeyEffective);
$smsApiSecretMasked = maskSecretValue($smsApiSecretEffective);

$darfixAiEnabled = conf('darfix_ai_enabled', '1');
$darfixAiEndpoint = conf(
    'darfix_ai_endpoint',
    (string) (getenv('DARFIX_AI_ENDPOINT') ?: DARFIX_AI_DEFAULT_ENDPOINT)
);
$darfixAiModel = conf(
    'darfix_ai_model',
    (string) (getenv('DARFIX_AI_MODEL') ?: DARFIX_AI_DEFAULT_MODEL)
);
$darfixAiStoredApiKey = trim((string) conf('darfix_ai_api_key', ''));
$darfixAiEnvApiKey = trim((string) (getenv('DARFIX_AI_API_KEY') ?: ''));
$darfixAiDefaultApiKey = DARFIX_AI_DEFAULT_API_KEY;
$darfixAiEffectiveApiKey = $darfixAiStoredApiKey !== ''
    ? $darfixAiStoredApiKey
    : ($darfixAiEnvApiKey !== '' ? $darfixAiEnvApiKey : $darfixAiDefaultApiKey);
$darfixAiApiKeySource = $darfixAiStoredApiKey !== ''
    ? 'قاعدة البيانات (app_settings)'
    : ($darfixAiEnvApiKey !== '' ? 'متغيرات البيئة (ENV)' : 'القيمة الافتراضية الحالية');
$darfixAiApiKeyMasked = maskSecretValue($darfixAiEffectiveApiKey);
$darfixAiMaxTokens = conf(
    'darfix_ai_max_tokens',
    (string) (getenv('DARFIX_AI_MAX_TOKENS') ?: DARFIX_AI_DEFAULT_MAX_TOKENS)
);
$darfixAiSystemPrompt = conf('darfix_ai_system_prompt', '');

include '../includes/header.php';
?>

<div class="card settings-card animate-slideUp">
    <div class="card-header settings-header">
        <div>
            <h3 class="card-title">
                <i class="fas fa-cogs"></i>
                إعدادات التطبيق
            </h3>
            <p class="settings-subtitle">تحكم في الميزات الرئيسية ومظهر أقسام الصفحة الرئيسية داخل التطبيق.</p>
        </div>
        <span class="badge badge-secondary">لوحة التحكم</span>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="settingsForm">

            <div class="settings-tabs">
                <div class="settings-tab-header" role="tablist" aria-label="تبويبات الإعدادات">
                    <button type="button" class="settings-tab-btn active" data-tab="features" onclick="switchSettingsTab(event, 'features')">
                        <i class="fas fa-sliders-h"></i>
                        الميزات والرسائل
                    </button>
                    <button type="button" class="settings-tab-btn" data-tab="home-sections" onclick="switchSettingsTab(event, 'home-sections')">
                        <i class="fas fa-layer-group"></i>
                        أقسام الرئيسية
                    </button>
                    <button type="button" class="settings-tab-btn" data-tab="how-it-works" onclick="switchSettingsTab(event, 'how-it-works')">
                        <i class="fas fa-list-ol"></i>
                        كيف يعمل DarFix
                    </button>
                    <button type="button" class="settings-tab-btn" data-tab="home-icons" onclick="switchSettingsTab(event, 'home-icons')">
                        <i class="fas fa-images"></i>
                        أيقونات الرئيسية
                    </button>
                    <button type="button" class="settings-tab-btn" data-tab="branding" onclick="switchSettingsTab(event, 'branding')">
                        <i class="fas fa-image"></i>
                        شعارات التطبيقات
                    </button>
                    <button type="button" class="settings-tab-btn" data-tab="static-content" onclick="switchSettingsTab(event, 'static-content')">
                        <i class="fas fa-file-alt"></i>
                        محتوى الصفحات
                    </button>
                </div>

                <div class="settings-tab-content">
                    <div id="features" class="settings-tab-pane active">
                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-info-circle"></i>
                            عند تعطيل بوابة الرسائل، سيتم استخدام وضع المحاكاة المجاني ورمز التحقق الثابت.
                        </div>

                        <div class="settings-grid">
                            <div class="form-group setting-item">
                                <label class="form-label">بوابة الرسائل النصية (SMS Gateway)</label>
                                <select name="settings[sms_enabled]" class="form-control">
                                    <option value="1" <?php echo conf('sms_enabled', '0') === '1' ? 'selected' : ''; ?>>مفعل (استخدام مزود خدمة الرسائل)</option>
                                    <option value="0" <?php echo conf('sms_enabled', '0') === '0' ? 'selected' : ''; ?>>معطل (وضع مجاني / محاكاة)</option>
                                </select>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">رمز التحقق الثابت (Fixed OTP - 4 أرقام)</label>
                                <input type="text" name="settings[fixed_otp]" class="form-control" value="<?php echo sanitizeFixedOtp(conf('fixed_otp', '1234')); ?>" dir="ltr" style="text-align: left;" inputmode="numeric" maxlength="4" pattern="\d{4}">
                                <small class="text-muted">يستخدم عند تعطيل بوابة الرسائل ويجب أن يكون 4 أرقام.</small>
                            </div>

                            <div class="form-group setting-item setting-item-wide">
                                <label class="form-label">SMS API Key (4Jawaly)</label>
                                <input
                                    type="password"
                                    name="settings[sms_api_key]"
                                    class="form-control"
                                    value=""
                                    dir="ltr"
                                    style="text-align: left;"
                                    autocomplete="new-password"
                                    placeholder="أدخل مفتاح API أو اتركه فارغاً للاحتفاظ بالحالي">
                                <small class="text-muted">
                                    حالة المفتاح الحالي: <?php echo $smsApiKeyMasked !== '' ? htmlspecialchars($smsApiKeyMasked, ENT_QUOTES, 'UTF-8') : 'غير مُعد'; ?>
                                    <?php if ($smsApiKeyStored === '' && $smsApiKeyMasked !== ''): ?>
                                        (يتم حالياً استخدام إعدادات الواتساب)
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="form-group setting-item setting-item-wide">
                                <label class="form-label">SMS API Secret (4Jawaly)</label>
                                <input
                                    type="password"
                                    name="settings[sms_api_secret]"
                                    class="form-control"
                                    value=""
                                    dir="ltr"
                                    style="text-align: left;"
                                    autocomplete="new-password"
                                    placeholder="أدخل API Secret أو اتركه فارغاً للاحتفاظ بالحالي">
                                <small class="text-muted">
                                    حالة السر الحالي: <?php echo $smsApiSecretMasked !== '' ? htmlspecialchars($smsApiSecretMasked, ENT_QUOTES, 'UTF-8') : 'غير مُعد'; ?>
                                    <?php if ($smsApiSecretStored === '' && $smsApiSecretMasked !== ''): ?>
                                        (يتم حالياً استخدام إعدادات الواتساب)
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">SMS Sender ID (4Jawaly)</label>
                                <input
                                    type="text"
                                    name="settings[sms_sender_id]"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($smsSenderIdStored, ENT_QUOTES, 'UTF-8'); ?>"
                                    dir="ltr"
                                    style="text-align: left;"
                                    placeholder="Darfix">
                                <small class="text-muted">يجب أن يكون Sender ID مفعلاً ومعتمداً داخل حساب 4Jawaly.</small>
                            </div>

                            <div class="form-group setting-item setting-item-wide">
                                <label class="form-label">SMS API URL (4Jawaly)</label>
                                <input
                                    type="text"
                                    name="settings[sms_api_url]"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($smsApiUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                    dir="ltr"
                                    style="text-align: left;"
                                    placeholder="https://api-sms.4jawaly.com/api/v1/account/area/sms/v2/send">
                                <small class="text-muted">اتركه كما هو إلا إذا أعطاك مزود الرسائل رابطاً مختلفاً.</small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">السماح بالتسجيل الجديد</label>
                                <select name="settings[allow_registration]" class="form-control">
                                    <option value="1" <?php echo conf('allow_registration') == '1' ? 'selected' : ''; ?>>مسموح</option>
                                    <option value="0" <?php echo conf('allow_registration') == '0' ? 'selected' : ''; ?>>مغلق مؤقتاً</option>
                                </select>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">وضع الصيانة</label>
                                <select name="settings[maintenance_mode]" class="form-control">
                                    <option value="1" <?php echo conf('maintenance_mode') == '1' ? 'selected' : ''; ?>>مفعل (التطبيق متوقف للمستخدمين)</option>
                                    <option value="0" <?php echo conf('maintenance_mode') == '0' ? 'selected' : ''; ?>>معطل (التطبيق يعمل)</option>
                                </select>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">ساعات التواصل قبل الموعد (تأكيد العمليات)</label>
                                <input type="number" min="1" max="48" name="settings[confirmation_lead_hours]" class="form-control" value="<?php echo conf('confirmation_lead_hours', '2'); ?>">
                                <small class="text-muted">مثال: 2 يعني التواصل قبل الموعد بساعتين.</small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">حد عدم الالتزام قبل الحظر التلقائي</label>
                                <input type="number" min="1" max="20" name="settings[no_show_blacklist_threshold]" class="form-control" value="<?php echo conf('no_show_blacklist_threshold', '3'); ?>">
                                <small class="text-muted">عند تجاوزه يتم تقييد الحجز تلقائياً.</small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">الحد الأدنى لطلب قطع الغيار مع التركيب</label>
                                <input type="number" step="0.01" min="0" name="settings[spare_parts_min_order_with_installation]" class="form-control" value="<?php echo htmlspecialchars(conf('spare_parts_min_order_with_installation', '0'), ENT_QUOTES, 'UTF-8'); ?>">
                                <small class="text-muted">يُطبق على سلة قطع الغيار مع التركيب فقط (0 = بدون حد أدنى).</small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">نوع خط التطبيق</label>
                                <select name="settings[app_font]" class="form-control">
                                    <option value="cairo" <?php echo conf('app_font', 'cairo') === 'cairo' ? 'selected' : ''; ?>>Cairo (الخط الحالي)</option>
                                    <option value="zain" <?php echo conf('app_font', 'cairo') === 'zain' ? 'selected' : ''; ?>>Zain</option>
                                </select>
                                <small class="text-muted">يتم تطبيق الخط المختار على تطبيق العميل وتطبيق مقدم الخدمة.</small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">رقم واتساب الدعم (زر تواصل معنا)</label>
                                <input
                                    type="text"
                                    name="settings[whatsapp]"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars(conf('whatsapp', '+966501234567'), ENT_QUOTES, 'UTF-8'); ?>"
                                    dir="ltr"
                                    style="text-align: left;"
                                    placeholder="+9665XXXXXXXX">
                                <small class="text-muted">يستخدم في شاشة تسجيل الدخول عند الضغط على "تواصل معنا".</small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">بوابة الدفع MyFatoorah</label>
                                <select name="settings[myfatoorah_enabled]" class="form-control">
                                    <option value="1" <?php echo $myFatoorahEnabled === '1' ? 'selected' : ''; ?>>مفعلة</option>
                                    <option value="0" <?php echo $myFatoorahEnabled !== '1' ? 'selected' : ''; ?>>معطلة</option>
                                </select>
                                <small class="text-muted">عند التعطيل لن يتم إنشاء روابط دفع جديدة من التطبيق.</small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">MyFatoorah Base URL</label>
                                <input
                                    type="text"
                                    name="settings[myfatoorah_base_url]"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($myFatoorahBaseUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                    dir="ltr"
                                    style="text-align: left;"
                                    placeholder="https://api-sa.myfatoorah.com">
                                <small class="text-muted">مثال للسعودية: https://api-sa.myfatoorah.com</small>
                            </div>

                            <div class="form-group setting-item setting-item-wide">
                                <label class="form-label">MyFatoorah Token</label>
                                <input
                                    type="password"
                                    name="settings[myfatoorah_token]"
                                    class="form-control"
                                    value=""
                                    dir="ltr"
                                    style="text-align: left;"
                                    autocomplete="new-password"
                                    placeholder="أدخل التوكن الجديد أو اتركه فارغاً للاحتفاظ بالحالي">
                                <small class="text-muted">
                                    حالة التوكن الحالي: <?php echo $myFatoorahTokenMasked !== '' ? htmlspecialchars($myFatoorahTokenMasked, ENT_QUOTES, 'UTF-8') : 'غير مُعد'; ?>
                                    (المصدر: <?php echo htmlspecialchars($myFatoorahTokenSource, ENT_QUOTES, 'UTF-8'); ?>).
                                </small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">Darfix AI</label>
                                <select name="settings[darfix_ai_enabled]" class="form-control">
                                    <option value="1" <?php echo $darfixAiEnabled === '1' ? 'selected' : ''; ?>>مفعل داخل تطبيق العميل</option>
                                    <option value="0" <?php echo $darfixAiEnabled !== '1' ? 'selected' : ''; ?>>معطل ويختفي من التطبيق</option>
                                </select>
                                <small class="text-muted">عند التعطيل سيختفي زر Darfix AI من الرئيسية بعد تحديث إعدادات التطبيق داخل العميل.</small>
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">Darfix AI Model</label>
                                <input
                                    type="text"
                                    name="settings[darfix_ai_model]"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($darfixAiModel, ENT_QUOTES, 'UTF-8'); ?>"
                                    dir="ltr"
                                    style="text-align: left;"
                                    placeholder="<?php echo htmlspecialchars(DARFIX_AI_DEFAULT_MODEL, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="form-group setting-item">
                                <label class="form-label">Darfix AI Max Tokens</label>
                                <input
                                    type="number"
                                    min="64"
                                    max="4000"
                                    name="settings[darfix_ai_max_tokens]"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($darfixAiMaxTokens, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="form-group setting-item setting-item-wide">
                                <label class="form-label">Darfix AI Endpoint</label>
                                <input
                                    type="text"
                                    name="settings[darfix_ai_endpoint]"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($darfixAiEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
                                    dir="ltr"
                                    style="text-align: left;"
                                    placeholder="<?php echo htmlspecialchars(DARFIX_AI_DEFAULT_ENDPOINT, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="form-group setting-item setting-item-wide">
                                <label class="form-label">Darfix AI API Key</label>
                                <input
                                    type="password"
                                    name="settings[darfix_ai_api_key]"
                                    class="form-control"
                                    value=""
                                    dir="ltr"
                                    style="text-align: left;"
                                    autocomplete="new-password"
                                    placeholder="أدخل مفتاح API الجديد أو اتركه فارغاً للاحتفاظ بالحالي">
                                <small class="text-muted">
                                    حالة المفتاح الحالي: <?php echo $darfixAiApiKeyMasked !== '' ? htmlspecialchars($darfixAiApiKeyMasked, ENT_QUOTES, 'UTF-8') : 'غير مُعد'; ?>
                                    (المصدر: <?php echo htmlspecialchars($darfixAiApiKeySource, ENT_QUOTES, 'UTF-8'); ?>).
                                </small>
                            </div>

                            <div class="form-group setting-item setting-item-wide">
                                <label class="form-label">تعليمات إضافية لـ Darfix AI (اختياري)</label>
                                <textarea
                                    name="settings[darfix_ai_system_prompt]"
                                    class="form-control"
                                    rows="4"
                                    placeholder="مثال: ركّز على الخدمات المنزلية داخل السعودية وأجب باختصار."><?php echo htmlspecialchars($darfixAiSystemPrompt, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <small class="text-muted">تُضاف هذه التعليمات إلى الـ system prompt في السيرفر دون تعديل التطبيق.</small>
                            </div>

                            <div id="service-country-settings" class="form-group setting-item setting-item-wide">
                                <label class="form-label">الدول المتاحة لتقديم الخدمات (حسب GPS)</label>
                                <div class="country-check-grid">
                                    <?php foreach ($serviceCountryOptions as $countryCode => $countryLabel): ?>
                                        <label class="country-check-item">
                                            <input type="checkbox"
                                                name="settings[supported_countries][]"
                                                value="<?php echo $countryCode; ?>"
                                                <?php echo in_array($countryCode, $selectedServiceCountries, true) ? 'checked' : ''; ?>>
                                            <span><?php echo $countryLabel; ?></span>
                                            <small><?php echo $countryCode; ?></small>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted">لو اختار العميل موقعه في دولة خارج الدول المحددة، التطبيق هيعرض: لا يوجد خدمات داخل منطقتك.</small>
                            </div>
                        </div>

                        <hr style="margin: 24px 0;">

                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-circle-info"></i>
                            التحكم في صفحة "عن Darfix" (الإحصائيات + لماذا Darfix + بيانات التواصل).
                        </div>

                        <div class="settings-grid">
                            <div class="form-group setting-item">
                                <label class="form-label">إحصائية: عميل سعيد</label>
                                <input type="text" name="settings[about_stat_happy_clients]" class="form-control" value="<?php echo htmlspecialchars(conf('about_stat_happy_clients', '50,000+'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="form-group setting-item">
                                <label class="form-label">إحصائية: مقدم خدمة</label>
                                <input type="text" name="settings[about_stat_service_providers]" class="form-control" value="<?php echo htmlspecialchars(conf('about_stat_service_providers', '2,500+'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="form-group setting-item">
                                <label class="form-label">إحصائية: طلب مكتمل</label>
                                <input type="text" name="settings[about_stat_completed_orders]" class="form-control" value="<?php echo htmlspecialchars(conf('about_stat_completed_orders', '100,000+'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="form-group setting-item">
                                <label class="form-label">رقم دعم العملاء</label>
                                <input type="text" name="settings[support_phone]" class="form-control" value="<?php echo htmlspecialchars(conf('support_phone', '+966501234567'), ENT_QUOTES, 'UTF-8'); ?>" dir="ltr" style="text-align: left;">
                            </div>
                            <div class="form-group setting-item">
                                <label class="form-label">ايميل الدعم</label>
                                <input type="text" name="settings[support_email]" class="form-control" value="<?php echo htmlspecialchars(conf('support_email', 'support@ertah.app'), ENT_QUOTES, 'UTF-8'); ?>" dir="ltr" style="text-align: left;">
                            </div>
                            <div class="form-group setting-item setting-item-wide">
                                <label class="form-label">العنوان (لصفحة عن التطبيق ومركز المساعدة)</label>
                                <input type="text" name="settings[support_address]" class="form-control" value="<?php echo htmlspecialchars(conf('support_address', conf('address', 'الرياض، المملكة العربية السعودية')), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="content-editor-grid" style="margin-top: 14px;">
                            <?php foreach ($aboutFeatureDefaults as $featureIndex => $featureMeta): ?>
                                <div class="content-page-card">
                                    <div class="content-page-header">
                                        <h4>
                                            <i class="fas fa-star"></i>
                                            سبب (<?php echo (int) $featureIndex; ?>) في قسم "لماذا Darfix"
                                        </h4>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">أيقونة Emoji</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="settings[about_feature_<?php echo (int) $featureIndex; ?>_icon]"
                                            value="<?php echo htmlspecialchars(conf('about_feature_' . $featureIndex . '_icon', (string) ($featureMeta['icon'] ?? '⭐')), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="content-language-grid">
                                        <?php foreach ($contentLanguages as $langCode => $langLabel): ?>
                                            <div class="content-language-card">
                                                <div class="content-language-title">
                                                    <span class="badge badge-secondary"><?php echo strtoupper($langCode); ?></span>
                                                    <strong><?php echo htmlspecialchars((string) $langLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">العنوان</label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="settings[about_feature_<?php echo (int) $featureIndex; ?>_title_<?php echo $langCode; ?>]"
                                                        value="<?php echo htmlspecialchars(conf('about_feature_' . $featureIndex . '_title_' . $langCode, (string) (($featureMeta['titles'][$langCode] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">الوصف</label>
                                                    <textarea
                                                        class="form-control"
                                                        rows="3"
                                                        name="settings[about_feature_<?php echo (int) $featureIndex; ?>_description_<?php echo $langCode; ?>]"><?php echo htmlspecialchars(conf('about_feature_' . $featureIndex . '_description_' . $langCode, (string) (($featureMeta['descriptions'][$langCode] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr style="margin: 24px 0;">

                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-share-nodes"></i>
                            التحكم في شاشة مشاركة التطبيق والمكافأة.
                        </div>

                        <div class="settings-grid">
                            <div class="form-group setting-item">
                                <label class="form-label">قيمة مكافأة الإحالة</label>
                                <input type="text" name="settings[referral_reward_amount]" class="form-control" value="<?php echo htmlspecialchars(conf('referral_reward_amount', '50'), ENT_QUOTES, 'UTF-8'); ?>" dir="ltr" style="text-align: left;">
                                <small class="text-muted">تظهر في شاشة مشاركة التطبيق بجانب العملة.</small>
                            </div>
                            <div class="form-group setting-item">
                                <label class="form-label">تحويل النقاط إلى رصيد</label>
                                <input type="number" step="0.01" min="1" name="settings[points_per_currency_unit]" class="form-control" value="<?php echo htmlspecialchars(conf('points_per_currency_unit', '10'), ENT_QUOTES, 'UTF-8'); ?>" dir="ltr" style="text-align: left;">
                                <small class="text-muted">كم نقطة تساوي 1 ر.س (مثال: 10 نقاط = 1 ر.س).</small>
                            </div>
                            <div class="form-group setting-item setting-item-wide">
                                <label class="form-label">رابط مشاركة مخصص (اختياري)</label>
                                <input type="text" name="settings[share_link_base]" class="form-control" value="<?php echo htmlspecialchars(conf('share_link_base', ''), ENT_QUOTES, 'UTF-8'); ?>" dir="ltr" style="text-align: left;" placeholder="https://ertah.org/ref">
                                <small class="text-muted">اتركه فارغاً لاستخدام الدومين الافتراضي للتطبيق.</small>
                            </div>
                        </div>

                        <div class="content-editor-grid" style="margin-top: 14px;">
                            <div class="content-page-card">
                                <div class="content-page-header">
                                    <h4>
                                        <i class="fas fa-language"></i>
                                        نصوص شاشة المشاركة (حسب اللغة)
                                    </h4>
                                </div>
                                <div class="content-language-grid">
                                    <?php foreach ($contentLanguages as $langCode => $langLabel): ?>
                                        <div class="content-language-card">
                                            <div class="content-language-title">
                                                <span class="badge badge-secondary"><?php echo strtoupper($langCode); ?></span>
                                                <strong><?php echo htmlspecialchars((string) $langLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">سطر وصفي أعلى الشاشة</label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    name="settings[share_invite_subtitle_<?php echo $langCode; ?>]"
                                                    value="<?php echo htmlspecialchars(conf('share_invite_subtitle_' . $langCode, ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">سبب المكافأة</label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    name="settings[share_reward_reason_<?php echo $langCode; ?>]"
                                                    value="<?php echo htmlspecialchars(conf('share_reward_reason_' . $langCode, ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">عنوان قسم مزايا الإحالة</label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    name="settings[share_program_title_<?php echo $langCode; ?>]"
                                                    value="<?php echo htmlspecialchars(conf('share_program_title_' . $langCode, ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label">رسالة المشاركة (اختياري)</label>
                                                <textarea
                                                    class="form-control"
                                                    rows="3"
                                                    name="settings[share_invite_message_<?php echo $langCode; ?>]"><?php echo htmlspecialchars(conf('share_invite_message_' . $langCode, ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                <small class="text-muted">يمكن استخدام {code} و {link} داخل الرسالة.</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php foreach ($shareBenefitDefaults as $benefitIndex => $benefitMeta): ?>
                                <div class="content-page-card">
                                    <div class="content-page-header">
                                        <h4>
                                            <i class="fas fa-gift"></i>
                                            ميزة الإحالة (<?php echo (int) $benefitIndex; ?>)
                                        </h4>
                                    </div>
                                    <div class="content-language-grid">
                                        <?php foreach ($contentLanguages as $langCode => $langLabel): ?>
                                            <div class="content-language-card">
                                                <div class="content-language-title">
                                                    <span class="badge badge-secondary"><?php echo strtoupper($langCode); ?></span>
                                                    <strong><?php echo htmlspecialchars((string) $langLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">العنوان</label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="settings[share_benefit_<?php echo (int) $benefitIndex; ?>_title_<?php echo $langCode; ?>]"
                                                        value="<?php echo htmlspecialchars(conf('share_benefit_' . $benefitIndex . '_title_' . $langCode, (string) (($benefitMeta['titles'][$langCode] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">الوصف</label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="settings[share_benefit_<?php echo (int) $benefitIndex; ?>_subtitle_<?php echo $langCode; ?>]"
                                                        value="<?php echo htmlspecialchars(conf('share_benefit_' . $benefitIndex . '_subtitle_' . $langCode, (string) (($benefitMeta['subtitles'][$langCode] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr style="margin: 24px 0;">

                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-headset"></i>
                            التحكم في مركز المساعدة والأسئلة الشائعة داخل التطبيق.
                        </div>

                        <div class="settings-grid">
                            <div class="form-group setting-item">
                                <label class="form-label">عدد الأسئلة المعروضة في مركز المساعدة</label>
                                <input type="number" min="1" max="8" name="settings[help_faq_count]" class="form-control" value="<?php echo (int) conf('help_faq_count', '4'); ?>">
                            </div>
                        </div>

                        <div class="content-editor-grid" style="margin-top: 14px;">
                            <div class="content-page-card">
                                <div class="content-page-header">
                                    <h4>
                                        <i class="fas fa-comment-dots"></i>
                                        نص بانر مركز المساعدة
                                    </h4>
                                </div>
                                <div class="content-language-grid">
                                    <?php foreach ($contentLanguages as $langCode => $langLabel): ?>
                                        <div class="content-language-card">
                                            <div class="content-language-title">
                                                <span class="badge badge-secondary"><?php echo strtoupper($langCode); ?></span>
                                                <strong><?php echo htmlspecialchars((string) $langLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">نص البانر</label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    name="settings[help_banner_text_<?php echo $langCode; ?>]"
                                                    value="<?php echo htmlspecialchars(conf('help_banner_text_' . $langCode, ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php foreach ($helpFaqDefaults as $faqIndex => $faqMeta): ?>
                                <div class="content-page-card">
                                    <div class="content-page-header">
                                        <h4>
                                            <i class="fas fa-question-circle"></i>
                                            سؤال شائع (<?php echo (int) $faqIndex; ?>)
                                        </h4>
                                    </div>
                                    <div class="content-language-grid">
                                        <?php foreach ($contentLanguages as $langCode => $langLabel): ?>
                                            <div class="content-language-card">
                                                <div class="content-language-title">
                                                    <span class="badge badge-secondary"><?php echo strtoupper($langCode); ?></span>
                                                    <strong><?php echo htmlspecialchars((string) $langLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">السؤال</label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="settings[help_faq_<?php echo (int) $faqIndex; ?>_question_<?php echo $langCode; ?>]"
                                                        value="<?php echo htmlspecialchars(conf('help_faq_' . $faqIndex . '_question_' . $langCode, (string) (($faqMeta['questions'][$langCode] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">الإجابة</label>
                                                    <textarea
                                                        class="form-control"
                                                        rows="4"
                                                        name="settings[help_faq_<?php echo (int) $faqIndex; ?>_answer_<?php echo $langCode; ?>]"><?php echo htmlspecialchars(conf('help_faq_' . $faqIndex . '_answer_' . $langCode, (string) (($faqMeta['answers'][$langCode] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="home-sections" class="settings-tab-pane">
                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-info-circle"></i>
                            تحكم في الأقسام التي تظهر في الصفحة الرئيسية داخل التطبيق، وحدد ترتيب ظهور كل قسم.
                        </div>

                        <div class="home-sections-grid">
                            <?php foreach ($homeSectionsFormState as $sectionState): ?>
                                <div class="home-section-card">
                                    <div class="home-section-meta">
                                        <div class="home-section-title"><?php echo htmlspecialchars($sectionState['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="home-section-key"><?php echo htmlspecialchars($sectionState['key'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>

                                    <div class="home-section-controls">
                                        <div class="form-group">
                                            <label class="form-label">الظهور</label>
                                            <select name="settings[<?php echo $sectionState['visible_key']; ?>]" class="form-control">
                                                <option value="1" <?php echo $sectionState['is_visible'] ? 'selected' : ''; ?>>إظهار</option>
                                                <option value="0" <?php echo !$sectionState['is_visible'] ? 'selected' : ''; ?>>إخفاء</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">الترتيب</label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="99"
                                                name="settings[<?php echo $sectionState['order_key']; ?>]"
                                                class="form-control"
                                                value="<?php echo (int) $sectionState['order']; ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr style="margin: 24px 0;">

                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-hashtag"></i>
                            تحكم في عدد العناصر الظاهرة داخل كل قسم في الصفحة الرئيسية.
                        </div>

                        <div class="settings-grid">
                            <?php foreach ($homeFeedLimitsFormState as $limitState): ?>
                                <div class="form-group setting-item">
                                    <label class="form-label"><?php echo htmlspecialchars($limitState['label'], ENT_QUOTES, 'UTF-8'); ?></label>
                                    <input
                                        type="number"
                                        min="<?php echo (int) $limitState['min']; ?>"
                                        max="<?php echo (int) $limitState['max']; ?>"
                                        name="settings[<?php echo htmlspecialchars($limitState['key'], ENT_QUOTES, 'UTF-8'); ?>]"
                                        class="form-control"
                                        value="<?php echo (int) $limitState['value']; ?>">
                                    <?php if (!empty($limitState['help'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($limitState['help'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="how-it-works" class="settings-tab-pane">
                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-info-circle"></i>
                            يمكنك تعديل بيانات "كيف يعمل DarFix" (٤ خطوات) مع صورة مستقلة لكل خطوة. إذا لم ترفع صورة جديدة سيتم الاحتفاظ بالصورة الحالية.
                        </div>

                        <div class="how-it-works-grid">
                            <?php foreach ($howItWorksFormState as $stepState): ?>
                                <?php
                                $stepNumber = (int) ($stepState['step_number'] ?? 0);
                                $imageInputId = 'how_step_image_' . $stepNumber;
                                $currentStepImage = (string) ($stepState['image_value'] ?? '');
                                ?>
                                <div class="how-step-card">
                                    <div class="how-step-header">
                                        <h4>
                                            <i class="fas fa-shoe-prints"></i>
                                            الخطوة <?php echo $stepNumber; ?>
                                        </h4>
                                        <span class="badge badge-secondary">How It Works</span>
                                    </div>

                                    <div class="how-step-image-row">
                                        <div class="icon-upload-preview how-step-image-preview" data-preview-for="<?php echo $imageInputId; ?>">
                                            <?php if (!empty($currentStepImage)): ?>
                                                <img src="<?php echo imageUrl($currentStepImage); ?>" alt="Step <?php echo $stepNumber; ?>">
                                            <?php else: ?>
                                                <i class="fas fa-image"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="how-step-image-actions">
                                            <input type="file" id="<?php echo $imageInputId; ?>" name="<?php echo htmlspecialchars((string) $stepState['image_key'], ENT_QUOTES, 'UTF-8'); ?>" class="hidden-file-input" accept="image/*">
                                            <label for="<?php echo $imageInputId; ?>" class="btn btn-outline btn-sm upload-trigger">
                                                <i class="fas fa-upload"></i>
                                                تغيير صورة الخطوة
                                            </label>
                                            <span class="file-name" data-file-name-for="<?php echo $imageInputId; ?>">لم يتم اختيار ملف جديد</span>
                                        </div>
                                    </div>

                                    <div class="how-step-language-grid">
                                        <?php foreach (($stepState['languages'] ?? []) as $langCode => $langState): ?>
                                            <div class="how-step-language-card">
                                                <div class="content-language-title">
                                                    <span class="badge badge-secondary"><?php echo strtoupper($langCode); ?></span>
                                                    <strong><?php echo htmlspecialchars((string) ($langState['label'] ?? $langCode), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">العنوان</label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="how_it_works[<?php echo $stepNumber; ?>][title_<?php echo $langCode; ?>]"
                                                        value="<?php echo htmlspecialchars((string) ($langState['title_value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">الوصف المختصر</label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="how_it_works[<?php echo $stepNumber; ?>][subtitle_<?php echo $langCode; ?>]"
                                                        value="<?php echo htmlspecialchars((string) ($langState['subtitle_value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="home-icons" class="settings-tab-pane">
                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-image"></i>
                            ارفع صورة لكل قسم، وستظهر مباشرة بجانب العنوان في تطبيق الجوال.
                        </div>

                        <div class="icon-cards-grid">
                            <?php foreach ($homeSectionIconFields as $key => $label): ?>
                                <?php
                                $currentIcon = conf($key);
                                $inputId = 'icon_' . $key;
                                ?>
                                <div class="icon-upload-card">
                                    <div class="icon-upload-top">
                                        <div class="icon-upload-preview" data-preview-for="<?php echo $inputId; ?>">
                                            <?php if (!empty($currentIcon)): ?>
                                                <img src="<?php echo imageUrl($currentIcon); ?>" alt="<?php echo $label; ?>">
                                            <?php else: ?>
                                                <i class="fas fa-image"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="icon-upload-title"><?php echo $label; ?></div>
                                            <div class="icon-upload-hint">يفضل صورة PNG أو WEBP بخلفية شفافة.</div>
                                        </div>
                                    </div>

                                    <div class="icon-upload-actions">
                                        <input type="file" id="<?php echo $inputId; ?>" name="<?php echo $key; ?>" class="hidden-file-input" accept="image/*">
                                        <label for="<?php echo $inputId; ?>" class="btn btn-outline btn-sm upload-trigger">
                                            <i class="fas fa-upload"></i>
                                            اختيار صورة
                                        </label>
                                        <span class="file-name" data-file-name-for="<?php echo $inputId; ?>">لم يتم اختيار ملف جديد</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="branding" class="settings-tab-pane">
                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-paint-brush"></i>
                            يمكنك رفع شعار لكل تطبيق ليتم تحديثه مباشرة بدون تعديل الكود.
                        </div>

                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-font"></i>
                            يمكنك تعديل اسم التطبيق لكل لغة ليظهر مباشرة داخل تطبيقات الجوال.
                        </div>

                        <div class="content-language-grid" style="margin-bottom: 24px;">
                            <?php foreach ($contentLanguages as $langCode => $langLabel): ?>
                                <?php
                                $nameKey = 'app_name_' . $langCode;
                                $defaultAppName = conf('app_name', 'Darfix');
                                $nameValue = conf($nameKey, $defaultAppName);
                                ?>
                                <div class="content-language-card">
                                    <div class="content-language-title">
                                        <span class="badge badge-secondary"><?php echo strtoupper($langCode); ?></span>
                                        <strong><?php echo htmlspecialchars($langLabel); ?></strong>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">اسم التطبيق</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="settings[<?php echo $nameKey; ?>]"
                                            value="<?php echo htmlspecialchars((string) $nameValue, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="icon-cards-grid">
                            <?php foreach ($brandingLogoFields as $key => $label): ?>
                                <?php
                                $currentLogo = conf($key);
                                $inputId = 'branding_' . $key;
                                ?>
                                <div class="icon-upload-card">
                                    <div class="icon-upload-top">
                                        <div class="icon-upload-preview" data-preview-for="<?php echo $inputId; ?>">
                                            <?php if (!empty($currentLogo)): ?>
                                                <img src="<?php echo imageUrl($currentLogo); ?>" alt="<?php echo $label; ?>">
                                            <?php else: ?>
                                                <i class="fas fa-image"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="icon-upload-title"><?php echo $label; ?></div>
                                            <div class="icon-upload-hint">يفضل صورة PNG شفافة بمقاس 512x512.</div>
                                        </div>
                                    </div>

                                    <div class="icon-upload-actions">
                                        <input type="file" id="<?php echo $inputId; ?>" name="<?php echo $key; ?>" class="hidden-file-input" accept="image/*">
                                        <label for="<?php echo $inputId; ?>" class="btn btn-outline btn-sm upload-trigger">
                                            <i class="fas fa-upload"></i>
                                            اختيار شعار
                                        </label>
                                        <span class="file-name" data-file-name-for="<?php echo $inputId; ?>">لم يتم اختيار ملف جديد</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="static-content" class="settings-tab-pane">
                        <div class="alert alert-info settings-alert">
                            <i class="fas fa-language"></i>
                            يمكنك التحكم الكامل في محتوى صفحات: عن التطبيق، سياسة الخصوصية، شروط الاستخدام، وسياسة الاسترداد لكل لغة (العربية، الإنجليزية، الأردية).
                        </div>

                        <div class="content-editor-grid">
                            <?php foreach ($contentPages as $pageKey => $pageMeta): ?>
                                <div class="content-page-card">
                                    <div class="content-page-header">
                                        <h4>
                                            <i class="fas fa-file-lines"></i>
                                            <?php echo htmlspecialchars($pageMeta['label']); ?>
                                        </h4>
                                    </div>

                                    <div class="content-language-grid">
                                        <?php foreach ($contentLanguages as $langCode => $langLabel): ?>
                                            <?php
                                            $pageLangData = $contentPagesData[$pageKey][$langCode] ?? ['title' => '', 'content' => ''];
                                            ?>
                                            <div class="content-language-card">
                                                <div class="content-language-title">
                                                    <span class="badge badge-secondary"><?php echo strtoupper($langCode); ?></span>
                                                    <strong><?php echo htmlspecialchars($langLabel); ?></strong>
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">العنوان</label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="content_pages[<?php echo $pageKey; ?>][<?php echo $langCode; ?>][title]"
                                                        value="<?php echo htmlspecialchars((string) $pageLangData['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">المحتوى</label>
                                                    <textarea
                                                        class="form-control content-textarea"
                                                        name="content_pages[<?php echo $pageKey; ?>][<?php echo $langCode; ?>][content]"
                                                        rows="8"><?php echo htmlspecialchars((string) $pageLangData['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="settings-actions">
                <button type="submit" class="btn btn-primary btn-lg settings-save-btn">
                    <i class="fas fa-save"></i>
                    حفظ التغييرات
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    function switchSettingsTab(evt, tabName) {
        var panes = document.getElementsByClassName('settings-tab-pane');
        var buttons = document.getElementsByClassName('settings-tab-btn');

        for (var i = 0; i < panes.length; i++) {
            panes[i].style.display = 'none';
            panes[i].classList.remove('active');
        }

        for (var j = 0; j < buttons.length; j++) {
            buttons[j].classList.remove('active');
        }

        var targetPane = document.getElementById(tabName);
        if (targetPane) {
            targetPane.style.display = 'block';
            targetPane.classList.add('active');
        }

        if (evt && evt.currentTarget) {
            evt.currentTarget.classList.add('active');
        } else {
            var targetButton = document.querySelector('.settings-tab-btn[data-tab="' + tabName + '"]');
            if (targetButton) {
                targetButton.classList.add('active');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        switchSettingsTab(null, 'features');

        var fileInputs = document.querySelectorAll('.hidden-file-input');
        fileInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                var fileNameEl = document.querySelector('[data-file-name-for="' + input.id + '"]');
                var previewEl = document.querySelector('[data-preview-for="' + input.id + '"]');

                if (!input.files || !input.files[0]) {
                    if (fileNameEl) {
                        fileNameEl.textContent = 'لم يتم اختيار ملف جديد';
                    }
                    return;
                }

                var selectedFile = input.files[0];
                if (fileNameEl) {
                    fileNameEl.textContent = selectedFile.name;
                }

                if (!previewEl) {
                    return;
                }

                var previewUrl = URL.createObjectURL(selectedFile);
                previewEl.innerHTML = '<img src="' + previewUrl + '" alt="Preview">';
            });
        });
    });
</script>

<style>
    .settings-card {
        overflow: visible;
    }

    .settings-header .card-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
    }

    .settings-header .card-title i {
        color: var(--secondary-color);
    }

    .settings-subtitle {
        margin-top: 6px;
        color: var(--gray-500);
        font-size: 13px;
    }

    .settings-tabs {
        display: block;
        background: transparent;
        margin-bottom: 0;
    }

    .settings-tab-header {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: var(--gray-100);
        border: 1px solid var(--gray-200);
        border-radius: 14px;
        padding: 6px;
        margin-bottom: 20px;
    }

    .settings-tab-btn {
        border: none;
        background: transparent;
        color: var(--gray-600);
        font-family: inherit;
        font-size: 14px;
        font-weight: 700;
        border-radius: 10px;
        padding: 10px 16px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: var(--transition);
    }

    .settings-tab-btn:hover {
        color: var(--gray-800);
        background: rgba(255, 255, 255, 0.7);
    }

    .settings-tab-btn.active {
        background: white;
        color: var(--secondary-color);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    }

    .settings-tab-content {
        border: 1px solid var(--gray-200);
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
        padding: 20px;
    }

    .settings-tab-pane {
        display: none;
        animation: fadeIn 0.35s ease;
    }

    .settings-tab-pane.active {
        display: block;
    }

    .settings-alert {
        margin-bottom: 18px;
        border-radius: 12px;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .setting-item {
        margin: 0;
        border: 1px solid var(--gray-200);
        border-radius: 14px;
        background: white;
        padding: 14px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }

    .setting-item-wide {
        grid-column: 1 / -1;
    }

    .setting-item .text-muted {
        margin-top: 7px;
    }

    .country-check-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .country-check-item {
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        background: var(--gray-50);
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .country-check-item input {
        accent-color: var(--secondary-color);
    }

    .country-check-item span {
        font-size: 13px;
        color: var(--gray-700);
        font-weight: 600;
        flex: 1;
    }

    .country-check-item small {
        color: var(--gray-500);
        font-size: 11px;
        font-weight: 700;
    }

    .icon-cards-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .home-sections-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .home-section-card {
        border: 1px solid var(--gray-200);
        border-radius: 14px;
        background: white;
        padding: 14px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }

    .home-section-meta {
        min-width: 220px;
    }

    .home-section-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--gray-800);
    }

    .home-section-key {
        margin-top: 4px;
        font-size: 12px;
        color: var(--gray-500);
        direction: ltr;
    }

    .home-section-controls {
        display: grid;
        grid-template-columns: repeat(2, minmax(130px, 170px));
        gap: 10px;
    }

    .home-section-controls .form-group {
        margin: 0;
    }

    .how-it-works-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .how-step-card {
        border: 1px solid var(--gray-200);
        border-radius: 14px;
        background: white;
        padding: 14px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }

    .how-step-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px dashed var(--gray-200);
    }

    .how-step-header h4 {
        margin: 0;
        font-size: 15px;
        color: var(--gray-800);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .how-step-image-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        padding: 10px;
        border-radius: 12px;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
    }

    .how-step-image-preview {
        width: 72px;
        height: 72px;
        border-radius: 14px;
        background: white;
    }

    .how-step-image-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }

    .how-step-language-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .how-step-language-card {
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        background: var(--gray-50);
        padding: 12px;
    }

    .how-step-language-card .form-group {
        margin-bottom: 10px;
    }

    .how-step-language-card .form-group:last-child {
        margin-bottom: 0;
    }

    .content-editor-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .content-page-card {
        border: 1px solid var(--gray-200);
        border-radius: 14px;
        background: white;
        padding: 16px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }

    .content-page-header {
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px dashed var(--gray-200);
    }

    .content-page-header h4 {
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 16px;
        color: var(--gray-800);
    }

    .content-language-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .content-language-card {
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        background: var(--gray-50);
        padding: 12px;
    }

    .content-language-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        color: var(--gray-700);
        font-size: 13px;
    }

    .content-language-card .form-group {
        margin-bottom: 10px;
    }

    .content-language-card .form-group:last-child {
        margin-bottom: 0;
    }

    .content-textarea {
        min-height: 160px;
        resize: vertical;
        line-height: 1.7;
    }

    .icon-upload-card {
        border: 1px solid var(--gray-200);
        border-radius: 14px;
        background: white;
        padding: 14px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }

    .icon-upload-top {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .icon-upload-preview {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        border: 1px solid var(--gray-200);
        background: var(--gray-50);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gray-400);
        overflow: hidden;
        flex-shrink: 0;
    }

    .icon-upload-preview img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .icon-upload-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--gray-800);
    }

    .icon-upload-hint {
        font-size: 12px;
        color: var(--gray-500);
        margin-top: 3px;
    }

    .icon-upload-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .hidden-file-input {
        display: none;
    }

    .upload-trigger {
        border-color: #d4d8e2;
        background: #f8fafc;
    }

    .upload-trigger:hover {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: var(--secondary-color);
    }

    .file-name {
        font-size: 12px;
        color: var(--gray-500);
    }

    .settings-actions {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: flex-start;
    }

    .settings-save-btn {
        min-width: 220px;
        box-shadow: 0 12px 24px rgba(251, 204, 38, 0.25);
    }

    .text-muted {
        color: #6b7280;
        font-size: 0.875rem;
        display: block;
    }

    @media (max-width: 1100px) {
        .settings-grid,
        .icon-cards-grid {
            grid-template-columns: 1fr;
        }

        .home-section-card {
            flex-direction: column;
            align-items: flex-start;
        }

        .home-section-meta {
            min-width: 0;
        }

        .home-section-controls {
            width: 100%;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .country-check-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .content-language-grid {
            grid-template-columns: 1fr;
        }

        .how-step-language-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .settings-tab-header {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            justify-content: stretch;
            gap: 6px;
        }

        .settings-tab-btn {
            flex: 1 1 calc(50% - 6px);
            justify-content: center;
            padding: 10px 12px;
            font-size: 13px;
        }

        .home-section-controls {
            grid-template-columns: 1fr;
        }

        .settings-save-btn {
            width: 100%;
            min-width: 0;
        }

        .country-check-grid {
            grid-template-columns: 1fr;
        }

        .how-step-image-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .how-step-language-grid {
            grid-template-columns: 1fr;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<?php include '../includes/footer.php'; ?>
