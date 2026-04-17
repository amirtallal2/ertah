/// Wallet Service
/// خدمة المحفظة

import 'api_service.dart';

class WalletService {
  final ApiService _api = ApiService();

  /// Get wallet details and transactions
  Future<ApiResponse> getWalletDetails() async {
    return await _api.get('/mobile/wallet.php?action=details');
  }

  /// Add funds to wallet (Mock for now, usually involved payment gateway)
  Future<ApiResponse> addFunds(double amount) async {
    return await _api.post(
      '/mobile/wallet.php?action=add_funds',
      body: {'amount': amount},
    );
  }
}
