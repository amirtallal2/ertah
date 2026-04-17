import '../services/api_service.dart';
import '../models/complaint_model.dart';

class ComplaintRepository {
  final ApiService _api = ApiService();

  Future<List<ComplaintModel>> getComplaints() async {
    final response = await _api.get(
      '/mobile/complaints.php',
      params: {'action': 'list'},
    );

    if (response.success) {
      return response.toList((json) => ComplaintModel.fromJson(json));
    }
    throw Exception(response.message ?? 'Failed to load complaints');
  }

  Future<ComplaintModel> getComplaintDetails(int id) async {
    final response = await _api.get(
      '/mobile/complaints.php',
      params: {'action': 'details', 'id': id},
    );

    if (response.success) {
      return response.toObject((json) => ComplaintModel.fromJson(json))!;
    }
    throw Exception(response.message ?? 'Failed to load complaint details');
  }

  Future<ApiResponse> createComplaint(
    String subject,
    String description, {
    List<String> attachmentPaths = const [],
  }) async {
    if (attachmentPaths.isNotEmpty) {
      return await _api.multipart(
        '/mobile/complaints.php?action=create',
        method: 'POST',
        fields: {'subject': subject, 'description': description},
        files: {'attachments': attachmentPaths},
      );
    }

    return await _api.post(
      '/mobile/complaints.php?action=create',
      body: {'subject': subject, 'description': description},
    );
  }

  Future<bool> replyComplaint(
    int complaintId, {
    String message = '',
    List<String> attachmentPaths = const [],
  }) async {
    ApiResponse response;

    if (attachmentPaths.isNotEmpty) {
      response = await _api.multipart(
        '/mobile/complaints.php?action=reply',
        method: 'POST',
        fields: {
          'complaint_id': complaintId.toString(),
          if (message.trim().isNotEmpty) 'message': message.trim(),
        },
        files: {'attachments': attachmentPaths},
      );
    } else {
      response = await _api.post(
        '/mobile/complaints.php?action=reply',
        body: {'complaint_id': complaintId, 'message': message.trim()},
      );
    }

    return response.success;
  }
}
