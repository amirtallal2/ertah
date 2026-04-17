import 'package:flutter/material.dart';
import '../config/currency_config.dart';

class SaudiRiyalIcon extends StatelessWidget {
  final double size;

  const SaudiRiyalIcon({super.key, this.size = 14});

  @override
  Widget build(BuildContext context) {
    return Image.network(
      CurrencyConfig.symbolImageUrl,
      width: size * 0.82,
      height: size,
      fit: BoxFit.contain,
      errorBuilder: (_, __, ___) => Image.asset(
        'assets/images/saudi_riyal_symbol.png',
        width: size * 0.82,
        height: size,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => Text(
          CurrencyConfig.symbol,
          style: TextStyle(
            fontSize: size * 0.7,
            fontWeight: FontWeight.w600,
            color: Colors.black54,
          ),
        ),
      ),
      loadingBuilder: (context, child, loadingProgress) {
        if (loadingProgress == null) return child;
        return SizedBox(
          width: size * 0.82,
          height: size,
          child: Center(
            child: SizedBox(
              width: size * 0.55,
              height: size * 0.55,
              child: const CircularProgressIndicator(strokeWidth: 1.5),
            ),
          ),
        );
      },
      frameBuilder: (context, child, frame, _) {
        if (frame == null) {
          return SizedBox(width: size * 0.82, height: size, child: child);
        }
        return child;
      },
    );
  }
}

class SaudiRiyalText extends StatelessWidget {
  final String text;
  final TextStyle? style;
  final int? maxLines;
  final TextOverflow overflow;
  final TextAlign textAlign;
  final bool iconFirst;
  final double iconSize;

  const SaudiRiyalText({
    super.key,
    required this.text,
    this.style,
    this.maxLines,
    this.overflow = TextOverflow.clip,
    this.textAlign = TextAlign.start,
    this.iconFirst = false,
    this.iconSize = 14,
  });

  @override
  Widget build(BuildContext context) {
    final effectiveStyle = style ?? DefaultTextStyle.of(context).style;

    return Text.rich(
      TextSpan(
        style: effectiveStyle,
        children: [
          if (iconFirst)
            WidgetSpan(
              alignment: PlaceholderAlignment.middle,
              child: SaudiRiyalIcon(size: iconSize),
            ),
          if (iconFirst) const TextSpan(text: ' '),
          TextSpan(text: text),
          if (!iconFirst) const TextSpan(text: ' '),
          if (!iconFirst)
            WidgetSpan(
              alignment: PlaceholderAlignment.middle,
              child: SaudiRiyalIcon(size: iconSize),
            ),
        ],
      ),
      maxLines: maxLines,
      overflow: overflow,
      textAlign: textAlign,
    );
  }
}
