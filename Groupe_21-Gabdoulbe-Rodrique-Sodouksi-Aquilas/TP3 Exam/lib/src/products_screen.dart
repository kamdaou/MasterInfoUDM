import 'package:flutter/material.dart';

import 'api_client.dart';
import 'format.dart';
import 'widgets.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  final _api = ApiClient();
  late Future<List<JsonMap>> _products;

  @override
  void initState() {
    super.initState();
    _products = _api.products();
  }

  void _refresh() => setState(() => _products = _api.products());

  Future<void> _openForm([JsonMap? product]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => ProductFormScreen(product: product)),
    );
    if (saved == true) _refresh();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Produits'),
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
        label: const Text('Ajouter'),
      ),
      body: FutureBuilder<List<JsonMap>>(
        future: _products,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView();
          }
          if (snapshot.hasError) {
            return ErrorView(message: '${snapshot.error}', onRetry: _refresh);
          }
          final items = snapshot.data!;
          if (items.isEmpty) return const EmptyView(message: 'Catalogue vide.');
          return RefreshIndicator(
            onRefresh: () async => _refresh(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
              itemCount: items.length,
              separatorBuilder: (context, index) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final product = items[index];
                final stock = (product['stock'] as num).toInt();
                return Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 6,
                    ),
                    leading: CircleAvatar(
                      backgroundColor: const Color(0xFFE9F2EB),
                      foregroundColor: Theme.of(context).colorScheme.primary,
                      child: const Icon(Icons.inventory_2_outlined),
                    ),
                    title: Text(
                      '${product['name']}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    subtitle: Padding(
                      padding: const EdgeInsets.only(top: 5),
                      child: Text(
                        '${formatCurrency(product['unitPrice'])} · $stock en stock',
                        style: TextStyle(
                          color: stock < 5
                              ? const Color(0xFFB65D35)
                              : Colors.black54,
                        ),
                      ),
                    ),
                    trailing: IconButton(
                      tooltip: 'Modifier le produit',
                      onPressed: () => _openForm(product),
                      icon: const Icon(Icons.edit_outlined),
                    ),
                    onTap: () => _openForm(product),
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

class ProductFormScreen extends StatefulWidget {
  const ProductFormScreen({super.key, this.product});

  final JsonMap? product;

  @override
  State<ProductFormScreen> createState() => _ProductFormScreenState();
}

class _ProductFormScreenState extends State<ProductFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _stock = TextEditingController();
  final _price = TextEditingController();
  final _photo = TextEditingController();
  final _api = ApiClient();
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final product = widget.product;
    _name.text = '${product?['name'] ?? ''}';
    _stock.text = '${product?['stock'] ?? 0}';
    _price.text = '${product?['unitPrice'] ?? ''}';
    _photo.text = '${product?['photo'] ?? ''}';
  }

  @override
  void dispose() {
    _name.dispose();
    _stock.dispose();
    _price.dispose();
    _photo.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await _api.saveProduct(
        {
          'name': _name.text.trim(),
          'stock': int.parse(_stock.text),
          'unitPrice': double.parse(_price.text.replaceAll(',', '.')),
          'photo': _photo.text.trim(),
        },
        id: widget.product == null
            ? null
            : (widget.product!['id'] as num).toInt(),
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
    final isEdit = widget.product != null;
    return Scaffold(
      appBar: AppBar(
          title: Text(isEdit ? 'Modifier le produit' : 'Nouveau produit')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            TextFormField(
              controller: _name,
              maxLength: 255,
              decoration: const InputDecoration(labelText: 'Nom du produit'),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Le nom est requis.'
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _stock,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Stock'),
              validator: (value) {
                final number = int.tryParse(value ?? '');
                return number == null || number < 0
                    ? 'Saisissez un stock positif ou nul.'
                    : null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _price,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              decoration:
                  const InputDecoration(labelText: 'Prix unitaire (FCFA)'),
              validator: (value) {
                final number =
                    double.tryParse((value ?? '').replaceAll(',', '.'));
                return number == null || number < 0
                    ? 'Saisissez un prix valide.'
                    : null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _photo,
              keyboardType: TextInputType.url,
              decoration: const InputDecoration(
                labelText: 'Photo (URL facultative)',
              ),
              validator: validateUrl,
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
              label: Text(isEdit ? 'Enregistrer' : 'Créer le produit'),
            ),
          ],
        ),
      ),
    );
  }
}
