<?php
/**
 * صفحة إدارة برنامج المكافآت والولاء
 * Loyalty Rewards Management Page
 */

require_once '../init.php';
requireLogin();

$pageTitle = 'برنامج المكافآت';
$pageSubtitle = 'إدارة قائمة المكافآت واستبدال النقاط';

function ensureRewardsMultilingualSchema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [
        'title_en' => "VARCHAR(255) NULL AFTER `title`",
        'title_ur' => "VARCHAR(255) NULL AFTER `title_en`",
        'description_en' => "TEXT NULL AFTER `description`",
        'description_ur' => "TEXT NULL AFTER `description_en`",
    ];

    foreach ($columns as $column => $definition) {
        $exists = db()->fetch("SHOW COLUMNS FROM `rewards` LIKE '{$column}'");
        if (!$exists) {
            db()->query("ALTER TABLE `rewards` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

ensureRewardsMultilingualSchema();

$action = get('action', 'list');

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add') {
        db()->insert('rewards', [
            'title' => post('title'),
            'title_en' => post('title_en'),
            'title_ur' => post('title_ur'),
            'description' => post('description'),
            'description_en' => post('description_en'),
            'description_ur' => post('description_ur'),
            'points_required' => (int) post('points_required'),
            'discount_value' => (float) post('discount_value'),
            'discount_type' => post('discount_type'),
            'is_active' => 1
        ]);
        setFlashMessage('success', 'تم إضافة المكافأة');
        redirect('rewards.php');
    }

    if ($postAction === 'delete') {
        db()->delete('rewards', 'id = ?', [(int) post('id')]);
        setFlashMessage('success', 'تم الحذف');
        redirect('rewards.php');
    }

    if ($postAction === 'toggle') {
        $id = (int) post('id');
        $status = (int) post('status');
        db()->update('rewards', ['is_active' => $status], 'id = ?', [$id]);
        redirect('rewards.php');
    }
}

$rewards = db()->fetchAll("SELECT * FROM rewards ORDER BY points_required ASC");

include '../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">

    <!-- نموذج الإضافة -->
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">إضافة مكافأة جديدة</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>عنوان المكافأة</label>
                    <input type="text" name="title" class="form-control" placeholder="مثال: خصم 50 ريال" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label>عنوان المكافأة (إنجليزي)</label>
                        <input type="text" name="title_en" class="form-control" placeholder="Example: SAR 50 discount">
                    </div>
                    <div class="form-group">
                        <label>عنوان المكافأة (أوردو)</label>
                        <input type="text" name="title_ur" class="form-control" placeholder="Urdu title">
                    </div>
                </div>

                <div class="form-group">
                    <label>الوصف</label>
                    <textarea name="description" class="form-control" rows="2"
                        placeholder="تفاصيل المكافأة..."></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label>الوصف (إنجليزي)</label>
                        <textarea name="description_en" class="form-control" rows="2"
                            placeholder="Reward details..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>الوصف (أوردو)</label>
                        <textarea name="description_ur" class="form-control" rows="2"
                            placeholder="Urdu description"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>النقاط المطلوبة</label>
                    <input type="number" name="points_required" class="form-control" placeholder="1000" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label>نوع الخصم</label>
                        <select name="discount_type" class="form-control">
                            <option value="fixed">مبلغ ثابت</option>
                            <option value="percentage">نسبة %</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>قيمة الخصم</label>
                        <input type="number" name="discount_value" class="form-control" step="0.01">
                    </div>
                </div>

                <button class="btn btn-primary btn-block">إضافة للقائمة</button>
            </form>
        </div>
    </div>

    <!-- قائمة المكافآت -->
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">قائمة المكافآت المتاحة</h3>
        </div>
        <div class="card-body">
            <?php if (empty($rewards)): ?>
                <div class="empty-state">
                    <h3>لا توجد مكافآت</h3>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>المكافأة</th>
                            <th>النقاط</th>
                            <th>الخصم</th>
                            <th>الحالة</th>
                            <th>حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rewards as $r): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php echo $r['title']; ?>
                                    </strong>
                                    <?php if (!empty($r['title_en']) || !empty($r['title_ur'])): ?>
                                        <div style="font-size: 11px; color: #666; margin-top: 4px;">
                                            <?php echo htmlspecialchars((string) ($r['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if (!empty($r['title_en']) && !empty($r['title_ur'])): ?>
                                                •
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars((string) ($r['title_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size: 11px; color: #666;">
                                        <?php echo $r['description']; ?>
                                    </div>
                                    <?php if (!empty($r['description_en']) || !empty($r['description_ur'])): ?>
                                        <div style="font-size: 11px; color: #94a3b8;">
                                            <?php echo htmlspecialchars((string) ($r['description_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if (!empty($r['description_en']) && !empty($r['description_ur'])): ?>
                                                •
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars((string) ($r['description_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-star"></i>
                                        <?php echo $r['points_required']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $r['discount_value']; ?>
                                    <?php echo $r['discount_type'] == 'percentage' ? '%' : '⃁'; ?>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $r['is_active'] ? 0 : 1; ?>">
                                        <button
                                            class="btn btn-sm <?php echo $r['is_active'] ? 'btn-success' : 'btn-outline'; ?>">
                                            <?php echo $r['is_active'] ? 'نشط' : 'معطل'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('حذف؟')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                        <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
