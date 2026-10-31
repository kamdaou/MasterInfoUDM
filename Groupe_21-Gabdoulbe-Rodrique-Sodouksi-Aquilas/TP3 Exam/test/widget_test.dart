import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:commande_studio_mobile/src/app_shell.dart';

void main() {
  testWidgets('shows the four main navigation destinations', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: AppShell()));

    expect(find.text('Accueil'), findsOneWidget);
    expect(find.text('Produits'), findsOneWidget);
    expect(find.text('Commandes'), findsOneWidget);
    expect(find.text('Clients'), findsOneWidget);
  });
}
