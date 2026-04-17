// Order Confirmation Screen
// شاشة تأكيد الطلب

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config/app_theme.dart';
import '../config/app_config.dart';
import '../services/app_localizations.dart';

class OrderConfirmationScreen extends StatefulWidget {
  final Map<String, dynamic> service;
  final Map<String, dynamic> provider;
  final String inspectionType;
  final String orderNumber;
  final VoidCallback onViewOrder;
  final VoidCallback onBackToHome;

  const OrderConfirmationScreen({
    super.key,
    required this.service,
    required this.provider,
    required this.inspectionType,
    required this.orderNumber,
    required this.onViewOrder,
    required this.onBackToHome,
  });

  @override
  State<OrderConfirmationScreen> createState() =>
      _OrderConfirmationScreenState();
}

class _OrderConfirmationScreenState extends State<OrderConfirmationScreen> {
  final String _orderStatus =
      'confirmed'; // pending, confirmed, in-progress, completed

  List<Map<String, dynamic>> get _statusSteps => [
    {
      'id': 'pending',
      'title': context.tr('pending_title'),
      'description': context.tr('pending_desc'),
      'icon': '📝',
      'completed': true,
    },
    {
      'id': 'confirmed',
      'title': context.tr('confirmed_title'),
      'description': context.tr('confirmed_desc'),
      'icon': '✅',
      'completed':
          _orderStatus == 'confirmed' ||
          _orderStatus == 'in-progress' ||
          _orderStatus == 'completed',
    },
    {
      'id': 'in-progress',
      'title': context.tr('in_progress_title'),
      'description': context.tr('in_progress_desc'),
      'icon': '🔧',
      'completed': _orderStatus == 'in-progress' || _orderStatus == 'completed',
    },
    {
      'id': 'completed',
      'title': context.tr('completed_title'),
      'description': context.tr('completed_desc'),
      'icon': '🎉',
      'completed': _orderStatus == 'completed',
    },
  ];

  Color get _statusColor {
    switch (_orderStatus) {
      case 'pending':
        return Colors.amber;
      case 'confirmed':
        return Colors.blue;
      case 'in-progress':
        return Colors.orange;
      case 'completed':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }

  String get _statusText {
    switch (_orderStatus) {
      case 'pending':
        return context.tr('waiting_for_confirmation');
      case 'confirmed':
        return context.tr('status_confirmed'); // or just 'confirmed' if mapped
      case 'in-progress':
        return context.tr('status_in_progress');
      case 'completed':
        return context.tr('status_completed');
      default:
        return '';
    }
  }

  String _digitsOnly(String value) => value.replaceAll(RegExp(r'[^\d+]'), '');

  Future<void> _callNumber(String rawPhone) async {
    final phone = _digitsOnly(rawPhone);
    if (phone.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('phone_not_available'))),
      );
      return;
    }

    final uri = Uri.parse('tel:$phone');
    final opened = await launchUrl(uri);
    if (!opened && mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('open_dialer_failed'))));
    }
  }

  Future<void> _callProvider() async {
    final providerPhone =
        (widget.provider['phone'] ??
                widget.provider['provider_phone'] ??
                AppConfig.supportPhone)
            .toString();
    await _callNumber(providerPhone);
  }

  Future<void> _contactSupport() async {
    await _callNumber(AppConfig.supportPhone);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Column(
        children: [
          // Header
          Container(
            padding: EdgeInsets.only(
              top: MediaQuery.of(context).padding.top + 12,
              left: 16,
              right: 16,
              bottom: 12,
            ),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: AppShadows.sm,
            ),
            child: Row(
              children: [
                InkWell(
                  onTap: widget.onBackToHome,
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: AppColors.gray100,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.arrow_back,
                      color: AppColors.gray700,
                      size: 20,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  context.tr('track_order'),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),

          // Content
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  // Order Number & Status
                  _buildOrderStatusCard().animate().fadeIn().slideY(begin: 0.1),

                  const SizedBox(height: 16),

                  // Order Timeline
                  _buildTimelineCard()
                      .animate()
                      .fadeIn(delay: 200.ms)
                      .slideY(begin: 0.1),

                  const SizedBox(height: 16),

                  // Inspection Details
                  _buildDetailsCard()
                      .animate()
                      .fadeIn(delay: 400.ms)
                      .slideY(begin: 0.1),

                  const SizedBox(height: 16),

                  // Info Message
                  _buildInfoMessage().animate().fadeIn(delay: 600.ms),

                  // Rate Service if completed
                  if (_orderStatus == 'completed') ...[
                    const SizedBox(height: 16),
                    _buildRatingCard().animate().fadeIn(delay: 800.ms).scale(),
                  ],

                  const SizedBox(height: 80),
                ],
              ),
            ),
          ),

          // Action Buttons
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: AppColors.gray100)),
            ),
            child: SafeArea(
              top: false,
              child: Row(
                children: [
                  Expanded(
                    child: ElevatedButton(
                      onPressed: widget.onBackToHome,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(24),
                        ),
                        elevation: 4,
                      ),
                      child: Text(
                        context.tr('back_to_home'),
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  OutlinedButton.icon(
                    onPressed: _contactSupport,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.primary,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 16,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(24),
                      ),
                      side: BorderSide(
                        color: AppColors.primary.withValues(alpha: 0.3),
                      ),
                    ),
                    icon: const Icon(Icons.support_agent, size: 20),
                    label: Text(context.tr('support')),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderStatusCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.tr('order_number'),
                    style: const TextStyle(
                      color: AppColors.gray500,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '#${widget.orderNumber}',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontFamily: 'monospace',
                    ),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [_statusColor, _statusColor.withValues(alpha: 0.8)],
                  ),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  _statusText,
                  style: TextStyle(color: Colors.white, fontSize: 12),
                ),
              ),
            ],
          ),

          const SizedBox(height: 16),
          const Divider(),
          const SizedBox(height: 16),

          // Service Info
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [Colors.orange, Colors.amber],
                  ),
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: AppShadows.md,
                ),
                child: Center(
                  child: Text(
                    widget.service['icon'] ?? '🔧',
                    style: TextStyle(fontSize: 24),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('service'),
                      style: const TextStyle(
                        color: AppColors.gray500,
                        fontSize: 12,
                      ),
                    ),
                    Text(
                      widget.service['name'] ?? context.tr('service'),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          // Provider Info
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(Icons.person, color: Colors.blue, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('service_provider'),
                      style: const TextStyle(
                        color: AppColors.gray500,
                        fontSize: 12,
                      ),
                    ),
                    Text(
                      widget.provider['name'] ?? context.tr('technician'),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    Row(
                      children: [
                        Icon(Icons.star, color: Colors.amber, size: 14),
                        const SizedBox(width: 4),
                        Text(
                          '${widget.provider['rating'] ?? 4.8}',
                          style: TextStyle(
                            color: AppColors.gray600,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              ElevatedButton.icon(
                onPressed: _callProvider,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                  ),
                ),
                icon: const Icon(Icons.phone, size: 14),
                label: Text(
                  context.tr('call'),
                  style: const TextStyle(fontSize: 12),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTimelineCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.access_time, color: Colors.orange, size: 20),
              const SizedBox(width: 8),
              Text(
                context.tr('track_order_status'),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ],
          ),
          const SizedBox(height: 16),
          ...List.generate(_statusSteps.length, (index) {
            final step = _statusSteps[index];
            final isLast = index == _statusSteps.length - 1;
            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Column(
                  children: [
                    Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        gradient: step['completed']
                            ? LinearGradient(
                                colors: [Colors.orange, Colors.amber],
                              )
                            : null,
                        color: step['completed'] ? null : AppColors.gray200,
                        shape: BoxShape.circle,
                        boxShadow: step['completed'] ? AppShadows.md : null,
                      ),
                      child: Center(
                        child: step['completed']
                            ? Icon(Icons.check, color: Colors.white, size: 20)
                            : Container(
                                width: 12,
                                height: 12,
                                decoration: BoxDecoration(
                                  color: AppColors.gray400,
                                  shape: BoxShape.circle,
                                ),
                              ),
                      ),
                    ),
                    if (!isLast)
                      Container(
                        width: 2,
                        height: 40,
                        color: step['completed']
                            ? Colors.orange
                            : AppColors.gray200,
                      ),
                  ],
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 24),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          step['title'],
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: step['completed']
                                ? AppColors.gray800
                                : AppColors.gray400,
                          ),
                        ),
                        Text(
                          step['description'],
                          style: TextStyle(
                            fontSize: 12,
                            color: step['completed']
                                ? AppColors.gray600
                                : AppColors.gray400,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            );
          }),
        ],
      ),
    );
  }

  Widget _buildDetailsCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.tr('service_details'),
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: Colors.purple.shade50,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  widget.inspectionType == 'home'
                      ? Icons.location_on
                      : Icons.phone,
                  color: Colors.purple,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('inspection_type_lbl'),
                      style: const TextStyle(
                        color: AppColors.gray500,
                        fontSize: 12,
                      ),
                    ),
                    Text(
                      widget.inspectionType == 'home'
                          ? context.tr('home_inspection')
                          : context.tr('online_inspection'),
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  Icons.calendar_today,
                  color: Colors.green,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('expected_date'),
                      style: const TextStyle(
                        color: AppColors.gray500,
                        fontSize: 12,
                      ),
                    ),
                    Text(
                      context.tr('within_24_hours'),
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildInfoMessage() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.blue.shade50,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.blue.shade200),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: Colors.blue,
              shape: BoxShape.circle,
            ),
            child: Icon(Icons.message, color: Colors.white, size: 16),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('contact_provider'),
                  style: TextStyle(
                    color: Colors.blue.shade800,
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  context.tr('contact_provider_desc'),
                  style: TextStyle(
                    color: Colors.blue.shade600,
                    fontSize: 12,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRatingCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFFFF7ED), Color(0xFFFEF3C7)],
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.orange.shade200),
      ),
      child: Column(
        children: [
          Text('⭐', style: TextStyle(fontSize: 40)),
          const SizedBox(height: 8),
          Text(
            context.tr('rate_experience'),
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 4),
          Text(
            context.tr('share_opinion'),
            style: const TextStyle(color: AppColors.gray600, fontSize: 12),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: widget.onViewOrder,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(24),
                ),
              ),
              child: Text(
                context.tr('rate_service'),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
