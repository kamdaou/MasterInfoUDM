import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'package:commande_studio_mobile/src/format.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  group('formatCurrency', () {
    test('adds the FCFA currency suffix', () {
      expect(formatCurrency(91500), endsWith(' FCFA'));
    });

    test('handles numeric values returned as strings', () {
      expect(formatCurrency('4500.00'), endsWith(' FCFA'));
    });
  });

  group('formatDate', () {
    test('formats API date values for display', () {
      expect(formatDate('2026-10-12 00:00:00'), '12/10/2026');
    });

    test('returns a fallback for invalid dates', () {
      expect(formatDate('not-a-date'), 'Date inconnue');
    });
  });

  group('validateUrl', () {
    test('allows an empty optional URL', () {
      expect(validateUrl(''), isNull);
    });

    test('accepts a complete web URL', () {
      expect(validateUrl('https://example.test/photo.png'), isNull);
    });

    test('rejects an incomplete URL', () {
      expect(validateUrl('example.test/photo.png'), isNotNull);
    });
  });
}
