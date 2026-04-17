<?php
/**
 * Schema update v7
 * - Adds multilingual static pages content table for About / Privacy / Terms / Refund.
 */

require_once __DIR__ . '/../config/database.php';

function tableExists($conn, $table)
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column)
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }

    if (!tableExists($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function addColumnIfMissing($conn, $table, $column, $definition)
{
    if (!tableExists($conn, $table)) {
        echo "Table {$table} does not exist; skipped {$column}\n";
        return;
    }

    if (columnExists($conn, $table, $column)) {
        echo "{$table}.{$column} already exists\n";
        return;
    }

    if ($conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}")) {
        echo "Added {$table}.{$column}\n";
    } else {
        echo "Failed adding {$table}.{$column}: " . $conn->error . "\n";
    }
}

addColumnIfMissing($conn, 'settings', 'privacy_policy', 'TEXT NULL AFTER `terms_and_conditions`');

if (!tableExists($conn, 'app_content_pages')) {
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

    if ($conn->query($sql)) {
        echo "Created table app_content_pages\n";
    } else {
        echo "Failed creating app_content_pages: " . $conn->error . "\n";
    }
} else {
    echo "Table app_content_pages already exists\n";
}

if (tableExists($conn, 'app_content_pages')) {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM app_content_pages");
    $count = 0;
    if ($countResult) {
        $count = (int) ($countResult->fetch_assoc()['total'] ?? 0);
    }

    if ($count === 0) {
        $seedRows = [
            ['about', 'ar', 'عن Darfix', "Darfix هو تطبيق سعودي رائد في مجال الخدمات المنزلية، نربط بين العملاء ومقدمي الخدمات المحترفين بسرعة وسهولة في مختلف مدن المملكة.\n\nنوفر لك تجربة موثوقة تشمل الحجز السريع، متابعة الطلب لحظياً، طرق دفع آمنة، ودعم متواصل لضمان أفضل تجربة خدمة."],
            ['about', 'en', 'About Darfix', "Darfix is a leading Saudi home-services app that connects customers with trusted service providers quickly and reliably across the Kingdom.\n\nWe provide a smooth experience with fast booking, real-time order tracking, secure payments, and responsive support."],
            ['about', 'ur', 'Darfix کے بارے میں', "Darfix سعودی عرب میں گھریلو خدمات کے لیے ایک نمایاں ایپ ہے جو صارفین کو قابلِ اعتماد سروس فراہم کنندگان سے تیزی اور آسانی کے ساتھ جوڑتی ہے۔\n\nہم تیز بکنگ، آرڈر کی لائیو ٹریکنگ، محفوظ ادائیگی اور مسلسل سپورٹ فراہم کرتے ہیں تاکہ بہترین تجربہ مل سکے۔"],
            ['privacy', 'ar', 'سياسة الخصوصية', "تطبيق Darfix يحترم خصوصيتك. نقوم بجمع البيانات التي تقدمها (الاسم، رقم الهاتف، البريد الإلكتروني، العناوين، تفاصيل الطلب، الصور) والبيانات الناتجة عن الاستخدام (الموقع عند طلب الخدمة، معلومات الجهاز، سجلات الاستخدام).\n\nنستخدم هذه البيانات لتقديم الخدمة، ربطك بمقدمي خدمات مناسبين، تنفيذ الطلبات، إرسال الإشعارات، منع الاحتيال، وتحسين التطبيق.\n\nنشارك البيانات فقط مع مقدمي الخدمات المعنيين ومع مزودي خدمات موثوقين مثل الدفع والرسائل والخرائط وفق ضوابط تعاقدية. لا نبيع بياناتك.\n\nيمكنك الوصول إلى بياناتك أو تحديثها أو حذف حسابك من داخل التطبيق (الإعدادات > حذف الحساب) أو عبر فريق الدعم. عند حذف الحساب نحذف أو نُجهّل بياناتك الشخصية، وقد نحتفظ ببعض السجلات للالتزامات القانونية أو المحاسبية أو الأمان.\n\nللاستفسارات: support@darfix.org."],
            ['privacy', 'en', 'Privacy Policy', "Darfix respects your privacy. We collect data you provide (name, phone number, email, addresses, order details, photos) and data generated during use (location when you request a service, device info, usage logs).\n\nWe use this data to deliver services, match you with providers, process orders, send notifications, prevent fraud, and improve the app.\n\nWe share data only with relevant service providers and trusted vendors such as payment, messaging, and maps under contractual safeguards. We do not sell your data.\n\nYou can access, update, or delete your account from the app (Settings > Delete Account) or by contacting support. When you delete your account, we remove or anonymize personal data; some records may be retained for legal, accounting, or safety obligations.\n\nFor questions: support@darfix.org."],
            ['privacy', 'ur', 'رازداری کی پالیسی', "Darfix respects your privacy. We collect data you provide (name, phone number, email, addresses, order details, photos) and data generated during use (location when you request a service, device info, usage logs).\n\nWe use this data to deliver services, match you with providers, process orders, send notifications, prevent fraud, and improve the app.\n\nWe share data only with relevant service providers and trusted vendors such as payment, messaging, and maps under contractual safeguards. We do not sell your data.\n\nYou can access, update, or delete your account from the app (Settings > Delete Account) or by contacting support. When you delete your account, we remove or anonymize personal data; some records may be retained for legal, accounting, or safety obligations.\n\nFor questions: support@darfix.org."],
            ['terms', 'ar', 'شروط الاستخدام', "باستخدامك لتطبيق Darfix، فإنك توافق على الالتزام بشروط الاستخدام.\n\n1) يجب استخدام التطبيق بطريقة قانونية وعدم إساءة الاستخدام.\n2) الأسعار والمواعيد تخضع لتأكيد مقدم الخدمة.\n3) يمكن إلغاء الطلب وفق سياسة الإلغاء المعتمدة.\n4) يحق للتطبيق تحديث هذه الشروط عند الحاجة."],
            ['terms', 'en', 'Terms of Use', "By using Darfix, you agree to comply with these terms.\n\n1) The app must be used lawfully and without abuse.\n2) Pricing and scheduling are subject to provider confirmation.\n3) Orders may be canceled according to the cancellation policy.\n4) We reserve the right to update these terms when needed."],
            ['terms', 'ur', 'استعمال کی شرائط', "Darfix ایپ استعمال کرنے سے آپ ان شرائط کی پابندی سے اتفاق کرتے ہیں۔\n\n1) ایپ کو قانونی طور پر اور بغیر غلط استعمال کے استعمال کیا جائے۔\n2) قیمت اور وقت سروس فراہم کنندہ کی تصدیق کے تابع ہیں۔\n3) آرڈر منسوخی پالیسی کے مطابق منسوخ کیا جا سکتا ہے۔\n4) ضرورت کے مطابق ان شرائط میں تبدیلی کا حق محفوظ ہے۔"],
            ['refund', 'ar', 'سياسة الاسترداد', "بعد تأكيد الطلب وبدء إجراءات التنفيذ، تصبح عملية الدفع غير قابلة للاسترداد.\n\nيمكن قبول طلبات الاسترداد فقط في الحالات التي يتعذر فيها تقديم الخدمة من طرفنا أو عند وجود خطأ في عملية الخصم.\n\nيتم تقديم طلب الاسترداد خلال 7 أيام عمل من تاريخ الدفع عبر فريق الدعم."],
            ['refund', 'en', 'Refund Policy', "Payments become non-refundable once the order is confirmed and processing starts.\n\nRefund requests are accepted only if the service cannot be delivered by us or if there is a billing error.\n\nPlease submit refund requests within 7 business days of payment via support."],
            ['refund', 'ur', 'واپسی کی پالیسی', "آرڈر کی تصدیق اور پراسیسنگ شروع ہونے کے بعد ادائیگی ناقابل واپسی ہو جاتی ہے۔\n\nواپسی کی درخواستیں صرف اس صورت میں قبول کی جائیں گی جب سروس فراہم نہ ہو سکے یا ادائیگی میں غلطی ہو۔\n\nبراہ کرم ادائیگی کے 7 کاروباری دنوں کے اندر سپورٹ سے رابطہ کریں۔"],
        ];

        $stmt = $conn->prepare("INSERT INTO app_content_pages (page_key, language_code, title, content) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            foreach ($seedRows as $row) {
                [$pageKey, $langCode, $title, $content] = $row;
                $stmt->bind_param('ssss', $pageKey, $langCode, $title, $content);
                $stmt->execute();
            }
            $stmt->close();
            echo "Seeded default rows for app_content_pages\n";
        } else {
            echo "Failed preparing seed insert: " . $conn->error . "\n";
        }
    } else {
        echo "app_content_pages already has data\n";
    }
}

echo "Schema update v7 completed.\n";
