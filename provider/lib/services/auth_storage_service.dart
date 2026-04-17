import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Persists auth token in both secure storage and shared preferences.
/// Shared preferences are included for Android backup/restore compatibility.
class AuthStorageService {
  static const String _tokenKey = 'auth_token';
  static const FlutterSecureStorage _secureStorage = FlutterSecureStorage();
  static const AndroidOptions _androidOptions = AndroidOptions(
    encryptedSharedPreferences: true,
  );
  static const IOSOptions _iosOptions = IOSOptions(
    accessibility: KeychainAccessibility.first_unlock,
  );

  Future<String?> readToken() async {
    final prefs = await SharedPreferences.getInstance();
    final prefsToken = prefs.getString(_tokenKey);
    final secureToken = await _readSecureToken();
    final token = secureToken ?? prefsToken;

    if (token != null) {
      if (prefsToken != token) {
        await prefs.setString(_tokenKey, token);
      }
      if (secureToken != token) {
        await _writeSecureToken(token);
      }
    }

    return token;
  }

  Future<void> saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
    await _writeSecureToken(token);
  }

  Future<void> clearToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await _deleteSecureToken();
  }

  Future<String?> _readSecureToken() async {
    try {
      return await _secureStorage.read(
        key: _tokenKey,
        aOptions: _androidOptions,
        iOptions: _iosOptions,
      );
    } catch (e) {
      debugPrint('Secure token read failed: $e');
      await _deleteSecureToken();
      return null;
    }
  }

  Future<void> _writeSecureToken(String token) async {
    try {
      await _secureStorage.write(
        key: _tokenKey,
        value: token,
        aOptions: _androidOptions,
        iOptions: _iosOptions,
      );
    } catch (e) {
      debugPrint('Secure token write failed: $e');
    }
  }

  Future<void> _deleteSecureToken() async {
    try {
      await _secureStorage.delete(
        key: _tokenKey,
        aOptions: _androidOptions,
        iOptions: _iosOptions,
      );
    } catch (e) {
      debugPrint('Secure token delete failed: $e');
      try {
        await _secureStorage.deleteAll(
          aOptions: _androidOptions,
          iOptions: _iosOptions,
        );
      } catch (_) {
        // Ignore secondary cleanup failures.
      }
    }
  }
}
