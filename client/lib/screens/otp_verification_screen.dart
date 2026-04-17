// OTP Verification Screen
// شاشة التحقق من رمز OTP

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'dart:async';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/app_localizations.dart';

class OTPVerificationScreen extends StatefulWidget {
  final String phone;
  final VoidCallback onComplete;

  const OTPVerificationScreen({
    super.key,
    required this.phone,
    required this.onComplete,
  });

  @override
  State<OTPVerificationScreen> createState() => _OTPVerificationScreenState();
}

class _OTPVerificationScreenState extends State<OTPVerificationScreen> {
  static final RegExp _otpInputRegex = RegExp(r'[0-9٠-٩۰-۹]');

  final List<TextEditingController> _controllers = List.generate(
    AppConfig.otpLength,
    (_) => TextEditingController(),
  );
  final List<FocusNode> _focusNodes = List.generate(
    AppConfig.otpLength,
    (_) => FocusNode(),
  );
  int _timer = AppConfig.otpResendTimeout;
  Timer? _countdownTimer;
  bool _isComplete = false;

  @override
  void initState() {
    super.initState();
    _startTimer();
    // Focus first input
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _focusNodes[0].requestFocus();
    });
  }

  void _startTimer() {
    _countdownTimer?.cancel();
    _timer = AppConfig.otpResendTimeout;
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_timer > 0) {
        setState(() => _timer--);
      } else {
        timer.cancel();
      }
    });
  }

  String _normalizeOtpDigits(String value) {
    const arabicIndicStart = 0x0660;
    const easternArabicIndicStart = 0x06F0;
    final buffer = StringBuffer();

    for (final rune in value.runes) {
      if (rune >= 0x0660 && rune <= 0x0669) {
        buffer.writeCharCode(0x30 + (rune - arabicIndicStart));
      } else if (rune >= 0x06F0 && rune <= 0x06F9) {
        buffer.writeCharCode(0x30 + (rune - easternArabicIndicStart));
      } else {
        buffer.writeCharCode(rune);
      }
    }

    return buffer.toString();
  }

  void _onCodeChanged(String value, int index) {
    if (value.length == 1 && index < _focusNodes.length - 1) {
      _focusNodes[index + 1].requestFocus();
    }

    // Check completion
    setState(() {
      _isComplete = _controllers.every(
        (controller) => controller.text.isNotEmpty,
      );
    });

    if (_isComplete) {
      FocusScope.of(context).unfocus();
    }
  }

  void _clearCode() {
    for (var c in _controllers) {
      c.clear();
    }

    _isComplete = false;
    if (_focusNodes.isNotEmpty) {
      _focusNodes[0].requestFocus();
    }
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    for (var c in _controllers) {
      c.dispose();
    }
    for (var f in _focusNodes) {
      f.dispose();
    }
    super.dispose();
  }

  Future<void> _verifyCode() async {
    final authProvider = context.read<AuthProvider>();
    final code = _normalizeOtpDigits(_controllers.map((c) => c.text).join());
    final result = await authProvider.verifyOtp(widget.phone, code);

    if (!mounted) return;

    if (result.success) {
      widget.onComplete();
      return;
    }

    final message = result.message ?? context.tr('invalid_verification_code');
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red),
    );
  }

  Future<void> _resendCode() async {
    if (_timer > 0) return;

    final authProvider = context.read<AuthProvider>();
    final response = await authProvider.sendOtp(widget.phone);
    if (!mounted) return;

    if (response.success) {
      setState(() {
        _clearCode();
        _startTimer();
      });

      final data = response.data;
      if (data is Map<String, dynamic> && data['otp'] != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${context.tr('verification_code')}: ${data['otp']}'),
            duration: const Duration(seconds: 8),
          ),
        );
      }
      return;
    }

    final message = response.message ?? context.tr('send_otp_failed');
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red),
    );
  }

  @override
  Widget build(BuildContext ctx) {
    final mediaQuery = MediaQuery.of(context);
    final keyboardInset = mediaQuery.viewInsets.bottom;
    final safeBottom = mediaQuery.padding.bottom;
    final isKeyboardVisible = keyboardInset > 0;
    final isLoading = context.watch<AuthProvider>().isLoading;

    return Scaffold(
      resizeToAvoidBottomInset: false,
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              const Color(0xFFFBCC26).withValues(alpha: 0.1),
              const Color(0xFFFBCC26).withValues(alpha: 0.05),
              Colors.white,
            ],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: EdgeInsets.only(bottom: isKeyboardVisible ? 16 : 24),
                  child: Column(
                    children: [
                      SizedBox(height: isKeyboardVisible ? 24 : 80),

                      // Simple Back Button
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 24),
                        child: Align(
                          alignment: Alignment.centerRight,
                          child: GestureDetector(
                            onTap: () => Navigator.pop(context),
                            child: Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                shape: BoxShape.circle,
                                border: Border.all(color: Colors.grey.shade200),
                              ),
                              child: const Icon(
                                Icons.close,
                                size: 16,
                                color: Colors.grey,
                              ),
                            ),
                          ),
                        ),
                      ),

                      const SizedBox(height: 24),

                      // Header
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 24),
                        child: Align(
                          alignment: Alignment.centerRight,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Text(
                                    '🔐',
                                    style: TextStyle(fontSize: 24),
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    context.tr('confirmation_code'),
                                    style: const TextStyle(
                                      fontSize: 24,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.gray800,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                context.tr('enter_6_digit_code'),
                                style: const TextStyle(
                                  fontSize: 14,
                                  color: AppColors.gray500,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                widget.phone,
                                textDirection: TextDirection.ltr,
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFF7466ED),
                                  letterSpacing: 1,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ).animate().fadeIn().slideX(begin: 0.1, end: 0),

                      const SizedBox(height: 48),

                      // OTP Inputs (LTR)
                      Directionality(
                        textDirection: TextDirection.ltr,
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: List.generate(_controllers.length, (index) {
                            return Container(
                                  margin: const EdgeInsets.symmetric(
                                    horizontal: 4,
                                  ),
                                  width: 45,
                                  height: 55,
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(16),
                                    border: Border.all(
                                      color: _controllers[index].text.isNotEmpty
                                          ? const Color(0xFF7466ED)
                                          : const Color(
                                              0xFFFBCC26,
                                            ).withValues(alpha: 0.3),
                                      width: 2,
                                    ),
                                    boxShadow: [
                                      BoxShadow(
                                        color:
                                            _controllers[index].text.isNotEmpty
                                            ? const Color(
                                                0xFF7466ED,
                                              ).withValues(alpha: 0.25)
                                            : Colors.transparent,
                                        blurRadius: 12,
                                        offset: const Offset(0, 4),
                                      ),
                                    ],
                                  ),
                                  child: TextField(
                                    controller: _controllers[index],
                                    focusNode: _focusNodes[index],
                                    keyboardType: TextInputType.number,
                                    textAlign: TextAlign.center,
                                    maxLength: 1,
                                    style: const TextStyle(
                                      fontSize: 24,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.gray800,
                                    ),
                                    decoration: const InputDecoration(
                                      counterText: '',
                                      border: InputBorder.none,
                                      enabledBorder: InputBorder.none,
                                      focusedBorder: InputBorder.none,
                                      errorBorder: InputBorder.none,
                                      disabledBorder: InputBorder.none,
                                      filled: false,
                                      contentPadding: EdgeInsets.zero,
                                    ),
                                    inputFormatters: [
                                      FilteringTextInputFormatter.allow(
                                        _otpInputRegex,
                                      ),
                                    ],
                                    onChanged: (value) =>
                                        _onCodeChanged(value, index),
                                  ),
                                )
                                .animate(
                                  target: _controllers[index].text.isNotEmpty
                                      ? 1
                                      : 0,
                                )
                                .scale(
                                  begin: const Offset(1, 1),
                                  end: const Offset(1.05, 1.05),
                                );
                          }),
                        ),
                      ).animate().fadeIn(delay: 200.ms),

                      const SizedBox(height: 32),

                      // Timer/Resend
                      if (_timer > 0)
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Text('⏱️ ', style: TextStyle(fontSize: 16)),
                            Text(
                              context
                                  .tr('resend_after_seconds')
                                  .replaceAll('{}', '$_timer'),
                              style: const TextStyle(color: AppColors.gray500),
                            ),
                          ],
                        ).animate().fadeIn()
                      else
                        TextButton.icon(
                          onPressed: isLoading ? null : _resendCode,
                          icon: const Icon(
                            Icons.refresh,
                            color: Color(0xFF7466ED),
                          ),
                          label: Text(
                            context.tr('resend_code_action'),
                            style: const TextStyle(
                              color: Color(0xFF7466ED),
                              fontWeight: FontWeight.bold,
                              decoration: TextDecoration.underline,
                            ),
                          ),
                        ).animate().fadeIn(),

                      if (!isKeyboardVisible) ...[
                        const SizedBox(height: 32),

                        // Info Box
                        Container(
                              margin: const EdgeInsets.symmetric(
                                horizontal: 24,
                              ),
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  colors: [
                                    const Color(
                                      0xFFFBCC26,
                                    ).withValues(alpha: 0.2),
                                    const Color(
                                      0xFFF5C01F,
                                    ).withValues(alpha: 0.2),
                                  ],
                                ),
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                  color: const Color(
                                    0xFFFBCC26,
                                  ).withValues(alpha: 0.3),
                                ),
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    '💡',
                                    style: TextStyle(fontSize: 20),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Text(
                                      context.tr('didnt_receive_code_hint'),
                                      style: const TextStyle(
                                        fontSize: 13,
                                        color: AppColors.gray600,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            )
                            .animate()
                            .fadeIn(delay: 400.ms)
                            .slideY(begin: 0.1, end: 0),
                      ],
                    ],
                  ),
                ),
              ),
              AnimatedPadding(
                duration: const Duration(milliseconds: 220),
                curve: Curves.easeOut,
                padding: EdgeInsets.fromLTRB(
                  24,
                  8,
                  24,
                  (keyboardInset > 0 ? keyboardInset : safeBottom) + 16,
                ),
                child: Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        const Color(0xFFFBCC26).withValues(alpha: 0.05),
                        Colors.white,
                      ],
                    ),
                  ),
                  child: Column(
                    children: [
                      SizedBox(
                        width: double.infinity,
                        height: 56,
                        child: ElevatedButton(
                          onPressed: (_isComplete && !isLoading)
                              ? _verifyCode
                              : null,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFFBCC26),
                            foregroundColor: Colors.white,
                            disabledBackgroundColor: AppColors.gray200,
                            disabledForegroundColor: AppColors.gray400,
                            elevation: _isComplete ? 4 : 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
                          ),
                          child: isLoading
                              ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child: CircularProgressIndicator(
                                    color: Colors.white,
                                    strokeWidth: 2,
                                  ),
                                )
                              : Text(
                                  context.tr('confirm_code'),
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                        ),
                      ),
                      if (!isKeyboardVisible) ...[
                        const SizedBox(height: 16),
                        Container(
                          width: 130,
                          height: 4,
                          decoration: BoxDecoration(
                            color: AppColors.gray300,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
