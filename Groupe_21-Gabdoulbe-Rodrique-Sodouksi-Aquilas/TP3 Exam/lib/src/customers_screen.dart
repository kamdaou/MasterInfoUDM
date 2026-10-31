import 'package:flutter/material.dart';

import 'api_client.dart';
import 'format.dart';
import 'widgets.dart';

class CustomersScreen extends StatefulWidget {
  const CustomersScreen({super.key});

  @override
  State<CustomersScreen> createState() => _CustomersScreenState();
}

class _CustomersScreenState extends State<CustomersScreen> {
  final _api = ApiClient();
  late Future<List<JsonMap>> _customers;

  @override
  void initState() {
    super.initState();
    _customers = _api.customers();
  }

  void _refresh() => setState(() => _customers = _api.customers());

  Future<void> _openForm([JsonMap? customer]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => CustomerFormScreen(customer: customer),
      ),
    );
    if (saved == true) _refresh();
  }

  Future<void> _delete(JsonMap customer) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer le client ?'),
        content: Text(
          '${customer['firstName']} ${customer['lastName']} sera supprimé si aucune commande ne lui est associée.',
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
      await _api.deleteCustomer((customer['id'] as num).toInt());
      if (mounted) _refresh();
    } catch (error) {
      if (mounted) showError(context, error);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Clients'),
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
        icon: const Icon(Icons.person_add_alt_1),
        label: const Text('Ajouter'),
      ),
      body: FutureBuilder<List<JsonMap>>(
        future: _customers,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView();
          }
          if (snapshot.hasError) {
            return ErrorView(message: '${snapshot.error}', onRetry: _refresh);
          }
          final items = snapshot.data!;
          if (items.isEmpty) return const EmptyView(message: 'Aucun client.');
          return RefreshIndicator(
            onRefresh: () async => _refresh(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
              itemCount: items.length,
              separatorBuilder: (context, index) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final customer = items[index];
                final orderCount = (customer['orderCount'] as num).toInt();
                final name = '${customer['firstName']} ${customer['lastName']}';
                return Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.only(
                      left: 16,
                      right: 6,
                      top: 5,
                      bottom: 5,
                    ),
                    leading: CircleAvatar(
                      backgroundColor: const Color(0xFFE9F2EB),
                      foregroundColor: Theme.of(context).colorScheme.primary,
                      child: Text(
                        '${customer['firstName']}'
                            .characters
                            .first
                            .toUpperCase(),
                      ),
                    ),
                    title: Text(name,
                        maxLines: 1, overflow: TextOverflow.ellipsis),
                    subtitle: Text(
                      [
                        if ('${customer['email'] ?? ''}'.isNotEmpty)
                          '${customer['email']}',
                        if ('${customer['region'] ?? ''}'.isNotEmpty)
                          '${customer['region']}',
                        '$orderCount commande(s)',
                      ].join(' · '),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    trailing: PopupMenuButton<String>(
                      tooltip: 'Actions client',
                      onSelected: (action) {
                        if (action == 'edit') _openForm(customer);
                        if (action == 'delete') _delete(customer);
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
                    onTap: () => _openForm(customer),
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

class CustomerFormScreen extends StatefulWidget {
  const CustomerFormScreen({super.key, this.customer});

  final JsonMap? customer;

  @override
  State<CustomerFormScreen> createState() => _CustomerFormScreenState();
}

class _CustomerFormScreenState extends State<CustomerFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _email = TextEditingController();
  final _photo = TextEditingController();
  final _api = ApiClient();
  late Future<List<JsonMap>> _regions;
  int? _regionId;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final customer = widget.customer;
    _firstName.text = '${customer?['firstName'] ?? ''}';
    _lastName.text = '${customer?['lastName'] ?? ''}';
    _email.text = '${customer?['email'] ?? ''}';
    _photo.text = '${customer?['photo'] ?? ''}';
    _regionId = (customer?['regionId'] as num?)?.toInt();
    _regions = _api.regions();
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    _photo.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await _api.saveCustomer(
        {
          'firstName': _firstName.text.trim(),
          'lastName': _lastName.text.trim(),
          'email': _email.text.trim(),
          'photo': _photo.text.trim(),
          'regionId': _regionId,
        },
        id: widget.customer == null
            ? null
            : (widget.customer!['id'] as num).toInt(),
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
    final isEdit = widget.customer != null;
    return Scaffold(
      appBar:
          AppBar(title: Text(isEdit ? 'Modifier le client' : 'Nouveau client')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            TextFormField(
              controller: _firstName,
              maxLength: 150,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Prénom'),
              validator: _required,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _lastName,
              maxLength: 150,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Nom'),
              validator: _required,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration:
                  const InputDecoration(labelText: 'E-mail (facultatif)'),
              validator: (value) {
                final text = value?.trim() ?? '';
                if (text.isEmpty) return null;
                return RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(text)
                    ? null
                    : 'Saisissez une adresse e-mail valide.';
              },
            ),
            const SizedBox(height: 16),
            FutureBuilder<List<JsonMap>>(
              future: _regions,
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return Text('Régions indisponibles : ${snapshot.error}');
                }
                final regions = snapshot.data ?? [];
                return DropdownButtonFormField<int>(
                  initialValue:
                      regions.any((region) => region['id'] == _regionId)
                          ? _regionId
                          : null,
                  decoration: const InputDecoration(labelText: 'Région'),
                  items: [
                    const DropdownMenuItem<int>(
                      value: null,
                      child: Text('Sans région'),
                    ),
                    for (final region in regions)
                      DropdownMenuItem<int>(
                        value: (region['id'] as num).toInt(),
                        child: Text('${region['name']}'),
                      ),
                  ],
                  onChanged: (value) => setState(() => _regionId = value),
                );
              },
            ),
            const SizedBox(height: 16),
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
              label: Text(isEdit ? 'Enregistrer' : 'Créer le client'),
            ),
          ],
        ),
      ),
    );
  }
}

String? _required(String? value) =>
    value == null || value.trim().isEmpty ? 'Ce champ est requis.' : null;
