// API Service
// خدمة التواصل مع الـ API

import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  String? _authToken;

  void setAuthToken(String? token) {
    _authToken = token;
  }

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (_authToken != null) {
      headers['Authorization'] = 'Bearer $_authToken';
    }
    return headers;
  }

  Uri _buildUri(
    String endpoint, {
    String? baseUrl,
    Map<String, dynamic>? params,
  }) {
    var uri = Uri.parse('${baseUrl ?? AppConfig.baseUrl}$endpoint');
    if (params != null && params.isNotEmpty) {
      final mergedQuery = <String, String>{
        ...uri.queryParameters,
        ...params.map((k, v) => MapEntry(k, v.toString())),
      };
      uri = uri.replace(queryParameters: mergedQuery);
    }
    return uri;
  }

  String? _fallbackBaseUrl() {
    if (!AppConfig.isProduction) return null;

    final primary = AppConfig.baseUrl;
    final candidates = <String>[
      AppConfig.productionBaseUrl,
      AppConfig.productionFallbackBaseUrl,
    ];

    for (final candidate in candidates) {
      if (candidate != primary) {
        return candidate;
      }
    }
    return null;
  }

  bool _looksLikeHtml(String content) {
    final trimmed = content.trimLeft();
    return trimmed.startsWith('<!DOCTYPE html') ||
        trimmed.startsWith('<html') ||
        trimmed.startsWith('<head') ||
        trimmed.startsWith('<body');
  }

  bool _shouldRetryWithFallback(http.Response response) {
    return response.statusCode == 404 && _looksLikeHtml(response.body);
  }

  Future<ApiResponse> get(
    String endpoint, {
    Map<String, dynamic>? params,
  }) async {
    try {
      final uri = _buildUri(endpoint, params: params);

      var response = await http
          .get(uri, headers: _headers)
          .timeout(const Duration(milliseconds: AppConfig.connectionTimeout));

      if (_shouldRetryWithFallback(response)) {
        final fallbackBase = _fallbackBaseUrl();
        if (fallbackBase != null) {
          final fallbackUri = _buildUri(
            endpoint,
            baseUrl: fallbackBase,
            params: params,
          );
          response = await http
              .get(fallbackUri, headers: _headers)
              .timeout(
                const Duration(milliseconds: AppConfig.connectionTimeout),
              );
        }
      }

      return _handleResponse(response);
    } catch (e) {
      return ApiResponse(success: false, message: 'حدث خطأ في الاتصال: $e');
    }
  }

  Future<ApiResponse> post(
    String endpoint, {
    Map<String, dynamic>? body,
  }) async {
    try {
      var response = await http
          .post(
            _buildUri(endpoint),
            headers: _headers,
            body: body != null ? jsonEncode(body) : null,
          )
          .timeout(const Duration(milliseconds: AppConfig.connectionTimeout));

      if (_shouldRetryWithFallback(response)) {
        final fallbackBase = _fallbackBaseUrl();
        if (fallbackBase != null) {
          response = await http
              .post(
                _buildUri(endpoint, baseUrl: fallbackBase),
                headers: _headers,
                body: body != null ? jsonEncode(body) : null,
              )
              .timeout(
                const Duration(milliseconds: AppConfig.connectionTimeout),
              );
        }
      }

      return _handleResponse(response);
    } catch (e) {
      return ApiResponse(success: false, message: 'حدث خطأ في الاتصال: $e');
    }
  }

  Future<ApiResponse> put(String endpoint, {Map<String, dynamic>? body}) async {
    try {
      var response = await http
          .put(
            _buildUri(endpoint),
            headers: _headers,
            body: body != null ? jsonEncode(body) : null,
          )
          .timeout(const Duration(milliseconds: AppConfig.connectionTimeout));

      if (_shouldRetryWithFallback(response)) {
        final fallbackBase = _fallbackBaseUrl();
        if (fallbackBase != null) {
          response = await http
              .put(
                _buildUri(endpoint, baseUrl: fallbackBase),
                headers: _headers,
                body: body != null ? jsonEncode(body) : null,
              )
              .timeout(
                const Duration(milliseconds: AppConfig.connectionTimeout),
              );
        }
      }

      return _handleResponse(response);
    } catch (e) {
      return ApiResponse(success: false, message: 'حدث خطأ في الاتصال: $e');
    }
  }

  Future<ApiResponse> delete(String endpoint) async {
    try {
      var response = await http
          .delete(_buildUri(endpoint), headers: _headers)
          .timeout(const Duration(milliseconds: AppConfig.connectionTimeout));

      if (_shouldRetryWithFallback(response)) {
        final fallbackBase = _fallbackBaseUrl();
        if (fallbackBase != null) {
          response = await http
              .delete(
                _buildUri(endpoint, baseUrl: fallbackBase),
                headers: _headers,
              )
              .timeout(
                const Duration(milliseconds: AppConfig.connectionTimeout),
              );
        }
      }

      return _handleResponse(response);
    } catch (e) {
      return ApiResponse(success: false, message: 'حدث خطأ في الاتصال: $e');
    }
  }

  Future<http.Response> _sendMultipartRequest(
    Uri uri, {
    required String method,
    Map<String, String>? fields,
    Map<String, dynamic>? files,
  }) async {
    final request = http.MultipartRequest(method, uri);

    request.headers.addAll(_headers);
    request.headers.remove('Content-Type');

    if (fields != null) {
      request.fields.addAll(fields);
    }

    if (files != null) {
      for (final entry in files.entries) {
        if (entry.value is String) {
          request.files.add(
            await http.MultipartFile.fromPath(entry.key, entry.value),
          );
        } else if (entry.value is List) {
          for (final path in entry.value) {
            request.files.add(
              await http.MultipartFile.fromPath(entry.key, path),
            );
          }
        }
      }
    }

    final streamedResponse = await request.send().timeout(
      const Duration(milliseconds: AppConfig.connectionTimeout),
    );
    return await http.Response.fromStream(streamedResponse);
  }

  Future<ApiResponse> multipart(
    String endpoint, {
    required String method,
    Map<String, String>? fields,
    Map<String, dynamic>? files,
  }) async {
    try {
      var response = await _sendMultipartRequest(
        _buildUri(endpoint),
        method: method,
        fields: fields,
        files: files,
      );

      if (_shouldRetryWithFallback(response)) {
        final fallbackBase = _fallbackBaseUrl();
        if (fallbackBase != null) {
          response = await _sendMultipartRequest(
            _buildUri(endpoint, baseUrl: fallbackBase),
            method: method,
            fields: fields,
            files: files,
          );
        }
      }

      return _handleResponse(response);
    } catch (e) {
      return ApiResponse(success: false, message: 'حدث خطأ في رفع الملف: $e');
    }
  }

  String _decodeBody(http.Response response) {
    return utf8.decode(response.bodyBytes, allowMalformed: true).trim();
  }

  String? _firstValidationError(Map<String, dynamic> errors) {
    if (errors.isEmpty) {
      return null;
    }

    final firstValue = errors.values.first;
    if (firstValue is List && firstValue.isNotEmpty) {
      return firstValue.first?.toString();
    }
    return firstValue?.toString();
  }

  String? _extractServerMessage(String body) {
    if (body.isEmpty) {
      return null;
    }

    final match = RegExp(
      r'"message"\s*:\s*"((?:\\.|[^"\\])*)"',
      caseSensitive: false,
    ).firstMatch(body);
    if (match != null) {
      final raw = match.group(1) ?? '';
      if (raw.isNotEmpty) {
        return raw.replaceAll(r'\"', '"').replaceAll(r'\\n', ' ').trim();
      }
    }

    if (RegExp(r'no data to update', caseSensitive: false).hasMatch(body)) {
      return 'No data to update';
    }

    return null;
  }

  String _normalizeMessage(String? message, {required int statusCode}) {
    final value = (message ?? '').trim();
    if (value.isEmpty) {
      return statusCode == 422 ? 'بيانات غير صحيحة' : 'حدث خطأ غير متوقع';
    }

    final lowered = value.toLowerCase();
    if (lowered.contains('no data to update') ||
        lowered.contains('nothing to update') ||
        lowered.contains('no fields to update')) {
      return 'لا توجد تغييرات للحفظ';
    }

    return value;
  }

  ApiResponse _handleResponse(http.Response response) {
    final rawBody = _decodeBody(response);

    try {
      final decoded = jsonDecode(rawBody.isEmpty ? '{}' : rawBody);
      final data = decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{'data': decoded};

      if (response.statusCode >= 200 && response.statusCode < 300) {
        return ApiResponse(
          success: true,
          data: data['data'] ?? data,
          message: data['message']?.toString(),
        );
      } else if (response.statusCode == 401) {
        final serverMsg = data['message']?.toString();
        return ApiResponse(
          success: false,
          message:
              serverMsg ?? 'انتهت صلاحية الجلسة، يرجى تسجيل الدخول مرة أخرى',
          statusCode: 401,
        );
      } else if (response.statusCode == 422) {
        final rawErrors = data['errors'];
        final errors = rawErrors is Map
            ? Map<String, dynamic>.from(rawErrors)
            : <String, dynamic>{};
        final serverMessage = data['message']?.toString();
        final firstError = _firstValidationError(errors);
        final message = _normalizeMessage(
          (serverMessage != null && serverMessage.trim().isNotEmpty)
              ? serverMessage
              : firstError,
          statusCode: 422,
        );
        return ApiResponse(
          success: false,
          message: message,
          errors: errors,
          statusCode: 422,
        );
      } else {
        final serverMessage = data['message']?.toString();
        return ApiResponse(
          success: false,
          message: _normalizeMessage(
            serverMessage,
            statusCode: response.statusCode,
          ),
          statusCode: response.statusCode,
        );
      }
    } catch (e) {
      final extractedMessage = _extractServerMessage(rawBody);
      if (extractedMessage != null && extractedMessage.isNotEmpty) {
        return ApiResponse(
          success: false,
          message: _normalizeMessage(
            extractedMessage,
            statusCode: response.statusCode,
          ),
          statusCode: response.statusCode,
        );
      }

      final bodySnippet = rawBody.length > 200
          ? '${rawBody.substring(0, 200)}...'
          : rawBody;
      return ApiResponse(
        success: false,
        message: 'خطأ معالجة (${response.statusCode}): $bodySnippet',
        statusCode: response.statusCode,
      );
    }
  }
}

class ApiResponse {
  final bool success;
  final dynamic data;
  final String? message;
  final Map<String, dynamic>? errors;
  final int? statusCode;

  ApiResponse({
    required this.success,
    this.data,
    this.message,
    this.errors,
    this.statusCode,
  });

  bool get isUnauthorized => statusCode == 401;

  List<T> toList<T>(T Function(Map<String, dynamic>) fromJson) {
    if (data == null || data is! List) return [];
    return (data as List)
        .map((e) => fromJson(e as Map<String, dynamic>))
        .toList();
  }

  T? toObject<T>(T Function(Map<String, dynamic>) fromJson) {
    if (data == null || data is! Map) return null;
    return fromJson(data as Map<String, dynamic>);
  }
}
