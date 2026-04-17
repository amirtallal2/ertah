/// Order Model
/// موديل الطلب

class OrderModel {
  final int id;
  final String orderNumber;
  final int userId;
  final int? providerId;
  final int categoryId;
  final OrderStatus status;
  final double totalAmount;
  final String? address;
  final double? lat;
  final double? lng;
  final String? notes;
  final String? scheduledDate;
  final String? scheduledTime;
  final DateTime createdAt;
  final DateTime? completedAt;

  // Related data
  final String? userName;
  final String? providerName;
  final String? providerAvatar;
  final String? categoryName;
  final String? categoryIcon;
  final double? providerRating;

  OrderModel({
    required this.id,
    required this.orderNumber,
    required this.userId,
    this.providerId,
    required this.categoryId,
    this.status = OrderStatus.pending,
    this.totalAmount = 0.0,
    this.address,
    this.lat,
    this.lng,
    this.notes,
    this.scheduledDate,
    this.scheduledTime,
    required this.createdAt,
    this.completedAt,
    this.userName,
    this.providerName,
    this.providerAvatar,
    this.categoryName,
    this.categoryIcon,
    this.providerRating,
  });

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    return OrderModel(
      id: json['id'] ?? 0,
      orderNumber: json['order_number'] ?? '',
      userId: json['user_id'] ?? 0,
      providerId: json['provider_id'],
      categoryId: json['category_id'] ?? 0,
      status: OrderStatus.fromString(json['status']),
      totalAmount:
          double.tryParse(json['total_amount']?.toString() ?? '0') ?? 0.0,
      address: json['address'],
      lat: double.tryParse(json['lat']?.toString() ?? ''),
      lng: double.tryParse(json['lng']?.toString() ?? ''),
      notes: json['notes'],
      scheduledDate: json['scheduled_date'],
      scheduledTime: json['scheduled_time'],
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
      completedAt: json['completed_at'] != null
          ? DateTime.tryParse(json['completed_at'])
          : null,
      userName: json['user_name'],
      providerName: json['provider_name'],
      providerAvatar: json['provider_avatar'],
      categoryName: json['category_name'],
      categoryIcon: json['category_icon'],
      providerRating: double.tryParse(
        json['provider_rating']?.toString() ?? '',
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'order_number': orderNumber,
      'user_id': userId,
      'provider_id': providerId,
      'category_id': categoryId,
      'status': status.value,
      'total_amount': totalAmount,
      'address': address,
      'lat': lat,
      'lng': lng,
      'notes': notes,
      'scheduled_date': scheduledDate,
      'scheduled_time': scheduledTime,
    };
  }

  String get statusAr => status.arabicName;

  String get statusIcon => status.icon;

  bool get isPending => status == OrderStatus.pending;
  bool get isAssigned => status == OrderStatus.assigned;
  bool get isInProgress => status == OrderStatus.inProgress;
  bool get isCompleted => status == OrderStatus.completed;
  bool get isCancelled => status == OrderStatus.cancelled;

  bool get canCancel => isPending || isAssigned;
  bool get canRate => isCompleted;

  // Sample data for demo
  static List<OrderModel> getSampleOrders() {
    return [
      OrderModel(
        id: 1,
        orderNumber: 'RT10001',
        userId: 1,
        providerId: 1,
        categoryId: 3,
        status: OrderStatus.inProgress,
        totalAmount: 150.0,
        address: 'الرياض، حي النخيل، شارع الملك فهد',
        categoryName: 'تكييف',
        categoryIcon: '❄️',
        providerName: 'محمد أحمد',
        providerRating: 4.9,
        createdAt: DateTime.now().subtract(const Duration(hours: 2)),
      ),
      OrderModel(
        id: 2,
        orderNumber: 'RT10002',
        userId: 1,
        providerId: 2,
        categoryId: 1,
        status: OrderStatus.completed,
        totalAmount: 80.0,
        address: 'الرياض، حي الملك عبدالله',
        categoryName: 'سباكة',
        categoryIcon: '🔧',
        providerName: 'خالد محمد',
        providerRating: 4.8,
        createdAt: DateTime.now().subtract(const Duration(days: 2)),
        completedAt: DateTime.now().subtract(const Duration(days: 1)),
      ),
      OrderModel(
        id: 3,
        orderNumber: 'RT10003',
        userId: 1,
        categoryId: 2,
        status: OrderStatus.pending,
        totalAmount: 0.0,
        address: 'الرياض، حي الورود',
        categoryName: 'كهرباء',
        categoryIcon: '⚡',
        createdAt: DateTime.now().subtract(const Duration(minutes: 30)),
      ),
    ];
  }
}

enum OrderStatus {
  pending,
  assigned,
  inProgress,
  completed,
  cancelled;

  String get value {
    switch (this) {
      case OrderStatus.pending:
        return 'pending';
      case OrderStatus.assigned:
        return 'assigned';
      case OrderStatus.inProgress:
        return 'in_progress';
      case OrderStatus.completed:
        return 'completed';
      case OrderStatus.cancelled:
        return 'cancelled';
    }
  }

  String get arabicName {
    switch (this) {
      case OrderStatus.pending:
        return 'في الانتظار';
      case OrderStatus.assigned:
        return 'تم التعيين';
      case OrderStatus.inProgress:
        return 'قيد التنفيذ';
      case OrderStatus.completed:
        return 'مكتمل';
      case OrderStatus.cancelled:
        return 'ملغي';
    }
  }

  String get icon {
    switch (this) {
      case OrderStatus.pending:
        return '⏳';
      case OrderStatus.assigned:
        return '👤';
      case OrderStatus.inProgress:
        return '🔄';
      case OrderStatus.completed:
        return '✅';
      case OrderStatus.cancelled:
        return '❌';
    }
  }

  static OrderStatus fromString(String? status) {
    switch (status?.toLowerCase()) {
      case 'assigned':
        return OrderStatus.assigned;
      case 'in_progress':
        return OrderStatus.inProgress;
      case 'completed':
        return OrderStatus.completed;
      case 'cancelled':
        return OrderStatus.cancelled;
      default:
        return OrderStatus.pending;
    }
  }
}
