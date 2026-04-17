import 'dart:math' as math;

import 'package:flutter/material.dart';

class ResponsiveConfig {
  const ResponsiveConfig._();

  static const double designWidth = 390.0;
  static const double minScale = 0.80;
  static const double maxScale = 1.00;

  static double resolveScale(double screenWidth) {
    if (screenWidth <= 0) return 1.0;
    final raw = screenWidth / designWidth;
    return raw.clamp(minScale, maxScale);
  }

  static double resolveFrameWidth(double screenWidth) {
    final scale = resolveScale(screenWidth);
    if (scale >= 0.999) return screenWidth;
    return screenWidth / scale;
  }

  static double resolveTextScale({required double systemTextScale}) {
    return systemTextScale.clamp(0.90, 1.20);
  }
}

class ResponsiveRoot extends StatelessWidget {
  const ResponsiveRoot({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final media = MediaQuery.of(context);
        final width = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : media.size.width;
        final layoutScale = ResponsiveConfig.resolveScale(width);
        final frameWidth = ResponsiveConfig.resolveFrameWidth(width);
        final isKeyboardVisible = media.viewInsets.bottom > 0;
        final systemTextScale = media.textScaler.scale(1);
        final textScale = ResponsiveConfig.resolveTextScale(
          systemTextScale: systemTextScale,
        );

        Widget content = Theme(
          data: _buildAdaptedTheme(Theme.of(context), width),
          child: child,
        );

        // Keep the exact visual language from large screens, but shrink
        // proportionally on smaller widths to preserve composition without
        // introducing an empty strip at the bottom on short/small devices.
        // Avoid scaling transformed text inputs while IME is visible.
        // Some Android devices fail to open/show keyboard reliably when
        // EditableText is inside a transformed subtree.
        if (layoutScale < 0.999 && !isKeyboardVisible) {
          final viewportHeight = constraints.maxHeight.isFinite
              ? constraints.maxHeight
              : media.size.height;

          content = ColoredBox(
            color: Theme.of(context).scaffoldBackgroundColor,
            child: Align(
              alignment: Alignment.topCenter,
              child: SizedBox(
                width: width,
                height: viewportHeight,
                child: FittedBox(
                  fit: BoxFit.fitWidth,
                  alignment: Alignment.topCenter,
                  child: SizedBox(
                    width: frameWidth,
                    height: viewportHeight / layoutScale,
                    child: content,
                  ),
                ),
              ),
            ),
          );
        }

        return MediaQuery(
          data: media.copyWith(textScaler: TextScaler.linear(textScale)),
          child: content,
        );
      },
    );
  }

  ThemeData _buildAdaptedTheme(ThemeData theme, double width) {
    if (width >= 360) return theme;

    return theme.copyWith(
      visualDensity: const VisualDensity(horizontal: -0.4, vertical: -0.4),
      appBarTheme: theme.appBarTheme.copyWith(
        toolbarHeight: (theme.appBarTheme.toolbarHeight ?? kToolbarHeight)
            .clamp(52.0, 64.0),
      ),
    );
  }
}

extension ResponsiveContext on BuildContext {
  double get screenWidth => MediaQuery.sizeOf(this).width;

  double get layoutScale => ResponsiveConfig.resolveScale(screenWidth);

  bool get isSmallPhone => screenWidth < 360;

  bool get isLargePhone => screenWidth >= 430;

  double rw(double value, {double? min, double? max}) {
    final scaled = value * ResponsiveConfig.resolveScale(screenWidth);
    if (min == null && max == null) return scaled;
    final minValue = min ?? -double.infinity;
    final maxValue = max ?? double.infinity;
    return math.min(maxValue, math.max(minValue, scaled));
  }
}
