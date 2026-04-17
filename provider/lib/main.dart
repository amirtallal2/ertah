import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'config/app_theme.dart';
import 'config/responsive_layout.dart';
import 'providers/auth_provider.dart';
import 'providers/app_settings_provider.dart';
import 'providers/location_provider.dart';
import 'providers/localization_provider.dart';
import 'services/app_localizations.dart';
import 'services/push_notification_service.dart';
import 'screens/home_screen.dart';
import 'screens/location_picker_screen.dart';
import 'screens/otp_verification_screen.dart';
import 'screens/order_details_screen.dart';
import 'screens/phone_login_screen.dart';
import 'screens/provider_profile_setup_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      systemNavigationBarColor: AppColors.white,
      systemNavigationBarDividerColor: Colors.transparent,
      systemNavigationBarIconBrightness: Brightness.dark,
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
    ),
  );
  await PushNotificationService.instance.initialize();
  runApp(const ProviderApp());
}

class ProviderApp extends StatelessWidget {
  const ProviderApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => AppSettingsProvider()),
        ChangeNotifierProvider(create: (_) => LocationProvider()),
        ChangeNotifierProvider(create: (_) => LocalizationProvider()),
      ],
      child: Consumer2<LocalizationProvider, AppSettingsProvider>(
        builder: (context, localizationProvider, appSettingsProvider, _) {
          final locale = localizationProvider.locale;
          final isLtr = locale.languageCode == 'en';
          final baseName = appSettingsProvider.appName.trim();
          final fallbackBase = 'Darfix';
          final effectiveBase =
              baseName.isNotEmpty ? baseName : fallbackBase;
          final appTitle = switch (locale.languageCode) {
            'en' => '$effectiveBase Provider',
            'ur' => '$effectiveBase سروس فراہم کنندہ',
            _ => 'مقدم خدمة $effectiveBase',
          };

          return MaterialApp(
            title: appTitle,
            debugShowCheckedModeBanner: false,
            theme: AppTheme.lightTheme,
            darkTheme: AppTheme.darkTheme,
            themeMode: ThemeMode.light,
            home: const AppNavigator(),
            locale: locale,
            supportedLocales: const [
              Locale('ar', 'SA'),
              Locale('en', 'US'),
              Locale('ur', 'PK'),
            ],
            localizationsDelegates: const [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            builder: (context, child) {
              if (child == null) return const SizedBox.shrink();
              return Directionality(
                textDirection: isLtr ? TextDirection.ltr : TextDirection.rtl,
                child: ResponsiveRoot(child: child),
              );
            },
          );
        },
      ),
    );
  }
}

enum AppScreen {
  loading,
  phoneLogin,
  otpVerification,
  locationPicker,
  profileSetup,
  home,
}

class AppNavigator extends StatefulWidget {
  const AppNavigator({super.key});

  @override
  State<AppNavigator> createState() => _AppNavigatorState();
}

class _AppNavigatorState extends State<AppNavigator> {
  final PushNotificationService _pushService = PushNotificationService.instance;
  AppScreen _currentScreen = AppScreen.loading;
  String _phone = '';
  late final AuthProvider _authProvider;

  @override
  void initState() {
    super.initState();
    _authProvider = context.read<AuthProvider>();
    _authProvider.addListener(_onAuthChanged);
    _pushService.onOrderNotificationTap = _handlePendingOrderFromPush;
    _initializePushNotifications();
    _initialize();
  }

  @override
  void dispose() {
    _authProvider.removeListener(_onAuthChanged);
    _pushService.onOrderNotificationTap = null;
    super.dispose();
  }

  Future<void> _initializePushNotifications() async {
    await _pushService.initialize();
  }

  Future<void> _syncPushNotifications() async {
    await _pushService.syncWithAuth(_authProvider);
    if (!mounted) return;
    _handlePendingOrderFromPush();
  }

  void _handlePendingOrderFromPush() {
    if (!mounted || _currentScreen != AppScreen.home) {
      return;
    }

    final orderId = _pushService.takePendingOrderId();
    if (orderId == null || orderId <= 0) {
      return;
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || _currentScreen != AppScreen.home) {
        return;
      }

      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => OrderDetailsScreen(orderId: orderId)),
      );
    });
  }

  Future<void> _initialize() async {
    await _authProvider.initialize();
    if (!mounted) return;

    setState(() {
      if (_authProvider.isLoggedIn) {
        _currentScreen = _authProvider.needsProfileCompletion
            ? AppScreen.locationPicker
            : AppScreen.home;
      } else {
        _currentScreen = AppScreen.phoneLogin;
      }
    });

    await _syncPushNotifications();
  }

  void _onAuthChanged() {
    if (!mounted) return;
    _syncPushNotifications();

    if (_authProvider.isLoggedIn) {
      if (_authProvider.needsProfileCompletion) {
        // Keep provider inside the completion flow, but do not kick them out
        // from profile setup while they are actively editing/saving.
        if (_currentScreen == AppScreen.home) {
          setState(() {
            _currentScreen = AppScreen.locationPicker;
          });
        }
        return;
      }

      if (_currentScreen == AppScreen.locationPicker ||
          _currentScreen == AppScreen.profileSetup) {
        setState(() {
          _currentScreen = AppScreen.home;
        });
      }
      return;
    }

    if (!_authProvider.isLoggedIn &&
        (_currentScreen == AppScreen.home ||
            _currentScreen == AppScreen.locationPicker ||
            _currentScreen == AppScreen.profileSetup)) {
      setState(() {
        _currentScreen = AppScreen.phoneLogin;
      });
    }
  }

  void _navigateTo(AppScreen screen, {String? phone}) {
    if (!mounted) return;
    setState(() {
      _currentScreen = screen;
      if (phone != null) _phone = phone;
    });
  }

  void _handlePhoneComplete(String phone) {
    if (_authProvider.isLoggedIn) {
      _navigateTo(
        _authProvider.needsProfileCompletion
            ? AppScreen.locationPicker
            : AppScreen.home,
      );
      return;
    }
    _navigateTo(AppScreen.otpVerification, phone: phone);
  }

  @override
  Widget build(BuildContext context) {
    switch (_currentScreen) {
      case AppScreen.loading:
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      case AppScreen.phoneLogin:
        return PhoneLoginScreen(onComplete: _handlePhoneComplete);
      case AppScreen.otpVerification:
        return OTPVerificationScreen(
          phone: _phone,
          onComplete: () => _navigateTo(
            _authProvider.needsProfileCompletion
                ? AppScreen.locationPicker
                : AppScreen.home,
          ),
          onChangePhone: () => _navigateTo(AppScreen.phoneLogin),
        );
      case AppScreen.locationPicker:
        return LocationPickerScreen(
          onComplete: (_) => _navigateTo(
            _authProvider.needsProfileCompletion
                ? AppScreen.profileSetup
                : AppScreen.home,
          ),
        );
      case AppScreen.profileSetup:
        return ProviderProfileSetupScreen(
          onComplete: () => _navigateTo(AppScreen.home),
        );
      case AppScreen.home:
        return const HomeScreen();
    }
  }
}
