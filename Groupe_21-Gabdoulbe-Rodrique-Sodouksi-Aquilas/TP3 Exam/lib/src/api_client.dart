import 'dart:convert';

import 'package:http/http.dart' as http;

typedef JsonMap = Map<String, dynamic>;

class ApiException implements Exception {
  const ApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();

  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8002/api/v1',
  );

  final http.Client _client;

  Uri _uri(String path) => Uri.parse('$baseUrl$path');

  Future<JsonMap> _send(
    String method,
    String path, {
    JsonMap? body,
  }) async {
    final request = http.Request(method, _uri(path))
      ..headers['Accept'] = 'application/json'
      ..headers['Content-Type'] = 'application/json';
    if (body != null) request.body = jsonEncode(body);

    try {
      final streamed = await _client.send(request).timeout(
        const Duration(seconds: 12),
      );
      final response = await http.Response.fromStream(streamed);
      final decoded = response.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(response.body) as JsonMap;
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw ApiException(
          decoded['error'] as String? ?? 'Erreur serveur.',
          response.statusCode,
        );
      }
      return decoded;
    } on ApiException {
      rethrow;
    } catch (error) {
      throw ApiException('Connexion impossible : $error', 0);
    }
  }

  Future<JsonMap> dashboard() => _send('GET', '/dashboard');

  Future<List<JsonMap>> products() async => _items(await _send('GET', '/products'));

  Future<int> saveProduct(JsonMap data, {int? id}) async {
    final result = await _send(
      id == null ? 'POST' : 'PUT',
      id == null ? '/products' : '/products/$id',
      body: data,
    );
    return id ?? (result['id'] as num).toInt();
  }

  Future<List<JsonMap>> customers() async =>
      _items(await _send('GET', '/customers'));

  Future<List<JsonMap>> regions() async => _items(await _send('GET', '/regions'));

  Future<int> saveCustomer(JsonMap data, {int? id}) async {
    final result = await _send(
      id == null ? 'POST' : 'PUT',
      id == null ? '/customers' : '/customers/$id',
      body: data,
    );
    return id ?? (result['id'] as num).toInt();
  }

  Future<void> deleteCustomer(int id) async {
    await _send('DELETE', '/customers/$id');
  }

  Future<List<JsonMap>> orders() async => _items(await _send('GET', '/orders'));

  Future<int> saveOrder(JsonMap data, {int? id}) async {
    final result = await _send(
      id == null ? 'POST' : 'PUT',
      id == null ? '/orders' : '/orders/$id',
      body: data,
    );
    return id ?? (result['id'] as num).toInt();
  }

  Future<JsonMap> orderDetail(int id) => _send('GET', '/orders/$id');

  Future<void> deleteOrder(int id) async {
    await _send('DELETE', '/orders/$id');
  }

  Future<void> addOrderLine(int orderId, int productId, int quantity) async {
    await _send(
      'POST',
      '/orders/$orderId/lines',
      body: {'productId': productId, 'quantity': quantity},
    );
  }

  Future<void> deleteOrderLine(int orderId, int productId) async {
    await _send('DELETE', '/orders/$orderId/lines/$productId');
  }

  List<JsonMap> _items(JsonMap response) =>
      (response['items'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
}