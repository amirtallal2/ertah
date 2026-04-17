// Phone Login Screen
// شاشة تسجيل الدخول - تصميم جديد مع اللوجو وتعديل القائمة

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/localization_provider.dart';
import '../services/app_localizations.dart';
import '../widgets/app_logo.dart';

class PhoneLoginScreen extends StatefulWidget {
  final Function(String phone) onComplete;

  const PhoneLoginScreen({super.key, required this.onComplete});

  @override
  State<PhoneLoginScreen> createState() => _PhoneLoginScreenState();
}

class _PhoneLoginScreenState extends State<PhoneLoginScreen> {
  final TextEditingController _phoneController = TextEditingController();
  bool _isButtonEnabled = false;
  static const String _saudiCountryCode = '+966';

  bool _isLanguageMenuOpen = false;

  final List<Map<String, String>> _languages = [
    {'code': 'ar', 'name': 'عربي', 'flag': '🇸🇦'},
    {'code': 'en', 'name': 'English', 'flag': '🇬🇧'},
    {'code': 'ur', 'name': 'اردو', 'flag': '🇵🇰'},
  ];

  // Brand Color
  static const Color brandYellow = Color(0xFFFBCC26);
  static const Color darkText = Color(0xFF333333);
  static const Color grayText = Color(0xFF666666);
  static const Color lightGray = Color(0xFFE0E0E0);

  @override
  void initState() {
    super.initState();
    _phoneController.addListener(() {
      setState(() {
        _isButtonEnabled = _isSaudiMobileNumber(_phoneController.text);
      });
    });
  }

  String _normalizeSaudiLocalNumber(String value) {
    var digits = value.replaceAll(RegExp(r'\D'), '');
    if (digits.startsWith('966')) {
      digits = digits.substring(3);
    }
    return digits.replaceFirst(RegExp(r'^0+'), '');
  }

  bool _isSaudiMobileNumber(String value) {
    final local = _normalizeSaudiLocalNumber(value);
    return RegExp(r'^5\d{8}$').hasMatch(local);
  }

  void _selectLanguage(String code) {
    setState(() {
      _isLanguageMenuOpen = false;
    });

    Locale newLocale;
    switch (code) {
      case 'en':
        newLocale = const Locale('en', 'US');
        break;
      case 'ur':
        newLocale = const Locale('ur', 'PK');
        break;
      case 'ar':
      default:
        newLocale = const Locale('ar', 'SA');
        break;
    }

    context.read<LocalizationProvider>().setLocale(newLocale);

    /* 
    // SnackBar is not needed as the app will reload with new language
    String message = '';
    if (code == 'ar') {
      message = 'تم التغيير إلى العربية 🇸🇦';
    } else if (code == 'en') {
      message = 'Changed to English 🇬🇧';
    } else {
      message = 'اردو میں تبدیل ہو گیا 🇵🇰';
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.green,
        duration: const Duration(milliseconds: 1500),
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.only(top: 80, left: 20, right: 20),
      ),
    );
    */
  }

  @override
  Widget build(BuildContext ctx) {
    final screenHeight = MediaQuery.of(ctx).size.height;

    return Scaffold(
      backgroundColor: Colors.white,
      body: GestureDetector(
        onTap: () {
          if (_isLanguageMenuOpen) {
            setState(() => _isLanguageMenuOpen = false);
          }
          FocusScope.of(context).unfocus();
        },
        child: Stack(
          children: [
            // 1. Background Header (Yellow Wave)
            ClipPath(
              clipper: WaveClipper(),
              child: Container(
                height: screenHeight * 0.45,
                width: double.infinity,
                decoration: const BoxDecoration(color: brandYellow),
                child: Stack(
                  children: [
                    // Decorative waves in background
                    Positioned(
                      top: -50,
                      left: -100,
                      child: Container(
                        width: 300,
                        height: 300,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.white.withValues(alpha: 0.1),
                        ),
                      ),
                    ),
                    Positioned(
                      top: 50,
                      right: -80,
                      child: Container(
                        width: 200,
                        height: 200,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.white.withValues(alpha: 0.08),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // 2. Main Content
            SafeArea(
              child: SingleChildScrollView(
                physics: const ClampingScrollPhysics(),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight:
                        screenHeight - MediaQuery.of(context).padding.top,
                  ),
                  child: IntrinsicHeight(
                    child: Column(
                      children: [
                        // Spacer for Logo
                        SizedBox(height: screenHeight * 0.1),

                        // Logo Section
                        _buildLogo()
                            .animate()
                            .fadeIn(delay: 200.ms)
                            .scale(
                              begin: const Offset(0.8, 0.8),
                              end: const Offset(1, 1),
                            ),

                        // Spacer to push content down below the wave
                        SizedBox(height: screenHeight * 0.15),

                        // Login Form Section
                        Expanded(
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 24),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.center,
                              children: [
                                // Title
                                Text(
                                      context.tr('login'),
                                      style: const TextStyle(
                                        fontSize: 26,
                                        fontWeight: FontWeight.bold,
                                        color: darkText,
                                      ),
                                    )
                                    .animate()
                                    .fadeIn(delay: 300.ms)
                                    .slideY(begin: 0.1, end: 0),

                                const SizedBox(height: 12),

                                // Subtitle
                                Text(
                                      '${context.tr('send_otp_note_1')}\n${context.tr('send_otp_note_2')}',
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(
                                        fontSize: 14,
                                        color: grayText,
                                        height: 1.5,
                                      ),
                                    )
                                    .animate()
                                    .fadeIn(delay: 350.ms)
                                    .slideY(begin: 0.1, end: 0),

                                const SizedBox(height: 32),

                                // Phone Number Label
                                Align(
                                  alignment: Alignment.centerRight,
                                  child: Text(
                                    context.tr('phone_number'),
                                    style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w600,
                                      color: darkText,
                                    ),
                                  ),
                                ),

                                const SizedBox(height: 12),

                                // Phone Input Row
                                Row(
                                      children: [
                                        // Phone Input Field
                                        Expanded(
                                          child: Container(
                                            height: 56,
                                            decoration: BoxDecoration(
                                              color: Colors.grey.shade50,
                                              borderRadius:
                                                  BorderRadius.circular(12),
                                              border: Border.all(
                                                color: lightGray,
                                                width: 1,
                                              ),
                                            ),
                                            child: Row(
                                              children: [
                                                // Phone icon
                                                const Padding(
                                                  padding: EdgeInsets.symmetric(
                                                    horizontal: 12,
                                                  ),
                                                  child: Icon(
                                                    Icons.phone_android,
                                                    color: grayText,
                                                    size: 22,
                                                  ),
                                                ),
                                                // Input field
                                                Expanded(
                                                  child: TextField(
                                                    controller:
                                                        _phoneController,
                                                    keyboardType:
                                                        TextInputType.phone,
                                                    maxLength: 10,
                                                    textAlign: TextAlign.right,
                                                    textDirection:
                                                        TextDirection.ltr,
                                                    style: const TextStyle(
                                                      fontSize: 16,
                                                      fontWeight:
                                                          FontWeight.w500,
                                                      color: darkText,
                                                    ),
                                                    decoration: InputDecoration(
                                                      hintText: context.tr(
                                                        'please_enter_phone',
                                                      ),
                                                      hintStyle: TextStyle(
                                                        color: Colors
                                                            .grey
                                                            .shade400,
                                                        fontWeight:
                                                            FontWeight.normal,
                                                        fontSize: 14,
                                                      ),
                                                      counterText: '',
                                                      border: InputBorder.none,
                                                      contentPadding:
                                                          const EdgeInsets.symmetric(
                                                            horizontal: 12,
                                                            vertical: 16,
                                                          ),
                                                    ),
                                                    inputFormatters: [
                                                      FilteringTextInputFormatter
                                                          .digitsOnly,
                                                    ],
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ),

                                        const SizedBox(width: 12),

                                        // Country Code Dropdown
                                        Container(
                                          height: 56,
                                          padding: const EdgeInsets.symmetric(
                                            horizontal: 12,
                                          ),
                                          decoration: BoxDecoration(
                                            color: Colors.grey.shade50,
                                            borderRadius: BorderRadius.circular(
                                              12,
                                            ),
                                            border: Border.all(
                                              color: lightGray,
                                              width: 1,
                                            ),
                                          ),
                                          child: DropdownButtonHideUnderline(
                                            child: DropdownButton<String>(
                                              value: _saudiCountryCode,
                                              icon: const SizedBox.shrink(),
                                              items: const [
                                                DropdownMenuItem<String>(
                                                  value: _saudiCountryCode,
                                                  child: Row(
                                                    mainAxisSize:
                                                        MainAxisSize.min,
                                                    children: [
                                                      Text(
                                                        '🇸🇦',
                                                        style: TextStyle(
                                                          fontSize: 16,
                                                        ),
                                                      ),
                                                      SizedBox(width: 4),
                                                      Text(
                                                        '+966',
                                                        style: TextStyle(
                                                          fontWeight:
                                                              FontWeight.w600,
                                                          fontSize: 14,
                                                          color: darkText,
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                ),
                                              ],
                                              onChanged: null,
                                            ),
                                          ),
                                        ),
                                      ],
                                    )
                                    .animate()
                                    .fadeIn(delay: 400.ms)
                                    .slideY(begin: 0.1, end: 0),

                                const SizedBox(height: 24),

                                // Login Button
                                SizedBox(
                                      width: double.infinity,
                                      height: 56,
                                      child: ElevatedButton(
                                        onPressed: _isButtonEnabled
                                            ? () async {
                                                final phoneBase =
                                                    _normalizeSaudiLocalNumber(
                                                      _phoneController.text,
                                                    );
                                                if (!_isSaudiMobileNumber(
                                                  _phoneController.text,
                                                )) {
                                                  if (!mounted) return;
                                                  ScaffoldMessenger.of(
                                                    context,
                                                  ).showSnackBar(
                                                    SnackBar(
                                                      content: Text(
                                                        context.tr(
                                                          'invalid_sa_phone',
                                                        ),
                                                      ),
                                                    ),
                                                  );
                                                  return;
                                                }
                                                final fullPhone =
                                                    '$_saudiCountryCode$phoneBase';

                                                final authProvider = context
                                                    .read<AuthProvider>();
                                                final response =
                                                    await authProvider.sendOtp(
                                                      fullPhone,
                                                    );

                                                if (response.success) {
                                                  final data = response.data;
                                                  if (data != null &&
                                                      data['otp'] != null) {
                                                    if (!mounted) return;
                                                    ScaffoldMessenger.of(
                                                      context,
                                                    ).showSnackBar(
                                                      SnackBar(
                                                        content: Text(
                                                          '${context.tr('verify_code')}: ${data['otp']}',
                                                        ),
                                                        duration:
                                                            const Duration(
                                                              seconds: 10,
                                                            ),
                                                      ),
                                                    );
                                                  }
                                                  widget.onComplete(fullPhone);
                                                } else {
                                                  if (!mounted) return;
                                                  ScaffoldMessenger.of(
                                                    context,
                                                  ).showSnackBar(
                                                    SnackBar(
                                                      content: Text(
                                                        response.message ??
                                                            context.tr(
                                                              'verification_failed',
                                                            ),
                                                      ),
                                                    ),
                                                  );
                                                }
                                              }
                                            : null,
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: brandYellow,
                                          foregroundColor: Colors.white,
                                          disabledBackgroundColor: brandYellow
                                              .withValues(alpha: 0.5),
                                          disabledForegroundColor:
                                              Colors.white70,
                                          elevation: 0,
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(
                                              12,
                                            ),
                                          ),
                                        ),
                                        child:
                                            context
                                                .watch<AuthProvider>()
                                                .isLoading
                                            ? const SizedBox(
                                                height: 20,
                                                width: 20,
                                                child:
                                                    CircularProgressIndicator(
                                                      color: Colors.white,
                                                      strokeWidth: 2,
                                                    ),
                                              )
                                            : Text(
                                                context.tr('login'),
                                                style: const TextStyle(
                                                  fontSize: 18,
                                                  fontWeight: FontWeight.bold,
                                                ),
                                              ),
                                      ),
                                    )
                                    .animate()
                                    .fadeIn(delay: 450.ms)
                                    .slideY(begin: 0.1, end: 0),

                                const SizedBox(height: 24),

                                // Create Account Link
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    GestureDetector(
                                      onTap: () {
                                        ScaffoldMessenger.of(
                                          context,
                                        ).showSnackBar(
                                          SnackBar(
                                            content: Text(
                                              context.tr('please_enter_phone'),
                                            ),
                                          ),
                                        );
                                      },
                                      child: Text(
                                        context.tr('create_account'),
                                        style: TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.bold,
                                          color: brandYellow.withValues(
                                            alpha: 0.9,
                                          ),
                                        ),
                                      ),
                                    ),
                                    const Text(
                                      ' ؟ ', // Consider localizing separator if needed, but '?' is usually universal, spacing might differ
                                      style: TextStyle(
                                        fontSize: 14,
                                        color: grayText,
                                      ),
                                    ),
                                    Text(
                                      context.tr('dont_have_account'),
                                      style: const TextStyle(
                                        fontSize: 14,
                                        color: grayText,
                                      ),
                                    ),
                                  ],
                                ).animate().fadeIn(delay: 500.ms),

                                const SizedBox(height: 16),

                                // Contact Support Link
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    GestureDetector(
                                      onTap: () {
                                        ScaffoldMessenger.of(
                                          context,
                                        ).showSnackBar(
                                          SnackBar(
                                            content: Text(
                                              context.tr('contact_us'),
                                            ),
                                          ),
                                        );
                                      },
                                      child: Text(
                                        context.tr('contact_us'),
                                        style: TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.bold,
                                          color: brandYellow.withValues(
                                            alpha: 0.9,
                                          ),
                                        ),
                                      ),
                                    ),
                                    Text(
                                      ' ${context.tr('problem_registering')} ',
                                      style: const TextStyle(
                                        fontSize: 14,
                                        color: grayText,
                                      ),
                                    ),
                                  ],
                                ).animate().fadeIn(delay: 550.ms),

                                const SizedBox(height: 24),

                                // Guest Login Link
                                GestureDetector(
                                  onTap: () {
                                    context
                                        .read<AuthProvider>()
                                        .continueAsGuest();
                                  },
                                  child: Text(
                                    context.tr('login_as_guest'),
                                    style: const TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.bold,
                                      color: brandYellow,
                                    ),
                                  ),
                                ).animate().fadeIn(delay: 600.ms),

                                const Spacer(),

                                // Bottom Indicator
                                Container(
                                  margin: const EdgeInsets.only(bottom: 16),
                                  width: 130,
                                  height: 5,
                                  decoration: BoxDecoration(
                                    color: Colors.grey.shade300,
                                    borderRadius: BorderRadius.circular(3),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),

            // 3. Language Selector (Fixed Position)
            Positioned(
              top: MediaQuery.of(context).padding.top + 12,
              left: 16,
              child: GestureDetector(
                onTap: () =>
                    setState(() => _isLanguageMenuOpen = !_isLanguageMenuOpen),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 10,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(25),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.1),
                        blurRadius: 10,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // Translate icon
                      Container(
                        width: 24,
                        height: 24,
                        decoration: BoxDecoration(
                          color: brandYellow,
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: const Icon(
                          Icons.translate,
                          size: 16,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        _languages.firstWhere(
                          (l) =>
                              l['code'] ==
                              context
                                  .read<LocalizationProvider>()
                                  .locale
                                  .languageCode,
                          orElse: () => _languages.first,
                        )['name']!,
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: darkText,
                        ),
                      ),
                      const SizedBox(width: 4),
                      Icon(
                        _isLanguageMenuOpen
                            ? Icons.keyboard_arrow_up
                            : Icons.keyboard_arrow_down,
                        size: 16,
                        color: grayText,
                      ),
                    ],
                  ),
                ),
              ).animate().fadeIn(delay: 100.ms).slideX(begin: 0.1, end: 0),
            ),

            // 4. Dropdown Menu (Fixed Position relative to button)
            if (_isLanguageMenuOpen)
              Positioned(
                top: MediaQuery.of(context).padding.top + 60,
                left: 16,
                child:
                    Material(
                          elevation: 8,
                          borderRadius: BorderRadius.circular(16),
                          color: Colors.white,
                          child: Container(
                            width: 150,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: _languages.map((lang) {
                                final isSelected =
                                    context
                                        .watch<LocalizationProvider>()
                                        .locale
                                        .languageCode ==
                                    lang['code'];
                                return InkWell(
                                  onTap: () => _selectLanguage(lang['code']!),
                                  borderRadius: BorderRadius.circular(16),
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 16,
                                      vertical: 14,
                                    ),
                                    decoration: BoxDecoration(
                                      color: isSelected
                                          ? brandYellow.withValues(alpha: 0.1)
                                          : Colors.transparent,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Row(
                                      children: [
                                        Text(
                                          lang['flag']!,
                                          style: const TextStyle(fontSize: 18),
                                        ),
                                        const SizedBox(width: 12),
                                        Text(
                                          lang['name']!,
                                          style: TextStyle(
                                            fontSize: 14,
                                            color: darkText,
                                            fontWeight: isSelected
                                                ? FontWeight.bold
                                                : FontWeight.normal,
                                          ),
                                        ),
                                        if (isSelected) ...[
                                          const Spacer(),
                                          const Icon(
                                            Icons.check,
                                            size: 18,
                                            color: brandYellow,
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                );
                              }).toList(),
                            ),
                          ),
                        )
                        .animate()
                        .fadeIn(duration: 200.ms)
                        .slideY(begin: -0.1, end: 0),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildLogo() {
    return Container(
      width: 140,
      height: 140,
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Center(
        child: Container(
          width: 120,
          height: 120,
          decoration: const BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
          ),
          child: Padding(
            padding: const EdgeInsets.all(25.0),
            child: const AppLogo(fit: BoxFit.contain),
          ),
        ),
      ),
    );
  }
}

// Wave Clipper for the yellow header
class WaveClipper extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    Path path = Path();
    path.lineTo(0, size.height * 0.75);

    // Create wave effect
    var firstControlPoint = Offset(size.width * 0.25, size.height);
    var firstEndPoint = Offset(size.width * 0.5, size.height * 0.85);

    var secondControlPoint = Offset(size.width * 0.75, size.height * 0.7);
    var secondEndPoint = Offset(size.width, size.height * 0.85);

    path.quadraticBezierTo(
      firstControlPoint.dx,
      firstControlPoint.dy,
      firstEndPoint.dx,
      firstEndPoint.dy,
    );

    path.quadraticBezierTo(
      secondControlPoint.dx,
      secondControlPoint.dy,
      secondEndPoint.dx,
      secondEndPoint.dy,
    );

    path.lineTo(size.width, 0);
    path.close();

    return path;
  }

  @override
  bool shouldReclip(CustomClipper<Path> oldClipper) => false;
}
