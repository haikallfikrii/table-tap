import 'package:flutter/material.dart';

import 'src/bridge_controller.dart';
import 'src/ui/home_page.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final BridgeController controller = BridgeController();
  await controller.init();
  runApp(PrintBridgeApp(controller: controller));
}

class PrintBridgeApp extends StatelessWidget {
  const PrintBridgeApp({super.key, required this.controller});

  final BridgeController controller;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'TableTap Print Bridge',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorSchemeSeed: const Color(0xFFE8590C),
      ),
      home: HomePage(controller: controller),
    );
  }
}
