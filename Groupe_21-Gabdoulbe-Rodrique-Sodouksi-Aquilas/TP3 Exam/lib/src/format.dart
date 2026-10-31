import 'package:intl/intl.dart';

final _currencyFormat = NumberFormat('#,##0', 'fr_FR');
final _dateFormat = DateFormat('dd/MM/yyyy', 'fr_FR');

String formatCurrency(Object? value) {
  final amount = value is num ? value : num.tryParse('$value') ?? 0;
  return '${_currencyFormat.format(amount)} FCFA';
}

String formatDate(Object? value) {
  final date = DateTime.tryParse('$value');
  return date == null ? 'Date inconnue' : _dateFormat.format(date);
}

String? validateUrl(String? value) {
  final text = value?.trim() ?? '';
  if (text.isEmpty) return null;
  final uri = Uri.tryParse(text);
  if (uri == null || !uri.hasScheme || !uri.hasAuthority) {
    return 'Saisissez une URL valide.';
  }
  return null;
}