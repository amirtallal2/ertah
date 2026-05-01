<?php
/**
 * صفحة إدارة التقييمات والمراجعات
 * Reviews Management Page
 */

require_once '../init.php';
require_once '../includes/special_services.php';
requireLogin();

$pageTitle = 'التقييمات';
$pageSubtitle = 'مراجعة آراء العملاء وتقييماتهم';

ensureSpecialServicesSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    $reviewId = (int)post('review_id');
    $reviewType = trim((string) post('review_type', 'provider'));
    if ($reviewType === 'container_store') {
        $review = db()->fetch('SELECT store_id FROM container_store_reviews WHERE id = ? LIMIT 1', [$reviewId]);
        db()->delete('container_store_reviews', 'id = ?', [$reviewId]);
        if (!empty($review['store_id'])) {
            specialRecalculateContainerStoreRating((int) $review['store_id']);
        }
    } else {
        db()->delete('reviews', 'id = ?', [$reviewId]);
    }
    setFlashMessage('success', 'تم حذف التقييم');
    redirect('reviews.php');
}

$reviews = [];

$providerReviews = db()->fetchAll("
    SELECT r.*, u.full_name as user_name, p.full_name as provider_name, o.order_number
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN providers p ON r.provider_id = p.id
    LEFT JOIN orders o ON r.order_id = o.id
    ORDER BY r.created_at DESC
    LIMIT 100
"); // Assuming reviews table exists
foreach ($providerReviews as $review) {
    $review['review_type'] = 'provider';
    $review['target_label'] = 'مقدم خدمة';
    $reviews[] = $review;
}

if (specialServiceTableExists('container_store_reviews')) {
    $storeReviews = db()->fetchAll("
        SELECT r.*, u.full_name as user_name, cs.name_ar as provider_name, o.order_number
        FROM container_store_reviews r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN container_stores cs ON r.store_id = cs.id
        LEFT JOIN orders o ON r.order_id = o.id
        ORDER BY r.created_at DESC
        LIMIT 100
    ");
    foreach ($storeReviews as $review) {
        $review['review_type'] = 'container_store';
        $review['target_label'] = 'متجر حاويات';
        $reviews[] = $review;
    }
}

usort($reviews, static function (array $a, array $b): int {
    return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
});
$reviews = array_slice($reviews, 0, 100);

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
                            <th>المقيَّم</th>
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
                            <td>
                                <?php echo htmlspecialchars((string) ($r['provider_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?><br>
                                <span class="badge <?php echo ($r['review_type'] ?? '') === 'container_store' ? 'badge-primary' : 'badge-success'; ?>">
                                    <?php echo htmlspecialchars((string) ($r['target_label'] ?? 'مقدم خدمة'), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
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
                                    <input type="hidden" name="review_type" value="<?php echo htmlspecialchars((string) ($r['review_type'] ?? 'provider'), ENT_QUOTES, 'UTF-8'); ?>">
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
