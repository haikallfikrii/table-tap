import 'dart:io';
import 'dart:typed_data';

/// Raw ESC/POS over TCP — almost every Wi-Fi/LAN thermal printer listens on 9100.
class PrinterClient {
  static const Duration connectTimeout = Duration(seconds: 6);
  static const Duration flushTimeout = Duration(seconds: 10);

  Future<void> send(String host, int port, Uint8List bytes) async {
    Socket? socket;
    try {
      socket = await Socket.connect(host, port, timeout: connectTimeout);
      socket.add(bytes);
      await socket.flush().timeout(flushTimeout);
      // Give the print head time to swallow the buffer before the FIN.
      await Future<void>.delayed(const Duration(milliseconds: 250));
    } finally {
      try {
        await socket?.close();
      } catch (_) {
        // socket already gone
      }
      socket?.destroy();
    }
  }

  /// Quick reachability check for the settings screen.
  Future<bool> ping(String host, int port) async {
    try {
      final Socket socket = await Socket.connect(host, port, timeout: connectTimeout);
      socket.destroy();
      return true;
    } catch (_) {
      return false;
    }
  }
}
