import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../services/app_localizations.dart';

class OTPVerificationScreen extends StatefulWidget {
  final String phone;
  final VoidCallback onComplete;
  final VoidCallback onChangePhone;

  const OTPVerificationScreen({
    super.key,
    required this.phone,
    required this.onComplete,
    required this.onChangePhone,
  });

  @override
  State<OTPVerificationScreen> createState() => _OTPVerificationScreenState();
}

class _OTPVerificationScreenState extends State<OTPVerificationScreen> {
  static final RegExp _otpInputRegex = RegExp(r'[0-9٠-٩۰-۹]');

  late final List<TextEditingController> _controllers;
  late final List<FocusNode> _focusNodes;
  int _secondsLeft = AppConfig.otpResendTimeout;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _controllers = List.generate(
      AppConfig.otpLength,
      (_) => TextEditingController(),
    );
    _focusNodes = List.generate(AppConfig.otpLength, (_) => FocusNode());
    _startTimer();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_focusNodes.isNotEmpty) {
        _focusNodes.first.requestFocus();
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    for (final controller in _controllers) {
      controller.dispose();
    }
    for (final focusNode in _focusNodes) {
      focusNode.dispose();
    }
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    _secondsLeft = AppConfig.otpResendTimeout;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_secondsLeft <= 1) {
        setState(() => _secondsLeft = 0);
        timer.cancel();
      } else {
        setState(() => _secondsLeft--);
      }
    });
  }

  bool get _isCodeComplete =>
      _controllers.every((controller) => controller.text.isNotEmpty);

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

  String get _otpCode => _normalizeOtpDigits(
    _controllers.map((controller) => controller.text).join(),
  );

  void _onCodeChanged(int index, String value) {
    if (value.isNotEmpty && index < _focusNodes.length - 1) {
      _focusNodes[index + 1].requestFocus();
    } else if (value.isEmpty && index > 0) {
      _focusNodes[index - 1].requestFocus();
    }
    setState(() {});
  }

  void _clearCode() {
    for (final controller in _controllers) {
      controller.clear();
    }
    if (_focusNodes.isNotEmpty) {
      _focusNodes.first.requestFocus();
    }
    setState(() {});
  }

  Future<void> _verifyCode() async {
    if (!_isCodeComplete) return;
    final authProvider = context.read<AuthProvider>();
    final result = await authProvider.verifyOtp(widget.phone, _otpCode);

    if (!mounted) return;

    if (result.success) {
      widget.onComplete();
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(result.message ?? context.tr('verification_failed')),
      ),
    );
  }

  Future<void> _resendCode() async {
    if (_secondsLeft > 0) return;

    final authProvider = context.read<AuthProvider>();
    final response = await authProvider.sendOtp(widget.phone);
    if (!mounted) return;

    if (response.success) {
      _clearCode();
      _startTimer();

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

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(response.message ?? context.tr('send_otp_failed')),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isLoading = context.watch<AuthProvider>().isLoading;
    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('verify_code')),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: isLoading ? null : widget.onChangePhone,
        ),
      ),
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
                      Text(
                        context.tr('code_sent_to'),
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        widget.phone,
                        textAlign: TextAlign.center,
                        textDirection: TextDirection.ltr,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: AppColors.secondaryDark,
                              fontWeight: FontWeight.w700,
                            ),
                      ),
                      const SizedBox(height: 18),
                      Directionality(
                        textDirection: TextDirection.ltr,
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: List.generate(_controllers.length, (index) {
                            return Container(
                              width: 52,
                              margin: const EdgeInsets.symmetric(horizontal: 5),
                              child: TextField(
                                controller: _controllers[index],
                                focusNode: _focusNodes[index],
                                enabled: !isLoading,
                                textAlign: TextAlign.center,
                                keyboardType: TextInputType.number,
                                maxLength: 1,
                                style: Theme.of(context).textTheme.titleLarge
                                    ?.copyWith(fontWeight: FontWeight.w700),
                                inputFormatters: [
                                  FilteringTextInputFormatter.allow(
                                    _otpInputRegex,
                                  ),
                                ],
                                decoration: const InputDecoration(
                                  counterText: '',
                                ),
                                onChanged: (value) =>
                                    _onCodeChanged(index, value),
                              ),
                            );
                          }),
                        ),
                      ),
                      const SizedBox(height: 14),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          TextButton(
                            onPressed: (isLoading || _secondsLeft > 0)
                                ? null
                                : _resendCode,
                            child: Text(context.tr('resend_code')),
                          ),
                          Text(
                            _secondsLeft > 0 ? '($_secondsLeft)' : '',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        height: 52,
                        child: ElevatedButton(
                          onPressed: (!isLoading && _isCodeComplete)
                              ? _verifyCode
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
                              : Text(context.tr('confirm')),
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
