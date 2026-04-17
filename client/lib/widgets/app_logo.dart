import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../providers/app_settings_provider.dart';

class AppLogo extends StatelessWidget {
  const AppLogo({
    super.key,
    this.width,
    this.height,
    this.fit = BoxFit.contain,
  });

  final double? width;
  final double? height;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) {
    final logoUrl = context.select<AppSettingsProvider, String?>(
      (provider) => provider.appLogoUrl,
    );

    if (logoUrl != null && logoUrl.isNotEmpty) {
      return Image.network(
        logoUrl,
        width: width,
        height: height,
        fit: fit,
        errorBuilder: (context, error, stackTrace) {
          return Image.asset(
            AppConfig.logoAsset,
            width: width,
            height: height,
            fit: fit,
          );
        },
      );
    }

    return Image.asset(
      AppConfig.logoAsset,
      width: width,
      height: height,
      fit: fit,
    );
  }
}
