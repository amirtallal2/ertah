<?php
/**
 * صفحة إدارة الشكاوى والدعم الفني
 * Complaints & Support Ticketing System
 */

require_once '../init.php';
requireLogin();

function complaintsEnsureRepliesSchema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;
    $GLOBALS['complaints_columns_cache'] = [];

    try {
        db()->query(
            "CREATE TABLE IF NOT EXISTS `complaint_replies` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `complaint_id` INT NOT NULL,
                `user_id` INT NULL,
                `admin_id` INT NULL,
                `message` TEXT NOT NULL,
                `attachments` LONGTEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_complaint_replies_complaint` (`complaint_id`),
                INDEX `idx_complaint_replies_user` (`user_id`),
                INDEX `idx_complaint_replies_admin` (`admin_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // لا نوقف الصفحة إذا تعذر إنشاء الجدول تلقائياً.
    }

    // توافق رجعي مع قواعد بيانات قديمة
    complaintsEnsureColumn('complaint_replies', 'complaint_id', 'INT NOT NULL');
    complaintsEnsureColumn('complaint_replies', 'user_id', 'INT NULL');
    complaintsEnsureColumn('complaint_replies', 'admin_id', 'INT NULL');
    complaintsEnsureColumn('complaint_replies', 'message', 'TEXT NOT NULL');
    complaintsEnsureColumn('complaint_replies', 'attachments', 'LONGTEXT NULL');
    complaintsEnsureColumn('complaint_replies', 'sender_type', "VARCHAR(20) NULL");
    complaintsEnsureColumn('complaint_replies', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    complaintsEnsureIndex('complaint_replies', 'idx_complaint_replies_complaint', ['complaint_id']);
    complaintsEnsureIndex('complaint_replies', 'idx_complaint_replies_user', ['user_id']);
    complaintsEnsureIndex('complaint_replies', 'idx_complaint_replies_admin', ['admin_id']);
}

complaintsEnsureRepliesSchema();

$pageTitle = 'الشكاوى والدعم';
$pageSubtitle = 'متابعة تذاكر الدعم الفني والشكاوى';
$complaintsPagePath = basename((string) ($_SERVER['PHP_SELF'] ?? 'complaints.php'));
if ($complaintsPagePath === '' || $complaintsPagePath === '.' || $complaintsPagePath === '..') {
    $complaintsPagePath = 'complaints.php';
}

$action = get('action', 'list');
$id = (int) get('id');

function complaintsTableHasColumn($table, $column)
{
    $cache = &$GLOBALS['complaints_columns_cache'];
    if (!is_array($cache)) {
        $cache = [];
    }
    $cacheKey = $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($safeTable === '' || $safeColumn === '') {
        $cache[$cacheKey] = false;
        return false;
    }

    try {
        $row = db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE ?", [$safeColumn]);
        $cache[$cacheKey] = !empty($row);
    } catch (Throwable $e) {
        $cache[$cacheKey] = false;
    }
    return $cache[$cacheKey];
}

function complaintsTableExists($table): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($safeTable === '') {
        return false;
    }

    try {
        $row = db()->fetch("SHOW TABLES LIKE ?", [$safeTable]);
        return !empty($row);
    } catch (Throwable $e) {
        return false;
    }
}

function complaintsEnsureColumn($table, $column, $definition): void
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($safeTable === '' || $safeColumn === '') {
        return;
    }

    if (!complaintsTableExists($safeTable)) {
        return;
    }

    if (!complaintsTableHasColumn($safeTable, $safeColumn)) {
        try {
            db()->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
            $cacheKey = $safeTable . '.' . $safeColumn;
            if (!isset($GLOBALS['complaints_columns_cache']) || !is_array($GLOBALS['complaints_columns_cache'])) {
                $GLOBALS['complaints_columns_cache'] = [];
            }
            $GLOBALS['complaints_columns_cache'][$cacheKey] = true;
        } catch (Throwable $e) {
            // تجاهل أخطاء التعديل لضمان عدم تعطيل الصفحة.
        }
    }
}

function complaintsEnsureIndex($table, $index, array $columns): void
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $index);
    if ($safeTable === '' || $safeIndex === '' || empty($columns)) {
        return;
    }

    if (!complaintsTableExists($safeTable)) {
        return;
    }

    try {
        $row = db()->fetch("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = ?", [$safeIndex]);
        if (!empty($row)) {
            return;
        }

        $safeColumns = [];
        foreach ($columns as $column) {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
            if ($safeCol !== '') {
                $safeColumns[] = "`{$safeCol}`";
            }
        }
        if (empty($safeColumns)) {
            return;
        }

        db()->query("ALTER TABLE `{$safeTable}` ADD INDEX `{$safeIndex}` (" . implode(', ', $safeColumns) . ")");
    } catch (Throwable $e) {
        // تجاهل أخطاء التعديل.
    }
}

function decodeComplaintAttachmentPaths($rawValue)
{
    if (is_array($rawValue)) {
        $items = $rawValue;
    } elseif (is_string($rawValue)) {
        $trimmed = trim($rawValue);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = array_map('trim', explode(',', $trimmed));
        }
    } else {
        return [];
    }

    $paths = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $value = trim((string) ($item['path'] ?? $item['url'] ?? $item['image'] ?? ''));
        } else {
            $value = trim((string) $item);
        }

        if ($value === '' || strtolower($value) === 'null') {
            continue;
        }

        $paths[] = $value;
    }

    return array_values(array_unique($paths));
}

function mapComplaintAttachmentUrls($rawValue)
{
    $paths = decodeComplaintAttachmentPaths($rawValue);
    return array_values(array_filter(array_map('imageUrl', $paths)));
}

function collectReplyAttachmentUploads($fieldName = 'reply_attachments')
{
    if (!isset($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['name'])) {
        return [];
    }

    $raw = $_FILES[$fieldName];
    $files = [];

    if (is_array($raw['name'])) {
        $count = count($raw['name']);
        for ($index = 0; $index < $count; $index++) {
            $files[] = [
                'name' => $raw['name'][$index] ?? '',
                'type' => $raw['type'][$index] ?? '',
                'tmp_name' => $raw['tmp_name'][$index] ?? '',
                'error' => $raw['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $raw['size'][$index] ?? 0,
            ];
        }
    } else {
        $files[] = $raw;
    }

    $paths = [];
    foreach ($files as $file) {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            setFlashMessage('danger', 'فشل رفع صورة مرفقة');
            continue;
        }

        $upload = uploadFile($file, 'complaints');
        if (!($upload['success'] ?? false)) {
            setFlashMessage('danger', 'فشل رفع صورة مرفقة: ' . ($upload['message'] ?? 'خطأ غير معروف'));
            continue;
        }

        if (!empty($upload['path'])) {
            $paths[] = $upload['path'];
        }
    }

    return array_values(array_unique($paths));
}

function complaintStatusMeta($status)
{
    $map = [
        'open' => ['label' => 'مفتوحة', 'badge_class' => 'badge-danger'],
        'in_progress' => ['label' => 'قيد المعالجة', 'badge_class' => 'badge-warning'],
        'resolved' => ['label' => 'تم الحل', 'badge_class' => 'badge-success'],
        'closed' => ['label' => 'مغلقة', 'badge_class' => 'badge-secondary'],
    ];

    return $map[$status] ?? ['label' => $status, 'badge_class' => 'badge-info'];
}

function fetchComplaintRepliesForAdmin($complaintId)
{
    $rows = db()->fetchAll(
        "SELECT r.*,
                u.full_name AS user_name,
                a.username AS admin_name
         FROM complaint_replies r
         LEFT JOIN users u ON r.user_id = u.id
         LEFT JOIN admins a ON r.admin_id = a.id
         WHERE r.complaint_id = ?
         ORDER BY r.created_at ASC, r.id ASC",
        [$complaintId]
    );

    $replies = [];
    foreach ($rows as $row) {
        $isAdmin = !empty($row['admin_id']);
        $senderType = $isAdmin ? 'admin' : 'user';
        $senderName = $isAdmin
            ? (trim((string) ($row['admin_name'] ?? '')) ?: 'الدعم الفني')
            : (trim((string) ($row['user_name'] ?? '')) ?: 'العميل');

        $replies[] = [
            'id' => (int) ($row['id'] ?? 0),
            'sender_type' => $senderType,
            'sender_name' => $senderName,
            'message' => (string) ($row['message'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'attachments' => mapComplaintAttachmentUrls($row['attachments'] ?? null),
        ];
    }

    return $replies;
}

function renderComplaintRepliesHtml($replies)
{
    if (empty($replies)) {
        return '<div class="text-center text-muted" style="padding: 20px;">لا توجد ردود حتى الآن</div>';
    }

    ob_start();
    foreach ($replies as $reply) {
        $isAdmin = ($reply['sender_type'] ?? '') === 'admin';
        $senderLabel = htmlspecialchars((string) ($reply['sender_name'] ?? ''), ENT_QUOTES, 'UTF-8')
            . ($isAdmin ? ' (دعم فني)' : ' (عميل)');
        $message = nl2br(htmlspecialchars((string) ($reply['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $createdAt = htmlspecialchars((string) ($reply['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
        $bgClass = $isAdmin ? '#eef2ff' : '#f9fafb';
        $side = $isAdmin ? 'left' : 'right';
        $borderColor = $isAdmin ? 'var(--primary-color)' : '#9ca3af';
        ?>
        <div style="margin-bottom: 15px; background: <?php echo $bgClass; ?>; padding: 10px 15px; border-radius: 8px; border-<?php echo $side; ?>: 4px solid <?php echo $borderColor; ?>;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12px; color: #6b7280;">
                <strong><?php echo $senderLabel; ?></strong>
                <span><?php echo $createdAt; ?></span>
            </div>
            <?php if ($message !== ''): ?>
                <div style="white-space: pre-wrap; color: #374151;"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($reply['attachments'])): ?>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                    <?php foreach ($reply['attachments'] as $imageUrl): ?>
                        <?php $safeImage = htmlspecialchars((string) $imageUrl, ENT_QUOTES, 'UTF-8'); ?>
                        <a href="<?php echo $safeImage; ?>" target="_blank" rel="noopener" style="display: inline-block;">
                            <img src="<?php echo $safeImage; ?>" alt="Attachment" style="width: 88px; height: 88px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    return (string) ob_get_clean();
}

// معالجة تحديث الحالة وإضافة رد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaintId = (int) post('complaint_id');
    $status = post('status');
    $admin_notes = post('admin_notes');
    $resolution = post('resolution');
    $reply_message = post('reply_message');
    $replyAttachmentPaths = collectReplyAttachmentUploads('reply_attachments');

    if ($complaintId <= 0) {
        setFlashMessage('danger', 'معرّف التذكرة غير صالح.');
        redirect($complaintsPagePath);
    }

    try {
        // 1. تحديث حالة الشكوى والملاحظات بالأعمدة المتاحة فقط
        $data = [];
        if (complaintsTableHasColumn('complaints', 'status')) {
            $data['status'] = $status;
        }
        if (complaintsTableHasColumn('complaints', 'admin_notes')) {
            $data['admin_notes'] = $admin_notes;
        }
        if (complaintsTableHasColumn('complaints', 'resolution')) {
            $data['resolution'] = $resolution; // يمكن الاحتفاظ بها كملخص أو حل نهائي
        }
        if (complaintsTableHasColumn('complaints', 'assigned_to')) {
            $data['assigned_to'] = $_SESSION['admin_id'] ?? null;
        }
        if (
            ($status === 'resolved' || $status === 'closed')
            && complaintsTableHasColumn('complaints', 'resolved_at')
        ) {
            $data['resolved_at'] = date('Y-m-d H:i:s');
        }

        if (!empty($data)) {
            db()->update('complaints', $data, 'id = :id', ['id' => $complaintId]);
        }

        // 2. إذا تم كتابة رد، إضافته إلى جدول الردود
        if (!empty($reply_message) || !empty($replyAttachmentPaths)) {
            $replyText = trim((string) $reply_message);
            if ($replyText === '' && !empty($replyAttachmentPaths)) {
                $replyText = 'صورة مرفقة';
            }

            $replyData = [];
            if (complaintsTableHasColumn('complaint_replies', 'complaint_id')) {
                $replyData['complaint_id'] = $complaintId;
            }

            if (complaintsTableHasColumn('complaint_replies', 'admin_id')) {
                $replyData['admin_id'] = $_SESSION['admin_id'] ?? null;
            }

            if (complaintsTableHasColumn('complaint_replies', 'sender_type')) {
                $replyData['sender_type'] = 'admin';
            }

            $messageColumn = null;
            foreach (['message', 'reply', 'content', 'body', 'text'] as $candidate) {
                if (complaintsTableHasColumn('complaint_replies', $candidate)) {
                    $messageColumn = $candidate;
                    break;
                }
            }

            if ($messageColumn) {
                $replyData[$messageColumn] = $replyText;
            }

            if (complaintsTableHasColumn('complaint_replies', 'attachments') && !empty($replyAttachmentPaths)) {
                $replyData['attachments'] = json_encode($replyAttachmentPaths, JSON_UNESCAPED_UNICODE);
            }

            if (complaintsTableHasColumn('complaint_replies', 'created_at')) {
                $replyData['created_at'] = date('Y-m-d H:i:s');
            }

            if (empty($replyData) || empty($replyData['complaint_id'])) {
                throw new RuntimeException('Replies table schema is incomplete');
            }

            if ($messageColumn === null && $replyText !== '') {
                throw new RuntimeException('Replies table does not include a message column');
            }

            db()->insert('complaint_replies', $replyData);
        }

        logActivity('update_complaint', 'complaints', $complaintId);
        setFlashMessage('success', 'تم تحديث التذكرة بنجاح');
    } catch (Throwable $e) {
        error_log('complaints.php update failed: ' . $e->getMessage());
        setFlashMessage('danger', 'تعذر إرسال الرد حالياً. تحقق من بنية الجداول ثم أعد المحاولة.');
    }
    redirect($complaintsPagePath . "?action=view&id=$complaintId");
}

// عرض التفاصيل
if ($action === 'view' && $id) {
    // جلب تفاصيل الشكوى
    $complaint = db()->fetch("
        SELECT c.*, u.full_name as user_name, u.phone as user_phone, 
               p.full_name as provider_name, p.phone as provider_phone,
               o.id as order_ref, o.order_number
        FROM complaints c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN providers p ON c.provider_id = p.id
        LEFT JOIN orders o ON c.order_id = o.id
        WHERE c.id = ?
    ", [$id]);

    if (!$complaint) {
        setFlashMessage('danger', 'التذكرة غير موجودة');
        redirect($complaintsPagePath);
    }

    $replies = fetchComplaintRepliesForAdmin($id);
    $complaintAttachmentUrls = mapComplaintAttachmentUrls($complaint['attachments'] ?? null);
    $statusMeta = complaintStatusMeta((string) ($complaint['status'] ?? 'open'));

    if (get('ajax') === 'thread') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'html' => renderComplaintRepliesHtml($replies),
            'thread_signature' => md5(json_encode($replies, JSON_UNESCAPED_UNICODE)),
            'status' => $complaint['status'] ?? 'open',
            'status_label' => $statusMeta['label'],
            'status_badge_class' => $statusMeta['badge_class'],
            'updated_at' => $complaint['updated_at'] ?? '',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// عرض القائمة
$page = max(1, (int) get('page', 1));
$statusFilter = get('status');
$where = '1=1';
$params = [];

if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$totalComplaints = db()->count('complaints', $where, $params);
$pagination = paginate($totalComplaints, $page);

$complaints = db()->fetchAll("
    SELECT c.*, u.full_name as user_name, admin.username as admin_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN admins admin ON c.assigned_to = admin.id
    WHERE {$where}
    ORDER BY CASE WHEN status = 'open' THEN 1 WHEN status = 'in_progress' THEN 2 ELSE 3 END, created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
", $params);

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="card animate-slideUp">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fas fa-headset" style="color: var(--primary-color);"></i>
                تذاكر الدعم الفني
            </h3>
            <div class="card-actions">
                <a href="?status=open"
                    class="btn btn-sm <?php echo $statusFilter === 'open' ? 'btn-primary' : 'btn-outline'; ?>">مفتوحة</a>
                <a href="?status=in_progress"
                    class="btn btn-sm <?php echo $statusFilter === 'in_progress' ? 'btn-primary' : 'btn-outline'; ?>">قيد
                    العمل</a>
                <a href="?status=resolved"
                    class="btn btn-sm <?php echo $statusFilter === 'resolved' ? 'btn-primary' : 'btn-outline'; ?>">محلولة</a>
                <a href="<?php echo $complaintsPagePath; ?>" class="btn btn-sm btn-outline">الكل</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($complaints)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <h3>لا توجد شكاوى</h3>
                    <p>رائع! لا توجد تذاكر دعم فني مفتوحة حالياً.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>رقم التذكرة</th>
                                <th>الموضوع</th>
                                <th>العميل</th>
                                <th>الأولوية</th>
                                <th>الحالة</th>
                                <th>المسؤول</th>
                                <th>تاريخ الإنشاء</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--secondary-color);">#
                                            <?php echo $c['ticket_number']; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;">
                                            <?php echo $c['subject']; ?>
                                        </div>
                                        <div
                                            style="font-size: 12px; color: #6b7280; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo $c['description']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $c['user_name']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priorityColors = ['low' => 'badge-secondary', 'medium' => 'badge-info', 'high' => 'badge-warning', 'urgent' => 'badge-danger'];
                                        $priorityNames = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة'];
                                        ?>
                                        <span class="badge <?php echo $priorityColors[$c['priority']] ?? ''; ?>">
                                            <?php echo $priorityNames[$c['priority']] ?? $c['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = ['open' => 'badge-danger', 'in_progress' => 'badge-warning', 'resolved' => 'badge-success', 'closed' => 'badge-secondary'];
                                        $statusNames = ['open' => 'مفتوحة', 'in_progress' => 'قيد المعالجة', 'resolved' => 'تم الحل', 'closed' => 'مغلقة'];
                                        ?>
                                        <span class="badge <?php echo $statusColors[$c['status']] ?? ''; ?>">
                                            <?php echo $statusNames[$c['status']] ?? $c['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $c['admin_name'] ?: '<span class="text-muted">-</span>'; ?>
                                    </td>
                                    <td>
                                        <?php echo date('Y-m-d', strtotime($c['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="?action=view&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- التصفح -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination" style="margin-top: 20px;">
                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>"
                                class="page-link <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'view' && isset($complaint)): ?>
    <div style="max-width: 900px; margin: 0 auto;">
        <div style="margin-bottom: 20px;">
            <a href="<?php echo $complaintsPagePath; ?>" class="btn btn-outline">
                <i class="fas fa-arrow-right"></i>
                العودة للقائمة
            </a>
        </div>

        <!-- تفاصيل التذكرة -->
        <div class="card animate-slideUp">
            <div class="card-header" style="display: flex; justify-content: space-between;">
                <h3 class="card-title">تذكرة #
                    <?php echo $complaint['ticket_number']; ?>
                </h3>
                <div>
                    <span id="complaint-status-badge" class="badge <?php echo htmlspecialchars((string) ($statusMeta['badge_class'] ?? 'badge-info'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) ($statusMeta['label'] ?? ($complaint['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span class="badge badge-info"><?php echo $complaint['created_at']; ?></span>
                </div>
            </div>
            <div class="card-body">
                <!-- معلومات الشكوى الأساسية -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label class="text-muted">صاحب الشكوى</label>
                        <div style="font-weight: bold;">
                            <?php echo $complaint['user_name']; ?>
                        </div>
                        <div style="font-size: 13px;">
                            <?php echo $complaint['user_phone']; ?>
                        </div>
                    </div>
                    <div>
                        <label class="text-muted">مقدم الخدمة (إن وجد)</label>
                        <div style="font-weight: bold;">
                            <?php echo $complaint['provider_name'] ?: '-'; ?>
                        </div>
                    </div>
                    <div>
                        <label class="text-muted">طلب مرتبط</label>
                        <?php if ($complaint['order_ref']): ?>
                            <a href="orders.php?action=view&id=<?php echo $complaint['order_ref']; ?>"
                                class="btn btn-sm btn-outline">
                                عرض الطلب #
                                <?php echo $complaint['order_number']; ?>
                            </a>
                        <?php else: ?>
                            <div>-</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="background: var(--gray-100); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">
                        <?php echo $complaint['subject']; ?>
                    </h4>
                    <p style="color: var(--gray-600); line-height: 1.6; white-space: pre-wrap;">
                        <?php echo $complaint['description']; ?>
                    </p>
                    <?php if (!empty($complaintAttachmentUrls)): ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;">
                            <?php foreach ($complaintAttachmentUrls as $imageUrl): ?>
                                <?php $safeImage = htmlspecialchars((string) $imageUrl, ENT_QUOTES, 'UTF-8'); ?>
                                <a href="<?php echo $safeImage; ?>" target="_blank" rel="noopener" style="display: inline-block;">
                                    <img src="<?php echo $safeImage; ?>" alt="Complaint Attachment" style="width: 90px; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <hr style="margin: 20px 0; border: 0; border-top: 1px solid var(--gray-200);">

                <!-- سجل المحادثة (الردود) -->
                <h4 style="margin-bottom: 15px;">سجل المحادثة</h4>
                <div id="conversation-history" class="conversation-history"
                    data-thread-signature="<?php echo htmlspecialchars(md5(json_encode($replies, JSON_UNESCAPED_UNICODE)), ENT_QUOTES, 'UTF-8'); ?>"
                    style="max-height: 400px; overflow-y: auto; margin-bottom: 20px; padding: 10px; border: 1px solid #eee; border-radius: 8px;">
                    <?php echo renderComplaintRepliesHtml($replies); ?>
                </div>

                <!-- نموذج الرد وتحديث الحالة -->
                <form method="POST" enctype="multipart/form-data" style="background: #fdfdfd; padding: 20px; border: 1px solid #eee; border-radius: 8px;">
                    <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">

                    <h4 style="margin: 0 0 15px 0;">إضافة رد وتحديث الحالة</h4>

                    <div class="form-group">
                        <label class="form-label">الرد على العميل</label>
                        <textarea name="reply_message" class="form-control" rows="3"
                            placeholder="اكتب ردك هنا... سيظهر هذا الرد للعميل في التطبيق."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">إرفاق صور مع الرد (اختياري)</label>
                        <input type="file" name="reply_attachments[]" class="form-control" accept="image/*" multiple>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">تحديث الحالة</label>
                            <select name="status" class="form-control">
                                <option value="open" <?php echo $complaint['status'] == 'open' ? 'selected' : ''; ?>>مفتوحة
                                </option>
                                <option value="in_progress" <?php echo $complaint['status'] == 'in_progress' ? 'selected' : ''; ?>>قيد المعالجة</option>
                                <option value="resolved" <?php echo $complaint['status'] == 'resolved' ? 'selected' : ''; ?>>
                                    تم الحل</option>
                                <option value="closed" <?php echo $complaint['status'] == 'closed' ? 'selected' : ''; ?>>مغلقة
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ملاحظات داخلية (اختياري)</label>
                            <textarea name="admin_notes" class="form-control" rows="1"
                                placeholder="ملاحظات لا تظهر للعميل..."><?php echo $complaint['admin_notes']; ?></textarea>
                        </div>
                    </div>

                    <!-- Hidden resolution field to keep backward compatibility if needed -->
                    <textarea name="resolution" style="display:none;"><?php echo $complaint['resolution']; ?></textarea>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i>
                        إرسال الرد وتحديث التذكرة
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>
        (() => {
            const historyEl = document.getElementById('conversation-history');
            const statusBadgeEl = document.getElementById('complaint-status-badge');
            if (!historyEl) return;

            const pollUrl = '<?php echo htmlspecialchars($complaintsPagePath, ENT_QUOTES, 'UTF-8'); ?>?action=view&id=<?php echo (int) $complaint['id']; ?>&ajax=thread';

            const scrollToBottom = () => {
                historyEl.scrollTop = historyEl.scrollHeight;
            };

            scrollToBottom();

            const pollThread = async () => {
                try {
                    const response = await fetch(pollUrl, {
                        cache: 'no-store',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!response.ok) return;

                    const payload = await response.json();
                    if (!payload || payload.success !== true) return;

                    const newSignature = String(payload.thread_signature || '');
                    const oldSignature = historyEl.getAttribute('data-thread-signature') || '';
                    if (newSignature !== oldSignature) {
                        historyEl.innerHTML = payload.html || '';
                        historyEl.setAttribute('data-thread-signature', newSignature);
                        scrollToBottom();
                    }

                    if (statusBadgeEl) {
                        statusBadgeEl.className = 'badge ' + String(payload.status_badge_class || 'badge-info');
                        statusBadgeEl.textContent = String(payload.status_label || payload.status || '');
                    }
                } catch (_) {
                    // Ignore polling errors to keep page stable.
                }
            };

            setInterval(pollThread, 4000);
        })();
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
