<?php
/**
 * إعدادات الإشعارات والإعلامات
 * Notification Settings Page
 */

require_once __DIR__ . '/../init.php';
requireLogin();

if (!hasPermission('settings') && getCurrentAdmin()['role'] !== 'super_admin') {
    setFlashMessage('danger', 'ليس لديك صلاحية لهذه الصفحة');
    redirect('../index.php');
}

require_once __DIR__ . '/../includes/notification_service.php';
ensureNotificationSchema();

$pageTitle = 'إعدادات الإشعارات';
$pageSubtitle = 'إدارة إعدادات البريد الإلكتروني والواتساب ومستلمي الإشعارات';

function normalizeNotificationSenderIdValue($value, $fallback = 'Darfix')
{
    $senderId = trim((string) $value);
    $compact = strtolower(preg_replace('/[\s_\-]+/', '', $senderId) ?? '');

    if ($senderId === '' || in_array($compact, ['ertah', 'ertahapp', 'ertahsms'], true)) {
        return $fallback;
    }

    return $senderId;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'save_smtp') {
        setNotifSetting('smtp_enabled', isset($_POST['smtp_enabled']) ? '1' : '0');
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        setNotifSetting('smtp_host', $smtpHost);
        setNotifSetting('smtp_port', trim($_POST['smtp_port'] ?? '587'));
        setNotifSetting('smtp_username', trim($_POST['smtp_username'] ?? ''));
        $smtpPassword = trim($_POST['smtp_password'] ?? '');
        if ($smtpPassword !== '') {
            $smtpPassword = normalizeSmtpPassword($smtpPassword, $smtpHost);
            setNotifSetting('smtp_password', $smtpPassword);
        }
        setNotifSetting('smtp_encryption', trim($_POST['smtp_encryption'] ?? 'tls'));
        setNotifSetting('smtp_from_email', trim($_POST['smtp_from_email'] ?? ''));
        setNotifSetting('smtp_from_name', trim($_POST['smtp_from_name'] ?? 'Darfix'));
        setFlashMessage('success', 'تم حفظ إعدادات SMTP بنجاح');
        redirect('notification-settings.php');
    }

    if ($action === 'save_whatsapp') {
        setNotifSetting('whatsapp_enabled', isset($_POST['whatsapp_enabled']) ? '1' : '0');
        setNotifSetting('whatsapp_api_key', trim($_POST['whatsapp_api_key'] ?? ''));
        $whatsappSecret = trim($_POST['whatsapp_api_secret'] ?? '');
        if ($whatsappSecret !== '') {
            setNotifSetting('whatsapp_api_secret', $whatsappSecret);
        }
        setNotifSetting('whatsapp_sender', normalizeNotificationSenderIdValue($_POST['whatsapp_sender'] ?? ''));
        setNotifSetting('whatsapp_gateway', trim($_POST['whatsapp_gateway'] ?? '4jawaly'));
        setFlashMessage('success', 'تم حفظ إعدادات الواتساب بنجاح');
        redirect('notification-settings.php');
    }

    if ($action === 'save_general') {
        setNotifSetting('notification_enabled', isset($_POST['notification_enabled']) ? '1' : '0');
        setNotifSetting('notify_new_orders', isset($_POST['notify_new_orders']) ? '1' : '0');
        setNotifSetting('notify_complaints', isset($_POST['notify_complaints']) ? '1' : '0');
        setNotifSetting('notify_furniture', isset($_POST['notify_furniture']) ? '1' : '0');
        setNotifSetting('notify_containers', isset($_POST['notify_containers']) ? '1' : '0');
        setNotifSetting('notify_incomplete', isset($_POST['notify_incomplete']) ? '1' : '0');
        setNotifSetting('incomplete_order_hours', max(1, (int)($_POST['incomplete_order_hours'] ?? 24)));
        setNotifSetting('cron_secret_key', trim($_POST['cron_secret_key'] ?? 'darfix_cron_2026'));
        setFlashMessage('success', 'تم حفظ الإعدادات العامة بنجاح');
        redirect('notification-settings.php');
    }

    if ($action === 'add_recipient') {
        $name    = trim($_POST['recipient_name'] ?? '');
        $email   = trim($_POST['recipient_email'] ?? '');
        $phone   = trim($_POST['recipient_phone'] ?? '');
        $channels = trim($_POST['recipient_channels'] ?? 'email');

        if (empty($name)) {
            setFlashMessage('danger', 'اسم المستلم مطلوب');
            redirect('notification-settings.php');
        }

        db()->insert('notification_recipients', [
            'name'                => $name,
            'email'               => $email ?: null,
            'phone'               => $phone ?: null,
            'receive_new_orders'  => isset($_POST['r_orders']) ? 1 : 0,
            'receive_complaints'  => isset($_POST['r_complaints']) ? 1 : 0,
            'receive_furniture'   => isset($_POST['r_furniture']) ? 1 : 0,
            'receive_containers'  => isset($_POST['r_containers']) ? 1 : 0,
            'receive_incomplete'  => isset($_POST['r_incomplete']) ? 1 : 0,
            'channels'            => $channels,
            'is_active'           => 1,
        ]);
        setFlashMessage('success', 'تم إضافة المستلم بنجاح');
        redirect('notification-settings.php');
    }

    if ($action === 'update_recipient') {
        $id = (int)($_POST['recipient_id'] ?? 0);
        if ($id > 0) {
            db()->update('notification_recipients', [
                'name'                => trim($_POST['recipient_name'] ?? ''),
                'email'               => trim($_POST['recipient_email'] ?? '') ?: null,
                'phone'               => trim($_POST['recipient_phone'] ?? '') ?: null,
                'receive_new_orders'  => isset($_POST['r_orders']) ? 1 : 0,
                'receive_complaints'  => isset($_POST['r_complaints']) ? 1 : 0,
                'receive_furniture'   => isset($_POST['r_furniture']) ? 1 : 0,
                'receive_containers'  => isset($_POST['r_containers']) ? 1 : 0,
                'receive_incomplete'  => isset($_POST['r_incomplete']) ? 1 : 0,
                'channels'            => trim($_POST['recipient_channels'] ?? 'email'),
                'is_active'           => isset($_POST['is_active']) ? 1 : 0,
            ], 'id = ?', [$id]);
            setFlashMessage('success', 'تم تحديث المستلم بنجاح');
        }
        redirect('notification-settings.php');
    }

    if ($action === 'delete_recipient') {
        $id = (int)($_POST['recipient_id'] ?? 0);
        if ($id > 0) {
            db()->delete('notification_recipients', 'id = ?', [$id]);
            setFlashMessage('success', 'تم حذف المستلم');
        }
        redirect('notification-settings.php');
    }

    if ($action === 'test_email') {
        $testEmail = trim($_POST['test_email'] ?? '');
        $result = sendTestNotification('email', $testEmail);
        if ($result['email']['success'] ?? false) {
            setFlashMessage('success', 'تم إرسال البريد التجريبي بنجاح إلى ' . $testEmail);
        } else {
            setFlashMessage('danger', 'فشل إرسال البريد: ' . ($result['email']['error'] ?? 'خطأ غير معروف'));
        }
        redirect('notification-settings.php');
    }

    if ($action === 'test_whatsapp') {
        $testPhone = trim($_POST['test_phone'] ?? '');
        $result = sendTestNotification('whatsapp', '', $testPhone);
        if ($result['whatsapp']['success'] ?? false) {
            setFlashMessage('success', 'تم إرسال رسالة واتساب تجريبية بنجاح');
        } else {
            setFlashMessage('danger', 'فشل إرسال الواتساب: ' . ($result['whatsapp']['error'] ?? 'خطأ غير معروف'));
        }
        redirect('notification-settings.php');
    }
}

// Load current settings
$smtp = getSmtpConfig(false);

$whatsapp = [
    'enabled'  => getNotifSetting('whatsapp_enabled', '0'),
    'api_key'  => getNotifSetting('whatsapp_api_key'),
    'secret_set' => getNotifSetting('whatsapp_api_secret') !== '',
    'sender'   => normalizeNotificationSenderIdValue(getNotifSetting('whatsapp_sender')),
    'gateway'  => getNotifSetting('whatsapp_gateway', '4jawaly'),
];

$general = [
    'notification_enabled' => getNotifSetting('notification_enabled', '1'),
    'notify_new_orders'    => getNotifSetting('notify_new_orders', '1'),
    'notify_complaints'    => getNotifSetting('notify_complaints', '1'),
    'notify_furniture'     => getNotifSetting('notify_furniture', '1'),
    'notify_containers'    => getNotifSetting('notify_containers', '1'),
    'notify_incomplete'    => getNotifSetting('notify_incomplete', '1'),
    'incomplete_order_hours' => getNotifSetting('incomplete_order_hours', '24'),
    'cron_secret_key'      => getNotifSetting('cron_secret_key', 'darfix_cron_2026'),
];

// Load recipients
$recipients = db()->fetchAll("SELECT * FROM notification_recipients ORDER BY id DESC");

// Load recent logs
$recentLogs = db()->fetchAll("
    SELECT * FROM notification_logs 
    ORDER BY created_at DESC 
    LIMIT 30
");

include __DIR__ . '/../includes/header.php';
?>

<style>
    .notif-tabs { display: flex; gap: 8px; margin-bottom: 25px; flex-wrap: wrap; }
    .notif-tab {
        padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px;
        transition: all 0.3s; border: 2px solid transparent; background: #f3f4f6; color: #6b7280;
    }
    .notif-tab.active { background: var(--primary-color); color: #fff; }
    .notif-tab:hover:not(.active) { background: #e5e7eb; }
    .notif-panel { display: none; }
    .notif-panel.active { display: block; }
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .settings-grid.single { grid-template-columns: 1fr; }
    .setting-group { margin-bottom: 20px; }
    .setting-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 14px; }
    .setting-group input, .setting-group select {
        width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;
        transition: border-color 0.2s;
    }
    .setting-group input:focus, .setting-group select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
    .toggle-switch { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
    .toggle-input { display: none; }
    .toggle-label {
        width: 50px; height: 26px; background: #d1d5db; border-radius: 13px; position: relative;
        cursor: pointer; transition: background 0.3s;
    }
    .toggle-label::after {
        content: ''; width: 20px; height: 20px; background: #fff; border-radius: 50%;
        position: absolute; top: 3px; left: 3px; transition: transform 0.3s;
    }
    .toggle-input:checked + .toggle-label { background: #059669; }
    .toggle-input:checked + .toggle-label::after { transform: translateX(24px); }
    .toggle-text { font-weight: 600; color: #374151; }
    .recipient-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px;
        margin-bottom: 15px; transition: box-shadow 0.2s;
    }
    .recipient-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .recipient-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .recipient-name { font-weight: bold; font-size: 16px; color: #1f2937; }
    .recipient-badges { display: flex; gap: 6px; flex-wrap: wrap; margin: 8px 0; }
    .recipient-badge { font-size: 11px; padding: 3px 8px; border-radius: 6px; }
    .log-row { padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
    .log-status-sent { color: #059669; }
    .log-status-failed { color: #dc2626; }
    .info-box { padding: 15px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; margin-bottom: 20px; }
    .info-box i { color: #0ea5e9; margin-left: 8px; }
    @media (max-width: 768px) {
        .settings-grid { grid-template-columns: 1fr; }
    }
    .bg-blue-100 { background: #dbeafe; } .text-blue-800 { color: #1e40af; }
    .bg-red-100 { background: #fee2e2; } .text-red-800 { color: #991b1b; }
    .bg-green-100 { background: #dcfce7; } .text-green-800 { color: #166534; }
    .bg-yellow-100 { background: #fef9c3; } .text-yellow-800 { color: #854d0e; }
    .bg-purple-100 { background: #f3e8ff; } .text-purple-800 { color: #6b21a8; }
    .bg-orange-100 { background: #ffedd5; } .text-orange-800 { color: #9a3412; }
</style>

<!-- Tabs -->
<div class="notif-tabs">
    <div class="notif-tab active" onclick="switchTab('general')">
        <i class="fas fa-sliders-h"></i> الإعدادات العامة
    </div>
    <div class="notif-tab" onclick="switchTab('smtp')">
        <i class="fas fa-envelope"></i> إعدادات SMTP
    </div>
    <div class="notif-tab" onclick="switchTab('whatsapp')">
        <i class="fab fa-whatsapp"></i> إعدادات الواتساب
    </div>
    <div class="notif-tab" onclick="switchTab('recipients')">
        <i class="fas fa-users"></i> المستلمون
        <span class="badge" style="background:#0ea5e9; color:#fff; margin-right:5px; font-size:11px; padding:2px 7px; border-radius:10px;">
            <?php echo count($recipients); ?>
        </span>
    </div>
    <div class="notif-tab" onclick="switchTab('logs')">
        <i class="fas fa-history"></i> سجل الإرسال
    </div>
</div>

<!-- ==================== General Settings ==================== -->
<div class="notif-panel active" id="panel-general">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-sliders-h" style="color:var(--primary-color);"></i> الإعدادات العامة للإشعارات</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="form_action" value="save_general">

                <div class="toggle-switch" style="padding: 15px; background:#f0fdf4; border-radius:10px; margin-bottom:20px;">
                    <input type="checkbox" class="toggle-input" id="notification_enabled" name="notification_enabled" <?php echo $general['notification_enabled'] === '1' ? 'checked' : ''; ?>>
                    <label class="toggle-label" for="notification_enabled"></label>
                    <span class="toggle-text" style="font-size:16px;">🔔 تفعيل نظام الإشعارات</span>
                </div>

                <h4 style="margin: 20px 0 15px; font-size: 15px; color: #374151;">أنواع الإشعارات</h4>
                <div class="settings-grid">
                    <div class="toggle-switch">
                        <input type="checkbox" class="toggle-input" id="notify_new_orders" name="notify_new_orders" <?php echo $general['notify_new_orders'] === '1' ? 'checked' : ''; ?>>
                        <label class="toggle-label" for="notify_new_orders"></label>
                        <span class="toggle-text">📋 الطلبات الجديدة</span>
                    </div>
                    <div class="toggle-switch">
                        <input type="checkbox" class="toggle-input" id="notify_complaints" name="notify_complaints" <?php echo $general['notify_complaints'] === '1' ? 'checked' : ''; ?>>
                        <label class="toggle-label" for="notify_complaints"></label>
                        <span class="toggle-text">🎫 تذاكر الدعم / الشكاوى</span>
                    </div>
                    <div class="toggle-switch">
                        <input type="checkbox" class="toggle-input" id="notify_furniture" name="notify_furniture" <?php echo $general['notify_furniture'] === '1' ? 'checked' : ''; ?>>
                        <label class="toggle-label" for="notify_furniture"></label>
                        <span class="toggle-text">🚚 طلبات نقل العفش</span>
                    </div>
                    <div class="toggle-switch">
                        <input type="checkbox" class="toggle-input" id="notify_containers" name="notify_containers" <?php echo $general['notify_containers'] === '1' ? 'checked' : ''; ?>>
                        <label class="toggle-label" for="notify_containers"></label>
                        <span class="toggle-text">📦 طلبات الحاويات</span>
                    </div>
                    <div class="toggle-switch">
                        <input type="checkbox" class="toggle-input" id="notify_incomplete" name="notify_incomplete" <?php echo $general['notify_incomplete'] === '1' ? 'checked' : ''; ?>>
                        <label class="toggle-label" for="notify_incomplete"></label>
                        <span class="toggle-text">⚠️ الطلبات المعلقة (غير مكتملة)</span>
                    </div>
                </div>

                <h4 style="margin: 25px 0 15px; font-size: 15px; color: #374151;">إعدادات الطلبات المعلقة</h4>
                <div class="settings-grid">
                    <div class="setting-group">
                        <label>إرسال تنبيه بعد (ساعات)</label>
                        <input type="number" name="incomplete_order_hours" value="<?php echo (int)$general['incomplete_order_hours']; ?>" min="1" max="168" placeholder="24">
                    </div>
                    <div class="setting-group">
                        <label>مفتاح Cron</label>
                        <input type="text" name="cron_secret_key" value="<?php echo htmlspecialchars($general['cron_secret_key']); ?>" placeholder="darfix_cron_2026">
                        <small style="color:#9ca3af; display:block; margin-top:4px;">
                            رابط Cron: <code dir="ltr" style="font-size:11px;"><?php echo APP_URL; ?>/api/mobile/cron_incomplete_orders.php?key=<?php echo urlencode($general['cron_secret_key']); ?></code>
                        </small>
                    </div>
                </div>

                <div style="text-align:left; margin-top:20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ الإعدادات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== SMTP Settings ==================== -->
<div class="notif-panel" id="panel-smtp">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-envelope" style="color:#0ea5e9;"></i> إعدادات البريد الإلكتروني (SMTP)</h3>
        </div>
        <div class="card-body">
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>أمثلة على خوادم SMTP:</strong>
                Gmail: smtp.gmail.com (587/TLS) |
                Outlook: smtp.office365.com (587/TLS) |
                Yahoo: smtp.mail.yahoo.com (587/TLS)
                <div style="margin-top:8px; color:#0f172a;">
                    ملاحظة Gmail: يجب استخدام كلمة مرور تطبيق (App Password) بدون مسافات، وليس كلمة مرور الحساب.
                </div>
            </div>
            <?php if (!empty($smtp['uses_env'])): ?>
                <div class="info-box" style="background:#fff7ed; border-color:#fdba74;">
                    <i class="fas fa-lock"></i>
                    <strong>تنبيه:</strong> إعدادات SMTP مضبوطة من متغيرات البيئة على الخادم، وقد تتجاوز قيم النموذج.
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="form_action" value="save_smtp">

                <div class="toggle-switch" style="padding: 15px; background:#f0f9ff; border-radius:10px; margin-bottom:20px;">
                    <input type="checkbox" class="toggle-input" id="smtp_enabled" name="smtp_enabled" <?php echo $smtp['enabled'] === '1' ? 'checked' : ''; ?>>
                    <label class="toggle-label" for="smtp_enabled"></label>
                    <span class="toggle-text">📧 تفعيل إرسال البريد الإلكتروني</span>
                </div>

                <div class="settings-grid">
                    <div class="setting-group">
                        <label><i class="fas fa-server"></i> خادم SMTP</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($smtp['host']); ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="setting-group">
                        <label><i class="fas fa-plug"></i> المنفذ (Port)</label>
                        <input type="number" name="smtp_port" value="<?php echo (int)$smtp['port']; ?>" placeholder="587">
                    </div>
                    <div class="setting-group">
                        <label><i class="fas fa-user"></i> اسم المستخدم</label>
                        <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($smtp['username']); ?>" placeholder="your-email@gmail.com">
                    </div>
                    <div class="setting-group">
                        <label><i class="fas fa-key"></i> كلمة المرور</label>
                        <input type="password" name="smtp_password" placeholder="<?php echo !empty($smtp['password_set']) ? '••••••••' : 'أدخل كلمة المرور'; ?>">
                        <small style="display:block; margin-top:6px; color:<?php echo !empty($smtp['password_set']) ? '#059669' : '#991b1b'; ?>;">
                            <?php echo !empty($smtp['password_set']) ? 'كلمة المرور محفوظة. اتركها فارغة إذا لا تريد تغييرها.' : 'لم يتم حفظ كلمة مرور بعد.'; ?>
                        </small>
                    </div>
                    <div class="setting-group">
                        <label><i class="fas fa-lock"></i> التشفير</label>
                        <select name="smtp_encryption">
                            <option value="tls" <?php echo $smtp['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (مُوصى)</option>
                            <option value="ssl" <?php echo $smtp['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo $smtp['encryption'] === 'none' ? 'selected' : ''; ?>>بدون تشفير</option>
                        </select>
                    </div>
                    <div class="setting-group">
                        <label><i class="fas fa-at"></i> البريد المرسل</label>
                        <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($smtp['from_email']); ?>" placeholder="noreply@darfix.org">
                    </div>
                    <div class="setting-group">
                        <label><i class="fas fa-user-tag"></i> اسم المرسل</label>
                        <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($smtp['from_name']); ?>" placeholder="Darfix">
                    </div>
                </div>

                <div style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ إعدادات SMTP</button>
                </div>
            </form>

            <hr style="margin:25px 0; border-color:#f3f4f6;">

            <h4 style="margin:0 0 15px; font-size:15px;">📧 إرسال بريد تجريبي</h4>
            <form method="POST" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="form_action" value="test_email">
                <div class="setting-group" style="flex:1; margin-bottom:0;">
                    <label>البريد المستلم</label>
                    <input type="email" name="test_email" required placeholder="test@example.com" value="<?php echo htmlspecialchars($smtp['from_email']); ?>">
                </div>
                <button type="submit" class="btn btn-outline" style="white-space:nowrap;">
                    <i class="fas fa-paper-plane"></i> إرسال تجريبي
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ==================== WhatsApp Settings ==================== -->
<div class="notif-panel" id="panel-whatsapp">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fab fa-whatsapp" style="color:#25d366;"></i> إعدادات الواتساب (4Jawaly)</h3>
        </div>
        <div class="card-body">
            <div class="info-box" style="background:#f0fdf4; border-color:#86efac;">
                <i class="fab fa-whatsapp" style="color:#25d366;"></i>
                يتم إرسال رسائل الواتساب عبر بوابة <strong>فور جوالي (4Jawaly)</strong>.
                يمكنك إنشاء حساب من <a href="https://www.4jawaly.com" target="_blank" style="color:#0ea5e9;">4jawaly.com</a>
            </div>

            <form method="POST">
                <input type="hidden" name="form_action" value="save_whatsapp">

                <div class="toggle-switch" style="padding: 15px; background:#f0fdf4; border-radius:10px; margin-bottom:20px;">
                    <input type="checkbox" class="toggle-input" id="whatsapp_enabled" name="whatsapp_enabled" <?php echo $whatsapp['enabled'] === '1' ? 'checked' : ''; ?>>
                    <label class="toggle-label" for="whatsapp_enabled"></label>
                    <span class="toggle-text">📱 تفعيل رسائل الواتساب</span>
                </div>

                <div class="settings-grid">
                    <div class="setting-group">
                        <label><i class="fas fa-key"></i> مفتاح API</label>
                        <input type="text" name="whatsapp_api_key" value="<?php echo htmlspecialchars($whatsapp['api_key']); ?>" placeholder="أدخل مفتاح API من 4Jawaly">
                    </div>
                    <div class="setting-group">
                        <label><i class="fas fa-lock"></i> API Secret</label>
                        <input type="password" name="whatsapp_api_secret" placeholder="<?php echo !empty($whatsapp['secret_set']) ? '••••••••' : 'أدخل API Secret'; ?>">
                        <small style="display:block; margin-top:6px; color:#6b7280;">
                            استخدم API Key + API Secret من صفحة Token API في 4Jawaly.
                        </small>
                    </div>
                    <div class="setting-group">
                        <label><i class="fas fa-mobile-alt"></i> اسم المرسل (Sender ID)</label>
                        <input type="text" name="whatsapp_sender" value="<?php echo htmlspecialchars($whatsapp['sender']); ?>" placeholder="Darfix أو رقم الهاتف">
                    </div>
                    <div class="setting-group">
                        <label><i class="fas fa-broadcast-tower"></i> البوابة</label>
                        <select name="whatsapp_gateway">
                            <option value="4jawaly" <?php echo $whatsapp['gateway'] === '4jawaly' ? 'selected' : ''; ?>>فور جوالي (4Jawaly)</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ إعدادات الواتساب</button>
                </div>
            </form>

            <hr style="margin:25px 0; border-color:#f3f4f6;">

            <h4 style="margin:0 0 15px; font-size:15px;">📱 إرسال رسالة واتساب تجريبية</h4>
            <form method="POST" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="form_action" value="test_whatsapp">
                <div class="setting-group" style="flex:1; margin-bottom:0;">
                    <label>رقم الهاتف</label>
                    <input type="text" name="test_phone" required placeholder="+966500000000" dir="ltr">
                </div>
                <button type="submit" class="btn btn-outline" style="white-space:nowrap;">
                    <i class="fas fa-paper-plane"></i> إرسال تجريبي
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ==================== Recipients ==================== -->
<div class="notif-panel" id="panel-recipients">
    <!-- Add New Recipient -->
    <div class="card" style="margin-bottom:25px;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user-plus" style="color:#059669;"></i> إضافة مستلم جديد</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="form_action" value="add_recipient">
                <div class="settings-grid">
                    <div class="setting-group">
                        <label>الاسم *</label>
                        <input type="text" name="recipient_name" required placeholder="مثال: أحمد - العمليات">
                    </div>
                    <div class="setting-group">
                        <label>البريد الإلكتروني</label>
                        <input type="email" name="recipient_email" placeholder="ahmed@darfix.org">
                    </div>
                    <div class="setting-group">
                        <label>رقم الواتساب</label>
                        <input type="text" name="recipient_phone" placeholder="+966500000000" dir="ltr">
                    </div>
                    <div class="setting-group">
                        <label>قنوات الإرسال</label>
                        <select name="recipient_channels">
                            <option value="email">📧 بريد إلكتروني فقط</option>
                            <option value="whatsapp">📱 واتساب فقط</option>
                            <option value="both">📧📱 بريد + واتساب</option>
                        </select>
                    </div>
                </div>

                <h4 style="margin: 15px 0 10px; font-size: 14px; color: #6b7280;">أنواع الإشعارات التي يستلمها:</h4>
                <div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:15px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="r_orders" checked> 📋 طلبات جديدة
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="r_complaints" checked> 🎫 شكاوى
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="r_furniture" checked> 🚚 نقل عفش
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="r_containers" checked> 📦 حاويات
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="r_incomplete"> ⚠️ طلبات معلقة
                    </label>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> إضافة المستلم</button>
            </form>
        </div>
    </div>

    <!-- Current Recipients -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users" style="color:var(--primary-color);"></i> المستلمون الحاليون</h3>
        </div>
        <div class="card-body">
            <?php if (empty($recipients)): ?>
                <div class="empty-state" style="padding:40px; text-align:center;">
                    <div style="font-size:48px; margin-bottom:15px;">📭</div>
                    <h3 style="font-size:16px; color:#374151;">لا يوجد مستلمون بعد</h3>
                    <p style="color:#9ca3af;">أضف مستلمين ليتم إرسال الإشعارات إليهم.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recipients as $r): ?>
                    <div class="recipient-card" style="<?php echo $r['is_active'] ? '' : 'opacity:0.6;'; ?>">
                        <div class="recipient-header">
                            <div>
                                <span class="recipient-name"><?php echo htmlspecialchars($r['name']); ?></span>
                                <?php if (!$r['is_active']): ?>
                                    <span class="badge bg-red-100 text-red-800" style="font-size:11px;">معطل</span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button class="btn btn-sm btn-outline" onclick="toggleEditRecipient(<?php echo $r['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                    <input type="hidden" name="form_action" value="delete_recipient">
                                    <input type="hidden" name="recipient_id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div style="display:flex; gap:20px; color:#6b7280; font-size:13px; flex-wrap:wrap;">
                            <?php if (!empty($r['email'])): ?>
                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($r['email']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($r['phone'])): ?>
                                <span dir="ltr"><i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($r['phone']); ?></span>
                            <?php endif; ?>
                            <span>
                                <i class="fas fa-broadcast-tower"></i>
                                <?php
                                    $ch = $r['channels'] ?? 'email';
                                    echo $ch === 'both' ? 'بريد + واتساب' : ($ch === 'whatsapp' ? 'واتساب' : 'بريد إلكتروني');
                                ?>
                            </span>
                        </div>

                        <div class="recipient-badges">
                            <?php if ($r['receive_new_orders']): ?>
                                <span class="recipient-badge bg-blue-100 text-blue-800">📋 الطلبات</span>
                            <?php endif; ?>
                            <?php if ($r['receive_complaints']): ?>
                                <span class="recipient-badge bg-red-100 text-red-800">🎫 الشكاوى</span>
                            <?php endif; ?>
                            <?php if ($r['receive_furniture']): ?>
                                <span class="recipient-badge bg-yellow-100 text-yellow-800">🚚 نقل العفش</span>
                            <?php endif; ?>
                            <?php if ($r['receive_containers']): ?>
                                <span class="recipient-badge bg-purple-100 text-purple-800">📦 الحاويات</span>
                            <?php endif; ?>
                            <?php if ($r['receive_incomplete']): ?>
                                <span class="recipient-badge bg-orange-100 text-orange-800">⚠️ معلقة</span>
                            <?php endif; ?>
                        </div>

                        <!-- Edit Form (hidden by default) -->
                        <div id="edit-recipient-<?php echo $r['id']; ?>" style="display:none; margin-top:15px; padding-top:15px; border-top:1px solid #e5e7eb;">
                            <form method="POST">
                                <input type="hidden" name="form_action" value="update_recipient">
                                <input type="hidden" name="recipient_id" value="<?php echo $r['id']; ?>">
                                <div class="settings-grid">
                                    <div class="setting-group">
                                        <label>الاسم</label>
                                        <input type="text" name="recipient_name" value="<?php echo htmlspecialchars($r['name']); ?>" required>
                                    </div>
                                    <div class="setting-group">
                                        <label>البريد</label>
                                        <input type="email" name="recipient_email" value="<?php echo htmlspecialchars($r['email'] ?? ''); ?>">
                                    </div>
                                    <div class="setting-group">
                                        <label>الواتساب</label>
                                        <input type="text" name="recipient_phone" value="<?php echo htmlspecialchars($r['phone'] ?? ''); ?>" dir="ltr">
                                    </div>
                                    <div class="setting-group">
                                        <label>القنوات</label>
                                        <select name="recipient_channels">
                                            <option value="email" <?php echo ($r['channels'] ?? '') === 'email' ? 'selected' : ''; ?>>بريد فقط</option>
                                            <option value="whatsapp" <?php echo ($r['channels'] ?? '') === 'whatsapp' ? 'selected' : ''; ?>>واتساب فقط</option>
                                            <option value="both" <?php echo ($r['channels'] ?? '') === 'both' ? 'selected' : ''; ?>>بريد + واتساب</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:flex; gap:15px; flex-wrap:wrap; margin:10px 0;">
                                    <label style="font-size:13px; cursor:pointer;"><input type="checkbox" name="r_orders" <?php echo $r['receive_new_orders'] ? 'checked' : ''; ?>> طلبات</label>
                                    <label style="font-size:13px; cursor:pointer;"><input type="checkbox" name="r_complaints" <?php echo $r['receive_complaints'] ? 'checked' : ''; ?>> شكاوى</label>
                                    <label style="font-size:13px; cursor:pointer;"><input type="checkbox" name="r_furniture" <?php echo $r['receive_furniture'] ? 'checked' : ''; ?>> نقل عفش</label>
                                    <label style="font-size:13px; cursor:pointer;"><input type="checkbox" name="r_containers" <?php echo $r['receive_containers'] ? 'checked' : ''; ?>> حاويات</label>
                                    <label style="font-size:13px; cursor:pointer;"><input type="checkbox" name="r_incomplete" <?php echo $r['receive_incomplete'] ? 'checked' : ''; ?>> معلقة</label>
                                    <label style="font-size:13px; cursor:pointer;"><input type="checkbox" name="is_active" <?php echo $r['is_active'] ? 'checked' : ''; ?>> مفعّل</label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> حفظ التعديلات</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ==================== Logs ==================== -->
<div class="notif-panel" id="panel-logs">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history" style="color:#6b7280;"></i> سجل الإرسال (آخر 30)</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($recentLogs)): ?>
                <div class="empty-state" style="padding:40px; text-align:center;">
                    <div style="font-size:48px; margin-bottom:15px;">📝</div>
                    <h3 style="font-size:16px;">لا توجد سجلات بعد</h3>
                    <p style="color:#9ca3af;">ستظهر هنا سجلات الإشعارات المرسلة.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>القناة</th>
                                <th>النوع</th>
                                <th>المستلم</th>
                                <th>الموضوع</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td>
                                        <?php if ($log['channel'] === 'email'): ?>
                                            <span style="color:#0ea5e9;"><i class="fas fa-envelope"></i> بريد</span>
                                        <?php elseif ($log['channel'] === 'whatsapp'): ?>
                                            <span style="color:#25d366;"><i class="fab fa-whatsapp"></i> واتساب</span>
                                        <?php else: ?>
                                            <span><i class="fas fa-broadcast-tower"></i> كلاهما</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            $typeLabels = [
                                                'new_order' => '📋 طلب جديد',
                                                'new_complaint' => '🎫 شكوى',
                                                'new_furniture_request' => '🚚 نقل عفش',
                                                'new_container_request' => '📦 حاوية',
                                                'incomplete_order' => '⚠️ طلب معلق',
                                                'test' => '🧪 تجريبي',
                                            ];
                                            echo $typeLabels[$log['event_type']] ?? $log['event_type'];
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($log['recipient_name'] ?? ($log['recipient_email'] ?? $log['recipient_phone'] ?? '-')); ?>
                                    </td>
                                    <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        <?php echo htmlspecialchars($log['subject'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <?php if ($log['status'] === 'sent'): ?>
                                            <span class="badge bg-green-100 text-green-800">✅ مُرسل</span>
                                        <?php elseif ($log['status'] === 'failed'): ?>
                                            <span class="badge bg-red-100 text-red-800" title="<?php echo htmlspecialchars($log['error_message'] ?? ''); ?>">❌ فشل</span>
                                        <?php else: ?>
                                            <span class="badge bg-yellow-100 text-yellow-800">⏳ قيد الانتظار</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px; color:#9ca3af;">
                                        <?php echo $log['created_at'] ?? '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.notif-panel').forEach(p => p.classList.remove('active'));
    
    event.currentTarget.classList.add('active');
    document.getElementById('panel-' + tab).classList.add('active');
}

function toggleEditRecipient(id) {
    const el = document.getElementById('edit-recipient-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
