// Ertah App - Main Entry Point
// تطبيق ارتاح - نقطة الدخول الرئيسية

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'config/app_theme.dart';
import 'config/responsive_layout.dart';
import 'providers/auth_provider.dart';
import 'providers/app_settings_provider.dart';
import 'providers/location_provider.dart';
import 'providers/localization_provider.dart';
import 'services/app_localizations.dart';
import 'services/push_notification_service.dart';
import 'screens/splash_screen.dart';
import 'screens/onboarding_screen.dart';
import 'screens/phone_login_screen.dart';
import 'screens/otp_verification_screen.dart';
import 'screens/location_picker_screen.dart';
import 'screens/profile_completion_screen.dart';
import 'screens/main_navigation.dart';
import 'utils/order_tracking_navigation.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize push handling as early as possible to capture notification clicks
  // when the app is launched from a terminated state.
  await PushNotificationService.instance.initialize();

  // Set preferred orientations
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  // Set system UI overlay style
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
      systemNavigationBarColor: Colors.white,
      systemNavigationBarIconBrightness: Brightness.dark,
    ),
  );

  runApp(const ErtahApp());
}

class ErtahApp extends StatelessWidget {
  const ErtahApp({super.key});

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
        builder: (context, localizationProvider, appSettingsProvider, child) {
          final fallbackTitle = 'Darfix';
          final dynamicTitle = appSettingsProvider.appName.trim();
          final appTitle =
              dynamicTitle.isNotEmpty ? dynamicTitle : fallbackTitle;
          return MaterialApp(
            title: appTitle,
            debugShowCheckedModeBanner: false,
            theme: AppTheme.lightTheme,
            darkTheme: AppTheme.darkTheme,
            themeMode: ThemeMode.light,
            locale: localizationProvider.locale,
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
              return ResponsiveRoot(child: child);
            },
            home: const AppNavigator(),
          );
        },
      ),
    );
  }
}

/// App Navigator - Manages app flow
class AppNavigator extends StatefulWidget {
  const AppNavigator({super.key});

  @override
  State<AppNavigator> createState() => _AppNavigatorState();
}

class _AppNavigatorState extends State<AppNavigator> {
  final PushNotificationService _pushService = PushNotificationService.instance;
  AppScreen _currentScreen = AppScreen.splash;
  String _phone = '';
  bool _shouldShowOnboardingThisLaunch = false;
  bool _initialized = false;
  static const int _onboardingFlowVersion = 1;

  @override
  void initState() {
    super.initState();
    _pushService.onOrderNotificationTap = _handlePendingOrderFromPush;
    _initializePushNotifications();
    _initialize();
    // Listen for auth changes to handle logout
    final authProvider = context.read<AuthProvider>();
    authProvider.addListener(_onAuthChange);
  }

  @override
  void dispose() {
    final authProvider = context.read<AuthProvider>();
    authProvider.removeListener(_onAuthChange);
    _pushService.onOrderNotificationTap = null;
    super.dispose();
  }

  void _onAuthChange() {
    final authProvider = context.read<AuthProvider>();
    _syncPushNotifications();

    if (authProvider.isLoggedIn &&
        authProvider.needsProfileCompletion &&
        _currentScreen == AppScreen.main) {
      if (mounted) {
        _navigateTo(AppScreen.locationPicker);
      }
      return;
    }

    // Redirect to login if user logs out while in main app (and is not a guest)
    if (!authProvider.isLoggedIn &&
        !authProvider.isGuest &&
        (_currentScreen == AppScreen.main ||
            _currentScreen == AppScreen.locationPicker ||
            _currentScreen == AppScreen.profileCompletion)) {
      if (mounted) {
        _navigateTo(AppScreen.phoneLogin);
      }
    }
    // If guest mode is activated, go to main
    if (authProvider.isGuest && _currentScreen == AppScreen.phoneLogin) {
      if (mounted) {
        _navigateTo(AppScreen.main);
      }
    }
  }

  Future<void> _initializePushNotifications() async {
    await _pushService.initialize();
  }

  Future<void> _syncPushNotifications() async {
    final authProvider = context.read<AuthProvider>();
    await _pushService.syncWithAuth(authProvider);
    _handlePendingOrderFromPush();
  }

  void _handlePendingOrderFromPush() {
    if (!mounted || _currentScreen != AppScreen.main) {
      return;
    }

    final orderId = _pushService.takePendingOrderId();
    if (orderId == null || orderId <= 0) {
      return;
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || _currentScreen != AppScreen.main) {
        return;
      }

      OrderTrackingNavigation.open(context, orderId: orderId);
    });
  }

  Future<void> _initialize() async {
    final authProvider = context.read<AuthProvider>();
    final prefs = await SharedPreferences.getInstance();

    // Initialize auth (restore session)
    await authProvider.initialize();

    final legacySeen = prefs.getBool('has_seen_onboarding') ?? false;
    final seenVersion =
        prefs.getInt('onboarding_seen_version') ??
        (legacySeen ? _onboardingFlowVersion : 0);

    _shouldShowOnboardingThisLaunch = seenVersion < _onboardingFlowVersion;

    // Mark as seen when it is shown the first time, even if user closes app mid-flow.
    if (_shouldShowOnboardingThisLaunch) {
      await prefs.setInt('onboarding_seen_version', _onboardingFlowVersion);
      await prefs.setBool('has_seen_onboarding', true);
    }

    if (mounted) {
      setState(() {
        _initialized = true;
      });
    }

    await _syncPushNotifications();
  }

  void _navigateTo(AppScreen screen, {String? phone}) {
    if (!mounted) return;
    setState(() {
      _currentScreen = screen;
      if (phone != null) _phone = phone;
    });
  }

  void _handleSplashComplete() {
    final authProvider = context.read<AuthProvider>();

    // Use Future.microtask to avoid calling setState during build
    Future.microtask(() {
      if (authProvider.isLoggedIn || authProvider.isGuest) {
        if (authProvider.isLoggedIn && authProvider.needsProfileCompletion) {
          _navigateTo(AppScreen.locationPicker);
        } else {
          _navigateTo(AppScreen.main);
        }
      } else if (_shouldShowOnboardingThisLaunch) {
        _navigateTo(AppScreen.onboarding);
      } else {
        _navigateTo(AppScreen.phoneLogin);
      }
    });
  }

  void _handlePhoneComplete(String phone) {
    final authProvider = context.read<AuthProvider>();

    Future.microtask(() {
      if (authProvider.isLoggedIn) {
        if (authProvider.needsProfileCompletion) {
          _navigateTo(AppScreen.locationPicker);
        } else {
          _navigateTo(AppScreen.main);
        }
      } else {
        _navigateTo(AppScreen.otpVerification, phone: phone);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    // Don't use context.watch here to avoid rebuild issues
    // Instead, we read the provider in callbacks

    if (!_initialized) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    switch (_currentScreen) {
      case AppScreen.splash:
        return SplashScreen(onComplete: _handleSplashComplete);

      case AppScreen.onboarding:
        return OnboardingScreen(
          onComplete: () {
            _navigateTo(AppScreen.phoneLogin);
          },
        );

      case AppScreen.phoneLogin:
        return PhoneLoginScreen(onComplete: _handlePhoneComplete);

      case AppScreen.otpVerification:
        return OTPVerificationScreen(
          phone: _phone,
          onComplete: () {
            final authProvider = context.read<AuthProvider>();
            if (authProvider.needsProfileCompletion) {
              _navigateTo(AppScreen.locationPicker);
            } else {
              _navigateTo(AppScreen.main);
            }
          },
        );

      case AppScreen.locationPicker:
        return LocationPickerScreen(
          onComplete: (_) {
            final authProvider = context.read<AuthProvider>();
            if (authProvider.needsProfileCompletion) {
              _navigateTo(AppScreen.profileCompletion);
            } else {
              _navigateTo(AppScreen.main);
            }
          },
        );

      case AppScreen.profileCompletion:
        return ProfileCompletionScreen(
          onComplete: () {
            _navigateTo(AppScreen.main);
          },
        );

      case AppScreen.main:
        _handlePendingOrderFromPush();
        return const MainNavigation();
    }
  }
}

enum AppScreen {
  splash,
  onboarding,
  phoneLogin,
  otpVerification,
  locationPicker,
  profileCompletion,
  main,
}
