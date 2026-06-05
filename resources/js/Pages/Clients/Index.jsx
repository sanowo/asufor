import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '../../Layouts/MainLayout';
import PrintButton from '../../Components/PrintButton';
import axios from 'axios';

export default function ClientsIndex({ quartiers, usages }) {
    const [clients, setClients] = useState([]);
    const [meta, setMeta] = useState({ count: 0, actifs: 0, suspendus: 0 });
    const [loading, setLoading] = useState(false);
    const [activeTab, setActiveTab] = useState('all');

    // Filters
    const [filters, setFilters] = useState({
        search: '',
        quartier: '*',
        usage: '*',
        statut: '*',
        view: 'all'
    });

    // Pagination
    const [pagination, setPagination] = useState({
        start: 0,
        length: 50,
        total: 0
    });

    // Modals
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [selectedClient, setSelectedClient] = useState(null);

    // Compteur modals
    const [showCompteurModal, setShowCompteurModal] = useState(false);
    const [showEditCompteurModal, setShowEditCompteurModal] = useState(false);
    const [selectedCompteur, setSelectedCompteur] = useState(null);

    // Forms
    const [createForm, setCreateForm] = useState({
        num_client: '',
        nom: '',
        prenom: '',
        telephone: '',
        id_quartier: '',
        used: '',
        abonnement: '',
        statut: 1
    });

    const [editForm, setEditForm] = useState({
        nom: '',
        prenom: '',
        telephone: '',
        id_quartier: '',
        used: '',
        abonnement: '',
        statut: 1
    });

    const [errors, setErrors] = useState({});

    // Compteur forms
    const [compteurForm, setCompteurForm] = useState({
        num_compteur: '',
        date_start: new Date().toISOString().split('T')[0],
        actif: 1
    });

    const [editCompteurForm, setEditCompteurForm] = useState({
        num_compteur: '',
        actif: 1
    });

    // Change tab
    const changeTab = (tab) => {
        setActiveTab(tab);
        setFilters(prev => ({ ...prev, view: tab }));
        setPagination(prev => ({ ...prev, start: 0 }));
    };

    // Load clients
    const loadClients = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/clients/list', {
                params: {
                    ...filters,
                    start: pagination.start,
                    length: pagination.length
                }
            });

            setClients(response.data.data.result);
            setMeta(response.data.data.meta);
            setPagination(prev => ({ ...prev, total: response.data.recordsTotal }));
        } catch (error) {
            console.error('Error loading clients:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadClients();
    }, [filters, pagination.start, pagination.length]);

    // View client details
    const viewClient = async (num_client) => {
        try {
            const response = await axios.get(`/clients/${num_client}`);
            setSelectedClient(response.data);
            setShowViewModal(true);
        } catch (error) {
            alert('Erreur lors du chargement du client');
        }
    };

    // Create client
    const handleCreate = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            await axios.post('/clients', createForm);
            alert('Client créé avec succès');
            setShowCreateModal(false);
            setCreateForm({
                num_client: '',
                nom: '',
                prenom: '',
                telephone: '',
                id_quartier: '',
                used: '',
                abonnement: '',
                statut: 0
            });
            loadClients();
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la création');
            }
        }
    };

    // Edit client
    const openEditModal = (client) => {
        setEditForm({
            nom: client.NOM,
            prenom: client.PRENOM,
            telephone: client.TELEPHONE || '',
            id_quartier: client.ID_QUARTIER,
            used: client.USED,
            abonnement: client.ABONNEMENT || '',
            statut: client.STATUT
        });
        setSelectedClient(client);
        setShowEditModal(true);
    };

    const handleEdit = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            await axios.put(`/clients/${selectedClient.NUM_CLIENT}`, editForm);
            alert('Client mis à jour avec succès');
            setShowEditModal(false);
            loadClients();
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la mise à jour');
            }
        }
    };

    // Delete client
    const handleDelete = async (num_client) => {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce client ?')) return;

        try {
            await axios.delete(`/clients/${num_client}`);
            alert('Client supprimé avec succès');
            loadClients();
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            } else {
                alert('Erreur lors de la suppression');
            }
        }
    };

    // === GESTION COMPTEURS ===

    // Ouvrir modal ajout compteur
    const openCompteurModal = () => {
        setCompteurForm({
            num_compteur: '',
            date_start: new Date().toISOString().split('T')[0],
            actif: 1
        });
        setErrors({});
        setShowCompteurModal(true);
    };

    // Créer compteur
    const handleCreateCompteur = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.post('/compteurs', {
                ...compteurForm,
                id_client: selectedClient.client.ID_CLIENT
            });

            if (response.data.success) {
                alert('Compteur ajouté avec succès !');
                setShowCompteurModal(false);
                // Recharger les détails du client
                viewClient(selectedClient.client.NUM_CLIENT);
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la création du compteur');
            }
        }
    };

    // Ouvrir modal édition compteur
    const openEditCompteurModal = (compteur) => {
        setSelectedCompteur(compteur);
        setEditCompteurForm({
            num_compteur: compteur.NUM_COMPTEUR,
            actif: compteur.ACTIF
        });
        setErrors({});
        setShowEditCompteurModal(true);
    };

    // Modifier compteur
    const handleUpdateCompteur = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.put(`/compteurs/${selectedCompteur.ID_COMPTEUR}`, editCompteurForm);

            if (response.data.success) {
                alert('Compteur modifié avec succès !');
                setShowEditCompteurModal(false);
                setSelectedCompteur(null);
                // Recharger les détails du client
                viewClient(selectedClient.client.NUM_CLIENT);
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la modification du compteur');
            }
        }
    };

    // Supprimer compteur
    const handleDeleteCompteur = async (id_compteur) => {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce compteur ?')) return;

        try {
            await axios.delete(`/compteurs/${id_compteur}`);
            alert('Compteur supprimé avec succès');
            // Recharger les détails du client
            viewClient(selectedClient.client.NUM_CLIENT);
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            } else {
                alert('Erreur lors de la suppression');
            }
        }
    };

    // Get tab label
    const getTabLabel = (tab) => {
        switch(tab) {
            case 'all': return 'Tous les Clients';
            case 'actifs': return 'Clients Actifs';
            case 'suspendus': return 'Clients Suspendus';
            case 'retard': return 'Retardataires';
            case 'social': return 'Usage Social';
            case 'inactifs': return 'Non Abonnés';
            default: return 'Clients';
        }
    };

    return (
        <MainLayout title="Gestion Clients">
            <Head title="Clients" />

            {/* Header */}
            <div className="mb-6 flex justify-between items-center">
                <h1 className="text-2xl font-bold">{getTabLabel(activeTab)}</h1>
                <button
                    onClick={() => setShowCreateModal(true)}
                    className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                >
                    + Nouveau Client
                </button>
            </div>

            {/* Tabs */}
            <div className="mb-6 border-b border-gray-200">
                <nav className="-mb-px flex space-x-8 overflow-x-auto">
                    {[
                        { key: 'all', label: 'Tous' },
                        { key: 'actifs', label: 'Actifs' },
                        { key: 'suspendus', label: 'Suspendus' },
                        { key: 'retard', label: 'Retardataires' },
                        { key: 'social', label: 'Social' },
                        { key: 'inactifs', label: 'Non Abonnés' }
                    ].map(tab => (
                        <button
                            key={tab.key}
                            onClick={() => changeTab(tab.key)}
                            className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm ${
                                activeTab === tab.key
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Statistics Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Total Clients</div>
                    <div className="text-2xl font-bold">{meta.count}</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Clients Actifs</div>
                    <div className="text-2xl font-bold text-green-600">{meta.actifs}</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="flex justify-between items-center">
                        <div>
                            <div className="text-sm text-gray-500">Clients Suspendus</div>
                            <div className="text-2xl font-bold text-red-600">{meta.suspendus}</div>
                        </div>
                        {activeTab === 'suspendus' && meta.suspendus > 0 && (
                            <PrintButton
                                endpoint="/print/clients-suspendus"
                                data={{}}
                                label="Imprimer"
                                icon="printer"
                                filename={`clients-suspendus-${Date.now()}.pdf`}
                                className="bg-red-600 hover:bg-red-700 text-xs px-2 py-1"
                            />
                        )}
                    </div>
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white p-4 rounded shadow mb-6">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label className="block text-sm font-medium mb-1">Recherche</label>
                        <input
                            type="text"
                            placeholder="N°, Nom, Prénom, Tél..."
                            className="w-full border rounded px-3 py-2"
                            value={filters.search}
                            onChange={(e) => setFilters({ ...filters, search: e.target.value })}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Quartier</label>
                        <select
                            className="w-full border rounded px-3 py-2"
                            value={filters.quartier}
                            onChange={(e) => setFilters({ ...filters, quartier: e.target.value })}
                        >
                            <option value="*">Tous</option>
                            {quartiers.map(q => (
                                <option key={q.ID_QUARTIER} value={q.ID_QUARTIER}>{q.NOM}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Usage</label>
                        <select
                            className="w-full border rounded px-3 py-2"
                            value={filters.usage}
                            onChange={(e) => setFilters({ ...filters, usage: e.target.value })}
                        >
                            <option value="*">Tous</option>
                            {usages.map(u => (
                                <option key={u.ID_USAGE} value={u.ID_USAGE}>{u.NOM}</option>
                            ))}
                        </select>
                    </div>
                    {activeTab === 'all' && (
                        <div>
                            <label className="block text-sm font-medium mb-1">Statut</label>
                            <select
                                className="w-full border rounded px-3 py-2"
                                value={filters.statut}
                                onChange={(e) => setFilters({ ...filters, statut: e.target.value })}
                            >
                                <option value="*">Tous</option>
                                <option value="1">Actifs</option>
                                <option value="0">Suspendus</option>
                            </select>
                        </div>
                    )}
                </div>
            </div>

            {/* Clients Table */}
            <div className="bg-white rounded shadow overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Client</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom & Prénom</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Téléphone</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quartier</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usage</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Compteurs</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Impayés</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {loading ? (
                                <tr>
                                    <td colSpan="9" className="px-4 py-8 text-center text-gray-500">
                                        Chargement...
                                    </td>
                                </tr>
                            ) : clients.length === 0 ? (
                                <tr>
                                    <td colSpan="9" className="px-4 py-8 text-center text-gray-500">
                                        Aucun client trouvé
                                    </td>
                                </tr>
                            ) : (
                                clients.map(client => (
                                    <tr key={client.NUM_CLIENT} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm">{client.NUM_CLIENT}</td>
                                        <td className="px-4 py-3 text-sm">
                                            <div className="font-medium">{client.NOM}</div>
                                            <div className="text-gray-500">{client.PRENOM}</div>
                                        </td>
                                        <td className="px-4 py-3 text-sm">{client.TELEPHONE || '-'}</td>
                                        <td className="px-4 py-3 text-sm">{client.QUARTIER}</td>
                                        <td className="px-4 py-3 text-sm">{client.USAGE_NOM}</td>
                                        <td className="px-4 py-3 text-sm">{client.NB_COMPTEURS}</td>
                                        <td className="px-4 py-3 text-sm">
                                            <span className={client.TOTAL_IMPAYE > 0 ? 'text-red-600 font-medium' : ''}>
                                                {client.TOTAL_IMPAYE > 0 ? `${client.TOTAL_IMPAYE} FCFA` : '-'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm">
                                            <span className={`px-2 py-1 rounded text-xs ${
                                                client.STATUT === 1
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            }`}>
                                                {client.STATUT === 1 ? 'Actif' : 'Suspendu'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm">
                                            <div className="flex gap-2">
                                                <button
                                                    onClick={() => viewClient(client.NUM_CLIENT)}
                                                    className="text-blue-600 hover:text-blue-800"
                                                    title="Voir"
                                                >
                                                    👁
                                                </button>
                                                <button
                                                    onClick={() => openEditModal(client)}
                                                    className="text-yellow-600 hover:text-yellow-800"
                                                    title="Modifier"
                                                >
                                                    ✏️
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(client.NUM_CLIENT)}
                                                    className="text-red-600 hover:text-red-800"
                                                    title="Supprimer"
                                                >
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="px-4 py-3 border-t flex items-center justify-between">
                    <div className="text-sm text-gray-700">
                        Affichage de {pagination.start + 1} à {Math.min(pagination.start + pagination.length, pagination.total)} sur {pagination.total} clients
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={() => setPagination(prev => ({ ...prev, start: Math.max(0, prev.start - prev.length) }))}
                            disabled={pagination.start === 0}
                            className="px-3 py-1 border rounded disabled:opacity-50"
                        >
                            Précédent
                        </button>
                        <button
                            onClick={() => setPagination(prev => ({ ...prev, start: prev.start + prev.length }))}
                            disabled={pagination.start + pagination.length >= pagination.total}
                            className="px-3 py-1 border rounded disabled:opacity-50"
                        >
                            Suivant
                        </button>
                    </div>
                </div>
            </div>

            {/* Create Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <h2 className="text-xl font-bold mb-4">Nouveau Client</h2>
                        <form onSubmit={handleCreate} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">N° Client *</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={createForm.num_client}
                                        onChange={(e) => setCreateForm({ ...createForm, num_client: e.target.value })}
                                        required
                                    />
                                    {errors.num_client && <p className="text-red-500 text-sm mt-1">{errors.num_client}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Nom *</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={createForm.nom}
                                        onChange={(e) => setCreateForm({ ...createForm, nom: e.target.value })}
                                        required
                                    />
                                    {errors.nom && <p className="text-red-500 text-sm mt-1">{errors.nom}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Prénom *</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={createForm.prenom}
                                        onChange={(e) => setCreateForm({ ...createForm, prenom: e.target.value })}
                                        required
                                    />
                                    {errors.prenom && <p className="text-red-500 text-sm mt-1">{errors.prenom}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Téléphone</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={createForm.telephone}
                                        onChange={(e) => setCreateForm({ ...createForm, telephone: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Quartier *</label>
                                    <select
                                        className="w-full border rounded px-3 py-2"
                                        value={createForm.id_quartier}
                                        onChange={(e) => setCreateForm({ ...createForm, id_quartier: e.target.value })}
                                        required
                                    >
                                        <option value="">Sélectionner...</option>
                                        {quartiers.map(q => (
                                            <option key={q.ID_QUARTIER} value={q.ID_QUARTIER}>{q.NOM}</option>
                                        ))}
                                    </select>
                                    {errors.id_quartier && <p className="text-red-500 text-sm mt-1">{errors.id_quartier}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Usage *</label>
                                    <select
                                        className="w-full border rounded px-3 py-2"
                                        value={createForm.used}
                                        onChange={(e) => setCreateForm({ ...createForm, used: e.target.value })}
                                        required
                                    >
                                        <option value="">Sélectionner...</option>
                                        {usages.map(u => (
                                            <option key={u.ID_USAGE} value={u.ID_USAGE}>{u.NOM}</option>
                                        ))}
                                    </select>
                                    {errors.used && <p className="text-red-500 text-sm mt-1">{errors.used}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Abonnement</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        className="w-full border rounded px-3 py-2"
                                        value={createForm.abonnement}
                                        onChange={(e) => setCreateForm({ ...createForm, abonnement: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Statut *</label>
                                    <select
                                        className="w-full border rounded px-3 py-2"
                                        value={createForm.statut}
                                        onChange={(e) => setCreateForm({ ...createForm, statut: parseInt(e.target.value) })}
                                        required
                                    >
                                        <option value={1}>Actif</option>
                                        <option value={0}>Suspendu</option>
                                    </select>
                                </div>
                            </div>
                            {errors.general && (
                                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                    {errors.general}
                                </div>
                            )}
                            <div className="flex gap-2 justify-end">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="px-4 py-2 border rounded hover:bg-gray-50"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                                >
                                    Créer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Edit Modal */}
            {showEditModal && selectedClient && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <h2 className="text-xl font-bold mb-4">Modifier Client - {selectedClient.NUM_CLIENT}</h2>
                        <form onSubmit={handleEdit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Nom *</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={editForm.nom}
                                        onChange={(e) => setEditForm({ ...editForm, nom: e.target.value })}
                                        required
                                    />
                                    {errors.nom && <p className="text-red-500 text-sm mt-1">{errors.nom}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Prénom *</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={editForm.prenom}
                                        onChange={(e) => setEditForm({ ...editForm, prenom: e.target.value })}
                                        required
                                    />
                                    {errors.prenom && <p className="text-red-500 text-sm mt-1">{errors.prenom}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Téléphone</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={editForm.telephone}
                                        onChange={(e) => setEditForm({ ...editForm, telephone: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Quartier *</label>
                                    <select
                                        className="w-full border rounded px-3 py-2"
                                        value={editForm.id_quartier}
                                        onChange={(e) => setEditForm({ ...editForm, id_quartier: e.target.value })}
                                        required
                                    >
                                        {quartiers.map(q => (
                                            <option key={q.ID_QUARTIER} value={q.ID_QUARTIER}>{q.NOM}</option>
                                        ))}
                                    </select>
                                    {errors.id_quartier && <p className="text-red-500 text-sm mt-1">{errors.id_quartier}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Usage *</label>
                                    <select
                                        className="w-full border rounded px-3 py-2"
                                        value={editForm.used}
                                        onChange={(e) => setEditForm({ ...editForm, used: e.target.value })}
                                        required
                                    >
                                        {usages.map(u => (
                                            <option key={u.ID_USAGE} value={u.ID_USAGE}>{u.NOM}</option>
                                        ))}
                                    </select>
                                    {errors.used && <p className="text-red-500 text-sm mt-1">{errors.used}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Abonnement</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        className="w-full border rounded px-3 py-2"
                                        value={editForm.abonnement}
                                        onChange={(e) => setEditForm({ ...editForm, abonnement: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Statut *</label>
                                    <select
                                        className="w-full border rounded px-3 py-2"
                                        value={editForm.statut}
                                        onChange={(e) => setEditForm({ ...editForm, statut: parseInt(e.target.value) })}
                                        required
                                    >
                                        <option value={1}>Actif</option>
                                        <option value={0}>Suspendu</option>
                                    </select>
                                </div>
                            </div>
                            {errors.general && (
                                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                    {errors.general}
                                </div>
                            )}
                            <div className="flex gap-2 justify-end">
                                <button
                                    type="button"
                                    onClick={() => setShowEditModal(false)}
                                    className="px-4 py-2 border rounded hover:bg-gray-50"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                                >
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* View Modal */}
            {showViewModal && selectedClient && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between items-start mb-4">
                            <h2 className="text-xl font-bold">Détails Client - {selectedClient.client.NUM_CLIENT}</h2>
                            <button
                                onClick={() => setShowViewModal(false)}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Client Info */}
                        <div className="bg-gray-50 p-4 rounded mb-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <div className="text-sm text-gray-500">Nom & Prénom</div>
                                    <div className="font-medium">{selectedClient.client.NOM} {selectedClient.client.PRENOM}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-500">Téléphone</div>
                                    <div className="font-medium">{selectedClient.client.TELEPHONE || '-'}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-500">Quartier</div>
                                    <div className="font-medium">{selectedClient.client.QUARTIER}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-500">Usage</div>
                                    <div className="font-medium">{selectedClient.client.USAGE_NOM}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-500">Abonnement</div>
                                    <div className="font-medium">{selectedClient.client.ABONNEMENT || 0} FCFA</div>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-500">Statut</div>
                                    <div>
                                        <span className={`px-2 py-1 rounded text-xs ${
                                            selectedClient.client.STATUT === 1
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        }`}>
                                            {selectedClient.client.STATUT === 1 ? 'Actif' : 'Suspendu'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Compteurs */}
                        <div className="mb-4">
                            <div className="flex justify-between items-center mb-2">
                                <h3 className="font-semibold">Compteurs ({selectedClient.compteurs.length})</h3>
                                <button
                                    onClick={openCompteurModal}
                                    className="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700"
                                >
                                    + Ajouter Compteur
                                </button>
                            </div>
                            {selectedClient.compteurs.length === 0 ? (
                                <p className="text-gray-500 text-sm">Aucun compteur</p>
                            ) : (
                                <div className="border rounded">
                                    <table className="min-w-full text-sm">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left">N° Compteur</th>
                                                <th className="px-3 py-2 text-left">Date Activité</th>
                                                <th className="px-3 py-2 text-left">Statut</th>
                                                <th className="px-3 py-2 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {selectedClient.compteurs.map(c => (
                                                <tr key={c.ID_COMPTEUR}>
                                                    <td className="px-3 py-2 font-medium">{c.NUM_COMPTEUR}</td>
                                                    <td className="px-3 py-2">
                                                        {c.DATE_START ? new Date(c.DATE_START).toLocaleDateString('fr-FR') : '-'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <span className={`px-2 py-1 text-xs rounded ${
                                                            c.ACTIF == 1
                                                                ? 'bg-green-100 text-green-800'
                                                                : 'bg-red-100 text-red-800'
                                                        }`}>
                                                            {c.ACTIF == 1 ? 'Actif' : 'Inactif'}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2 space-x-2">
                                                        <button
                                                            onClick={() => openEditCompteurModal(c)}
                                                            className="text-blue-600 hover:text-blue-900"
                                                        >
                                                            Modifier
                                                        </button>
                                                        <button
                                                            onClick={() => handleDeleteCompteur(c.ID_COMPTEUR)}
                                                            className="text-red-600 hover:text-red-900"
                                                        >
                                                            Supprimer
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        {/* Factures Impayées */}
                        <div className="mb-4">
                            <h3 className="font-semibold mb-2">Factures Impayées ({selectedClient.factures.length})</h3>
                            {selectedClient.factures.length === 0 ? (
                                <p className="text-gray-500 text-sm">Aucune facture impayée</p>
                            ) : (
                                <div className="border rounded">
                                    <table className="min-w-full text-sm">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left">N° Facture</th>
                                                <th className="px-3 py-2 text-left">Date</th>
                                                <th className="px-3 py-2 text-left">Montant Total</th>
                                                <th className="px-3 py-2 text-left">Impayé</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {selectedClient.factures.map(f => (
                                                <tr key={f.ID_FACTURE}>
                                                    <td className="px-3 py-2">{f.NUMERO_FACTURE}</td>
                                                    <td className="px-3 py-2">{new Date(f.DATEFACTURE).toLocaleDateString()}</td>
                                                    <td className="px-3 py-2">{f.TOTFACTURE} FCFA</td>
                                                    <td className="px-3 py-2 text-red-600 font-medium">{f.IMPAYE} FCFA</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        {/* Prêts Actifs */}
                        <div>
                            <h3 className="font-semibold mb-2">Prêts Actifs ({selectedClient.prets.length})</h3>
                            {selectedClient.prets.length === 0 ? (
                                <p className="text-gray-500 text-sm">Aucun prêt actif</p>
                            ) : (
                                <div className="border rounded">
                                    <table className="min-w-full text-sm">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left">Date Prêt</th>
                                                <th className="px-3 py-2 text-left">Montant</th>
                                                <th className="px-3 py-2 text-left">Payé</th>
                                                <th className="px-3 py-2 text-left">Restant</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {selectedClient.prets.map(p => (
                                                <tr key={p.ID_PRET}>
                                                    <td className="px-3 py-2">{new Date(p.DATE_PRET).toLocaleDateString()}</td>
                                                    <td className="px-3 py-2">{p.MONTANT_PRET} FCFA</td>
                                                    <td className="px-3 py-2 text-green-600">{p.PAYER} FCFA</td>
                                                    <td className="px-3 py-2 text-red-600 font-medium">{p.IMPAYER} FCFA</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        <div className="mt-6 flex justify-end">
                            <button
                                onClick={() => setShowViewModal(false)}
                                className="px-4 py-2 border rounded hover:bg-gray-50"
                            >
                                Fermer
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Ajouter Compteur */}
            {showCompteurModal && selectedClient && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Ajouter Compteur</h3>
                            <button
                                onClick={() => setShowCompteurModal(false)}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="bg-blue-50 p-3 rounded mb-4">
                            <p className="text-sm"><strong>Client:</strong> {selectedClient.client.PRENOM} {selectedClient.client.NOM}</p>
                            <p className="text-sm"><strong>N° Client:</strong> {selectedClient.client.NUM_CLIENT}</p>
                        </div>

                        <form onSubmit={handleCreateCompteur} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">N° Compteur</label>
                                <input
                                    type="text"
                                    className="w-full border rounded px-3 py-2"
                                    value={compteurForm.num_compteur}
                                    onChange={(e) => setCompteurForm({ ...compteurForm, num_compteur: e.target.value })}
                                    required
                                />
                                {errors.num_compteur && <p className="text-red-500 text-sm mt-1">{errors.num_compteur}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Date Mise en Activité</label>
                                <input
                                    type="date"
                                    className="w-full border rounded px-3 py-2"
                                    value={compteurForm.date_start}
                                    onChange={(e) => setCompteurForm({ ...compteurForm, date_start: e.target.value })}
                                    required
                                />
                                {errors.date_start && <p className="text-red-500 text-sm mt-1">{errors.date_start}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Statut</label>
                                <select
                                    className="w-full border rounded px-3 py-2"
                                    value={compteurForm.actif}
                                    onChange={(e) => setCompteurForm({ ...compteurForm, actif: parseInt(e.target.value) })}
                                >
                                    <option value="1">Actif</option>
                                    <option value="0">Inactif</option>
                                </select>
                                {errors.actif && <p className="text-red-500 text-sm mt-1">{errors.actif}</p>}
                            </div>

                            {errors.general && (
                                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                    {errors.general}
                                </div>
                            )}

                            <div className="flex justify-end gap-2 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setShowCompteurModal(false)}
                                    className="px-4 py-2 border rounded hover:bg-gray-100"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                                >
                                    Ajouter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Modifier Compteur */}
            {showEditCompteurModal && selectedCompteur && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Modifier Compteur</h3>
                            <button
                                onClick={() => setShowEditCompteurModal(false)}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="bg-blue-50 p-3 rounded mb-4">
                            <p className="text-sm"><strong>Compteur:</strong> {selectedCompteur.NUM_COMPTEUR}</p>
                            <p className="text-sm"><strong>Date activité:</strong> {selectedCompteur.DATE_START ? new Date(selectedCompteur.DATE_START).toLocaleDateString('fr-FR') : '-'}</p>
                        </div>

                        <form onSubmit={handleUpdateCompteur} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">N° Compteur</label>
                                <input
                                    type="text"
                                    className="w-full border rounded px-3 py-2"
                                    value={editCompteurForm.num_compteur}
                                    onChange={(e) => setEditCompteurForm({ ...editCompteurForm, num_compteur: e.target.value })}
                                    required
                                />
                                {errors.num_compteur && <p className="text-red-500 text-sm mt-1">{errors.num_compteur}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Statut</label>
                                <select
                                    className="w-full border rounded px-3 py-2"
                                    value={editCompteurForm.actif}
                                    onChange={(e) => setEditCompteurForm({ ...editCompteurForm, actif: parseInt(e.target.value) })}
                                >
                                    <option value="1">Actif</option>
                                    <option value="0">Inactif</option>
                                </select>
                                {errors.actif && <p className="text-red-500 text-sm mt-1">{errors.actif}</p>}
                            </div>

                            {errors.general && (
                                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                    {errors.general}
                                </div>
                            )}

                            <div className="flex justify-end gap-2 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setShowEditCompteurModal(false)}
                                    className="px-4 py-2 border rounded hover:bg-gray-100"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                                >
                                    Modifier
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </MainLayout>
    );
}
