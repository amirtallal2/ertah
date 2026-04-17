// Share Screen
// شاشة مشاركة التطبيق

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../services/user_service.dart';
import '../services/settings_service.dart';
import '../services/app_localizations.dart';
import '../utils/saudi_riyal_icon.dart';

class ShareScreen extends StatefulWidget {
  final VoidCallback? onBack;

  const ShareScreen({super.key, this.onBack});

  @override
  State<ShareScreen> createState() => _ShareScreenState();
}

class _ShareScreenState extends State<ShareScreen> {
  final UserService _userService = UserService();
  final SettingsService _settingsService = SettingsService();
  bool _copied = false;
  bool _isLoadingCode = true;
  String _referralCode = '';
  String _shareLink = '';
  String _rewardAmount = '50';
  String _rewardReason = '';
  String _shareInviteSubtitle = '';
  String _shareProgramTitle = '';
  String _shareMessageTemplate = '';
  String _shareLinkBase = '';
  List<Map<String, String>> _shareBenefits = [];
  String _activeLanguage = '';

  @override
  void initState() {
    super.initState();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final languageCode = Localizations.localeOf(context).languageCode;
    if (_activeLanguage != languageCode) {
      _activeLanguage = languageCode;
      _loadReferralCode(languageCode);
    }
  }

  Future<void> _loadReferralCode(String languageCode) async {
    setState(() => _isLoadingCode = true);

    String code = (context.read<AuthProvider>().user?.referralCode ?? '')
        .trim()
        .toUpperCase();
    var rewardAmount = _rewardAmount;
    var rewardReason = _rewardReason;
    var shareInviteSubtitle = _shareInviteSubtitle;
    var shareProgramTitle = _shareProgramTitle;
    var shareMessageTemplate = _shareMessageTemplate;
    var shareLinkBase = _shareLinkBase;
    var shareBenefits = List<Map<String, String>>.from(_shareBenefits);

    try {
      final appSettingsResponse = await _settingsService.getAppSettings(
        lang: languageCode,
      );
      if (appSettingsResponse.success && appSettingsResponse.data is Map) {
        final data = Map<String, dynamic>.from(
          (appSettingsResponse.data as Map).map(
            (key, value) => MapEntry(key.toString(), value),
          ),
        );
        if (data['share_meta'] is Map) {
          final shareMeta = Map<String, dynamic>.from(
            data['share_meta'] as Map,
          );
          rewardAmount = _firstNonEmpty([
            shareMeta['reward_amount'],
            data['referral_reward_amount'],
          ], rewardAmount);
          rewardReason = _firstNonEmpty([
            shareMeta['reward_reason'],
            data['share_reward_reason'],
          ], rewardReason);
          shareInviteSubtitle = _firstNonEmpty([
            shareMeta['invite_subtitle'],
          ], shareInviteSubtitle);
          shareProgramTitle = _firstNonEmpty([
            shareMeta['program_title'],
          ], shareProgramTitle);
          shareMessageTemplate = _firstNonEmpty([
            shareMeta['invite_message'],
          ], shareMessageTemplate);
          shareLinkBase = _firstNonEmpty([
            shareMeta['link_base'],
          ], shareLinkBase);
          shareBenefits = _parseBenefits(shareMeta['benefits']);
        } else {
          rewardAmount = _firstNonEmpty([
            data['referral_reward_amount'],
          ], rewardAmount);
          rewardReason = _firstNonEmpty([
            data['share_reward_reason'],
          ], rewardReason);
        }
      }
    } catch (_) {}

    if (code.isEmpty) {
      try {
        final response = await _userService.getProfile();
        if (response.success && response.data is Map) {
          final data = Map<String, dynamic>.from(
            (response.data as Map).map(
              (key, value) => MapEntry(key.toString(), value),
            ),
          );
          code = (data['referral_code'] ?? '').toString().trim().toUpperCase();
        }
      } catch (_) {}
    }

    if (code.isEmpty) {
      code = 'ERTAH';
    }

    if (!mounted) return;
    setState(() {
      _referralCode = code;
      _rewardAmount = rewardAmount;
      _rewardReason = rewardReason;
      _shareInviteSubtitle = shareInviteSubtitle;
      _shareProgramTitle = shareProgramTitle;
      _shareMessageTemplate = shareMessageTemplate;
      _shareLinkBase = shareLinkBase;
      _shareBenefits = shareBenefits;
      _shareLink = _buildShareLink(code);
      _isLoadingCode = false;
    });
  }

  String _buildShareLink(String code) {
    final customBase = _shareLinkBase.trim();
    if (customBase.isNotEmpty) {
      if (customBase.contains('{code}')) {
        return customBase.replaceAll('{code}', code);
      }
      final normalizedCustomBase = customBase.replaceAll(RegExp(r'/+$'), '');
      return '$normalizedCustomBase/$code';
    }

    final adminUri = Uri.tryParse(AppConfig.adminPanelUrl);
    final host = (adminUri?.host ?? '').trim();
    final scheme = (adminUri?.scheme ?? '').trim();

    if (host.isNotEmpty) {
      final safeScheme = scheme.isNotEmpty ? scheme : 'https';
      return '$safeScheme://$host/ref/$code';
    }

    return 'https://darfix.org/ref/$code';
  }

  String _shareMessage() {
    final template = _shareMessageTemplate.trim().isNotEmpty
        ? _shareMessageTemplate
        : context.tr('share_invite_message');

    return template
        .replaceAll('{code}', _referralCode)
        .replaceAll('{link}', _shareLink);
  }

  String _firstNonEmpty(List<dynamic> values, String fallback) {
    for (final value in values) {
      final text = value?.toString().trim() ?? '';
      if (text.isNotEmpty) return text;
    }
    return fallback;
  }

  List<Map<String, String>> _parseBenefits(dynamic raw) {
    if (raw is! List) return [];
    final benefits = <Map<String, String>>[];
    for (final item in raw) {
      if (item is! Map) continue;
      final map = Map<String, dynamic>.from(item);
      final title = (map['title'] ?? '').toString().trim();
      final subtitle = (map['subtitle'] ?? '').toString().trim();
      if (title.isEmpty && subtitle.isEmpty) continue;
      benefits.add({'title': title, 'subtitle': subtitle});
    }
    return benefits;
  }

  void _handleCopy() {
    Clipboard.setData(ClipboardData(text: _shareLink));
    setState(() => _copied = true);
    Future.delayed(const Duration(seconds: 2), () {
      if (mounted) setState(() => _copied = false);
    });
  }

  Future<void> _handleShare() async {
    if (_isLoadingCode) return;
    await Share.share(
      _shareMessage(),
      subject: context.tr('share_referral_subject'),
    );
  }

  final List<Map<String, dynamic>> _shareOptions = [
    {
      'id': 'whatsapp',
      'nameKey': 'share_option_whatsapp',
      'icon': Icons.chat,
      'colors': [Colors.green, Colors.green.shade600],
      'bgColor': Color(0xFFE8F5E9),
    },
    {
      'id': 'email',
      'nameKey': 'share_option_email',
      'icon': Icons.email,
      'colors': [Color(0xFFFBCC26), Color(0xFFF5C01F)],
      'bgColor': Color(0xFFFFF8E1),
    },
    {
      'id': 'facebook',
      'nameKey': 'share_option_facebook',
      'icon': Icons.facebook,
      'colors': [Color(0xFF7466ED), Color(0xFF6858E0)],
      'bgColor': Color(0xFFEDE7F6),
    },
    {
      'id': 'twitter',
      'nameKey': 'share_option_twitter',
      'icon': Icons.close,
      'colors': [Colors.cyan, Colors.cyan.shade600],
      'bgColor': Color(0xFFE0F7FA),
    },
    {
      'id': 'instagram',
      'nameKey': 'share_option_instagram',
      'icon': Icons.camera_alt,
      'colors': [Colors.pink, Colors.pinkAccent],
      'bgColor': Color(0xFFFCE4EC),
    },
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Header
            _buildHeader(),

            // Content
            Transform.translate(
              offset: const Offset(0, -64),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Share Link Card
                    _buildShareLinkCard()
                        .animate()
                        .fadeIn(delay: 400.ms)
                        .slideY(begin: 0.1),

                    const SizedBox(height: 24),

                    // Share Options
                    Text(
                      context.tr('share_via'),
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 16),
                    _buildShareOptions(),

                    const SizedBox(height: 24),

                    // Benefits Section
                    _buildBenefitsSection()
                        .animate()
                        .fadeIn(delay: 800.ms)
                        .slideY(begin: 0.1),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).padding.top + 16,
        left: 16,
        right: 16,
        bottom: 96,
      ),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primary, AppColors.primaryDark, Color(0xFFE5B41F)],
        ),
      ),
      child: Stack(
        children: [
          // Background Elements
          Positioned(
            top: -50,
            right: -50,
            child: Container(
              width: 160,
              height: 160,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            bottom: 0,
            left: -30,
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                color: Colors.cyan.withValues(alpha: 0.2),
                shape: BoxShape.circle,
              ),
            ),
          ),
          // Content
          Column(
            children: [
              // Top Bar
              Row(
                children: [
                  InkWell(
                    onTap: widget.onBack ?? () => Navigator.pop(context),
                    borderRadius: BorderRadius.circular(20),
                    child: Container(
                      width: 40,
                      height: 40,
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
                  const SizedBox(width: 12),
                  Text(
                    context.tr('share_app'),
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),

              // Icon
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.share, color: Colors.white, size: 40),
              ).animate().scale(delay: 200.ms, curve: Curves.elasticOut),

              const SizedBox(height: 16),

              Text(
                context.tr('share_app'),
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                _shareInviteSubtitle.isNotEmpty
                    ? _shareInviteSubtitle
                    : context.tr('share_invite_friends_rewards'),
                style: TextStyle(color: Colors.white70, fontSize: 14),
              ),

              const SizedBox(height: 24),

              // Referral Code Card
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  children: [
                    Text(
                      context.tr('your_referral_code'),
                      style: TextStyle(color: Colors.white70, fontSize: 12),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _isLoadingCode ? '...' : _referralCode,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 4,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        SaudiRiyalText(
                          text: _rewardAmount,
                          style: TextStyle(color: Colors.white, fontSize: 14),
                          iconSize: 13,
                        ),
                        const SizedBox(width: 8),
                        const Text(
                          '•',
                          style: TextStyle(color: Colors.white70),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          _rewardReason.isNotEmpty
                              ? _rewardReason
                              : context.tr('referral_each_friend_registers'),
                          style: TextStyle(color: Colors.white70, fontSize: 14),
                        ),
                      ],
                    ),
                  ],
                ),
              ).animate().fadeIn(delay: 300.ms).slideY(begin: 0.1),
            ],
          ),
        ],
      ),
    ).animate().slideY(begin: -0.5, end: 0, duration: 400.ms);
  }

  Widget _buildShareLinkCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: AppShadows.lg,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.tr('referral_link'),
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.gray50,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    _isLoadingCode ? '...' : _shareLink,
                    style: TextStyle(color: AppColors.gray600, fontSize: 13),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: _isLoadingCode ? null : _handleCopy,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: _copied
                          ? [Colors.green, Colors.green.shade600]
                          : [Colors.cyan, Colors.blue],
                    ),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    _copied ? Icons.check : Icons.copy,
                    color: Colors.white,
                    size: 20,
                  ),
                ),
              ),
            ],
          ),
          if (_copied)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Center(
                child: Text(
                  context.tr('copied_successfully'),
                  style: TextStyle(color: Colors.green, fontSize: 14),
                ).animate().fadeIn(),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildShareOptions() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 520 ? 3 : 2;
        final itemWidth =
            (constraints.maxWidth - ((columns - 1) * 12)) / columns;

        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: List.generate(_shareOptions.length, (index) {
            final option = _shareOptions[index];
            final List<Color> colors = option['colors'] as List<Color>;
            return GestureDetector(
              onTap: _handleShare,
              child:
                  Container(
                        width: itemWidth,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        decoration: BoxDecoration(
                          color: option['bgColor'],
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Column(
                          children: [
                            Container(
                              width: 40,
                              height: 40,
                              decoration: BoxDecoration(
                                gradient: LinearGradient(colors: colors),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Icon(
                                option['icon'],
                                color: Colors.white,
                                size: 20,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              context.tr(option['nameKey']),
                              style: TextStyle(
                                color: AppColors.gray700,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      )
                      .animate()
                      .fadeIn(delay: (500 + index * 100).ms)
                      .scale(begin: const Offset(0.8, 0.8)),
            );
          }),
        );
      },
    );
  }

  Widget _buildBenefitsSection() {
    final benefits = _shareBenefits.isNotEmpty
        ? _shareBenefits
        : [
            {
              'title': context.tr('share_benefit_1_title'),
              'subtitle': context.tr('share_benefit_1_subtitle'),
            },
            {
              'title': context.tr('share_benefit_2_title'),
              'subtitle': context.tr('share_benefit_2_subtitle'),
            },
            {
              'title': context.tr('share_benefit_3_title'),
              'subtitle': context.tr('share_benefit_3_subtitle'),
            },
          ];

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [Colors.orange, Colors.amber, Colors.yellow.shade600],
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: AppShadows.lg,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            _shareProgramTitle.isNotEmpty
                ? _shareProgramTitle
                : context.tr('share_program_benefits'),
            style: TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.bold,
              fontSize: 16,
            ),
          ),
          const SizedBox(height: 16),
          ...benefits.map(
            (benefit) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.check,
                      color: Colors.white,
                      size: 18,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          benefit['title'] ?? '',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Text(
                          benefit['subtitle'] ?? '',
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.8),
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
