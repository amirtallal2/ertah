// Rewards Screen
// شاشة المكافآت

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../services/services.dart';
import '../utils/saudi_riyal_icon.dart';

import '../services/app_localizations.dart';

class RewardsScreen extends StatefulWidget {
  const RewardsScreen({super.key});

  @override
  State<RewardsScreen> createState() => _RewardsScreenState();
}

class _RewardsScreenState extends State<RewardsScreen> {
  final RewardsService _rewardsService = RewardsService();

  bool _isLoading = true;
  bool _isRedeeming = false;
  int _points = 0;
  int _minRedeemPoints = 100;
  double _pointsPerCurrencyUnit = 10.0;
  List<dynamic> _rewards = [];
  List<dynamic> _history = [];

  @override
  void initState() {
    super.initState();
    _fetchRewards();
  }

  Future<void> _fetchRewards() async {
    try {
      int nextPoints = _points;
      int nextMinRedeemPoints = _minRedeemPoints;
      double nextPointsPerCurrencyUnit = _pointsPerCurrencyUnit;
      List<dynamic> nextRewards = [];
      List<dynamic> nextHistory = [];

      final infoResponse = await _rewardsService.getRewards();
      if (infoResponse.success && infoResponse.data is Map) {
        final data = Map<String, dynamic>.from(infoResponse.data as Map);
        nextPoints =
            int.tryParse((data['user_points'] ?? 0).toString()) ?? nextPoints;
        nextMinRedeemPoints =
            int.tryParse((data['min_redeem_points'] ?? '').toString()) ??
            nextMinRedeemPoints;
        nextPointsPerCurrencyUnit =
            double.tryParse(
              (data['points_per_currency_unit'] ??
                      data['points_per_sar'] ??
                      data['points_conversion_rate'] ??
                      '')
                  .toString(),
            ) ??
            nextPointsPerCurrencyUnit;
        nextRewards = (data['rewards'] as List?) ?? [];
        nextHistory = (data['history'] as List?) ?? [];
      }

      if (nextRewards.isEmpty) {
        final listResponse = await _rewardsService.getRewardsList();
        if (listResponse.success) {
          if (listResponse.data is List) {
            nextRewards = listResponse.data as List;
          } else if (listResponse.data is Map) {
            final map = Map<String, dynamic>.from(listResponse.data as Map);
            nextPoints =
                int.tryParse((map['user_points'] ?? nextPoints).toString()) ??
                nextPoints;
            nextRewards = (map['rewards'] as List?) ?? nextRewards;
          }
        }
      }

      if (nextHistory.isEmpty) {
        final historyResponse = await _rewardsService.getRewardHistory();
        if (historyResponse.success && historyResponse.data is List) {
          nextHistory = historyResponse.data as List;
        }
      }

      if (!mounted) return;
      setState(() {
        _points = nextPoints;
        _minRedeemPoints = nextMinRedeemPoints > 0 ? nextMinRedeemPoints : 100;
        _pointsPerCurrencyUnit = nextPointsPerCurrencyUnit > 0
            ? nextPointsPerCurrencyUnit
            : 10.0;
        _rewards = nextRewards;
        _history = nextHistory;
        _isLoading = false;
      });
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _redeemPoints() async {
    if (_points < _minRedeemPoints) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('redeem_min_points'))));
      return;
    }
    final authProvider = context.read<AuthProvider>();
    final redeemSuccessText = context.tr('points_redeemed_success');
    final redeemFailedText = context.tr('points_redeem_failed');

    setState(() => _isRedeeming = true);

    try {
      final response = await _rewardsService.redeemPoints(_points);
      if (response.success) {
        final payload = response.data is Map
            ? Map<String, dynamic>.from(response.data as Map)
            : const <String, dynamic>{};
        final creditAmount =
            double.tryParse((payload['credit_amount'] ?? '').toString()) ?? 0.0;
        final successMessage = response.message?.trim().isNotEmpty == true
            ? response.message!.trim()
            : redeemSuccessText;
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                creditAmount > 0
                    ? '$successMessage (+${creditAmount.toStringAsFixed(2)} ر.س)'
                    : successMessage,
              ),
              backgroundColor: Colors.green,
            ),
          );
        }
        await authProvider.refreshUser();
        await _fetchRewards();
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response.message ?? redeemFailedText),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      // Handle error
    } finally {
      if (mounted) setState(() => _isRedeeming = false);
    }
  }

  Future<void> _redeemReward(Map<String, dynamic> reward) async {
    final rewardId = int.tryParse(reward['id'].toString()) ?? 0;
    if (rewardId <= 0) return;

    setState(() => _isRedeeming = true);
    try {
      final response = await _rewardsService.redeemRewardById(rewardId);
      if (!mounted) return;

      final payload = response.data is Map
          ? Map<String, dynamic>.from(response.data as Map)
          : const <String, dynamic>{};
      final creditAmount =
          double.tryParse((payload['credit_amount'] ?? '').toString()) ?? 0.0;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            response.message ??
                (response.success
                    ? (creditAmount > 0
                        ? '${context.tr('points_redeemed_success')} (+${creditAmount.toStringAsFixed(2)} ر.س)'
                        : context.tr('points_redeemed_success'))
                    : context.tr('points_redeem_failed')),
          ),
          backgroundColor: response.success ? Colors.green : Colors.red,
        ),
      );

      if (response.success) {
        await context.read<AuthProvider>().refreshUser();
        await _fetchRewards();
      }
    } finally {
      if (mounted) setState(() => _isRedeeming = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final equivalentAmount = _points / _pointsPerCurrencyUnit;
    // Replace placeholder in string and remove inline currency symbol, then render with icon
    final equalsSarText = context
        .tr('equals_sar')
        .replaceAll('{amount}', equivalentAmount.toStringAsFixed(0))
        .replaceAll('⃁', '')
        .replaceAll('ر.س', '')
        .replaceAll('SAR', '')
        .replaceAll('sar', '')
        .trim();

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('rewards_title')),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  // Points Card
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Colors.amber, Colors.orange],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: AppShadows.lg,
                    ),
                    child: Column(
                      children: [
                        const Icon(
                          Icons.emoji_events,
                          color: Colors.white,
                          size: 48,
                        ),
                        const SizedBox(height: 16),
                        Text(
                          context.tr('points_balance'),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 14,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '$_points',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 40,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 8),
                        SaudiRiyalText(
                          text: equalsSarText,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                          iconSize: 11,
                        ),
                      ],
                    ),
                  ).animate().fadeIn().scale(),

                  const SizedBox(height: 32),

                  // Convert Points Button
                  SizedBox(
                    width: double.infinity,
                    height: 56,
                    child: ElevatedButton(
                      onPressed: _isRedeeming ? null : _redeemPoints,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: _isRedeeming
                          ? const SizedBox(
                              width: 24,
                              height: 24,
                              child: CircularProgressIndicator(
                                color: Colors.white,
                              ),
                            )
                          : Text(
                              context.tr('redeem_points_btn'),
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                    ),
                  ),

                  const SizedBox(height: 32),

                  if (_rewards.isNotEmpty) ...[
                    Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        context.tr('available_rewards_title'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.bold),
                      ),
                    ),
                    const SizedBox(height: 12),
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: _rewards.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, index) {
                        final rawReward = _rewards[index];
                        final reward = rawReward is Map
                            ? Map<String, dynamic>.from(
                                rawReward.map(
                                  (key, value) =>
                                      MapEntry(key.toString(), value),
                                ),
                              )
                            : <String, dynamic>{};
                        final title = (reward['title'] ?? '').toString();
                        final description = (reward['description'] ?? '')
                            .toString();
                        final pointsRequired =
                            int.tryParse(
                              reward['points_required']?.toString() ?? '',
                            ) ??
                            0;
                        final canRedeem =
                            reward['can_redeem'] == true ||
                            _points >= pointsRequired;

                        return Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(14),
                            boxShadow: AppShadows.sm,
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                title.isEmpty
                                    ? context.tr('reward_default_title')
                                    : title,
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              if (description.isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  description,
                                  style: const TextStyle(
                                    color: AppColors.gray600,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                              const SizedBox(height: 10),
                              Row(
                                children: [
                                  Text(
                                    context
                                        .tr('rewards_points_required')
                                        .replaceAll(
                                          '{points}',
                                          pointsRequired.toString(),
                                        ),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.primary,
                                    ),
                                  ),
                                  const Spacer(),
                                  ElevatedButton(
                                    onPressed: canRedeem && !_isRedeeming
                                        ? () => _redeemReward(reward)
                                        : null,
                                    child: Text(
                                      canRedeem
                                          ? context.tr('redeem_action')
                                          : context.tr('not_enough_points'),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 32),
                  ],

                  // History
                  Align(
                    alignment: Alignment.centerRight,
                    child: Text(
                      context.tr('points_history'),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  _history.isEmpty
                      ? Center(
                          child: Padding(
                            padding: const EdgeInsets.all(16.0),
                            child: Text(context.tr('no_points_history')),
                          ),
                        )
                      : ListView.separated(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: _history.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 12),
                          itemBuilder: (context, index) {
                            final item = _history[index];
                            final amount =
                                double.tryParse(
                                  (item['amount'] ?? 0).toString(),
                                )?.toInt() ??
                                0;
                            final isPositive = amount > 0;
                            final dateValue =
                                (item['date'] ?? item['created_at'] ?? '')
                                    .toString();

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
                                      color: isPositive
                                          ? Colors.amber.withValues(alpha: 0.1)
                                          : Colors.red.withValues(alpha: 0.1),
                                      shape: BoxShape.circle,
                                    ),
                                    child: Icon(
                                      isPositive
                                          ? Icons.star
                                          : Icons.arrow_outward,
                                      color: isPositive
                                          ? Colors.amber
                                          : Colors.red,
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
                                                  context.tr(
                                                    'point_transaction',
                                                  ))
                                              .toString(),
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                        Text(
                                          dateValue,
                                          style: const TextStyle(
                                            fontSize: 12,
                                            color: AppColors.gray400,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  Text(
                                    isPositive
                                        ? '+ $amount ${context.tr('point_label')}'
                                        : '$amount ${context.tr('point_label')}',
                                    style: TextStyle(
                                      color: isPositive
                                          ? Colors.amber
                                          : Colors.red,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                ],
              ),
            ),
    );
  }
}
