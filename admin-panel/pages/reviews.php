<?php
/**
 * صفحة إدارة التقييمات والمراجعات
 * Reviews Management Page
 */

require_once '../init.php';
requireLogin();

$pageTitle = 'التقييمات';
$pageSubtitle = 'مراجعة آراء العملاء وتقييماتهم';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    $reviewId = (int)post('review_id');
    db()->delete('reviews', 'id = ?', [$reviewId]);
    setFlashMessage('success', 'تم حذف التقييم');
    redirect('reviews.php');
}

$reviews = db()->fetchAll("
    SELECT r.*, u.full_name as user_name, p.full_name as provider_name, o.order_number
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN providers p ON r.provider_id = p.id
    LEFT JOIN orders o ON r.order_id = o.id
    ORDER BY r.created_at DESC
    LIMIT 100
"); // Assuming reviews table exists

include '../includes/header.php';
?>

<div class="card animate-slideUp">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-star" style="color: var(--warning-color);"></i> آخر التقييمات</h3>
    </div>
    <div class="card-body">
        <?php if(empty($reviews)): ?>
            <div class="empty-state"><h3>لا توجد تقييمات بعد</h3></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>العميل</th>
                            <th>المقيم (مقدم الخدمة)</th>
                            <th>الطلب</th>
                            <th>التقييم</th>
                            <th>تفاصيل التقييم</th>
                            <th>التعليق</th>
                            <th>التاريخ</th>
                            <th>حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reviews as $r): ?>
                        <tr>
                            <td><?php echo $r['user_name']; ?></td>
                            <td><?php echo $r['provider_name']; ?></td>
                            <td>
                                <?php if($r['order_number']): ?>
                                <a href="orders.php?action=view&id=<?php echo (int)$r['order_id']; ?>">#<?php echo $r['order_number']; ?></a>
                                <?php else: ?> - <?php endif; ?>
                            </td>
                            <td>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="fas fa-star" style="color: <?php echo $i <= $r['rating'] ? '#fbcc26' : '#ccc'; ?>; font-size: 12px;"></i>
                                <?php endfor; ?>
                            </td>
                            <td style="font-size: 12px;">
                                <div>الجودة: <?php echo $r['quality_rating'] ?? '-'; ?></div>
                                <div>السرعة/الالتزام: <?php echo $r['speed_rating'] ?? '-'; ?></div>
                                <div>الاحترافية: <?php echo $r['behavior_rating'] ?? '-'; ?></div>
                                <div>السعر: <?php echo $r['price_rating'] ?? '-'; ?></div>
                                <?php
                                    $tags = [];
                                    if (!empty($r['tags'])) {
                                        $decoded = json_decode($r['tags'], true);
                                        if (is_array($decoded)) {
                                            $tags = $decoded;
                                        } else {
                                            $tags = [(string)$r['tags']];
                                        }
                                    }
                                ?>
                                <?php if (!empty($tags)): ?>
                                <div style="margin-top: 6px; display: flex; gap: 4px; flex-wrap: wrap;">
                                    <?php foreach ($tags as $tag): ?>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><div style="max-width: 300px;"><?php echo $r['comment']; ?></div></td>
                            <td style="font-size: 12px;"><?php echo timeAgo($r['created_at']); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('حذف هذا التقييم؟');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="review_id" value="<?php echo $r['id']; ?>">
                                    <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
