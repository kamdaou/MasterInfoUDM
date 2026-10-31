import 'package:flutter/material.dart';

import 'api_client.dart';
import 'format.dart';
import 'widgets.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final _api = ApiClient();
  late Future<List<JsonMap>> _orders;

  @override
  void initState() {
    super.initState();
    _orders = _api.orders();
  }

  void _refresh() => setState(() => _orders = _api.orders());

  Future<void> _openForm([JsonMap? order]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => OrderFormScreen(order: order)),
    );
    if (saved == true) _refresh();
  }

  Future<void> _openDetail(JsonMap order) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => OrderDetailScreen(id: (order['id'] as num).toInt()),
      ),
    );
    if (mounted) _refresh();
  }

  Future<void> _delete(JsonMap order) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer la commande ?'),
        content: const Text(
          'Les articles de cette commande seront remis en stock.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton.tonal(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _api.deleteOrder((order['id'] as num).toInt());
      if (mounted) _refresh();
    } catch (error) {
      if (mounted) showError(context, error);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Commandes'),
        actions: [
          IconButton(
            tooltip: 'Actualiser',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openForm(),
        icon: const Icon(Icons.add),
        label: const Text('Nouvelle'),
      ),
      body: FutureBuilder<List<JsonMap>>(
        future: _orders,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView();
          }
          if (snapshot.hasError) {
            return ErrorView(message: '${snapshot.error}', onRetry: _refresh);
          }
          final items = snapshot.data!;
          if (items.isEmpty) {
            return const EmptyView(message: 'Aucune commande.');
          }
          return RefreshIndicator(
            onRefresh: () async => _refresh(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
              itemCount: items.length,
              separatorBuilder: (context, index) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final order = items[index];
                final customer = '${order['firstName']} ${order['lastName']}';
                return Card(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(8),
                    onTap: () => _openDetail(order),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 12, 8, 12),
                      child: Row(
                        children: [
                          CircleAvatar(
                            backgroundColor: const Color(0xFFE9F2EB),
                            foregroundColor:
                                Theme.of(context).colorScheme.primary,
                            child: const Icon(Icons.receipt_long_outlined),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Commande #${order['id']}',
                                  style: Theme.of(context)
                                      .textTheme
                                      .labelMedium
                                      ?.copyWith(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .primary,
                                        fontWeight: FontWeight.w700,
                                      ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  customer,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                Text(
                                  '${formatDate(order['date'])} · ${order['units']} unité(s)',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 6),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                formatCurrency(order['total']),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              PopupMenuButton<String>(
                                tooltip: 'Actions commande',
                                onSelected: (action) {
                                  if (action == 'edit') _openForm(order);
                                  if (action == 'delete') _delete(order);
                                },
                                itemBuilder: (context) => const [
                                  PopupMenuItem(
                                    value: 'edit',
                                    child: ListTile(
                                      leading: Icon(Icons.edit_outlined),
                                      title: Text('Modifier'),
                                    ),
                                  ),
                                  PopupMenuItem(
                                    value: 'delete',
                                    child: ListTile(
                                      leading: Icon(Icons.delete_outline),
                                      title: Text('Supprimer'),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class OrderFormScreen extends StatefulWidget {
  const OrderFormScreen({super.key, this.order});

  final JsonMap? order;

  @override
  State<OrderFormScreen> createState() => _OrderFormScreenState();
}

class _OrderFormScreenState extends State<OrderFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _api = ApiClient();
  late Future<List<JsonMap>> _customers;
  int? _customerId;
  DateTime _date = DateTime.now();
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _customers = _api.customers();
    final order = widget.order;
    _customerId = (order?['customerId'] as num?)?.toInt();
    final dateText = '${order?['date'] ?? ''}'.split(' ').first;
    _date = DateTime.tryParse(dateText) ?? DateTime.now();
  }

  Future<void> _pickDate() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (date != null) setState(() => _date = date);
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await _api.saveOrder(
        {
          'customerId': _customerId,
          'date': '${_date.year.toString().padLeft(4, '0')}-'
              '${_date.month.toString().padLeft(2, '0')}-'
              '${_date.day.toString().padLeft(2, '0')}',
        },
        id: widget.order == null ? null : (widget.order!['id'] as num).toInt(),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isEdit = widget.order != null;
    return Scaffold(
      appBar: AppBar(
          title: Text(isEdit ? 'Modifier la commande' : 'Nouvelle commande')),
      body: FutureBuilder<List<JsonMap>>(
        future: _customers,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView();
          }
          if (snapshot.hasError) {
            return ErrorView(
              message: '${snapshot.error}',
              onRetry: () => setState(() => _customers = _api.customers()),
            );
          }
          final customers = snapshot.data!;
          if (customers.isEmpty) {
            return const EmptyView(
              message: 'Créez un client avant d’enregistrer une commande.',
            );
          }
          return Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                DropdownButtonFormField<int>(
                  initialValue:
                      customers.any((item) => item['id'] == _customerId)
                          ? _customerId
                          : null,
                  decoration: const InputDecoration(labelText: 'Client'),
                  items: [
                    for (final customer in customers)
                      DropdownMenuItem<int>(
                        value: (customer['id'] as num).toInt(),
                        child: Text(
                          '${customer['firstName']} ${customer['lastName']}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                  ],
                  validator: (value) =>
                      value == null ? 'Choisissez un client.' : null,
                  onChanged: (value) => setState(() => _customerId = value),
                ),
                const SizedBox(height: 16),
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.calendar_month_outlined),
                    title: const Text('Date de commande'),
                    subtitle: Text(formatDate(_date.toIso8601String())),
                    trailing: const Icon(Icons.edit_calendar_outlined),
                    onTap: _pickDate,
                  ),
                ),
                const SizedBox(height: 24),
                FilledButton.icon(
                  onPressed: _saving ? null : _save,
                  icon: _saving
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.check),
                  label: Text(isEdit ? 'Enregistrer' : 'Créer la commande'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({required this.id, super.key});

  final int id;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  final _api = ApiClient();
  late Future<JsonMap> _order;

  @override
  void initState() {
    super.initState();
    _order = _api.orderDetail(widget.id);
  }

  void _refresh() => setState(() => _order = _api.orderDetail(widget.id));

  Future<void> _addLine(JsonMap order) async {
    final products = (order['products'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    if (products.isEmpty) {
      showError(context, 'Aucun produit disponible en stock.');
      return;
    }
    int? productId;
    var quantity = 1;
    final selection = await showDialog<(int, int)?>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Ajouter un produit'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<int>(
                initialValue: productId,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Produit'),
                items: [
                  for (final product in products)
                    DropdownMenuItem<int>(
                      value: (product['id'] as num).toInt(),
                      child: Text(
                        '${product['name']} · ${product['stock']} en stock',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: (value) => setDialogState(() => productId = value),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  const Expanded(child: Text('Quantité')),
                  IconButton(
                    tooltip: 'Diminuer',
                    onPressed: quantity <= 1
                        ? null
                        : () => setDialogState(() => quantity--),
                    icon: const Icon(Icons.remove_circle_outline),
                  ),
                  Text('$quantity'),
                  IconButton(
                    tooltip: 'Augmenter',
                    onPressed: () => setDialogState(() => quantity++),
                    icon: const Icon(Icons.add_circle_outline),
                  ),
                ],
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Annuler'),
            ),
            FilledButton(
              onPressed: productId == null
                  ? null
                  : () => Navigator.pop(context, (productId!, quantity)),
              child: const Text('Ajouter'),
            ),
          ],
        ),
      ),
    );
    if (selection == null) return;
    try {
      await _api.addOrderLine(widget.id, selection.$1, selection.$2);
      _refresh();
    } catch (error) {
      if (mounted) showError(context, error);
    }
  }

  Future<void> _deleteLine(int productId) async {
    try {
      await _api.deleteOrderLine(widget.id, productId);
      _refresh();
    } catch (error) {
      if (mounted) showError(context, error);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Commande #${widget.id}')),
      floatingActionButton: FutureBuilder<JsonMap>(
        future: _order,
        builder: (context, snapshot) => snapshot.hasData
            ? FloatingActionButton.extended(
                onPressed: () => _addLine(snapshot.data!),
                icon: const Icon(Icons.add_shopping_cart),
                label: const Text('Ajouter un article'),
              )
            : const SizedBox.shrink(),
      ),
      body: FutureBuilder<JsonMap>(
        future: _order,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView();
          }
          if (snapshot.hasError) {
            return ErrorView(message: '${snapshot.error}', onRetry: _refresh);
          }
          final order = snapshot.data!;
          final lines = (order['lines'] as List<dynamic>? ?? [])
              .map((item) => Map<String, dynamic>.from(item as Map))
              .toList();
          return RefreshIndicator(
            onRefresh: () async => _refresh(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${order['firstName']} ${order['lastName']}',
                          style:
                              Theme.of(context).textTheme.titleLarge?.copyWith(
                                    fontWeight: FontWeight.w800,
                                  ),
                        ),
                        const SizedBox(height: 5),
                        Text(formatDate(order['date'])),
                        const SizedBox(height: 18),
                        Text(
                          formatCurrency(order['total']),
                          style: Theme.of(context)
                              .textTheme
                              .headlineSmall
                              ?.copyWith(
                                color: Theme.of(context).colorScheme.primary,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        Text('${order['units']} unités commandées'),
                      ],
                    ),
                  ),
                ),
                const SectionTitle('Articles commandés'),
                if (lines.isEmpty)
                  const Card(
                    child:
                        ListTile(title: Text('Aucun article pour le moment.')),
                  )
                else
                  for (final line in lines)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        title: Text('${line['name']}'),
                        subtitle: Text(
                          '${line['quantity']} × ${formatCurrency(line['unitPrice'])}',
                        ),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              formatCurrency(line['total']),
                              style:
                                  const TextStyle(fontWeight: FontWeight.w700),
                            ),
                            IconButton(
                              tooltip: 'Retirer de la commande',
                              onPressed: () => _deleteLine(
                                (line['productId'] as num).toInt(),
                              ),
                              icon: const Icon(
                                Icons.delete_outline,
                                color: Color(0xFFB65D35),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
              ],
            ),
          );
        },
      ),
    );
  }
}
