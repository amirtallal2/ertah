class ComplaintModel {
  final int id;
  final String ticketNumber;
  final String subject;
  final String? description;
  final String status; // open, in_progress, resolved, closed
  final DateTime createdAt;
  final List<ComplaintReplyModel> replies;

  ComplaintModel({
    required this.id,
    required this.ticketNumber,
    required this.subject,
    this.description,
    required this.status,
    required this.createdAt,
    this.replies = const [],
  });

  factory ComplaintModel.fromJson(Map<String, dynamic> json) {
    return ComplaintModel(
      id: json['id'],
      ticketNumber: json['ticket_number'] ?? '',
      subject: json['subject'] ?? '',
      description: json['description'],
      status: json['status'] ?? 'open',
      createdAt: DateTime.parse(json['created_at']),
      replies:
          (json['replies'] as List?)
              ?.map((e) => ComplaintReplyModel.fromJson(e))
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
  final DateTime createdAt;

  ComplaintReplyModel({
    required this.id,
    required this.message,
    required this.senderType,
    required this.senderName,
    required this.createdAt,
  });

  factory ComplaintReplyModel.fromJson(Map<String, dynamic> json) {
    return ComplaintReplyModel(
      id: json['id'],
      message: json['message'] ?? '',
      senderType: json['sender_type'] ?? 'user',
      senderName: json['sender_name'] ?? '',
      createdAt: DateTime.parse(json['created_at']),
    );
  }
}
