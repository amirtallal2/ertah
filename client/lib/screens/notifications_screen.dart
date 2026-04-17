// Notifications Screen
// شاشة الإشعارات

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';

import '../config/app_theme.dart';
import '../services/app_localizations.dart';
import '../services/user_service.dart';
import '../utils/order_tracking_navigation.dart';

class NotificationsScreen extends StatefulWidget {
  final VoidCallback? onBack;

  const NotificationsScreen({super.key, this.onBack});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final UserService _userService = UserService();

  String _filter = 'all'; // all | unread
  bool _isLoading = true;
  String? _error;
  List<Map<String, dynamic>> _notifications = [];
  final Set<int> _locallyReadIds = <int>{};
  final Set<int> _deletedIds = <int>{};

  @override
  void initState() {
    super.initState();
    _fetchNotifications();
  }

  Future<void> _fetchNotifications() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final response = await _userService.getNotifications(
        page: 1,
        perPage: 100,
      );
      if (!mounted) return;

      if (!response.success) {
        setState(() {
          _isLoading = false;
          _error = response.message ?? context.tr('error_loading_data');
        });
        return;
      }

      final data = response.data;
      final rows = data is List ? data : <dynamic>[];
      final items = <Map<String, dynamic>>[];

      for (final row in rows) {
        if (row is! Map) continue;
        final item = Map<String, dynamic>.from(
          row.map((key, value) => MapEntry(key.toString(), value)),
        );

        final id = _toInt(item['id']);
        if (id <= 0) continue;

        final createdAtRaw = (item['created_at'] ?? '').toString();
        final createdAt = DateTime.tryParse(createdAtRaw) ?? DateTime.now();
        final payload = item['data'] is Map
            ? Map<String, dynamic>.from(
                (item['data'] as Map).map(
                  (key, value) => MapEntry(key.toString(), value),
                ),
              )
            : null;

        items.add({
          'id': id,
          'title': (item['title'] ?? '').toString(),
          'body': (item['body'] ?? '').toString(),
          'type': (item['type'] ?? 'system').toString(),
          'data': payload,
          'isRead': item['is_read'] == true || item['is_read'] == 1,
          'createdAt': createdAt,
        });
      }

      setState(() {
        _notifications = items;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _error = context.tr('connection_error');
      });
    }
  }

  int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  bool _isNotificationRead(Map<String, dynamic> notification) {
    final id = _toInt(notification['id']);
    final isReadServer =
        notification['isRead'] == true || notification['isRead'] == 1;
    return isReadServer || _locallyReadIds.contains(id);
  }

  List<Map<String, dynamic>> get _visibleNotifications {
    final items = _notifications
        .where((n) => !_deletedIds.contains(_toInt(n['id'])))
        .toList();

    if (_filter == 'all') return items;
    return items.where((n) => !_isNotificationRead(n)).toList();
  }

  int get _unreadCount => _notifications
      .where((n) => !_deletedIds.contains(_toInt(n['id'])))
      .where((n) => !_isNotificationRead(n))
      .length;

  void _markAsRead(int id) {
    setState(() => _locallyReadIds.add(id));
  }

  void _markAllAsRead() {
    setState(() {
      for (final n in _notifications) {
        final id = _toInt(n['id']);
        if (id > 0 && !_deletedIds.contains(id)) {
          _locallyReadIds.add(id);
        }
      }
    });
  }

  void _deleteNotification(int id) {
    setState(() => _deletedIds.add(id));
  }

  List<Color> _colorsForType(String type) {
    switch (type.toLowerCase()) {
      case 'order':
        return [Colors.green.shade400, Colors.green.shade600];
      case 'promotion':
      case 'offer':
        return [Colors.orange.shade400, Colors.amber.shade600];
      case 'wallet':
      case 'wallet_update':
        return [Colors.blue.shade400, Colors.blue.shade600];
      case 'review':
        return [Colors.purple.shade400, Colors.purple.shade600];
      default:
        return [Colors.grey.shade400, Colors.grey.shade600];
    }
  }

  String _iconForType(String type) {
    switch (type.toLowerCase()) {
      case 'order':
        return '📦';
      case 'promotion':
      case 'offer':
        return '🎁';
      case 'wallet':
      case 'wallet_update':
        return '💰';
      case 'review':
        return '⭐';
      default:
        return '🔔';
    }
  }

  String _timeAgo(DateTime dateTime) {
    final now = DateTime.now();
    final diff = now.difference(dateTime);

    if (diff.inMinutes < 1) return context.tr('notifications_time_just_now');
    if (diff.inMinutes < 60) {
      return context
          .tr('notifications_time_minutes_ago')
          .replaceAll('{count}', diff.inMinutes.toString());
    }
    if (diff.inHours < 24) {
      return context
          .tr('notifications_time_hours_ago')
          .replaceAll('{count}', diff.inHours.toString());
    }
    if (diff.inDays < 7) {
      return context
          .tr('notifications_time_days_ago')
          .replaceAll('{count}', diff.inDays.toString());
    }
    return context
        .tr('notifications_time_weeks_ago')
        .replaceAll('{count}', (diff.inDays / 7).floor().toString());
  }

  int? _extractOrderIdFromNotification(Map<String, dynamic> notification) {
    final payload = notification['data'];
    if (payload is! Map) return null;

    final data = Map<String, dynamic>.from(
      payload.map((key, value) => MapEntry(key.toString(), value)),
    );

    for (final key in const ['order_id', 'orderId', 'id']) {
      final raw = data[key];
      if (raw == null) continue;
      final parsed = int.tryParse(raw.toString());
      if (parsed != null && parsed > 0) return parsed;
    }

    return null;
  }

  Future<void> _handleNotificationTap(Map<String, dynamic> notification) async {
    final id = _toInt(notification['id']);
    if (id > 0) {
      _markAsRead(id);
    }

    final orderId = _extractOrderIdFromNotification(notification);
    if (orderId == null || orderId <= 0) return;

    await OrderTrackingNavigation.open(context, orderId: orderId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Column(
        children: [
          _buildHeader(),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                ? _buildErrorState()
                : _visibleNotifications.isEmpty
                ? _buildEmptyState()
                : RefreshIndicator(
                    onRefresh: _fetchNotifications,
                    child: ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _visibleNotifications.length,
                      itemBuilder: (context, index) {
                        return _buildNotificationCard(
                          _visibleNotifications[index],
                          index,
                        );
                      },
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).padding.top + 12,
        left: 16,
        right: 16,
        bottom: 24,
      ),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [Colors.orange.shade400, Colors.amber.shade500],
        ),
        boxShadow: AppShadows.lg,
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              InkWell(
                onTap: widget.onBack ?? () => Navigator.pop(context),
                borderRadius: BorderRadius.circular(20),
                child: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.arrow_forward,
                    color: Colors.white,
                    size: 20,
                  ),
                ),
              ),
              Expanded(
                child: Text(
                  context.tr('notifications'),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(width: 36),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: Row(
                  children: [
                    Expanded(
                      child: GestureDetector(
                        onTap: () => setState(() => _filter = 'all'),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          decoration: BoxDecoration(
                            color: _filter == 'all'
                                ? Colors.white
                                : Colors.white.withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(20),
                            boxShadow: _filter == 'all' ? AppShadows.md : null,
                          ),
                          child: Center(
                            child: Text(
                              '${context.tr('all_notifications')} (${_notifications.where((n) => !_deletedIds.contains(_toInt(n['id']))).length})',
                              style: TextStyle(
                                color: _filter == 'all'
                                    ? Colors.orange
                                    : Colors.white,
                                fontSize: 14,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: GestureDetector(
                        onTap: () => setState(() => _filter = 'unread'),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          decoration: BoxDecoration(
                            color: _filter == 'unread'
                                ? Colors.white
                                : Colors.white.withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(20),
                            boxShadow: _filter == 'unread'
                                ? AppShadows.md
                                : null,
                          ),
                          child: Center(
                            child: Text(
                              '${context.tr('unread_notifications')} ($_unreadCount)',
                              style: TextStyle(
                                color: _filter == 'unread'
                                    ? Colors.orange
                                    : Colors.white,
                                fontSize: 14,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (_unreadCount > 0) ...[
                const SizedBox(width: 8),
                InkWell(
                  onTap: _markAllAsRead,
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.done_all,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildNotificationCard(Map<String, dynamic> notification, int index) {
    final id = _toInt(notification['id']);
    final isUnread = !_isNotificationRead(notification);
    final type = (notification['type'] ?? 'system').toString();
    final icon = _iconForType(type);
    final colors = _colorsForType(type);

    return InkWell(
      onTap: () => _handleNotificationTap(notification),
      borderRadius: BorderRadius.circular(16),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: AppShadows.sm,
          border: isUnread
              ? const Border(right: BorderSide(color: Colors.orange, width: 4))
              : null,
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  gradient: LinearGradient(colors: colors),
                  shape: BoxShape.circle,
                  boxShadow: AppShadows.md,
                ),
                child: Center(
                  child: Text(icon, style: const TextStyle(fontSize: 20)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Text(
                            (notification['title'] ?? '').toString(),
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 14,
                              color: isUnread
                                  ? AppColors.gray800
                                  : AppColors.gray600,
                            ),
                          ),
                        ),
                        if (isUnread)
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: Colors.orange,
                              shape: BoxShape.circle,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      (notification['body'] ?? '').toString(),
                      style: const TextStyle(
                        color: AppColors.gray500,
                        fontSize: 12,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          _timeAgo(notification['createdAt'] as DateTime),
                          style: const TextStyle(
                            color: AppColors.gray400,
                            fontSize: 10,
                          ),
                        ),
                        Row(
                          children: [
                            if (isUnread)
                              GestureDetector(
                                onTap: () => _markAsRead(id),
                                child: Container(
                                  padding: const EdgeInsets.all(6),
                                  decoration: BoxDecoration(
                                    color: Colors.green.shade50,
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(
                                    Icons.check,
                                    color: Colors.green,
                                    size: 14,
                                  ),
                                ),
                              ),
                            const SizedBox(width: 8),
                            GestureDetector(
                              onTap: () => _deleteNotification(id),
                              child: Container(
                                padding: const EdgeInsets.all(6),
                                decoration: BoxDecoration(
                                  color: Colors.red.shade50,
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(
                                  Icons.delete,
                                  color: Colors.red,
                                  size: 14,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    ).animate().fadeIn(delay: (index * 50).ms).slideY(begin: 0.1);
  }

  Widget _buildErrorState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, color: Colors.orange, size: 46),
            const SizedBox(height: 12),
            Text(_error ?? context.tr('error_loading_data')),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _fetchNotifications,
              child: Text(context.tr('retry')),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 96,
            height: 96,
            decoration: const BoxDecoration(
              color: AppColors.gray100,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.notifications_off,
              color: AppColors.gray300,
              size: 48,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            _filter == 'all'
                ? context.tr('no_notifications')
                : context.tr('no_unread_notifications'),
            style: const TextStyle(color: AppColors.gray400),
          ),
        ],
      ),
    ).animate().fadeIn();
  }
}
