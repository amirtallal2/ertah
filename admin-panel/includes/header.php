<?php
/**
 * الهيدر المشترك
 * Header Include
 */

require_once __DIR__ . '/special_services.php';

$admin = getCurrentAdmin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isInPagesDir = strpos($_SERVER['PHP_SELF'], '/pages/') !== false;
$assetsPrefix = $isInPagesDir ? '../' : '';
$styleVersion = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();

// Dynamic Navigation Paths
$root = $isInPagesDir ? '../' : ''; // Path to root directory (where index.php is)
$pages = $isInPagesDir ? '' : 'pages/'; // Path to pages directory
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'لوحة التحكم'; ?> - Darfix</title>
    <link rel="stylesheet" href="<?php echo $assetsPrefix; ?>assets/css/style.css?v=<?php echo $styleVersion; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="admin-layout">
        <!-- الشريط الجانبي -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div style="font-size: 24px;">🏠</div>
                </div>
                <div class="sidebar-brand">
                    <h2>Darfix</h2>
                    <span>لوحة التحكم</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <!-- القائمة الرئيسية -->
                <?php if (hasPermission('dashboard')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">الرئيسية</div>
                        <a href="<?php echo $root; ?>index.php"
                            class="nav-item <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>لوحة التحكم</span>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- إدارة المستخدمين -->
                <?php if (hasPermission('users') || hasPermission('providers')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">المستخدمين</div>

                        <?php if (hasPermission('users')): ?>
                            <a href="<?php echo $pages; ?>users.php"
                                class="nav-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                                <i class="fas fa-users"></i>
                                <span>المستخدمين</span>
                            </a>
                        <?php endif; ?>

                        <?php if (hasPermission('providers')): ?>
                            <a href="<?php echo $pages; ?>providers.php"
                                class="nav-item <?php echo $currentPage === 'providers' ? 'active' : ''; ?>">
                                <i class="fas fa-user-tie"></i>
                                <span>مقدمي الخدمات</span>
                                <?php
                                $pendingProviders = db()->count('providers', "status = 'pending'");
                                if ($pendingProviders > 0): ?>
                                    <span class="nav-badge"><?php echo $pendingProviders; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- إدارة الخدمات والطلبات -->
                <?php if (hasPermission('orders') || hasPermission('providers') || hasPermission('services')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">الخدمات</div>
                        <?php
                        $furniturePendingRequests = 0;
                        $containerPendingRequests = 0;
                        if (specialServiceTableExists('furniture_requests')) {
                            $furniturePendingRequests = (int) db()->count(
                                'furniture_requests',
                                "status IN ('new', 'reviewed', 'quoted')"
                            );
                        }
                        if (specialServiceTableExists('container_requests')) {
                            $containerPendingRequests = (int) db()->count(
                                'container_requests',
                                "status IN ('new', 'reviewed', 'quoted')"
                            );
                        }
                        ?>

                        <?php if (hasPermission('providers')): // Categories managed with services ?>
                            <a href="<?php echo $pages; ?>categories.php"
                                class="nav-item <?php echo $currentPage === 'categories' ? 'active' : ''; ?>">
                                <i class="fas fa-th-large"></i>
                                <span>فئات الخدمات</span>
                            </a>
                        <?php endif; ?>

                        <?php if (hasPermission('services') || hasPermission('providers')): ?>
                            <a href="<?php echo $pages; ?>services.php"
                                class="nav-item <?php echo $currentPage === 'services' ? 'active' : ''; ?>">
                                <i class="fas fa-tools"></i>
                                <span>الخدمات</span>
                            </a>
                        <?php endif; ?>

                        <?php if (hasPermission('services') || $admin['role'] === 'super_admin'): ?>
                            <a href="<?php echo $pages; ?>problem-details.php"
                                class="nav-item <?php echo $currentPage === 'problem-details' ? 'active' : ''; ?>">
                                <i class="fas fa-list-check"></i>
                                <span>تفاصيل المشكلة</span>
                            </a>
                        <?php endif; ?>

                        <?php if (hasPermission('settings') || hasPermission('services') || $admin['role'] === 'super_admin'): ?>
                            <a href="<?php echo $pages; ?>service-areas.php"
                                class="nav-item <?php echo $currentPage === 'service-areas' ? 'active' : ''; ?>">
                                <i class="fas fa-map-marked-alt"></i>
                                <span>مناطق الخدمة GPS</span>
                            </a>
                        <?php endif; ?>

                        <?php if (hasPermission('orders')): ?>
                            <a href="<?php echo $pages; ?>orders.php"
                                class="nav-item <?php echo $currentPage === 'orders' ? 'active' : ''; ?>">
                                <i class="fas fa-clipboard-list"></i>
                                <span>الطلبات</span>
                                <?php
                                $pendingWhere = "status = 'pending'";
                                $pendingOrders = db()->count('orders', $pendingWhere);
                                if ($pendingOrders > 0): ?>
                                    <span class="nav-badge"><?php echo $pendingOrders; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>

                        <?php if (hasPermission('products')): // Assuming spare parts falls under products/services ?>
                            <a href="<?php echo $pages; ?>spare-parts.php"
                                class="nav-item <?php echo $currentPage === 'spare-parts' ? 'active' : ''; ?>">
                                <i class="fas fa-cogs"></i>
                                <span>قطع الغيار</span>
                            </a>
                        <?php endif; ?>

                        <?php if (hasPermission('services') || hasPermission('orders') || $admin['role'] === 'super_admin'): ?>
                            <div style="margin: 8px 12px 4px; font-size: 11px; color: #9ca3af;">خدمات متخصصة</div>
                        <?php endif; ?>

                        <?php if (hasPermission('services') || $admin['role'] === 'super_admin'): ?>
                            <a href="<?php echo $pages; ?>furniture-services.php"
                                class="nav-item <?php echo $currentPage === 'furniture-services' ? 'active' : ''; ?>">
                                <i class="fas fa-truck-moving"></i>
                                <span>خدمات نقل العفش</span>
                            </a>
                            <a href="<?php echo $pages; ?>furniture-settings.php"
                                class="nav-item <?php echo $currentPage === 'furniture-settings' ? 'active' : ''; ?>">
                                <i class="fas fa-sliders-h"></i>
                                <span>إعدادات نقل العفش</span>
                            </a>
                            <a href="<?php echo $pages; ?>container-services.php"
                                class="nav-item <?php echo $currentPage === 'container-services' ? 'active' : ''; ?>">
                                <i class="fas fa-boxes-stacked"></i>
                                <span>خدمات الحاويات</span>
                            </a>
                            <a href="<?php echo $pages; ?>container-stores.php"
                                class="nav-item <?php echo $currentPage === 'container-stores' ? 'active' : ''; ?>">
                                <i class="fas fa-store"></i>
                                <span>متاجر الحاويات</span>
                            </a>
                        <?php endif; ?>

                        <?php if (hasPermission('orders') || $admin['role'] === 'super_admin'): ?>
                            <a href="<?php echo $pages; ?>furniture-requests.php"
                                class="nav-item <?php echo $currentPage === 'furniture-requests' ? 'active' : ''; ?>">
                                <i class="fas fa-dolly"></i>
                                <span>طلبات نقل العفش</span>
                                <?php if ($furniturePendingRequests > 0): ?>
                                    <span class="nav-badge"><?php echo $furniturePendingRequests; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo $pages; ?>container-requests.php"
                                class="nav-item <?php echo $currentPage === 'container-requests' ? 'active' : ''; ?>">
                                <i class="fas fa-truck-ramp-box"></i>
                                <span>طلبات الحاويات</span>
                                <?php if ($containerPendingRequests > 0): ?>
                                    <span class="nav-badge"><?php echo $containerPendingRequests; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- إدارة المتاجر -->
                <?php if (hasPermission('products')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">المتجر</div>
                        <a href="<?php echo $pages; ?>stores.php"
                            class="nav-item <?php echo $currentPage === 'stores' ? 'active' : ''; ?>">
                            <i class="fas fa-store"></i>
                            <span>المتاجر</span>
                        </a>
                        <a href="<?php echo $pages; ?>products.php"
                            class="nav-item <?php echo $currentPage === 'products' ? 'active' : ''; ?>">
                            <i class="fas fa-box"></i>
                            <span>المنتجات</span>
                        </a>
                        <a href="<?php echo $pages; ?>product-categories.php"
                            class="nav-item <?php echo $currentPage === 'product-categories' ? 'active' : ''; ?>">
                            <i class="fas fa-tags"></i>
                            <span>فئات المنتجات</span>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- التسويق -->
                <?php if (hasPermission('offers')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">التسويق</div>
                        <a href="<?php echo $pages; ?>promo-codes.php"
                            class="nav-item <?php echo ($currentPage === 'promo-codes' || $currentPage === 'offers') ? 'active' : ''; ?>">
                            <i class="fas fa-ticket-alt"></i>
                            <span>العروض / أكواد الخصم</span>
                        </a>
                        <a href="<?php echo $pages; ?>banners.php"
                            class="nav-item <?php echo $currentPage === 'banners' ? 'active' : ''; ?>">
                            <i class="fas fa-image"></i>
                            <span>البانرات</span>
                        </a>
                        <a href="<?php echo $pages; ?>rewards.php"
                            class="nav-item <?php echo $currentPage === 'rewards' ? 'active' : ''; ?>">
                            <i class="fas fa-gift"></i>
                            <span>مكافآت الولاء</span>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- المالية -->
                <?php if (hasPermission('financial')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">المالية</div>
                        <a href="<?php echo $pages; ?>transactions.php"
                            class="nav-item <?php echo $currentPage === 'transactions' ? 'active' : ''; ?>">
                            <i class="fas fa-wallet"></i>
                            <span>المعاملات المالية</span>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- الدعم -->
                <?php if (hasPermission('complaints') || hasPermission('notifications')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">الدعم</div>

                        <?php if (hasPermission('complaints')): ?>
                            <a href="<?php echo $pages; ?>complaints.php"
                                class="nav-item <?php echo $currentPage === 'complaints' ? 'active' : ''; ?>">
                                <i class="fas fa-headset"></i>
                                <span>الشكاوى</span>
                                <?php
                                $openComplaints = db()->count('complaints', "status IN ('open', 'in_progress')");
                                if ($openComplaints > 0): ?>
                                    <span class="nav-badge"><?php echo $openComplaints; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo $pages; ?>reviews.php"
                                class="nav-item <?php echo $currentPage === 'reviews' ? 'active' : ''; ?>">
                                <i class="fas fa-star"></i>
                                <span>التقييمات</span>
                            </a>
                        <?php endif; ?>

                        <?php if (hasPermission('notifications')): ?>
                            <a href="<?php echo $pages; ?>notifications.php"
                                class="nav-item <?php echo $currentPage === 'notifications' ? 'active' : ''; ?>">
                                <i class="fas fa-bell"></i>
                                <span>الإشعارات</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- الإعدادات -->
                <?php if (hasPermission('settings') || $admin['role'] === 'super_admin'): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">النظام</div>

                        <?php if (hasPermission('settings') || $admin['role'] === 'super_admin'): ?>
                            <a href="<?php echo $pages; ?>settings.php"
                                class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                                <i class="fas fa-cog"></i>
                                <span>الإعدادات</span>
                            </a>
                            <a href="<?php echo $pages; ?>notification-settings.php"
                                class="nav-item <?php echo $currentPage === 'notification-settings' ? 'active' : ''; ?>">
                                <i class="fas fa-bell-concierge"></i>
                                <span>إعدادات الإشعارات</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($admin['role'] === 'super_admin'): ?>
                            <a href="<?php echo $pages; ?>admins.php"
                                class="nav-item <?php echo $currentPage === 'admins' ? 'active' : ''; ?>">
                                <i class="fas fa-user-shield"></i>
                                <span>المشرفين</span>
                            </a>
                            <a href="<?php echo $pages; ?>activity-logs.php"
                                class="nav-item <?php echo $currentPage === 'activity-logs' ? 'active' : ''; ?>">
                                <i class="fas fa-history"></i>
                                <span>سجل النشاطات</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <!-- الهيدر -->
            <header class="main-header">
                <div class="header-right">
                    <button class="header-icon" id="sidebar-toggle" style="display: none;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title">
                        <h1><?php echo $pageTitle ?? 'لوحة التحكم'; ?></h1>
                        <p><?php echo $pageSubtitle ?? 'مرحباً بك في لوحة تحكم Darfix'; ?></p>
                    </div>
                </div>

                <div class="header-left">
                    <div class="header-icon" id="notifications-wrapper" style="cursor: pointer; position: relative;">
                        <i class="fas fa-bell" onclick="toggleNotifications()"></i>
                        <span class="badge" id="notifications-count" style="display: none;">0</span>

                        <!-- Notifications Dropdown -->
                        <div id="notifications-menu" class="dropdown-menu"
                            style="display: none; position: absolute; left: 0; top: 100%; width: 320px; background: white; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1000; direction: rtl;">
                            <div
                                style="padding: 12px 15px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: bold; color: #374151;">الإشعارات</span>
                                <span class="badge bg-red-100 text-red-800" id="notifications-header-count"
                                    style="font-size: 11px;">0</span>
                            </div>
                            <div id="notifications-list" style="max-height: 300px; overflow-y: auto;">
                                <div style="padding: 20px; text-align: center; color: #9ca3af;">
                                    <i class="fas fa-spinner fa-spin"></i> جاري التحميل...
                                </div>
                            </div>
                            <a href="<?php echo $root; ?>pages/orders.php?status=pending"
                                style="display: block; text-align: center; padding: 10px; background: #f9fafb; border-top: 1px solid #eee; color: var(--primary-color); font-size: 13px; text-decoration: none; border-radius: 0 0 8px 8px;">
                                عرض كل الطلبات
                            </a>
                        </div>
                    </div>

                    <script>
                        function toggleNotifications() {
                            const menu = document.getElementById('notifications-menu');
                            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                            if (menu.style.display === 'block') {
                                loadNotifications();
                            }
                        }

                        function loadNotifications() {
                            const root = '<?php echo $root; ?>';
                            fetch(root + 'ajax/get_notifications.php')
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        updateNotificationsUI(data);
                                    }
                                })
                                .catch(err => console.error('Error loading notifications:', err));
                        }

                        function updateNotificationsUI(data) {
                            // Update badge
                            const badge = document.getElementById('notifications-count');
                            const headerCount = document.getElementById('notifications-header-count');

                            if (data.count > 0) {
                                badge.style.display = 'flex';
                                badge.textContent = data.count > 99 ? '99+' : data.count;
                                headerCount.textContent = data.count;
                            } else {
                                badge.style.display = 'none';
                                headerCount.textContent = '0';
                            }

                            // Update list
                            const list = document.getElementById('notifications-list');
                            if (data.items.length === 0) {
                                list.innerHTML = '<div style="padding: 20px; text-align: center; color: #9ca3af;">لا توجد إشعارات جديدة</div>';
                                return;
                            }

                            const pagesPath = '<?php echo $pages; ?>';
                            list.innerHTML = data.items.map(item => `
                            <a href="${pagesPath}orders.php?action=view&id=${item.id}" style="display: block; padding: 12px 15px; border-bottom: 1px solid #f3f4f6; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="font-weight: bold; color: #1f2937; font-size: 13px;">${item.title}</span>
                                    <span style="font-size: 11px; color: #9ca3af;">${item.time}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #6b7280; font-size: 12px;">${item.user} • ${item.service}</span>
                                    <span style="color: var(--primary-color); font-weight: bold; font-size: 12px;">${item.amount}</span>
                                </div>
                            </a>
                        `).join('');
                        }

                        // Initial load
                        document.addEventListener('DOMContentLoaded', () => {
                            loadNotifications();
                            // Poll every 15 seconds
                            setInterval(loadNotifications, 15000);

                            // Close on click outside
                            document.addEventListener('click', (e) => {
                                const wrapper = document.getElementById('notifications-wrapper');
                                const menu = document.getElementById('notifications-menu');
                                if (menu.style.display === 'block' && !wrapper.contains(e.target)) {
                                    menu.style.display = 'none';
                                }
                            });
                        });
                    </script>

                    <?php
                    $profileLink = $isInPagesDir ? 'profile.php' : 'pages/profile.php';
                    $logoutLink = $isInPagesDir ? '../logout.php' : 'logout.php';
                    ?>
                    <div style="position: relative; display: inline-block;" id="profile-dropdown-container">
                        <div class="header-profile"
                            onclick="document.getElementById('dropdown-menu').classList.toggle('show');"
                            style="cursor: pointer;">
                            <img src="<?php echo imageUrl($admin['avatar'] ?? null, 'https://ui-avatars.com/api/?name=' . urlencode($admin['full_name'])); ?>"
                                alt="Profile">
                            <div class="header-profile-info">
                                <h4><?php echo $admin['full_name']; ?></h4>
                                <span><?php echo $admin['role'] === 'super_admin' ? 'مدير أعلى' : ($admin['role'] === 'admin' ? 'مدير' : 'مشرف'); ?></span>
                            </div>
                            <i class="fas fa-chevron-down" style="color: #9ca3af; font-size: 12px;"></i>
                        </div>

                        <!-- القائمة المنسدلة -->
                        <div id="dropdown-menu"
                            style="display: none; position: absolute; left: 0; top: 100%; background: white; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 200px; z-index: 1000; overflow: hidden;">
                            <a href="<?php echo $profileLink; ?>"
                                style="display: block; padding: 10px 15px; color: #333; text-decoration: none; border-bottom: 1px solid #f9f9f9; transition: background 0.2s;">
                                <i class="fas fa-user-circle"
                                    style="margin-left: 8px; color: var(--primary-color);"></i> الملف الشخصي
                            </a>
                            <a href="<?php echo $logoutLink; ?>"
                                style="display: block; padding: 10px 15px; color: #dc2626; text-decoration: none; transition: background 0.2s;">
                                <i class="fas fa-sign-out-alt" style="margin-left: 8px;"></i> تسجيل الخروج
                            </a>
                        </div>
                    </div>

                    <script>
                        // إغلاق القائمة عند النقر خارجها
                        window.onclick = function (event) {
                            if (!event.target.closest('#profile-dropdown-container')) {
                                var dropdowns = document.getElementsByClassName("show");
                                var openDropdown = document.getElementById("dropdown-menu");
                                if (openDropdown && openDropdown.classList.contains('show')) {
                                    openDropdown.classList.remove('show');
                                }
                            }
                        }
                    </script>

                    <style>
                        .show {
                            display: block !important;
                        }

                        #dropdown-menu a:hover {
                            background-color: #f3f4f6;
                        }
                    </style>
                </div>
            </header>

            <!-- محتوى الصفحة -->
            <div class="page-content">
                <?php
                $flash = getFlashMessage();
                if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> animate-slideUp">
                        <i
                            class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                        <span><?php echo $flash['message']; ?></span>
                    </div>
                <?php endif; ?>
