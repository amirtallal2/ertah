// Help Center Screen
// مركز المساعدة

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/app_theme.dart';
import '../services/app_localizations.dart';
import '../services/settings_service.dart';
import 'complaints_list_screen.dart';

class HelpCenterScreen extends StatefulWidget {
  const HelpCenterScreen({super.key});

  @override
  State<HelpCenterScreen> createState() => _HelpCenterScreenState();
}

class _HelpCenterScreenState extends State<HelpCenterScreen> {
  final SettingsService _settingsService = SettingsService();

  bool _isLoadingContact = true;
  Map<String, dynamic> _contact = {};
  String _bannerText = '';
  List<Map<String, String>> _faqs = [];
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
      _loadContact(languageCode);
    }
  }

  Future<void> _loadContact([String? languageCode]) async {
    setState(() => _isLoadingContact = true);
    try {
      final responses = await Future.wait([
        _settingsService.getContact(),
        _settingsService.getAppSettings(lang: languageCode),
      ]);
      final contactResponse = responses[0];
      final appSettingsResponse = responses[1];
      if (!mounted) return;
      final merged = <String, dynamic>{};
      var bannerText = _bannerText;
      var faqs = List<Map<String, String>>.from(_faqs);

      if (appSettingsResponse.success && appSettingsResponse.data is Map) {
        final appData = Map<String, dynamic>.from(
          appSettingsResponse.data as Map,
        );
        merged.addAll(appData);
        if (appData['help_center_meta'] is Map) {
          final meta = Map<String, dynamic>.from(
            appData['help_center_meta'] as Map,
          );
          bannerText = _firstNonEmpty([meta['banner_text']], bannerText);
          faqs = _parseFaqs(meta['faqs']);
        }
      }

      if (contactResponse.success && contactResponse.data is Map) {
        merged.addAll(Map<String, dynamic>.from(contactResponse.data as Map));
      }

      if (merged.isNotEmpty) {
        setState(() {
          _contact = merged;
          _bannerText = bannerText;
          _faqs = faqs;
          _isLoadingContact = false;
        });
      } else {
        setState(() => _isLoadingContact = false);
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoadingContact = false);
    }
  }

  String _contactValue(String key, String fallback) {
    final value = (_contact[key] ?? '').toString().trim();
    return value.isNotEmpty ? value : fallback;
  }

  String _firstNonEmpty(List<dynamic> values, String fallback) {
    for (final value in values) {
      final text = value?.toString().trim() ?? '';
      if (text.isNotEmpty) return text;
    }
    return fallback;
  }

  List<Map<String, String>> _parseFaqs(dynamic raw) {
    if (raw is! List) return [];
    final parsed = <Map<String, String>>[];
    for (final item in raw) {
      if (item is! Map) continue;
      final map = Map<String, dynamic>.from(item);
      final question = (map['question'] ?? '').toString().trim();
      final answer = (map['answer'] ?? '').toString().trim();
      if (question.isEmpty && answer.isEmpty) continue;
      parsed.add({'question': question, 'answer': answer});
    }
    return parsed;
  }

  String _digitsOnly(String value) {
    return value.replaceAll(RegExp(r'[^0-9+]'), '');
  }

  Future<void> _openUrl(String url) async {
    final uri = Uri.tryParse(url);
    if (uri == null) return;
    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('open_link_failed'))));
    }
  }

  Future<void> _openPhone() async {
    final phone = _digitsOnly(_contactValue('support_phone', '+966501234567'));
    await _openUrl('tel:$phone');
  }

  Future<void> _openWhatsapp() async {
    final raw = _contactValue('whatsapp', '+966501234567');
    final digits = raw.replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.isEmpty) return;
    await _openUrl('https://wa.me/$digits');
  }

  Future<void> _openEmail() async {
    final email = _contactValue('support_email', 'support@darfix.org');
    await _openUrl('mailto:$email');
  }

  @override
  Widget build(BuildContext context) {
    final defaultFaqs = List.generate(4, (index) {
      final number = index + 1;
      return {
        'question': context.tr('faq_question_$number'),
        'answer': context.tr('faq_answer_$number'),
      };
    });
    final faqs = _faqs.isNotEmpty ? _faqs : defaultFaqs;
    final helpBannerText = _bannerText.trim();

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('help_center')),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Contact Support Card
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Colors.blue, Colors.lightBlueAccent],
                ),
                borderRadius: BorderRadius.circular(20),
                boxShadow: AppShadows.md,
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.headset_mic,
                      color: Colors.white,
                      size: 32,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          context.tr('need_immediate_help'),
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _isLoadingContact
                              ? context.tr('support_available')
                              : _firstNonEmpty([
                                  helpBannerText,
                                  _contactValue(
                                    'support_phone',
                                    _contactValue('phone', '+966501234567'),
                                  ),
                                  context.tr('support_available'),
                                ], context.tr('support_available')),
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.9),
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  ElevatedButton(
                    onPressed: _openPhone,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: Colors.blue,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: Text(context.tr('call_us')),
                  ),
                ],
              ),
            ).animate().fadeIn().slideY(begin: 0.1, end: 0),

            const SizedBox(height: 12),

            if (_isLoadingContact)
              const LinearProgressIndicator(minHeight: 2)
            else
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _buildContactChip(
                    icon: Icons.chat,
                    label: _contactValue('whatsapp', '+966501234567'),
                    color: Colors.green,
                    onTap: _openWhatsapp,
                  ),
                  _buildContactChip(
                    icon: Icons.email_outlined,
                    label: _contactValue('support_email', 'support@darfix.org'),
                    color: Colors.indigo,
                    onTap: _openEmail,
                  ),
                  _buildContactChip(
                    icon: Icons.support_agent,
                    label: context.tr('complaints'),
                    color: Colors.orange,
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const ComplaintsListScreen(),
                        ),
                      );
                    },
                  ),
                ],
              ),

            const SizedBox(height: 32),

            Text(
              context.tr('faq'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),

            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: faqs.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final faq = faqs[index];
                return Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: AppShadows.sm,
                      ),
                      child: Theme(
                        data: Theme.of(
                          context,
                        ).copyWith(dividerColor: Colors.transparent),
                        child: ExpansionTile(
                          title: Text(
                            faq['question'] ?? '',
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                              color: AppColors.gray800,
                            ),
                          ),
                          childrenPadding: const EdgeInsets.fromLTRB(
                            16,
                            0,
                            16,
                            16,
                          ),
                          children: [
                            Text(
                              faq['answer'] ?? '',
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.gray600,
                                height: 1.5,
                              ),
                            ),
                          ],
                        ),
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
    );
  }

  Widget _buildContactChip({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: color.withValues(alpha: 0.3)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(color: color, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}
