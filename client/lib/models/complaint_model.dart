class ComplaintModel {
  final int id;
  final String ticketNumber;
  final String subject;
  final String? description;
  final List<String> attachments;
  final String status; // open, in_progress, resolved, closed
  final DateTime createdAt;
  final DateTime? updatedAt;
  final List<ComplaintReplyModel> replies;

  ComplaintModel({
    required this.id,
    required this.ticketNumber,
    required this.subject,
    this.description,
    this.attachments = const [],
    required this.status,
    required this.createdAt,
    this.updatedAt,
    this.replies = const [],
  });

  static int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse((value ?? '').toString()) ?? 0;
  }

  static DateTime _toDateTime(dynamic value) {
    final raw = (value ?? '').toString().trim();
    if (raw.isEmpty) return DateTime.now();
    try {
      return DateTime.parse(raw);
    } catch (_) {
      return DateTime.now();
    }
  }

  static DateTime? _toNullableDateTime(dynamic value) {
    final raw = (value ?? '').toString().trim();
    if (raw.isEmpty) return null;
    try {
      return DateTime.parse(raw);
    } catch (_) {
      return null;
    }
  }

  static List<String> _extractAttachments(dynamic raw) {
    if (raw is! List) return const <String>[];
    final values = <String>[];
    for (final item in raw) {
      final value = (item ?? '').toString().trim();
      if (value.isEmpty) continue;
      values.add(value);
    }
    return values;
  }

  factory ComplaintModel.fromJson(Map<String, dynamic> json) {
    return ComplaintModel(
      id: _toInt(json['id']),
      ticketNumber: json['ticket_number'] ?? '',
      subject: json['subject'] ?? '',
      description: json['description'],
      attachments: _extractAttachments(json['attachments']),
      status: json['status'] ?? 'open',
      createdAt: _toDateTime(json['created_at']),
      updatedAt: _toNullableDateTime(json['updated_at']),
      replies:
          (json['replies'] as List?)
              ?.map((e) {
                if (e is Map<String, dynamic>) {
                  return ComplaintReplyModel.fromJson(e);
                }
                if (e is Map) {
                  return ComplaintReplyModel.fromJson(
                    e.map((key, value) => MapEntry(key.toString(), value)),
                  );
                }
                return null;
              })
              .whereType<ComplaintReplyModel>()
              .toList() ??
          [],
    );
  }
}

class ComplaintReplyModel {
  final int id;
  final String message;
  final String senderType; // user, admin
  final String senderName;
  final List<String> attachments;
  final DateTime createdAt;

  ComplaintReplyModel({
    required this.id,
    required this.message,
    required this.senderType,
    required this.senderName,
    this.attachments = const [],
    required this.createdAt,
  });

  static int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse((value ?? '').toString()) ?? 0;
  }

  static DateTime _toDateTime(dynamic value) {
    final raw = (value ?? '').toString().trim();
    if (raw.isEmpty) return DateTime.now();
    try {
      return DateTime.parse(raw);
    } catch (_) {
      return DateTime.now();
    }
  }

  static List<String> _extractAttachments(dynamic raw) {
    if (raw is! List) return const <String>[];
    final values = <String>[];
    for (final item in raw) {
      final value = (item ?? '').toString().trim();
      if (value.isEmpty) continue;
      values.add(value);
    }
    return values;
  }

  factory ComplaintReplyModel.fromJson(Map<String, dynamic> json) {
    return ComplaintReplyModel(
      id: _toInt(json['id']),
      message: json['message'] ?? '',
      senderType: json['sender_type'] ?? 'user',
      senderName: json['sender_name'] ?? '',
      attachments: _extractAttachments(json['attachments']),
      createdAt: _toDateTime(json['created_at']),
    );
  }
}
