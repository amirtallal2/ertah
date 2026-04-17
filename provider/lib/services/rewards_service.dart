/// Rewards Service
/// خدمة المكافآت والنقاط

import 'api_service.dart';

class RewardsService {
  final ApiService _api = ApiService();

  /// Get rewards info and history
  Future<ApiResponse> getRewards() async {
    return await _api.get('/mobile/rewards.php?action=info');
  }

  /// Redeem points for credit
  Future<ApiResponse> redeemPoints(int points) async {
    return await _api.post(
      '/mobile/rewards.php?action=redeem',
      body: {'points': points},
    );
  }
}
