import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/localization_provider.dart';
import '../services/app_localizations.dart';
import '../widgets/app_logo.dart';

class PhoneLoginScreen extends StatefulWidget {
  final ValueChanged<String> onComplete;

  const PhoneLoginScreen({super.key, required this.onComplete});

  @override
  State<PhoneLoginScreen> createState() => _PhoneLoginScreenState();
}

class _PhoneLoginScreenState extends State<PhoneLoginScreen> {
  final TextEditingController _phoneController = TextEditingController();
  static const String _saudiCountryCode = '+966';
  bool _canSubmit = false;
  final List<Map<String, String>> _languages = [
    {'code': 'ar', 'name': 'عربي', 'flag': '🇸🇦'},
    {'code': 'en', 'name': 'English', 'flag': '🇬🇧'},
    {'code': 'ur', 'name': 'اردو', 'flag': '🇵🇰'},
  ];

  @override
  void initState() {
    super.initState();
    _phoneController.addListener(_onPhoneChanged);
  }

  @override
  void dispose() {
    _phoneController
      ..removeListener(_onPhoneChanged)
      ..dispose();
    super.dispose();
  }

  void _onPhoneChanged() {
    final canSubmit = _isSaudiMobileNumber(_phoneController.text);
    if (canSubmit == _canSubmit) return;
    setState(() => _canSubmit = canSubmit);
  }

  String _digitsOnly(String value) {
    return value.replaceAll(RegExp(r'\D'), '');
  }

  String _normalizeSaudiLocalNumber(String value) {
    var digits = _digitsOnly(value);
    if (digits.startsWith('966')) {
      digits = digits.substring(3);
    }
    return digits.replaceFirst(RegExp(r'^0+'), '');
  }

  bool _isSaudiMobileNumber(String value) {
    final local = _normalizeSaudiLocalNumber(value);
    return RegExp(r'^5\d{8}$').hasMatch(local);
  }

  String _fullPhone() {
    final digits = _normalizeSaudiLocalNumber(_phoneController.text);
    return '$_saudiCountryCode$digits';
  }

  void _selectLanguage(String code) {
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
    context.read<LocalizationProvider>().setLocale(locale);
  }

  Future<void> _sendOtp() async {
    if (!_isSaudiMobileNumber(_phoneController.text)) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('invalid_sa_phone'))));
      return;
    }

    final fullPhone = _fullPhone();
    final authProvider = context.read<AuthProvider>();
    final response = await authProvider.sendOtp(fullPhone);

    if (!mounted) return;

    if (response.success) {
      final data = response.data;
      if (data is Map<String, dynamic> && data['otp'] != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${context.tr('verification_code')}: ${data['otp']}'),
            duration: const Duration(seconds: 8),
          ),
        );
      }
      widget.onComplete(fullPhone);
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(response.message ?? context.tr('send_otp_failed')),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isLoading = context.watch<AuthProvider>().isLoading;
    final currentLanguage = context
        .watch<LocalizationProvider>()
        .locale
        .languageCode;
    final currentLangData = _languages.firstWhere(
      (lang) => lang['code'] == currentLanguage,
      orElse: () => _languages.first,
    );

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 460),
              child: Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: const BorderSide(color: AppColors.gray200),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Align(
                        alignment: AlignmentDirectional.centerEnd,
                        child: PopupMenuButton<String>(
                          onSelected: _selectLanguage,
                          itemBuilder: (_) => _languages
                              .map(
                                (lang) => PopupMenuItem<String>(
                                  value: lang['code'],
                                  child: Text(
                                    '${lang['flag']} ${lang['name']}',
                                  ),
                                ),
                              )
                              .toList(),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.gray50,
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: AppColors.gray200),
                            ),
                            child: Text(
                              '${currentLangData['flag']} ${context.tr('language')}',
                              style: Theme.of(context).textTheme.labelMedium,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Container(
                        width: 84,
                        height: 84,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.gray200),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.06),
                              blurRadius: 14,
                              offset: const Offset(0, 6),
                            ),
                          ],
                        ),
                        child: const AppLogo(fit: BoxFit.contain),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        context.tr('provider_login_title'),
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        context.tr('provider_login_subtitle'),
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(height: 20),
                      Text(
                        context.tr('phone_number'),
                        style: Theme.of(context).textTheme.labelLarge,
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                            decoration: BoxDecoration(
                              color: AppColors.gray50,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: AppColors.gray200),
                            ),
                            child: DropdownButtonHideUnderline(
                              child: DropdownButton<String>(
                                value: _saudiCountryCode,
                                items: const [
                                  DropdownMenuItem(
                                    value: _saudiCountryCode,
                                    child: Text('+966'),
                                  ),
                                ],
                                onChanged: null,
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextField(
                              controller: _phoneController,
                              enabled: !isLoading,
                              keyboardType: TextInputType.phone,
                              maxLength: 10,
                              textDirection: TextDirection.ltr,
                              inputFormatters: [
                                FilteringTextInputFormatter.digitsOnly,
                              ],
                              decoration: const InputDecoration(
                                hintText: '5XXXXXXXX',
                                prefixIcon: Icon(Icons.phone_outlined),
                                counterText: '',
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),
                      SizedBox(
                        height: 52,
                        child: ElevatedButton(
                          onPressed: (!isLoading && _canSubmit)
                              ? _sendOtp
                              : null,
                          child: isLoading
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : Text(context.tr('send_otp')),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
