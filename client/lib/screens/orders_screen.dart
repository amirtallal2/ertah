// Orders Screen
// شاشة الطلبات

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:intl/intl.dart';
import '../config/app_theme.dart';
import '../models/service_category_model.dart';
import 'service_rating_screen.dart';
import 'order_tracking_screen.dart';
import '../services/services.dart';
import '../config/app_config.dart';
import '../services/app_localizations.dart';
import '../utils/saudi_riyal_icon.dart';
import '../services/furniture_requests_service.dart';

class OrdersScreen extends StatefulWidget {
  final VoidCallback? onGoHome;
  final bool isActiveTab;

  const OrdersScreen({super.key, this.onGoHome, this.isActiveTab = true});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  late TabController _tabController;
  final OrdersService _ordersService = OrdersService();
  final FurnitureRequestsService _furnitureRequestsService =
      FurnitureRequestsService();

  bool _isLoading = true;
  List<dynamic> _activeOrders = [];
  List<dynamic> _pastOrders = [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _tabController = TabController(length: 2, vsync: this);
    _fetchOrders();
  }

  @override
  void didUpdateWidget(covariant OrdersScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!oldWidget.isActiveTab && widget.isActiveTab) {
      _fetchOrders();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && widget.isActiveTab) {
      _fetchOrders();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchOrders() async {
    if (!mounted) return;
    setState(() => _isLoading = true);
    try {
      final furnitureResponse = await _furnitureRequestsService.getMyRequests();
      final response = await _ordersService.getOrders();
      if (!mounted) return;
      final mergedOrders = <Map<String, dynamic>>[];

      if (response.success && response.data is List) {
        mergedOrders.addAll(
          (response.data as List).whereType<Map>().map(
            (item) => Map<String, dynamic>.from(
              item.map((key, value) => MapEntry(key.toString(), value)),
            ),
          ),
        );
      }

      final existingOrderIds = <int>{};
      for (final order in mergedOrders) {
        final orderId = int.tryParse('${order['id'] ?? 0}') ?? 0;
        if (orderId > 0) {
          existingOrderIds.add(orderId);
        }
      }

      if (furnitureResponse.success && furnitureResponse.data is List) {
        final furnitureRows = (furnitureResponse.data as List)
            .whereType<Map>()
            .map(
              (item) => Map<String, dynamic>.from(
                item.map((key, value) => MapEntry(key.toString(), value)),
              ),
            );

        for (final item in furnitureRows) {
          final mapped = _mapFurnitureRequestToOrder(
            item,
            existingOrderIds: existingOrderIds,
          );
          if (mapped == null) continue;
          mergedOrders.add(mapped);

          final mappedOrderId = int.tryParse('${mapped['id'] ?? 0}') ?? 0;
          if (mappedOrderId > 0) {
            existingOrderIds.add(mappedOrderId);
          }
        }
      }

      mergedOrders.sort((a, b) {
        final bDate = DateTime.tryParse('${b['created_at'] ?? ''}');
        final aDate = DateTime.tryParse('${a['created_at'] ?? ''}');
        if (aDate != null && bDate != null) {
          final byDate = bDate.compareTo(aDate);
          if (byDate != 0) return byDate;
        }
        final bId = int.tryParse('${b['id'] ?? 0}') ?? 0;
        final aId = int.tryParse('${a['id'] ?? 0}') ?? 0;
        return bId.compareTo(aId);
      });

      const activeStatuses = {
        'pending',
        'assigned',
        'accepted',
        'on_the_way',
        'arrived',
        'in_progress',
      };

      setState(() {
        _activeOrders = mergedOrders
            .where(
              (order) => activeStatuses.contains(
                (order['status'] ?? '').toString().toLowerCase(),
              ),
            )
            .toList();

        _pastOrders = mergedOrders
            .where(
              (order) => !activeStatuses.contains(
                (order['status'] ?? '').toString().toLowerCase(),
              ),
            )
            .toList();
        _isLoading = false;
      });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: Text(
          context.tr('my_orders'),
          style: const TextStyle(
            color: AppColors.gray800,
            fontWeight: FontWeight.bold,
          ),
        ),
        centerTitle: true,
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.gray500,
          indicatorColor: AppColors.primary,
          indicatorWeight: 3,
          labelStyle: const TextStyle(
            fontWeight: FontWeight.bold,
            fontFamily: 'Cairo',
          ),
          tabs: [
            Tab(text: context.tr('current')),
            Tab(text: context.tr('past')),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : TabBarView(
              controller: _tabController,
              children: [
                RefreshIndicator(
                  onRefresh: _fetchOrders,
                  child: _buildOrdersList(_activeOrders, isActive: true),
                ),
                RefreshIndicator(
                  onRefresh: _fetchOrders,
                  child: _buildOrdersList(_pastOrders, isActive: false),
                ),
              ],
            ),
    );
  }

  Widget _buildOrdersList(List<dynamic> orders, {required bool isActive}) {
    if (orders.isEmpty) {
      return _buildEmptyState();
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: orders.length,
      separatorBuilder: (_, __) => const SizedBox(height: 16),
      itemBuilder: (context, index) {
        final order = Map<String, dynamic>.from(
          (orders[index] as Map).map(
            (key, value) => MapEntry(key.toString(), value),
          ),
        );
        return _buildOrderCard(order, isActive: isActive, index: index);
      },
    );
  }

  Widget _buildOrderCard(
    Map<String, dynamic> order, {
    required bool isActive,
    required int index,
  }) {
    final serviceTitle = _resolveOrderServiceTitle(order);
    final status = (order['status'] ?? '').toString().toLowerCase();
    final statusColor = _getStatusColor(status);
    final paidAmount = _paidAmount(order);
    final trackingOrderId = _resolveTrackingOrderId(order);
    final isVirtualSpecialOrder = trackingOrderId <= 0;
    return GestureDetector(
      onTap: () async {
        if (isVirtualSpecialOrder) {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                'يتم الآن تجهيز الطلب للمتابعة، جرب التحديث بعد قليل',
              ),
            ),
          );
          return;
        }
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => OrderTrackingScreen(
              service: ServiceCategoryModel(
                id: int.tryParse('${order['category_id'] ?? 0}') ?? 0,
                nameAr: serviceTitle,
                nameEn: serviceTitle,
                icon: (order['category_icon'] ?? '').toString(),
                createdAt: DateTime.now(),
              ),
              orderId: trackingOrderId.toString(),
              onBackToHome: () {
                Navigator.pop(context);
                if (widget.onGoHome != null) widget.onGoHome!();
              },
            ),
          ),
        );
        if (!mounted) return;
        _fetchOrders();
      },
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: AppShadows.sm,
        ),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(
                    color: AppColors.gray50,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  padding: const EdgeInsets.all(8),
                  child: order['category_icon'] != null
                      ? CachedNetworkImage(
                          imageUrl: AppConfig.fixMediaUrl(
                            order['category_icon'],
                          ),
                          fit: BoxFit.contain,
                        )
                      : const Icon(Icons.build, color: AppColors.primary),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        serviceTitle,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '#${_displayOrderReference(order)} • ${order['provider_name'] ?? context.tr('searching_provider')}',
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppColors.gray500,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    _getStatusText(status),
                    style: TextStyle(
                      color: statusColor,
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Divider(height: 1),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 4,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.access_time,
                            size: 14,
                            color: AppColors.gray500,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            _formatOrderDate(order['created_at']),
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppColors.gray600,
                            ),
                          ),
                        ],
                      ),
                      if (paidAmount != null)
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.receipt_long_outlined,
                              size: 13,
                              color: Colors.green,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '${context.tr('order_tracking_invoice_paid')}: ${paidAmount.toStringAsFixed(2)} ⃁',
                              style: const TextStyle(
                                fontSize: 10,
                                color: Colors.green,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                SaudiRiyalText(
                  text: '${order['total_amount'] ?? 0}',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                    color: AppColors.primary,
                  ),
                  iconSize: 14,
                ),
              ],
            ),

            if (!isActive && status == 'completed' && order['is_rated'] != true)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => ServiceRatingScreen(
                            service: ServiceCategoryModel(
                              id: 0,
                              nameAr: serviceTitle,
                              nameEn: '',
                              icon: order['category_icon'] ?? '',
                              createdAt: DateTime.now(),
                            ),
                            providerName:
                                order['provider_name'] ??
                                context.tr(
                                  'service_provider',
                                ), // Assuming this key exists or create one
                            orderNumber: order['id'].toString(),
                            onSubmit: () {
                              if (!context.mounted) return;
                              Navigator.pop(context, true);
                            },
                          ),
                        ),
                      ).then((_) {
                        if (!mounted) return;
                        _fetchOrders();
                      });
                    },
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFFFBCC26),
                      side: const BorderSide(color: Color(0xFFFBCC26)),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: Text(context.tr('rate_service')),
                  ),
                ),
              ),
          ],
        ),
      ),
    ).animate().fadeIn(delay: (index * 100).ms).slideY(begin: 0.1, end: 0);
  }

  String _resolveOrderServiceTitle(Map<String, dynamic> order) {
    final fromApi =
        (order['display_service_name'] ??
                order['service_name'] ??
                order['category_name'])
            .toString()
            .trim();
    if (fromApi.isNotEmpty) {
      return fromApi;
    }

    final serviceItems = order['service_items'];
    if (serviceItems is List) {
      for (final item in serviceItems) {
        if (item is! Map) continue;
        final mapped = Map<String, dynamic>.from(
          item.map((key, value) => MapEntry(key.toString(), value)),
        );
        final name = (mapped['service_name'] ?? '').toString().trim();
        if (name.isNotEmpty) {
          return name;
        }
      }
    }

    if ((order['special_module'] ?? '').toString() == 'furniture') {
      return _furnitureRequestFallbackTitle();
    }

    return context.tr('unknown_service');
  }

  int _resolveTrackingOrderId(Map<String, dynamic> order) {
    final sourceOrderId = int.tryParse('${order['source_order_id'] ?? 0}') ?? 0;
    if (sourceOrderId > 0) {
      return sourceOrderId;
    }
    return int.tryParse('${order['id'] ?? 0}') ?? 0;
  }

  String _displayOrderReference(Map<String, dynamic> order) {
    final requestNumber = (order['special_request_number'] ?? '')
        .toString()
        .trim();
    if (requestNumber.isNotEmpty) {
      return requestNumber;
    }
    final orderId = _resolveTrackingOrderId(order);
    if (orderId > 0) {
      return '$orderId';
    }
    return (order['id'] ?? '-').toString();
  }

  Map<String, dynamic>? _mapFurnitureRequestToOrder(
    Map<String, dynamic> item, {
    required Set<int> existingOrderIds,
  }) {
    final requestId = int.tryParse('${item['id'] ?? 0}') ?? 0;
    if (requestId <= 0) return null;

    final sourceOrderId = int.tryParse('${item['source_order_id'] ?? 0}') ?? 0;
    if (sourceOrderId > 0 && existingOrderIds.contains(sourceOrderId)) {
      return null;
    }

    final requestStatus = (item['status'] ?? '')
        .toString()
        .trim()
        .toLowerCase();
    final mappedStatus = switch (requestStatus) {
      'new' => 'pending',
      'confirmed' => 'accepted',
      'in_progress' => 'in_progress',
      'completed' => 'completed',
      'cancelled' => 'cancelled',
      'rejected' => 'rejected',
      _ => 'pending',
    };

    final fallbackId = sourceOrderId > 0 ? sourceOrderId : -requestId;
    final serviceName = (item['service_name'] ?? '').toString().trim();
    final totalAmountCandidate = _toPositiveAmount(item['final_price']) > 0
        ? _toPositiveAmount(item['final_price'])
        : _toPositiveAmount(item['estimated_price']);

    return {
      'id': fallbackId,
      'source_order_id': sourceOrderId > 0 ? sourceOrderId : null,
      'special_request_number': (item['request_number'] ?? '').toString(),
      'special_module': 'furniture',
      'is_virtual_special_order': sourceOrderId <= 0,
      'display_service_name': serviceName.isNotEmpty
          ? serviceName
          : _furnitureRequestFallbackTitle(),
      'category_name': _furnitureRequestFallbackTitle(),
      'provider_name': (item['provider_name'] ?? '').toString().trim(),
      'status': mappedStatus,
      'created_at': item['created_at'],
      'total_amount': totalAmountCandidate,
      'payment_status': (item['payment_status'] ?? '').toString(),
      'is_rated': false,
    };
  }

  double _toPositiveAmount(dynamic value) {
    final parsed = double.tryParse('${value ?? ''}') ?? 0;
    return parsed > 0 ? parsed : 0;
  }

  String _furnitureRequestFallbackTitle() {
    final languageCode = Localizations.localeOf(context).languageCode;
    return switch (languageCode) {
      'en' => 'Furniture Moving',
      'ur' => 'فرنیچر کی منتقلی',
      _ => 'نقل العفش',
    };
  }

  String _getStatusText(String status) {
    switch (status) {
      case 'pending':
        return context.tr('status_pending');
      case 'assigned':
        return context.tr('provider_status_assigned');
      case 'accepted':
        return context.tr('status_accepted');
      case 'on_the_way':
        return context.tr('provider_status_on_the_way');
      case 'arrived':
        return context.tr('status_arrived');
      case 'in_progress':
        return context.tr('status_in_progress');
      case 'completed':
        return context.tr('status_completed');
      case 'cancelled':
        return context.tr('status_cancelled');
      case 'rejected':
        return context.tr('status_rejected');
      default:
        return status.replaceAll('_', ' ');
    }
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'completed':
        return Colors.green;
      case 'cancelled':
      case 'rejected':
        return Colors.red;
      case 'in_progress':
      case 'arrived':
      case 'on_the_way':
        return Colors.blue;
      case 'accepted':
      case 'assigned':
        return Colors.indigo;
      default:
        return Colors.orange;
    }
  }

  String _formatOrderDate(dynamic rawDate) {
    final value = rawDate?.toString() ?? '';
    if (value.isEmpty) return '-';

    final parsed = DateTime.tryParse(value);
    if (parsed == null) return value;

    final localeCode = Localizations.localeOf(context).languageCode;
    return DateFormat('d MMM yyyy - hh:mm a', localeCode).format(parsed);
  }

  double? _paidAmount(Map<String, dynamic> order) {
    final paymentStatus = (order['payment_status'] ?? '')
        .toString()
        .trim()
        .toLowerCase();
    if (paymentStatus != 'paid') {
      return null;
    }

    final candidates = [
      order['paid_amount'],
      order['paid_total'],
      order['invoice_paid_amount'],
      order['invoice_total'],
      order['invoice_amount'],
      order['final_amount'],
      order['total_amount'],
    ];

    for (final candidate in candidates) {
      final amount = double.tryParse('${candidate ?? ''}') ?? 0;
      if (amount > 0) {
        return amount;
      }
    }

    return null;
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.receipt_long_outlined, size: 64, color: Colors.grey[300]),
          const SizedBox(height: 16),
          Text(
            context.tr('no_orders_here'),
            style: const TextStyle(
              color: AppColors.gray500,
              fontWeight: FontWeight.bold,
            ),
          ),
          if (widget.onGoHome != null) ...[
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: widget.onGoHome,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                ),
              ),
              child: Text(context.tr('browse_services')),
            ),
          ],
        ],
      ),
    );
  }
}
