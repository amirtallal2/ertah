// Payment Screen
// شاشة الدفع

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config/app_theme.dart';
import '../services/app_localizations.dart';
import '../services/offers_service.dart';
import '../services/orders_service.dart';
import '../services/wallet_service.dart';
import '../utils/saudi_riyal_icon.dart';

class PaymentScreen extends StatefulWidget {
  final double amount;
  final String serviceName;
  final VoidCallback onPaymentSuccess;
  final String? providerName;
  final String? inspectionType;
  final int? orderId;
  final bool autoStartCardPayment;

  const PaymentScreen({
    super.key,
    required this.amount,
    required this.serviceName,
    required this.onPaymentSuccess,
    this.providerName,
    this.inspectionType,
    this.orderId,
    this.autoStartCardPayment = false,
  });

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentBrandChip extends StatelessWidget {
  const _PaymentBrandChip({required this.label, required this.icon});

  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.gray200),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: AppColors.gray700),
          const SizedBox(width: 4),
          Text(
            label,
            style: const TextStyle(
              fontSize: 10,
              color: AppColors.gray700,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentScreenState extends State<PaymentScreen> {
  static const String _myFatoorahMadaMethodId = 'myfatoorah_mada';
  static const String _myFatoorahVisaMethodId = 'myfatoorah_visa';
  static const String _myFatoorahAppleMethodId = 'myfatoorah_apple';
  static const String _defaultCardMethodId = _myFatoorahMadaMethodId;
  static const Map<String, String> _myFatoorahMethodCodes = {
    _myFatoorahMadaMethodId: 'md',
    _myFatoorahVisaMethodId: 'vm',
    _myFatoorahAppleMethodId: 'ap',
  };
  final TextEditingController _promoCodeController = TextEditingController();
  String? _selectedMethod; // 'wallet', 'myfatoorah_*'
  Map<String, dynamic>? _orderSummary;
  bool _agreedToPolicy = false;
  bool _showError = false;
  bool _isProcessing = false;
  bool _autoPaymentTriggered = false;
  bool _isApplyingPromo = false;
  Map<String, dynamic>? _appliedPromo;
  String? _promoErrorMessage;

  final OrdersService _ordersService = OrdersService();
  final WalletService _walletService = WalletService();
  final OffersService _offersService = OffersService();
  List<Map<String, dynamic>> _paymentMethods = [];

  @override
  void initState() {
    super.initState();
    _initPaymentMethods();
    _loadOrderSummary();
    if (widget.autoStartCardPayment) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _triggerAutoCardPayment();
      });
    }
  }

  Future<void> _loadOrderSummary() async {
    final orderId = widget.orderId;
    if (orderId == null || orderId <= 0) {
      return;
    }

    try {
      final response = await _ordersService.getOrderDetail(orderId);
      if (!mounted) return;
      if (response.success && response.data is Map) {
        setState(() {
          _orderSummary = Map<String, dynamic>.from(
            (response.data as Map).map(
              (key, value) => MapEntry(key.toString(), value),
            ),
          );
        });
      }
    } catch (_) {
      // Keep fallback summary values when request fails.
    }
  }

  Future<void> _triggerAutoCardPayment() async {
    if (!mounted ||
        _autoPaymentTriggered ||
        !widget.autoStartCardPayment ||
        widget.orderId == null) {
      return;
    }

    _autoPaymentTriggered = true;

    setState(() {
      _selectedMethod = _defaultCardMethodId;
      _showError = false;
    });
  }

  Future<void> _initPaymentMethods() async {
    double walletBalance = 0.0;
    try {
      final response = await _walletService.getWalletDetails();
      if (response.success && response.data != null) {
        walletBalance =
            double.tryParse(response.data['balance'].toString()) ?? 0.0;
      }
    } catch (e) {
      debugPrint('Error fetching wallet balance: $e');
    }

    if (mounted) {
      setState(() {
        final methods = <Map<String, dynamic>>[
          {
            'id': 'wallet',
            'name': context.tr('e_wallet'),
            'description': context.tr('fast_secure_payment'),
            'icon': Icons.account_balance_wallet_outlined,
            'color': const Color(0xFFF97316),
            'bgColor': const Color(0xFFFFF3E7),
            'balance': walletBalance,
            'image':
                'https://images.pexels.com/photos/210574/pexels-photo-210574.jpeg?auto=compress&cs=tinysrgb?w=400',
          },
          {
            'id': _myFatoorahMadaMethodId,
            'name': 'مدى',
            'description': context.tr('secure_encrypted_transactions'),
            'icon': Icons.payments_outlined,
            'color': const Color(0xFFF97316),
            'bgColor': const Color(0xFFFFF3E7),
            'brands': const ['mada'],
            'image':
                'https://images.pexels.com/photos/4386476/pexels-photo-4386476.jpeg?auto=compress&cs=tinysrgb?w=400',
          },
          {
            'id': _myFatoorahVisaMethodId,
            'name': 'Visa',
            'description': context.tr('secure_encrypted_transactions'),
            'icon': Icons.credit_card_rounded,
            'color': const Color(0xFFF97316),
            'bgColor': const Color(0xFFFFF3E7),
            'brands': const ['VISA'],
            'image':
                'https://images.pexels.com/photos/4386476/pexels-photo-4386476.jpeg?auto=compress&cs=tinysrgb?w=400',
          },
        ];

        if (!kIsWeb && defaultTargetPlatform == TargetPlatform.iOS) {
          methods.insert(2, {
            'id': _myFatoorahAppleMethodId,
            'name': 'Apple Pay',
            'description': context.tr('secure_encrypted_transactions'),
            'icon': Icons.phone_iphone_rounded,
            'color': const Color(0xFFF97316),
            'bgColor': const Color(0xFFFFF3E7),
            'brands': const ['Apple Pay'],
            'image':
                'https://images.pexels.com/photos/4386476/pexels-photo-4386476.jpeg?auto=compress&cs=tinysrgb?w=400',
          });
        }

        _paymentMethods = methods;
      });
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_paymentMethods.isEmpty) {
      // Initial placeholder while fetching
      _initPaymentMethods();
    }
  }

  @override
  void dispose() {
    _promoCodeController.dispose();
    super.dispose();
  }

  double get _baseAmount => widget.amount;

  double get _discountAmount =>
      double.tryParse('${_appliedPromo?['discount_amount'] ?? 0}') ?? 0;

  double get _payableAmount {
    final value = _baseAmount - _discountAmount;
    if (value <= 0) return 0;
    return double.parse(value.toStringAsFixed(2));
  }

  String? get _appliedPromoCode {
    final code = (_appliedPromo?['code'] ?? _appliedPromo?['promo_code'] ?? '')
        .toString()
        .trim();
    return code.isEmpty ? null : code;
  }

  bool _isMyFatoorahMethod(String? methodId) {
    return _myFatoorahMethodCodes.containsKey(methodId);
  }

  String? _resolveMyFatoorahMethodCode(String? methodId) {
    if (methodId == null) return null;
    return _myFatoorahMethodCodes[methodId];
  }

  IconData _resolveBrandIcon(String label) {
    final normalized = label.toLowerCase().trim();
    if (normalized.contains('mada')) {
      return Icons.payments_outlined;
    }
    if (normalized.contains('apple')) {
      return Icons.phone_iphone_rounded;
    }
    return Icons.credit_card_rounded;
  }

  Future<bool> _applyPromoCode({bool silentSuccess = false}) async {
    final code = _promoCodeController.text.trim();
    if (code.isEmpty) {
      setState(() {
        _appliedPromo = null;
        _promoErrorMessage = null;
      });
      return true;
    }

    setState(() {
      _isApplyingPromo = true;
      _promoErrorMessage = null;
    });

    try {
      final response = await _offersService.validatePromoCode(
        code,
        orderAmount: _baseAmount,
      );

      if (!mounted) return false;

      if (response.success && response.data is Map) {
        final promo = Map<String, dynamic>.from(response.data as Map);
        setState(() {
          _appliedPromo = promo;
          _promoErrorMessage = null;
        });
        if (!silentSuccess) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(context.tr('discount_code_applied'))),
          );
        }
        return true;
      }

      setState(() {
        _appliedPromo = null;
        _promoErrorMessage =
            response.message ?? context.tr('invalid_discount_code');
      });
      return false;
    } catch (_) {
      if (!mounted) return false;
      setState(() {
        _appliedPromo = null;
        _promoErrorMessage = context.tr('invalid_discount_code');
      });
      return false;
    } finally {
      if (mounted) {
        setState(() => _isApplyingPromo = false);
      }
    }
  }

  void _handlePayment() async {
    if (_selectedMethod == null || !_agreedToPolicy) {
      setState(() => _showError = true);
      return;
    }

    final paymentOrderReferenceMissingText = context.tr(
      'payment_order_reference_missing',
    );
    final invalidDiscountCodeText = context.tr('invalid_discount_code');

    setState(() => _isProcessing = true);

    try {
      final typedPromoCode = _promoCodeController.text.trim().toUpperCase();
      if (typedPromoCode.isNotEmpty) {
        final appliedCode = _appliedPromoCode?.toUpperCase();
        if (appliedCode == null || appliedCode != typedPromoCode) {
          final applied = await _applyPromoCode(silentSuccess: true);
          if (!applied) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(_promoErrorMessage ?? invalidDiscountCodeText),
                  backgroundColor: Colors.red,
                ),
              );
            }
            return;
          }
        }
      }

      bool success = false;
      String message = '';

      final orderId = widget.orderId;
      final promoCodeToSend = _appliedPromoCode;
      if (orderId == null || orderId <= 0) {
        success = false;
        message = paymentOrderReferenceMissingText;
      } else if (_isMyFatoorahMethod(_selectedMethod)) {
        final paymentMethodCode = _resolveMyFatoorahMethodCode(_selectedMethod);
        final gatewayResult = await _handleCardGatewayPayment(
          orderId,
          promoCode: promoCodeToSend,
          paymentMethodCode: paymentMethodCode,
        );
        success = gatewayResult.$1;
        message = gatewayResult.$2 ?? '';
      } else {
        // Wallet direct payment
        final response = await _ordersService.payOrder(
          orderId: orderId,
          paymentMethod: _selectedMethod!,
          amount: _baseAmount,
          promoCode: promoCodeToSend,
        );
        success = response.success;
        if (!success) {
          message =
              response.message ??
              (mounted ? context.tr('payment_failed') : 'Payment Failed');
        }
      }

      if (success && mounted) {
        _showSuccessDialog();
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              message.isNotEmpty
                  ? message
                  : context.tr('payment_not_completed_retry'),
            ),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.tr('connection_error')),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isProcessing = false);
    }
  }

  Future<(bool, String?)> _handleCardGatewayPayment(
    int orderId, {
    String? promoCode,
    String? paymentMethodCode,
  }) async {
    final localizations = AppLocalizations.of(context);
    String tr(String key) => localizations?.translate(key) ?? key;

    final executeResponse = await _ordersService.executeMyFatoorahPayment(
      orderId: orderId,
      amount: _baseAmount,
      promoCode: promoCode,
      paymentMethodCode: paymentMethodCode,
    );

    if (!executeResponse.success || executeResponse.data is! Map) {
      return (
        false,
        executeResponse.message?.trim().isNotEmpty == true
            ? executeResponse.message
            : tr('payment_url_create_failed'),
      );
    }

    final data = Map<String, dynamic>.from(executeResponse.data as Map);
    final skipGateway = data['skip_gateway'] == true;
    final alreadyPaid = data['is_paid'] == true;
    if (skipGateway || alreadyPaid) {
      return (true, null);
    }

    final paymentUrl = (data['payment_url'] ?? '').toString().trim();
    final invoiceId = (data['invoice_id'] ?? '').toString().trim();

    if (paymentUrl.isEmpty || invoiceId.isEmpty) {
      return (false, tr('payment_url_create_failed'));
    }

    final uri = Uri.tryParse(paymentUrl);
    if (uri == null) {
      return (false, tr('payment_url_invalid'));
    }

    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened) {
      return (false, tr('payment_gateway_open_failed'));
    }

    if (!mounted) return (false, null);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(tr('payment_gateway_opened'))));

    final paid = await _verifyMyFatoorahPayment(
      orderId: orderId,
      invoiceId: invoiceId,
      payableAmount: _payableAmount > 0 ? _payableAmount : _baseAmount,
    );

    if (!paid) {
      return (false, tr('payment_not_confirmed'));
    }

    return (true, null);
  }

  Future<bool> _verifyMyFatoorahPayment({
    required int orderId,
    required String invoiceId,
    required double payableAmount,
  }) async {
    for (var attempt = 0; attempt < 4; attempt++) {
      final statusResponse = await _ordersService.getMyFatoorahPaymentStatus(
        orderId: orderId,
        invoiceId: invoiceId,
        amount: payableAmount,
      );

      if (statusResponse.success && statusResponse.data is Map) {
        final data = Map<String, dynamic>.from(statusResponse.data as Map);
        final invoiceStatus = (data['invoice_status'] ?? '')
            .toString()
            .trim()
            .toLowerCase();
        final isPaid = data['is_paid'] == true || invoiceStatus == 'paid';
        if (isPaid) {
          return true;
        }
      }

      if (attempt < 3) {
        await Future.delayed(const Duration(seconds: 2));
      }
    }

    return false;
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: Colors.green.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_circle,
                  color: Colors.green,
                  size: 48,
                ),
              ).animate().scale(),
              const SizedBox(height: 16),
              Text(
                context.tr('payment_successful'),
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                context.tr('order_received_processing'),
                style: const TextStyle(color: AppColors.gray600, fontSize: 12),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(context);
                    widget.onPaymentSuccess();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: Text(context.tr('track_order')),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F3EF),
      body: Column(
        children: [
          // Header with Gradient
          _buildHeader(),

          // Content
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Title with Icon
                  Center(
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.auto_awesome,
                              color: Colors.orange,
                              size: 16,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              context.tr('payment_info'),
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                              ),
                            ),
                            const SizedBox(width: 6),
                            Icon(
                              Icons.auto_awesome,
                              color: Colors.amber,
                              size: 16,
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          context.tr('review_order_details'),
                          style: TextStyle(
                            color: AppColors.gray600,
                            fontSize: 10,
                          ),
                        ),
                      ],
                    ),
                  ).animate().fadeIn().slideY(begin: 0.1),

                  const SizedBox(height: 12),

                  // Order Summary
                  _buildSummaryCard()
                      .animate()
                      .fadeIn(delay: 100.ms)
                      .slideY(begin: 0.1),

                  const SizedBox(height: 12),

                  // Non-Refundable Warning
                  _buildWarningCard()
                      .animate()
                      .fadeIn(delay: 200.ms)
                      .slideY(begin: 0.1),

                  const SizedBox(height: 12),

                  _buildPromoCodeCard()
                      .animate()
                      .fadeIn(delay: 250.ms)
                      .slideY(begin: 0.1),

                  const SizedBox(height: 12),

                  // Payment Methods Title
                  Row(
                    children: [
                      Container(
                        width: 26,
                        height: 26,
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF3E7),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(
                          Icons.lock_outline,
                          color: Color(0xFFF97316),
                          size: 16,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        context.tr('choose_payment_method'),
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    context.tr('secure_encrypted_transactions'),
                    style: TextStyle(color: AppColors.gray600, fontSize: 10),
                  ),

                  const SizedBox(height: 8),

                  // Payment Methods
                  ...List.generate(_paymentMethods.length, (index) {
                    return _buildPaymentMethodCard(
                          _paymentMethods[index],
                          index,
                        )
                        .animate()
                        .fadeIn(delay: (300 + index * 100).ms)
                        .slideY(begin: 0.1);
                  }),

                  const SizedBox(height: 12),

                  // Terms Agreement
                  _buildTermsCard()
                      .animate()
                      .fadeIn(delay: 500.ms)
                      .slideY(begin: 0.1),

                  // Error Message
                  if (_showError) _buildErrorMessage(),

                  const SizedBox(height: 12),

                  // Payment Button
                  _buildPaymentButton().animate().fadeIn(delay: 600.ms).scale(),

                  const SizedBox(height: 12),

                  // Security Note
                  _buildSecurityNote().animate().fadeIn(delay: 700.ms),

                  const SizedBox(height: 20),
                ],
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
        left: 12,
        right: 12,
        bottom: 18,
      ),
      decoration: BoxDecoration(
        color: const Color(0xFFF6F1ED),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.06),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Stack(
        children: [
          // Content
          Row(
            children: [
              InkWell(
                onTap: () => Navigator.pop(context),
                borderRadius: BorderRadius.circular(20),
                child: Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    boxShadow: AppShadows.md,
                  ),
                  child: const Icon(
                    Icons.close,
                    color: AppColors.gray700,
                    size: 18,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'الدفع الآمن',
                      style: const TextStyle(
                        color: AppColors.gray900,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'معاملة مشفرة بنسبة 100٪',
                      style: TextStyle(
                        color: AppColors.gray600,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF3E7),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(
                  Icons.lock_outline,
                  color: Color(0xFFF97316),
                  size: 24,
                ),
              ),
            ],
          ),
        ],
      ),
    ).animate().slideY(begin: -0.5, end: 0, duration: 400.ms);
  }

  Widget _buildSummaryCard() {
    final summary = _orderSummary;
    final serviceName =
        ((summary?['display_service_name'] ??
                summary?['category_name'] ??
                widget.serviceName)
            ?.toString() ??
        widget.serviceName);
    final providerName =
        ((summary?['provider_name'] ?? widget.providerName)
                    ?.toString()
                    .trim() ??
                '')
            .trim();
    final scheduleDate = (summary?['scheduled_date'] ?? '').toString().trim();
    final scheduleTime = (summary?['scheduled_time'] ?? '').toString().trim();
    final scheduleValue = [
      scheduleDate,
      scheduleTime,
    ].where((e) => e.isNotEmpty).join(' ');
    final address = (summary?['address'] ?? '').toString().trim();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFF1E8E3)),
        boxShadow: AppShadows.md,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 26,
                height: 26,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF3E7),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.receipt_long,
                  color: Color(0xFFF97316),
                  size: 16,
                ),
              ),
              const SizedBox(width: 8),
              const Text(
                'تفاصيل الفاتورة',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _buildSummaryRow(context.tr('service_type'), serviceName),
          const SizedBox(height: 6),
          _buildSummaryRow(
            context.tr('service_provider'),
            providerName.isNotEmpty
                ? providerName
                : context.tr('certified_technician'),
          ),
          const SizedBox(height: 6),
          _buildSummaryRow(
            context.tr('inspection_type'),
            widget.inspectionType ?? context.tr('remote_inspection'),
          ),
          if (scheduleValue.isNotEmpty) ...[
            const SizedBox(height: 6),
            _buildSummaryRow(context.tr('expected_date'), scheduleValue),
          ],
          if (address.isNotEmpty) ...[
            const SizedBox(height: 6),
            _buildSummaryRow(context.tr('address'), address),
          ],
          const SizedBox(height: 12),
          const Divider(height: 1),
          const SizedBox(height: 12),
          _buildAmountRow(
            label: context.tr('total_amount'),
            amount: _baseAmount,
          ),
          if (_discountAmount > 0) ...[
            const SizedBox(height: 8),
            _buildAmountRow(
              label: context.tr('discount_value'),
              amount: _discountAmount,
              isDiscount: true,
            ),
          ],
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF3E7),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  context.tr('payable_amount'),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: AppColors.gray800,
                  ),
                ),
                SaudiRiyalText(
                  text: _payableAmount.toStringAsFixed(2),
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFFF97316),
                  ),
                  iconSize: 12,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAmountRow({
    required String label,
    required double amount,
    bool isDiscount = false,
  }) {
    final valueText = amount.toStringAsFixed(2);
    final color = isDiscount ? Colors.green : AppColors.gray800;
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(fontSize: 12, color: AppColors.gray600)),
        SaudiRiyalText(
          text: isDiscount ? '-$valueText' : valueText,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: color,
          ),
          iconSize: 10,
        ),
      ],
    );
  }

  Widget _buildSummaryRow(String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 92,
          child: Text(
            label,
            textAlign: TextAlign.end,
            style: TextStyle(color: AppColors.gray500, fontSize: 10),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.start,
            softWrap: true,
            style: TextStyle(color: AppColors.gray800, fontSize: 12),
          ),
        ),
      ],
    );
  }

  Widget _buildWarningCard() {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFFEF2F2), Color(0xFFFCE7F3)],
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.red.withValues(alpha: 0.2)),
        boxShadow: AppShadows.md,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(
              color: Colors.red,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(
              Icons.warning_amber,
              color: Colors.white,
              size: 16,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('important_warning'),
                  style: TextStyle(
                    color: Colors.red.shade800,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  context.tr('non_refundable_note'),
                  style: TextStyle(
                    color: Colors.red.shade700,
                    fontSize: 10,
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

  Widget _buildPromoCodeCard() {
    final hasAppliedPromo = _appliedPromoCode != null;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: AppShadows.md,
        border: Border.all(color: AppColors.gray100),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.discount_outlined, color: Colors.orange),
              const SizedBox(width: 8),
              Text(
                context.tr('discount_code_optional'),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _promoCodeController,
                  textCapitalization: TextCapitalization.characters,
                  enabled: !_isApplyingPromo && !_isProcessing,
                  onChanged: (value) {
                    final normalizedTyped = value.trim().toUpperCase();
                    final normalizedApplied = (_appliedPromoCode ?? '')
                        .toUpperCase();
                    if (normalizedTyped != normalizedApplied) {
                      setState(() {
                        _appliedPromo = null;
                        _promoErrorMessage = null;
                      });
                    }
                  },
                  decoration: InputDecoration(
                    hintText: context.tr('apply_discount_code'),
                    isDense: true,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              if (hasAppliedPromo)
                OutlinedButton(
                  onPressed: _isProcessing
                      ? null
                      : () {
                          setState(() {
                            _promoCodeController.clear();
                            _appliedPromo = null;
                            _promoErrorMessage = null;
                          });
                        },
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(72, 34),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    textStyle: const TextStyle(fontSize: 11),
                  ),
                  child: Text(context.tr('remove_discount_code')),
                )
              else
                ElevatedButton(
                  onPressed: (_isApplyingPromo || _isProcessing)
                      ? null
                      : () => _applyPromoCode(),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.black,
                    minimumSize: const Size(72, 34),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    textStyle: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  child: _isApplyingPromo
                      ? const SizedBox(
                          width: 14,
                          height: 14,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(context.tr('apply_discount_code')),
                ),
            ],
          ),
          if (_promoErrorMessage != null &&
              _promoErrorMessage!.trim().isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                _promoErrorMessage!,
                style: const TextStyle(
                  color: Colors.red,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          if (hasAppliedPromo && _discountAmount > 0)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                '${context.tr('discount_value')}: ${_discountAmount.toStringAsFixed(2)}',
                style: const TextStyle(
                  color: Colors.green,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildPaymentMethodCard(Map<String, dynamic> method, int index) {
    final bool isSelected = _selectedMethod == method['id'];
    final bool isWallet = method['id'] == 'wallet';
    final Color accent = const Color(0xFFF97316);
    final List<dynamic> brands =
        (method['brands'] as List<dynamic>? ?? const []);

    return GestureDetector(
      onTap: () => setState(() => _selectedMethod = method['id']),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFFFF7ED) : Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: isSelected ? accent : AppColors.gray200,
            width: 2,
          ),
          boxShadow: AppShadows.md,
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildSelectionDot(isSelected, accent),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    method['name'],
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    method['description'],
                    style: TextStyle(
                      color: AppColors.gray600,
                      fontSize: 10,
                      height: 1.3,
                    ),
                  ),
                  if (brands.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: brands
                          .map(
                            (brand) => _PaymentBrandChip(
                              label: brand.toString(),
                              icon: _resolveBrandIcon(brand.toString()),
                            ),
                          )
                          .toList(),
                    ),
                  ],
                  if (isWallet) ...[
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF3E7),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.account_balance_wallet_outlined,
                            color: Color(0xFFF97316),
                            size: 14,
                          ),
                          const SizedBox(width: 6),
                          SaudiRiyalText(
                            text:
                                '${context.tr('current_balance')}: ${method['balance']}',
                            style: const TextStyle(
                              color: Color(0xFFF97316),
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                            ),
                            iconSize: 10,
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 10),
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: const Color(0xFFFFF3E7),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                method['icon'] as IconData,
                color: const Color(0xFFF97316),
                size: 22,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSelectionDot(bool isSelected, Color accent) {
    return Container(
      width: 22,
      height: 22,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(
          color: isSelected ? accent : AppColors.gray300,
          width: 2,
        ),
      ),
      child: isSelected
          ? Center(
              child: Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  color: accent,
                  shape: BoxShape.circle,
                ),
              ),
            )
          : null,
    );
  }

  Widget _buildTermsCard() {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.gray100),
        boxShadow: AppShadows.md,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 20,
            height: 20,
            child: Checkbox(
              value: _agreedToPolicy,
              onChanged: (val) => setState(() => _agreedToPolicy = val!),
              activeColor: Colors.orange,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: GestureDetector(
              onTap: () => setState(() => _agreedToPolicy = !_agreedToPolicy),
              child: RichText(
                text: TextSpan(
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.gray700,
                    fontFamily: 'Cairo',
                    height: 1.4,
                  ),
                  children: [
                    TextSpan(text: context.tr('i_agree_to')),
                    TextSpan(
                      text: context.tr('terms_and_conditions'),
                      style: const TextStyle(
                        color: Colors.orange,
                        decoration: TextDecoration.underline,
                      ),
                    ),
                    TextSpan(text: context.tr('and')),
                    TextSpan(
                      text: context.tr('refund_policy'),
                      style: const TextStyle(
                        color: Colors.orange,
                        decoration: TextDecoration.underline,
                      ),
                    ),
                    TextSpan(text: context.tr('confirm_non_refundable')),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorMessage() {
    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFFEF2F2), Color(0xFFFCE7F3)],
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.red.withValues(alpha: 0.2)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(
              color: Colors.red,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(
              Icons.error_outline,
              color: Colors.white,
              size: 16,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('complete_requirements'),
                  style: TextStyle(color: Colors.red.shade800, fontSize: 12),
                ),
                const SizedBox(height: 4),
                if (_selectedMethod == null)
                  Text(
                    context.tr('select_payment_method_requirement'),
                    style: TextStyle(color: Colors.red.shade600, fontSize: 10),
                  ),
                if (!_agreedToPolicy)
                  Text(
                    context.tr('agree_terms_requirement'),
                    style: TextStyle(color: Colors.red.shade600, fontSize: 10),
                  ),
              ],
            ),
          ),
        ],
      ),
    ).animate().shake();
  }

  Widget _buildPaymentButton() {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton(
        onPressed: _isProcessing ? null : _handlePayment,
        style: ElevatedButton.styleFrom(
          backgroundColor: Colors.transparent,
          shadowColor: Colors.transparent,
          padding: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
        child: Ink(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [Color(0xFFEA580C), Color(0xFFF97316)],
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: Color(0xFFF97316).withValues(alpha: 0.35),
                blurRadius: 12,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Container(
            alignment: Alignment.center,
            child: _isProcessing
                ? const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2,
                    ),
                  )
                : Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.shield, color: Colors.white, size: 20),
                      const SizedBox(width: 8),
                      SaudiRiyalText(
                        text:
                            '${context.tr('confirm_payment')} - ${_payableAmount.toStringAsFixed(2)}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                        iconSize: 12,
                      ),
                      const SizedBox(width: 8),
                      Icon(Icons.lock, color: Colors.white, size: 16),
                    ],
                  ),
          ),
        ),
      ),
    );
  }

  Widget _buildSecurityNote() {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.gray100),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.shield, color: Colors.green, size: 16),
              const SizedBox(width: 4),
              Text(
                context.tr('secure_payment_note'),
                style: TextStyle(color: AppColors.gray600, fontSize: 10),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _buildSecurityBadge(context.tr('ssl_encrypted')),
              const SizedBox(width: 8),
              _buildSecurityBadge(context.tr('pci_compliant')),
              const SizedBox(width: 8),
              _buildSecurityBadge(context.tr('secure_100')),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSecurityBadge(String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.gray100,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        text,
        style: TextStyle(color: AppColors.gray500, fontSize: 9),
      ),
    );
  }
}
