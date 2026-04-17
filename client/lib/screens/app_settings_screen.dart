// App Settings Screen
// شاشة الإعدادات (اللغة فقط حالياً)

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/localization_provider.dart';
import '../services/app_localizations.dart';
import '../services/user_service.dart';
import 'privacy_policy_screen.dart';
import 'terms_and_conditions_screen.dart';

class AppSettingsScreen extends StatefulWidget {
  const AppSettingsScreen({super.key});

  @override
  State<AppSettingsScreen> createState() => _AppSettingsScreenState();
}

class _AppSettingsScreenState extends State<AppSettingsScreen> {
  final UserService _userService = UserService();
  bool _isDeletingAccount = false;

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    final localizationProvider = context.watch<LocalizationProvider>();
    final currentLanguage = localizationProvider.locale.languageCode;

    String languageName = 'العربية';
    if (currentLanguage == 'en') languageName = 'English';
    if (currentLanguage == 'ur') languageName = 'اردو';

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('settings')),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            _buildSettingsCard(
              children: [
                ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFE6F4EA),
                    child: Icon(Icons.language, color: Color(0xFF1E8E3E)),
                  ),
                  title: Text(
                    context.tr('language'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text(languageName),
                  trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                  onTap: () => _showLanguageDialog(context),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _buildSettingsCard(
              children: [
                ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFEAF1FF),
                    child: Icon(Icons.privacy_tip_outlined, color: Colors.blue),
                  ),
                  title: Text(
                    context.tr('privacy_policy'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const PrivacyPolicyScreen(),
                    ),
                  ),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFF4F1EA),
                    child: Icon(Icons.description_outlined, color: Colors.brown),
                  ),
                  title: Text(
                    context.tr('terms_and_conditions'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const TermsAndConditionsScreen(),
                    ),
                  ),
                ),
                if (authProvider.canAccessFeatures) ...[
                  const Divider(height: 1),
                  ListTile(
                    leading: const CircleAvatar(
                      backgroundColor: Color(0xFFFFEBEE),
                      child: Icon(Icons.delete_outline, color: Colors.red),
                    ),
                    title: Text(
                      context.tr('delete_account'),
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        color: Colors.red,
                      ),
                    ),
                    trailing: _isDeletingAccount
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.arrow_forward_ios, size: 16),
                    onTap: _isDeletingAccount
                        ? null
                        : () => _confirmDeleteAccount(context),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _showLanguageDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (dialogContext) => SimpleDialog(
        title: Text(context.tr('language')),
        children: [
          _buildLanguageOption(dialogContext, 'العربية', 'ar', 'SA'),
          _buildLanguageOption(dialogContext, 'English', 'en', 'US'),
          _buildLanguageOption(dialogContext, 'اردو', 'ur', 'PK'),
        ],
      ),
    );
  }

  Widget _buildLanguageOption(
    BuildContext context,
    String name,
    String code,
    String country,
  ) {
    final localizationProvider = context.read<LocalizationProvider>();
    final isSelected = localizationProvider.locale.languageCode == code;

    return SimpleDialogOption(
      onPressed: () {
        localizationProvider.setLocale(Locale(code, country));
        Navigator.pop(context);
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              name,
              style: TextStyle(
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                color: isSelected ? AppColors.primary : Colors.black,
              ),
            ),
            if (isSelected)
              const Icon(Icons.check, color: AppColors.primary, size: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildSettingsCard({required List<Widget> children}) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        children: children,
      ),
    );
  }

  Future<void> _confirmDeleteAccount(BuildContext context) async {
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
    final response = await _userService.deleteAccount(
      reason: controller.text.trim(),
    );

    if (!mounted) return;
    setState(() => _isDeletingAccount = false);

    if (response.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('delete_account_success'))),
      );
      await context.read<AuthProvider>().logout();
      if (mounted) {
        Navigator.of(context).popUntil((route) => route.isFirst);
      }
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
}
