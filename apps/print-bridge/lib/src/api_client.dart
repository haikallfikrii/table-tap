import 'dart:convert';

import 'package:http/http.dart' as http;

/// One queued ticket handed to this device by the server.
class PrintJob {
  PrintJob({
    required this.id,
    required this.station,
    required this.jenis,
    required this.payload,
  });

  final int id;
  final String station;
  final String jenis;
  final Map<String, dynamic> payload;

  static PrintJob fromJson(Map<String, dynamic> json) => PrintJob(
        id: (json['id'] ?? 0) as int,
        station: '${json['station'] ?? 'kasir'}',
        jenis: '${json['jenis'] ?? 'kitchen'}',
        payload: json['payload'] is Map
            ? Map<String, dynamic>.from(json['payload'] as Map)
            : <String, dynamic>{},
      );
}

class BridgeApiException implements Exception {
  BridgeApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

/// Talks to public/api/print_bridge/* on the TableTap server over HTTPS.
class BridgeApi {
  BridgeApi({required this.baseUrl, required this.token, http.Client? client})
      : _client = client ?? http.Client();

  final String baseUrl;
  final String token;
  final http.Client _client;

  static const Duration _timeout = Duration(seconds: 12);

  Uri _uri(String path, [Map<String, String>? query]) {
    final String root = baseUrl.trim().replaceAll(RegExp(r'/+$'), '');
    return Uri.parse('$root/public/api/print_bridge/$path')
        .replace(queryParameters: query);
  }

  Map<String, String> get _headers => <String, String>{
        'X-Print-Bridge-Token': token,
        'Accept': 'application/json',
      };

  Future<Map<String, dynamic>> _decode(http.Response res) async {
    Map<String, dynamic> body;
    try {
      body = Map<String, dynamic>.from(jsonDecode(res.body) as Map);
    } catch (_) {
      throw BridgeApiException('Server error ${res.statusCode}');
    }
    if (body['ok'] != true) {
      throw BridgeApiException('${body['error'] ?? 'Server error ${res.statusCode}'}');
    }
    return body;
  }

  Future<Map<String, dynamic>> heartbeat() async {
    final http.Response res =
        await _client.get(_uri('heartbeat.php'), headers: _headers).timeout(_timeout);
    return _decode(res);
  }

  Future<List<PrintJob>> claim(List<String> stations, {int limit = 5}) async {
    final Map<String, String> query = <String, String>{'limit': '$limit'};
    if (stations.isNotEmpty) {
      query['stations'] = stations.join(',');
    }
    final http.Response res = await _client
        .get(_uri('claim.php', query), headers: _headers)
        .timeout(_timeout);
    final Map<String, dynamic> body = await _decode(res);
    final dynamic jobs = body['jobs'];
    if (jobs is! List) {
      return <PrintJob>[];
    }
    return jobs
        .whereType<Map<dynamic, dynamic>>()
        .map((Map<dynamic, dynamic> e) =>
            PrintJob.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<void> ack(List<Map<String, dynamic>> acks) async {
    if (acks.isEmpty) {
      return;
    }
    final http.Response res = await _client
        .post(
          _uri('ack.php'),
          headers: <String, String>{
            ..._headers,
            'Content-Type': 'application/json',
          },
          body: jsonEncode(<String, dynamic>{'jobs': acks}),
        )
        .timeout(_timeout);
    await _decode(res);
  }

  void close() => _client.close();
}
