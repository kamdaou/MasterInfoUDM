import 'package:flutter/material.dart';

import 'api_client.dart';
import 'format.dart';
import 'widgets.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _api = ApiClient();
  late Future<JsonMap> _dashboard;

  @override
  void initState() {
    super.initState();
    _dashboard = _api.dashboard();
  }

  void _refresh() => setState(() => _dashboard = _api.dashboard());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Commande Studio'),
        actions: [
          IconButton(
            tooltip: 'Actualiser',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<JsonMap>(
        future: _dashboard,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView();
          }
          if (snapshot.hasError) {
            return ErrorView(message: '${snapshot.error}', onRetry: _refresh);
          }
          final data = snapshot.data!;
          final summary = Map<String, dynamic>.from(data['summary'] as Map);
          final products = _maps(data['topProducts']);
          final customers = _maps(data['topCustomers']);
          final lowStock = _maps(data['lowStock']);
          final recentOrders = _maps(data['recentOrders']);
          final monthlySales = _maps(data['monthlySales']);
          return RefreshIndicator(
            onRefresh: () async => _refresh(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
              children: [
                Text(
                  'Votre activité',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Chiffres clés et suivi des ventes',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Colors.black54,
                      ),
                ),
                const SizedBox(height: 16),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  childAspectRatio: 1.2,
                  children: [
                    MetricCard(
                      label: 'Chiffre d’affaires',
                      value: formatCurrency(summary['revenue']),
                      icon: Icons.account_balance_wallet_outlined,
                      emphasized: true,
                    ),
                    MetricCard(
                      label: 'Ce mois-ci',
                      value: formatCurrency(summary['monthRevenue']),
                      icon: Icons.calendar_month_outlined,
                    ),
                    MetricCard(
                      label: 'Commandes',
                      value: '${summary['orders']}',
                      icon: Icons.receipt_long_outlined,
                    ),
                    MetricCard(
                      label: 'Unités vendues',
                      value: '${summary['units']}',
                      icon: Icons.inventory_2_outlined,
                    ),
                  ],
                ),
                const SectionTitle('Ventes sur six mois'),
                _MonthlyBars(items: monthlySales),
                const SectionTitle('Meilleures ventes'),
                _SimpleRows(
                  items: products,
                  primary: (item) => '${item['name']}',
                  secondary: (item) => '${item['units']} unités vendues',
                  trailing: (item) => Text(formatCurrency(item['revenue'])),
                  empty: 'Aucune vente pour le moment.',
                ),
                const SectionTitle('Commandes récentes'),
                _SimpleRows(
                  items: recentOrders,
                  primary: (item) =>
                      '#${item['id']} · ${item['firstName']} ${item['lastName']}',
                  secondary: (item) => formatDate(item['date']),
                  trailing: (item) => Text(formatCurrency(item['total'])),
                  empty: 'Aucune commande enregistrée.',
                ),
                const SectionTitle('Meilleurs clients'),
                _SimpleRows(
                  items: customers,
                  primary: (item) => '${item['firstName']} ${item['lastName']}',
                  secondary: (item) => '${item['orderCount']} commande(s)',
                  trailing: (item) => Text(formatCurrency(item['revenue'])),
                  empty: 'Aucun achat enregistré.',
                ),
                const SectionTitle('Stock bas'),
                _SimpleRows(
                  items: lowStock,
                  primary: (item) => '${item['name']}',
                  secondary: (item) => '${item['stock']} unité(s) restantes',
                  trailing: (_) => const Icon(
                    Icons.warning_amber_rounded,
                    color: Color(0xFFE88047),
                  ),
                  empty: 'Aucun produit sous cinq unités.',
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

List<JsonMap> _maps(Object? value) => (value as List<dynamic>? ?? [])
    .map((item) => Map<String, dynamic>.from(item as Map))
    .toList();

class _MonthlyBars extends StatelessWidget {
  const _MonthlyBars({required this.items});

  final List<JsonMap> items;

  @override
  Widget build(BuildContext context) {
    final maxRevenue = items.fold<double>(
      0,
      (max, item) =>
          max > _number(item['revenue']) ? max : _number(item['revenue']),
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 18, 14, 12),
        child: SizedBox(
          height: 140,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              for (final item in items)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Expanded(
                          child: Align(
                            alignment: Alignment.bottomCenter,
                            child: FractionallySizedBox(
                              heightFactor: maxRevenue == 0
                                  ? 0.02
                                  : (_number(item['revenue']) / maxRevenue)
                                      .clamp(0.02, 1)
                                      .toDouble(),
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  color: item == items.last
                                      ? const Color(0xFFE88047)
                                      : Theme.of(context).colorScheme.primary,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '${item['month']}'.substring(5),
                          style: Theme.of(context).textTheme.labelSmall,
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

double _number(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

class _SimpleRows extends StatelessWidget {
  const _SimpleRows({
    required this.items,
    required this.primary,
    required this.secondary,
    required this.trailing,
    required this.empty,
  });

  final List<JsonMap> items;
  final String Function(JsonMap) primary;
  final String Function(JsonMap) secondary;
  final Widget Function(JsonMap) trailing;
  final String empty;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return Card(child: ListTile(title: Text(empty)));
    return Card(
      child: Column(
        children: [
          for (var index = 0; index < items.length; index++) ...[
            ListTile(
              title: Text(
                primary(items[index]),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              subtitle: Text(secondary(items[index])),
              trailing: trailing(items[index]),
            ),
            if (index < items.length - 1)
              const Divider(height: 1, indent: 16, endIndent: 16),
          ],
        ],
      ),
    );
  }
}
