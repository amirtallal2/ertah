import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/localization_provider.dart';
import '../services/app_localizations.dart';
import '../services/orders_service.dart';
import '../services/providers_service.dart';
import '../utils/saudi_riyal_icon.dart';
import 'order_details_screen.dart';
import 'provider_profile_setup_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final OrdersService _ordersService = OrdersService();
  final ProvidersService _providersService = ProvidersService();
  bool _isDeletingAccount = false;

  List<dynamic> _activeOrders = [];
  List<dynamic> _pendingOrders = [];
  List<dynamic> _completedOrders = [];
  List<dynamic> _cancelledOrders = [];
  bool _isLoading = true;
  bool _isAvailabilityUpdating = false;
  String? _ordersErrorMessage;
  final List<Map<String, String>> _languages = const [
    {'code': 'ar', 'name': 'عربي', 'flag': '🇸🇦'},
    {'code': 'en', 'name': 'English', 'flag': '🇬🇧'},
    {'code': 'ur', 'name': 'اردو', 'flag': '🇵🇰'},
  ];

  List<dynamic> _asList(dynamic value) {
    return value is List ? value : <dynamic>[];
  }

  List<dynamic> _filterVisibleOrders(List<dynamic> orders) {
    return orders.where((item) {
      if (item is! Map) {
        return true;
      }

      final assignmentStatus =
          (item['current_provider_assignment_status'] ??
                  item['assignment_status'] ??
                  '')
              .toString()
              .trim()
              .toLowerCase();

      return assignmentStatus != 'cancelled' && assignmentStatus != 'rejected';
    }).toList();
  }

  List<int> _extractCategoryIds(dynamic raw) {
    if (raw is List) {
      return raw
          .map((item) => int.tryParse(item.toString()) ?? 0)
          .where((id) => id != 0)
          .toList();
    }

    final normalized = raw?.toString().trim() ?? '';
    if (normalized.isEmpty) {
      return const <int>[];
    }

    return normalized
        .split(',')
        .map((item) => int.tryParse(item.trim()) ?? 0)
        .where((id) => id != 0)
        .toList();
  }

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    _initializeDashboard();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _initializeDashboard() async {
    if (!mounted) return;
    final authProvider = context.read<AuthProvider>();
    await authProvider.refreshUser();
    if (!mounted) return;
    await _loadOrders();
  }

  Future<void> _loadOrders() async {
    setState(() {
      _isLoading = true;
      _ordersErrorMessage = null;
    });
    try {
      final authProvider = context.read<AuthProvider>();
      final status = authProvider.providerStatus;
      if (status.isNotEmpty && status != 'approved') {
        if (!mounted) return;
        setState(() {
          _activeOrders = [];
          _pendingOrders = [];
          _completedOrders = [];
          _cancelledOrders = [];
          _isLoading = false;
          _ordersErrorMessage = null;
        });
        return;
      }

      final responses = await Future.wait([
        _ordersService.getOrders(status: 'active'),
        _ordersService.getOrders(status: 'pending'),
        _ordersService.getOrders(status: 'completed'),
        _ordersService.getOrders(status: 'cancelled'),
      ]);
      final activeRes = responses[0];
      final pendingRes = responses[1];
      final completedRes = responses[2];
      final cancelledRes = responses[3];

      String? errorMessage;
      if (!activeRes.success &&
          activeRes.message != null &&
          activeRes.message!.trim().isNotEmpty) {
        errorMessage = activeRes.message!.trim();
      } else if (!pendingRes.success &&
          pendingRes.message != null &&
          pendingRes.message!.trim().isNotEmpty) {
        errorMessage = pendingRes.message!.trim();
      } else if (!completedRes.success &&
          completedRes.message != null &&
          completedRes.message!.trim().isNotEmpty) {
        errorMessage = completedRes.message!.trim();
      } else if (!cancelledRes.success &&
          cancelledRes.message != null &&
          cancelledRes.message!.trim().isNotEmpty) {
        errorMessage = cancelledRes.message!.trim();
      }

      if (mounted) {
        setState(() {
          _activeOrders = activeRes.success
              ? _filterVisibleOrders(_asList(activeRes.data))
              : [];
          _pendingOrders = pendingRes.success
              ? _filterVisibleOrders(_asList(pendingRes.data))
              : [];
          _completedOrders = completedRes.success
              ? _filterVisibleOrders(_asList(completedRes.data))
              : [];
          _cancelledOrders = cancelledRes.success
              ? _filterVisibleOrders(_asList(cancelledRes.data))
              : [];
          _isLoading = false;
          _ordersErrorMessage = errorMessage;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _ordersErrorMessage = context.tr('provider_orders_load_failed');
        });
      }
    }
  }

  Future<void> _toggleAvailability(bool value) async {
    if (_isAvailabilityUpdating) return;
    final authProvider = context.read<AuthProvider>();
    if (!authProvider.isApprovedProvider) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('provider_account_pending_approval')),
        ),
      );
      return;
    }

    setState(() => _isAvailabilityUpdating = true);
    final response = await authProvider.setAvailability(value);

    if (!mounted) return;
    setState(() => _isAvailabilityUpdating = false);

    if (!response.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            response.message ??
                context.tr('provider_availability_update_failed'),
          ),
        ),
      );
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          value
              ? context.tr('provider_available_now')
              : context.tr('provider_unavailable_now'),
        ),
      ),
    );

    if (value) {
      _loadOrders();
    }
  }

  Future<void> _refreshDashboard() async {
    final authProvider = context.read<AuthProvider>();
    await authProvider.refreshUser();
    if (!mounted) return;
    await _loadOrders();
  }

  Future<void> _openProfileEditor() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ProviderProfileSetupScreen(
          onComplete: () => Navigator.of(context).pop(),
        ),
      ),
    );
    if (!mounted) return;
    await _refreshDashboard();
  }

  Future<void> _openExternalUrl(String url) async {
    final uri = Uri.tryParse(url);
    if (uri == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('order_action_failed_default'))),
      );
      return;
    }
    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('order_action_failed_default'))),
      );
    }
  }

  void _showAccountActions() {
    showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.privacy_tip_outlined),
              title: Text(context.tr('privacy_policy')),
              onTap: () {
                Navigator.pop(sheetContext);
                _openExternalUrl(AppConfig.privacyUrl);
              },
            ),
            ListTile(
              leading: const Icon(Icons.description_outlined),
              title: Text(context.tr('terms_and_conditions')),
              onTap: () {
                Navigator.pop(sheetContext);
                _openExternalUrl(AppConfig.termsUrl);
              },
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: Colors.red),
              title: Text(
                context.tr('delete_account'),
                style: const TextStyle(color: Colors.red),
              ),
              onTap: _isDeletingAccount
                  ? null
                  : () {
                      Navigator.pop(sheetContext);
                      _confirmDeleteAccount();
                    },
            ),
            ListTile(
              leading: const Icon(Icons.logout),
              title: Text(context.tr('logout')),
              onTap: () async {
                Navigator.pop(sheetContext);
                await context.read<AuthProvider>().logout();
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmDeleteAccount() async {
    final controller = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.tr('delete_account_title')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(context.tr('delete_account_body')),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              maxLines: 2,
              decoration: InputDecoration(
                hintText: context.tr('delete_account_reason_hint'),
                border: const OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.tr('cancel')),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(
              context.tr('delete_account_confirm'),
              style: const TextStyle(color: Colors.red),
            ),
          ),
        ],
      ),
    );

    if (confirmed != true || _isDeletingAccount) {
      return;
    }

    setState(() => _isDeletingAccount = true);
    final response = await _providersService.deleteAccount(
      reason: controller.text.trim(),
    );
    if (!mounted) return;
    setState(() => _isDeletingAccount = false);

    if (response.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('delete_account_success'))),
      );
      await context.read<AuthProvider>().logout();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            response.message ?? context.tr('delete_account_failed'),
          ),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _changeLanguage(String code) async {
    Locale locale;
    switch (code) {
      case 'en':
        locale = const Locale('en', 'US');
        break;
      case 'ur':
        locale = const Locale('ur', 'PK');
        break;
      case 'ar':
      default:
        locale = const Locale('ar', 'SA');
        break;
    }
    await context.read<LocalizationProvider>().setLocale(locale);
  }

  Widget _buildApprovalBanner(AuthProvider authProvider) {
    final status = authProvider.providerStatus;
    if (status == 'approved' || status.isEmpty) {
      return const SizedBox.shrink();
    }

    final Color tone = status == 'pending'
        ? AppColors.warning
        : AppColors.error;
    final String message = status == 'pending'
        ? context.tr('provider_account_pending_approval')
        : context.tr('provider_account_not_active');

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: tone.withValues(alpha: 0.25)),
      ),
      child: Text(
        message,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(color: tone),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    final isAvailable = authProvider.isAvailable;
    final isApprovedProvider = authProvider.isApprovedProvider;
    final currentLanguage = context
        .watch<LocalizationProvider>()
        .locale
        .languageCode;
    final currentLangData = _languages.firstWhere(
      (lang) => lang['code'] == currentLanguage,
      orElse: () => _languages.first,
    );

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('my_orders')),
        actions: [
          PopupMenuButton<String>(
            onSelected: _changeLanguage,
            itemBuilder: (_) => _languages
                .map(
                  (lang) => PopupMenuItem<String>(
                    value: lang['code'],
                    child: Text('${lang['flag']} ${lang['name']}'),
                  ),
                )
                .toList(),
            icon: Text(
              currentLangData['flag'] ?? '🌐',
              style: const TextStyle(fontSize: 18),
            ),
            tooltip: context.tr('language'),
          ),
          IconButton(
            onPressed: _openProfileEditor,
            icon: const Icon(Icons.person_outline),
            tooltip: context.tr('my_profile'),
          ),
          IconButton(
            onPressed: _showAccountActions,
            icon: const Icon(Icons.more_vert),
            tooltip: context.tr('account_actions'),
          ),
          IconButton(
            onPressed: _isLoading ? null : _refreshDashboard,
            icon: const Icon(Icons.refresh),
            tooltip: context.tr('refresh'),
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          tabs: [
            Tab(text: context.tr('provider_tab_active')),
            Tab(text: context.tr('provider_tab_new')),
            Tab(text: context.tr('provider_tab_completed')),
            Tab(text: context.tr('provider_tab_cancelled')),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Container(
                  margin: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: AppColors.gray50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.gray200),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        isAvailable
                            ? Icons.check_circle_outline
                            : Icons.pause_circle_outline,
                        color: isAvailable
                            ? AppColors.success
                            : AppColors.gray500,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              context.tr('provider_availability_status'),
                              style: Theme.of(context).textTheme.titleSmall,
                            ),
                            const SizedBox(height: 2),
                            Text(
                              isAvailable
                                  ? context.tr('provider_available_for_orders')
                                  : context.tr('provider_not_available'),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                      Switch(
                        value: isAvailable,
                        onChanged:
                            _isAvailabilityUpdating || !isApprovedProvider
                            ? null
                            : _toggleAvailability,
                      ),
                    ],
                  ),
                ),
                _buildProviderSummary(authProvider),
                _buildApprovalBanner(authProvider),
                if (_ordersErrorMessage != null &&
                    _ordersErrorMessage!.trim().isNotEmpty)
                  Container(
                    width: double.infinity,
                    margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.warning.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: AppColors.warning.withValues(alpha: 0.25),
                      ),
                    ),
                    child: Text(
                      _ordersErrorMessage!,
                      style: Theme.of(
                        context,
                      ).textTheme.bodySmall?.copyWith(color: AppColors.warning),
                    ),
                  ),
                Expanded(
                  child: TabBarView(
                    controller: _tabController,
                    children: [
                      _buildOrderList(_activeOrders),
                      _buildOrderList(_pendingOrders),
                      _buildOrderList(_completedOrders),
                      _buildOrderList(_cancelledOrders),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildProviderSummary(AuthProvider authProvider) {
    final profile = authProvider.providerProfile ?? <String, dynamic>{};
    final fullName = (profile['full_name'] ?? '').toString().trim();
    final rating = double.tryParse(profile['rating']?.toString() ?? '0') ?? 0;
    final wallet =
        double.tryParse(profile['wallet_balance']?.toString() ?? '0') ?? 0;
    final avatarRaw = (profile['avatar'] ?? '').toString().trim();
    final avatarUrl =
        avatarRaw.isNotEmpty &&
            avatarRaw.toLowerCase() != 'null' &&
            avatarRaw.toLowerCase() != 'undefined'
        ? AppConfig.fixMediaUrl(avatarRaw)
        : null;
    final categoriesCount = _extractCategoryIds(profile['category_ids']).length;
    final profileCompleted =
        profile['profile_completed'] == true ||
        profile['profile_completed'] == 1;

    Widget statItem({
      required IconData icon,
      required String label,
      String? value,
      Widget? valueWidget,
      Color color = AppColors.gray700,
    }) {
      return Expanded(
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: AppColors.gray200),
          ),
          child: Column(
            children: [
              Icon(icon, size: 18, color: color),
              const SizedBox(height: 4),
              DefaultTextStyle(
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: color,
                  fontSize: 13,
                ),
                child:
                    valueWidget ??
                    Text(
                      value ?? '',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        color: color,
                        fontSize: 13,
                      ),
                    ),
              ),
              const SizedBox(height: 2),
              Text(
                label,
                style: const TextStyle(fontSize: 11, color: AppColors.gray500),
              ),
            ],
          ),
        ),
      );
    }

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.gray50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.gray200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: AppColors.primary.withValues(alpha: 0.1),
                backgroundImage: avatarUrl != null && avatarUrl.isNotEmpty
                    ? NetworkImage(avatarUrl)
                    : null,
                child: avatarUrl == null || avatarUrl.isEmpty
                    ? const Icon(
                        Icons.person_outline,
                        color: AppColors.primaryDark,
                        size: 18,
                      )
                    : null,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  fullName.isNotEmpty
                      ? fullName
                      : context.tr('service_provider'),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: AppColors.gray800,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: profileCompleted
                      ? AppColors.success.withValues(alpha: 0.12)
                      : Colors.orange.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  profileCompleted
                      ? context.tr('provider_profile_complete')
                      : context.tr('provider_profile_incomplete_required'),
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: profileCompleted ? AppColors.success : Colors.orange,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              statItem(
                icon: Icons.star_outline,
                label: context.tr('rating'),
                value: rating.toStringAsFixed(1),
                color: Colors.amber.shade700,
              ),
              const SizedBox(width: 8),
              statItem(
                icon: Icons.account_balance_wallet_outlined,
                label: context.tr('wallet'),
                valueWidget: SaudiRiyalText(
                  text: wallet.toStringAsFixed(0),
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                    color: AppColors.primaryDark,
                  ),
                  iconSize: 12,
                ),
                color: AppColors.primaryDark,
              ),
              const SizedBox(width: 8),
              statItem(
                icon: Icons.category_outlined,
                label: context.tr('provider_specialties'),
                value: '$categoriesCount',
                color: AppColors.gray700,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${context.tr('provider_new_orders_count')}: ${_pendingOrders.length}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.gray600,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Expanded(
                child: Text(
                  '${context.tr('provider_active_orders_count')}: ${_activeOrders.length}',
                  textAlign: TextAlign.end,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.gray600,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${context.tr('provider_completed_orders_count')}: ${_completedOrders.length}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.gray600,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Expanded(
                child: Text(
                  '${context.tr('provider_cancelled_orders_count')}: ${_cancelledOrders.length}',
                  textAlign: TextAlign.end,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.gray600,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildOrderList(List<dynamic> orders) {
    if (orders.isEmpty) {
      return Center(child: Text(context.tr('no_orders_here')));
    }
    return ListView.builder(
      itemCount: orders.length,
      padding: const EdgeInsets.all(16),
      itemBuilder: (context, index) {
        final order = orders[index];
        return Card(
          margin: const EdgeInsets.only(bottom: 16),
          child: ListTile(
            title: Text(
              order['category_name'] ?? context.tr('unknown_service'),
            ),
            subtitle: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${context.tr('order_number')}: ${order['order_number']}'),
                Text('${context.tr('address')}: ${order['address'] ?? '-'}'),
                if (order['scheduled_date'] != null)
                  Text(
                    '${context.tr('expected_date')}: ${order['scheduled_date']} ${order['scheduled_time']}',
                  ),
              ],
            ),
            trailing: _getStatusChip('${order['status'] ?? ''}'),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => OrderDetailsScreen(orderId: order['id']),
                ),
              ).then((_) => _loadOrders());
            },
          ),
        );
      },
    );
  }

  Widget _getStatusChip(String status) {
    Color color = Colors.grey;
    String text = status;

    switch (status) {
      case 'pending':
        color = Colors.orange;
        text = context.tr('status_pending');
        break;
      case 'assigned':
        color = Colors.blue;
        text = context.tr('provider_status_assigned');
        break;
      case 'accepted':
        color = Colors.teal;
        text = context.tr('status_accepted');
        break;
      case 'on_the_way':
        color = Colors.indigo;
        text = context.tr('provider_status_on_the_way');
        break;
      case 'arrived':
        color = Colors.blueGrey;
        text = context.tr('status_arrived');
        break;
      case 'in_progress':
        color = Colors.purple;
        text = context.tr('status_in_progress');
        break;
      case 'completed':
        color = Colors.green;
        text = context.tr('status_completed');
        break;
      case 'cancelled':
        color = Colors.red;
        text = context.tr('status_cancelled');
        break;
    }

    return Chip(
      label: Text(
        text,
        style: const TextStyle(color: Colors.white, fontSize: 10),
      ),
      backgroundColor: color,
    );
  }
}
