// Wallet Screen
// شاشة المحفظة

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../config/app_theme.dart';
import '../services/services.dart';
import '../utils/saudi_riyal_icon.dart';

import '../services/app_localizations.dart';

class WalletScreen extends StatefulWidget {
  const WalletScreen({super.key});

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  final WalletService _walletService = WalletService();
  final UserService _userService = UserService();

  bool _isLoading = true;
  double _balance = 0.0;
  List<dynamic> _transactions = [];

  @override
  void initState() {
    super.initState();
    _fetchWallet();
  }

  Future<void> _fetchWallet() async {
    try {
      // Fetch both profile (for guaranteed balance) and wallet (for transactions)
      final profileResponse = await _userService.getProfile();
      final walletResponse = await _walletService.getWalletDetails();

      if (mounted) {
        setState(() {
          // Prioritize profile balance as it's the core user record
          if (profileResponse.success && profileResponse.data != null) {
            _balance =
                double.tryParse(
                  profileResponse.data['wallet_balance'].toString(),
                ) ??
                double.tryParse(profileResponse.data['balance'].toString()) ??
                0.0;
          } else if (walletResponse.success && walletResponse.data != null) {
            _balance =
                double.tryParse(walletResponse.data['balance'].toString()) ??
                0.0;
          }

          if (walletResponse.success && walletResponse.data != null) {
            final data = walletResponse.data;
            final transactions = data is Map ? data['transactions'] : null;
            _transactions = transactions is List ? transactions : [];
          }

          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _addFunds() async {
    // Show dialog to enter amount
    final amountController = TextEditingController();

    await showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('top_up_balance')),
        content: TextField(
          controller: amountController,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            hintText: context.tr('enter_amount'),
            suffixIcon: const Padding(
              padding: EdgeInsetsDirectional.only(end: 10),
              child: SaudiRiyalIcon(size: 16),
            ),
            suffixIconConstraints: const BoxConstraints(
              minHeight: 20,
              minWidth: 32,
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(
              context.tr('cancel'),
              style: const TextStyle(color: Colors.red),
            ),
          ),
          ElevatedButton(
            onPressed: () async {
              final amount = double.tryParse(amountController.text);
              if (amount != null && amount > 0) {
                Navigator.pop(context);
                _processTopUp(amount);
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
            child: Text(
              context.tr('top_up'),
              style: const TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _processTopUp(double amount) async {
    // Call API to top up
    try {
      final response = await _walletService.addFunds(amount);
      if (response.success) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(context.tr('balance_topped_up')),
              backgroundColor: Colors.green,
            ),
          );
        }
        _fetchWallet(); // Refresh
      } else {
        // Error
      }
    } catch (e) {
      // Error
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('wallet')),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _fetchWallet,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    // Balance Card
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        gradient: AppColors.primaryGradient,
                        borderRadius: BorderRadius.circular(24),
                        boxShadow: AppShadows.lg,
                      ),
                      child: Column(
                        children: [
                          Text(
                            context.tr('current_balance'),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                          const SizedBox(height: 8),
                          SaudiRiyalText(
                            text: _balance.toStringAsFixed(2),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 32,
                              fontWeight: FontWeight.bold,
                            ),
                            iconSize: 24,
                          ),
                          const SizedBox(height: 24),
                          Row(
                            children: [
                              Expanded(
                                child: ElevatedButton.icon(
                                  onPressed: _addFunds,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: Colors.white,
                                    foregroundColor: AppColors.primary,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                  ),
                                  icon: const Icon(Icons.add),
                                  label: Text(context.tr('top_up_balance')),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ).animate().fadeIn().slideY(begin: 0.1, end: 0),

                    const SizedBox(height: 32),

                    // Transactions History
                    Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        context.tr('transaction_history'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.bold),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // List
                    _transactions.isEmpty
                        ? Center(child: Text(context.tr('no_transactions')))
                        : ListView.separated(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: _transactions.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(height: 12),
                            itemBuilder: (context, index) {
                              final rawItem = _transactions[index];
                              final item = rawItem is Map
                                  ? Map<String, dynamic>.from(
                                      rawItem.map(
                                        (key, value) =>
                                            MapEntry(key.toString(), value),
                                      ),
                                    )
                                  : <String, dynamic>{};
                              final amount =
                                  double.tryParse(item['amount'].toString()) ??
                                  0.0;
                              final type = item['type']
                                  .toString()
                                  .toLowerCase();
                              final isCredit = [
                                'deposit',
                                'reward',
                                'topup',
                                'credit',
                              ].contains(type);

                              return Container(
                                    padding: const EdgeInsets.all(16),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(16),
                                      boxShadow: AppShadows.sm,
                                    ),
                                    child: Row(
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.all(10),
                                          decoration: BoxDecoration(
                                            color: isCredit
                                                ? AppColors.success.withValues(
                                                    alpha: 0.1,
                                                  )
                                                : AppColors.error.withValues(
                                                    alpha: 0.1,
                                                  ),
                                            shape: BoxShape.circle,
                                          ),
                                          child: Icon(
                                            isCredit
                                                ? Icons.arrow_downward
                                                : Icons.arrow_upward,
                                            color: isCredit
                                                ? AppColors.success
                                                : AppColors.error,
                                            size: 20,
                                          ),
                                        ),
                                        const SizedBox(width: 16),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                (item['description'] ??
                                                        (isCredit
                                                            ? context.tr(
                                                                'balance_deposit',
                                                              )
                                                            : context.tr(
                                                                'service_payment',
                                                              )))
                                                    .toString(),
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.w600,
                                                ),
                                              ),
                                              Text(
                                                (item['date'] ??
                                                        item['created_at'] ??
                                                        '')
                                                    .toString(),
                                                style: TextStyle(
                                                  fontSize: 12,
                                                  color: AppColors.gray400,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                        SaudiRiyalText(
                                          text: isCredit
                                              ? '+ $amount'
                                              : '- $amount',
                                          style: TextStyle(
                                            color: isCredit
                                                ? AppColors.success
                                                : AppColors.error,
                                            fontWeight: FontWeight.bold,
                                          ),
                                          iconSize: 13,
                                        ),
                                      ],
                                    ),
                                  )
                                  .animate()
                                  .fadeIn(delay: (100 * index).ms)
                                  .slideX(begin: 0.1, end: 0);
                            },
                          ),
                  ],
                ),
              ),
            ),
    );
  }
}
