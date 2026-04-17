<?php
$isEditForm = isset($option) && is_array($option);
$formPrefix = $isEditForm ? 'edit' : 'add';
$selectedCategoryId = (int) ($option['category_id'] ?? 0);
$selectedServiceId = isset($option['service_id']) && $option['service_id'] !== null ? (int) $option['service_id'] : 0;
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
    <div class="form-group">
        <label class="form-label">الفئة</label>
        <select
            id="<?php echo $formPrefix; ?>-category-id"
            name="category_id"
            class="form-control"
            required
        >
            <option value="">اختر الفئة</option>
            <?php foreach ($categories as $category): ?>
            <option
                value="<?php echo (int) $category['id']; ?>"
                <?php echo $selectedCategoryId === (int) $category['id'] ? 'selected' : ''; ?>
            >
                <?php echo htmlspecialchars($category['display_name_ar'] ?? $category['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label">نوع الخدمة (اختياري)</label>
        <select id="<?php echo $formPrefix; ?>-service-id" name="service_id" class="form-control">
            <option value="">عام لكل أنواع الخدمة</option>
            <?php foreach ($services as $service): ?>
            <option
                value="<?php echo (int) $service['id']; ?>"
                data-category-id="<?php echo (int) $service['category_id']; ?>"
                <?php echo $selectedServiceId === (int) $service['id'] ? 'selected' : ''; ?>
            >
                <?php echo htmlspecialchars($service['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($service['category_display_name'])): ?>
                    <?php echo ' (' . htmlspecialchars($service['category_display_name'], ENT_QUOTES, 'UTF-8') . ')'; ?>
                <?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
    <div class="form-group">
        <label class="form-label">عنوان التفصيلة (عربي)</label>
        <input
            type="text"
            name="title_ar"
            class="form-control"
            value="<?php echo isset($option['title_ar']) ? $option['title_ar'] : ''; ?>"
            required
        >
    </div>

    <div class="form-group">
        <label class="form-label">عنوان التفصيلة (إنجليزي - اختياري)</label>
        <input
            type="text"
            name="title_en"
            class="form-control"
            value="<?php echo isset($option['title_en']) ? $option['title_en'] : ''; ?>"
        >
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
    <div class="form-group">
        <label class="form-label">عنوان التفصيلة (أوردو - اختياري)</label>
        <input
            type="text"
            name="title_ur"
            class="form-control"
            value="<?php echo isset($option['title_ur']) ? $option['title_ur'] : ''; ?>"
        >
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
    <div class="form-group">
        <label class="form-label">الترتيب</label>
        <input
            type="number"
            name="sort_order"
            class="form-control"
            value="<?php echo isset($option['sort_order']) ? (int) $option['sort_order'] : 0; ?>"
        >
    </div>
    <div class="form-group">
        <label class="form-label" style="display: flex; align-items: center; gap: 10px; margin-top: 30px; cursor: pointer;">
            <input type="checkbox" name="is_active" <?php echo !isset($option) || !empty($option['is_active']) ? 'checked' : ''; ?>>
            تفعيل التفصيلة
        </label>
    </div>
</div>
