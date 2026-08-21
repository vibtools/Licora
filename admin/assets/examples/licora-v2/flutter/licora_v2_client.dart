import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:cryptography/cryptography.dart';
import 'package:http/http.dart' as http;

/// Licora Secure API v2 lifecycle reference for Flutter/Dart.
///
/// This creates an ephemeral P-256 device for developer testing and deactivates
/// it before exit. A production app must persist the private key and rotated
/// refresh token in platform secure storage, and verify LICORA-V2/RS256 tokens
/// with the pinned Licora server public key before trusting token claims.
class LicoraV2Client {
  LicoraV2Client(this.baseUrl, this.appId, this.appVersion);

  final String baseUrl;
  final String appId;
  final String appVersion;
  final Ecdsa _ecdsa = Ecdsa.p256(Sha256());
  final Random _random = Random.secure();
  SimpleKeyPair? _keyPair;
  String? _publicPem;
  late final String deviceId = 'flutter-${_randomBytes(16).map((b) => b.toRadixString(16).padLeft(2, '0')).join()}';

  List<int> _randomBytes(int count) => List<int>.generate(count, (_) => _random.nextInt(256));
  String _endpoint(String name) => '${baseUrl.replaceFirst(RegExp(r'/+$'), '')}/api/v2/$name.php';
  String _b64url(List<int> data) => base64Url.encode(data).replaceAll('=', '');

  Future<String> _sha256Hex(List<int> data) async {
    final hash = await Sha256().hash(data);
    return hash.bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
  }

  Future<void> _ensureKey() async {
    if (_keyPair != null) return;
    _keyPair = await _ecdsa.newKeyPair();
    final publicKey = await _keyPair!.extractPublicKey();
    final raw = publicKey.bytes;
    if (raw.length != 65 || raw.first != 0x04) {
      throw StateError('Unexpected P-256 public-key encoding; expected uncompressed 65-byte point.');
    }
    // X.509 SubjectPublicKeyInfo prefix for id-ecPublicKey + prime256v1.
    const prefixHex = '3059301306072a8648ce3d020106082a8648ce3d030107034200';
    final prefix = <int>[];
    for (var i = 0; i < prefixHex.length; i += 2) {
      prefix.add(int.parse(prefixHex.substring(i, i + 2), radix: 16));
    }
    final der = Uint8List.fromList([...prefix, ...raw]);
    final encoded = base64.encode(der);
    final lines = <String>[];
    for (var i = 0; i < encoded.length; i += 64) {
      lines.add(encoded.substring(i, min(i + 64, encoded.length)));
    }
    _publicPem = '-----BEGIN PUBLIC KEY-----\n${lines.join('\n')}\n-----END PUBLIC KEY-----\n';
  }

  List<int> _ecdsaDer(List<int> signature) {
    if (signature.isNotEmpty && signature.first == 0x30) return signature;
    if (signature.length != 64) throw StateError('Unexpected P-256 signature length: ${signature.length}');
    List<int> integer(List<int> value) {
      var i = 0;
      while (i < value.length - 1 && value[i] == 0) i++;
      var bytes = value.sublist(i);
      if ((bytes.first & 0x80) != 0) bytes = [0, ...bytes];
      return [0x02, bytes.length, ...bytes];
    }
    final r = integer(signature.sublist(0, 32));
    final s = integer(signature.sublist(32, 64));
    final body = [...r, ...s];
    return [0x30, body.length, ...body];
  }

  String _jti(String token) {
    final parts = token.split('.');
    if (parts.length != 3) throw StateError('Licora returned a malformed access token.');
    final payload = jsonDecode(utf8.decode(base64Url.decode(base64Url.normalize(parts[1])))) as Map<String, dynamic>;
    return payload['jti'] as String;
  }

  Future<Map<String, dynamic>> _post(String name, Map<String, dynamic> payload, String context, [String? accessToken]) async {
    await _ensureKey();
    final url = _endpoint(name);
    final bodyText = jsonEncode(payload);
    final body = utf8.encode(bodyText);
    final timestamp = DateTime.now().toUtc().millisecondsSinceEpoch ~/ 1000;
    final nonce = _b64url(_randomBytes(18));
    final canonical = ['POST', Uri.parse(url).path, '$timestamp', nonce, await _sha256Hex(body), context].join('\n');
    final signature = await _ecdsa.sign(utf8.encode(canonical), keyPair: _keyPair!);
    final response = await http.post(Uri.parse(url), body: body, headers: {
      'Content-Type': 'application/json',
      'X-Licora-Timestamp': '$timestamp',
      'X-Licora-Nonce': nonce,
      'X-Licora-Device-Signature': _b64url(_ecdsaDer(signature.bytes)),
      if (accessToken != null) 'Authorization': 'Bearer $accessToken',
    });
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (data['success'] != true) throw StateError('Licora error ${data['code'] ?? 'UNKNOWN'} (HTTP ${response.statusCode})');
    return data;
  }

  Future<Map<String, dynamic>> activate(String licenseKey) async {
    await _ensureKey();
    return _post('activate', {
      'license_key': licenseKey, 'app_id': appId, 'app_version': appVersion,
      'device_id': deviceId, 'device_public_key': _publicPem,
    }, 'activate:$appId');
  }

  Future<Map<String, dynamic>> status(String accessToken) => _post('status', {}, _jti(accessToken), accessToken);
  Future<Map<String, dynamic>> refresh(String refreshToken) async => _post('refresh', {
    'refresh_token': refreshToken, 'app_version': appVersion,
  }, 'refresh:${await _sha256Hex(utf8.encode(refreshToken))}');
  Future<Map<String, dynamic>> deactivate(String accessToken) => _post('deactivate', {}, _jti(accessToken), accessToken);

  static Future<void> lifecycleTest({required String baseUrl, required String appId, required String licenseKey, String appVersion = '1.0.0'}) async {
    final client = LicoraV2Client(baseUrl, appId, appVersion);
    String accessToken = '';
    try {
      final activated = await client.activate(licenseKey); accessToken = activated['access_token'] as String; var refreshToken = activated['refresh_token'] as String; print('[PASS] activate');
      await client.status(accessToken); print('[PASS] status');
      final refreshed = await client.refresh(refreshToken); accessToken = refreshed['access_token'] as String; refreshToken = refreshed['refresh_token'] as String; print('[PASS] refresh (rotated)');
      await client.status(accessToken); print('[PASS] status-after-refresh');
      await client.deactivate(accessToken); accessToken = ''; print('[PASS] deactivate');
    } finally {
      if (accessToken.isNotEmpty) { try { await client.deactivate(accessToken); print('[INFO] cleanup deactivate completed'); } catch (_) { print('[WARN] cleanup deactivate failed'); } }
    }
  }
}
