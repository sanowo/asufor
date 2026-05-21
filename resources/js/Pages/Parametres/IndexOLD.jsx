import { Head } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import axios from 'axios';

export default function Parametres({ general, roles, ressources, actions, permissions: initialPermissions, typesUsage }) {
    // Active tab state
    const [activeTab, setActiveTab] = useState('general');

    // General tab
    const [generalForm, setGeneralForm] = useState({
        general_entreprise: general.entreprise || '',
        general_adress: general.adresse || '',
        general_telephone: general.telephone || ''
    });

    // Usages tab
    const [usages, setUsages] = useState([]);
    const [usageForm, setUsageForm] = useState({ usage_name: '', usage_tarif: '' });
    const [showEditUsageModal, setShowEditUsageModal] = useState(false);
    const [selectedUsage, setSelectedUsage] = useState(null);

    // Type Operations tab
    const [typeOperations, setTypeOperations] = useState([]);
    const [typeOpForm, setTypeOpForm] = useState({ type_libelle: '', type_is_revenue: '' });
    const [showEditTypeOpModal, setShowEditTypeOpModal] = useState(false);
    const [selectedTypeOp, setSelectedTypeOp] = useState(null);

    // Users tab
    const [users, setUsers] = useState([]);
    const [userForm, setUserForm] = useState({
        nom: '',
        prenom: '',
        login: '',
        password: '',
        telephone: '',
        adresse: '',
        profile: []
    });
    const [showEditUserModal, setShowEditUserModal] = useState(false);
    const [selectedUser, setSelectedUser] = useState(null);

    // Roles & Permissions tab
    const [rolesList, setRolesList] = useState(roles || []);
    const [roleForm, setRoleForm] = useState({ role_name: '' });
    const [permissions, setPermissions] = useState(initialPermissions || {});

    // Réductions tab
    const [reductions, setReductions] = useState([]);
    const [reductionForm, setReductionForm] = useState({
        libelle: '',
        date_debut: new Date().toISOString().split('T')[0],
        date_fin: new Date().toISOString().split('T')[0],
        pourcentage: '',
        types_client: [],
        actif: 1,
        description: ''
    });
    const [showEditReductionModal, setShowEditReductionModal] = useState(false);
    const [selectedReduction, setSelectedReduction] = useState(null);

    // Errors
    const [errors, setErrors] = useState({});

    // Load data on mount
    useEffect(() => {
        if (activeTab === 'usages') loadUsages();
        if (activeTab === 'operations') loadTypeOperations();
        if (activeTab === 'utilisateurs') loadUsers();
        if (activeTab === 'reductions') loadReductions();
    }, [activeTab]);

    // ============ GENERAL TAB ============
    const handleSaveGeneral = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.post('/parametres/general', generalForm);
            if (response.data.success) {
                alert(response.data.message);
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    // ============ USAGES TAB ============
    const loadUsages = async () => {
        try {
            const response = await axios.get('/parametres/usages/list');
            setUsages(response.data);
        } catch (error) {
            console.error('Error loading usages:', error);
        }
    };

    const handleAddUsage = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.post('/parametres/usages', usageForm);
            if (response.data.success) {
                alert(response.data.message);
                setUsageForm({ usage_name: '', usage_tarif: '' });
                loadUsages();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    const openEditUsageModal = (usage) => {
        setSelectedUsage(usage);
        setErrors({});
        setShowEditUsageModal(true);
    };

    const handleUpdateUsage = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.put(`/parametres/usages/${selectedUsage.ID_USAGE}`, {
                nom: selectedUsage.NOM,
                tarif: selectedUsage.TARIF
            });

            if (response.data.success) {
                alert(response.data.message);
                setShowEditUsageModal(false);
                loadUsages();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    const handleDeleteUsage = async (id) => {
        if (!confirm('Confirmer la suppression de cet usage ?')) return;

        try {
            const response = await axios.delete(`/parametres/usages/${id}`);
            if (response.data.success) {
                alert(response.data.message);
                loadUsages();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            }
        }
    };

    // ============ TYPE OPERATIONS TAB ============
    const loadTypeOperations = async () => {
        try {
            const response = await axios.get('/parametres/typeoperations/list');
            setTypeOperations(response.data);
        } catch (error) {
            console.error('Error loading type operations:', error);
        }
    };

    const handleAddTypeOperation = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.post('/parametres/typeoperations', typeOpForm);
            if (response.data.success) {
                alert(response.data.message);
                setTypeOpForm({ type_libelle: '', type_is_revenue: '' });
                loadTypeOperations();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    const openEditTypeOpModal = (typeOp) => {
        setSelectedTypeOp(typeOp);
        setErrors({});
        setShowEditTypeOpModal(true);
    };

    const handleUpdateTypeOp = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.put(`/parametres/typeoperations/${selectedTypeOp.ID_TYPEOPERATION}`, {
                libelle: selectedTypeOp.LIBELLE,
                is_revenue: selectedTypeOp.IS_REVENUE
            });

            if (response.data.success) {
                alert(response.data.message);
                setShowEditTypeOpModal(false);
                loadTypeOperations();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    const handleDeleteTypeOp = async (id) => {
        if (!confirm('Confirmer la suppression de ce type d\'opération ?')) return;

        try {
            const response = await axios.delete(`/parametres/typeoperations/${id}`);
            if (response.data.success) {
                alert(response.data.message);
                loadTypeOperations();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            }
        }
    };

    // ============ USERS TAB ============
    const loadUsers = async () => {
        try {
            const response = await axios.get('/parametres/users/list', {
                params: { start: 0, length: 100 }
            });
            setUsers(response.data.data);
        } catch (error) {
            console.error('Error loading users:', error);
        }
    };

    const handleAddUser = async (e) => {
        e.preventDefault();
        setErrors({});

        if (userForm.profile.length === 0) {
            setErrors({ profile: ['Veuillez sélectionner au moins un profil'] });
            return;
        }

        try {
            const response = await axios.post('/parametres/users', userForm);
            if (response.data.success) {
                alert(response.data.message);
                setUserForm({
                    nom: '',
                    prenom: '',
                    login: '',
                    password: '',
                    telephone: '',
                    adresse: '',
                    profile: []
                });
                loadUsers();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    const openEditUserModal = (user) => {
        setSelectedUser({
            ...user,
            profile: user.PROFILE ? user.PROFILE.split('&') : []
        });
        setErrors({});
        setShowEditUserModal(true);
    };

    const handleUpdateUser = async (e) => {
        e.preventDefault();
        setErrors({});

        if (selectedUser.profile.length === 0) {
            setErrors({ profile: ['Veuillez sélectionner au moins un profil'] });
            return;
        }

        try {
            const response = await axios.put(`/parametres/users/${selectedUser.ID_USER}`, {
                nom: selectedUser.NOM,
                prenom: selectedUser.PRENOM,
                login: selectedUser.LOGIN,
                password: selectedUser.PASSWORD || '',
                telephone: selectedUser.TELEPHONE,
                adresse: selectedUser.ADRESSE,
                profile: selectedUser.profile
            });

            if (response.data.success) {
                alert(response.data.message);
                setShowEditUserModal(false);
                loadUsers();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    const handleDeleteUser = async (id) => {
        if (!confirm('Confirmer la suppression de cet utilisateur ?')) return;

        try {
            const response = await axios.delete(`/parametres/users/${id}`);
            if (response.data.success) {
                alert(response.data.message);
                loadUsers();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            }
        }
    };

    const toggleUserProfile = (profileValue) => {
        const currentProfiles = userForm.profile;
        if (currentProfiles.includes(profileValue)) {
            setUserForm({
                ...userForm,
                profile: currentProfiles.filter(p => p !== profileValue)
            });
        } else {
            setUserForm({
                ...userForm,
                profile: [...currentProfiles, profileValue]
            });
        }
    };

    const toggleEditUserProfile = (profileValue) => {
        const currentProfiles = selectedUser.profile;
        if (currentProfiles.includes(profileValue)) {
            setSelectedUser({
                ...selectedUser,
                profile: currentProfiles.filter(p => p !== profileValue)
            });
        } else {
            setSelectedUser({
                ...selectedUser,
                profile: [...currentProfiles, profileValue]
            });
        }
    };

    // ============ ROLES & PERMISSIONS TAB ============
    const handleAddRole = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.post('/parametres/roles', roleForm);
            if (response.data.success) {
                alert(response.data.message);
                setRoleForm({ role_name: '' });
                // Reload page to get updated roles
                window.location.reload();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    const handleDeleteRole = async (id) => {
        if (!confirm('Confirmer la suppression de ce rôle ?')) return;

        try {
            const response = await axios.delete(`/parametres/roles/${id}`);
            if (response.data.success) {
                alert(response.data.message);
                window.location.reload();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            }
        }
    };

    const handlePermissionChange = (role, ressource, action, checked) => {
        setPermissions(prev => ({
            ...prev,
            [role]: {
                ...prev[role],
                [ressource]: {
                    ...prev[role]?.[ressource],
                    [action]: checked ? 1 : 0
                }
            }
        }));
    };

    const handleSavePermissions = async (e) => {
        e.preventDefault();

        try {
            const response = await axios.post('/parametres/permissions', { permissions });
            if (response.data.success) {
                alert(response.data.message);
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general || 'Erreur lors de l\'enregistrement');
            }
        }
    };

    // ============ RÉDUCTIONS TAB ============
    const loadReductions = async () => {
        try {
            const response = await axios.get('/parametres/reductions/list');
            setReductions(response.data);
        } catch (error) {
            console.error('Error loading reductions:', error);
        }
    };

    const handleAddReduction = async (e) => {
        e.preventDefault();
        setErrors({});

        if (reductionForm.types_client.length === 0) {
            setErrors({ types_client: ['Veuillez sélectionner au moins un type de client'] });
            return;
        }

        try {
            const response = await axios.post('/parametres/reductions', reductionForm);
            if (response.data.success) {
                alert(response.data.message);
                setReductionForm({
                    libelle: '',
                    date_debut: new Date().toISOString().split('T')[0],
                    date_fin: new Date().toISOString().split('T')[0],
                    pourcentage: '',
                    types_client: [],
                    actif: 1,
                    description: ''
                });
                loadReductions();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    const openEditReductionModal = (reduction) => {
        setSelectedReduction({
            ...reduction,
            types_client: reduction.TYPES_CLIENT_IDS || [] // Utiliser les IDs, pas les noms
        });
        setErrors({});
        setShowEditReductionModal(true);
    };

    const handleUpdateReduction = async (e) => {
        e.preventDefault();
        setErrors({});

        if (selectedReduction.types_client.length === 0) {
            setErrors({ types_client: ['Veuillez sélectionner au moins un type de client'] });
            return;
        }

        try {
            const response = await axios.put(`/parametres/reductions/${selectedReduction.ID_REDUCTION}`, {
                libelle: selectedReduction.LIBELLE,
                date_debut: selectedReduction.DATE_DEBUT,
                date_fin: selectedReduction.DATE_FIN,
                pourcentage: selectedReduction.POURCENTAGE,
                types_client: selectedReduction.types_client,
                actif: selectedReduction.ACTIF,
                description: selectedReduction.DESCRIPTION
            });

            if (response.data.success) {
                alert(response.data.message);
                setShowEditReductionModal(false);
                loadReductions();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    const handleDeleteReduction = async (id) => {
        if (!confirm('Confirmer la suppression de cette réduction ?')) return;

        try {
            const response = await axios.delete(`/parametres/reductions/${id}`);
            if (response.data.success) {
                alert(response.data.message);
                loadReductions();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            }
        }
    };

    const handleToggleReduction = async (id) => {
        try {
            const response = await axios.post(`/parametres/reductions/${id}/toggle`);
            if (response.data.success) {
                alert(response.data.message);
                loadReductions();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            }
        }
    };

    const toggleReductionTypeClient = (typeId) => {
        const currentTypes = reductionForm.types_client;
        if (currentTypes.includes(typeId)) {
            setReductionForm({
                ...reductionForm,
                types_client: currentTypes.filter(t => t !== typeId)
            });
        } else {
            setReductionForm({
                ...reductionForm,
                types_client: [...currentTypes, typeId]
            });
        }
    };

    const toggleEditReductionTypeClient = (typeId) => {
        const currentTypes = selectedReduction.types_client;
        if (currentTypes.includes(typeId)) {
            setSelectedReduction({
                ...selectedReduction,
                types_client: currentTypes.filter(t => t !== typeId)
            });
        } else {
            setSelectedReduction({
                ...selectedReduction,
                types_client: [...currentTypes, typeId]
            });
        }
    };

    return (
        <>
            <Head title="Paramètres" />

            <div className="container-fluid p-0">
                <div className="row mb-2 mb-xl-3">
                    <div className="col-auto d-none d-sm-block">
                        <h3><strong>Paramètres</strong></h3>
                    </div>
                </div>

                {/* Tabs Navigation */}
                <div className="row">
                    <div className="col-12">
                        <div className="card">
                            <div className="card-header">
                                <ul className="nav nav-tabs card-header-tabs">
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link ${activeTab === 'general' ? 'active' : ''}`}
                                            onClick={() => setActiveTab('general')}
                                        >
                                            Général
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link ${activeTab === 'usages' ? 'active' : ''}`}
                                            onClick={() => setActiveTab('usages')}
                                        >
                                            Usages
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link ${activeTab === 'operations' ? 'active' : ''}`}
                                            onClick={() => setActiveTab('operations')}
                                        >
                                            Opération Trésorerie
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link ${activeTab === 'utilisateurs' ? 'active' : ''}`}
                                            onClick={() => setActiveTab('utilisateurs')}
                                        >
                                            Utilisateurs
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link ${activeTab === 'permissions' ? 'active' : ''}`}
                                            onClick={() => setActiveTab('permissions')}
                                        >
                                            Permissions
                                        </button>
                                    </li>
                                    <li className="nav-item">
                                        <button
                                            className={`nav-link ${activeTab === 'reductions' ? 'active' : ''}`}
                                            onClick={() => setActiveTab('reductions')}
                                        >
                                            Réductions
                                        </button>
                                    </li>
                                </ul>
                            </div>

                            <div className="card-body">
                                {/* ========== GENERAL TAB ========== */}
                                {activeTab === 'general' && (
                                    <div>
                                        <h4 className="mb-4">Informations Générales</h4>
                                        <form onSubmit={handleSaveGeneral}>
                                            <div className="mb-3">
                                                <label className="form-label">Entreprise :</label>
                                                <textarea
                                                    className="form-control"
                                                    rows="5"
                                                    value={generalForm.general_entreprise}
                                                    onChange={e => setGeneralForm({ ...generalForm, general_entreprise: e.target.value })}
                                                />
                                                {errors.general_entreprise && (
                                                    <p className="text-danger">{errors.general_entreprise}</p>
                                                )}
                                            </div>

                                            <div className="mb-3">
                                                <label className="form-label">Adresse :</label>
                                                <textarea
                                                    className="form-control"
                                                    rows="3"
                                                    value={generalForm.general_adress}
                                                    onChange={e => setGeneralForm({ ...generalForm, general_adress: e.target.value })}
                                                />
                                                {errors.general_adress && (
                                                    <p className="text-danger">{errors.general_adress}</p>
                                                )}
                                            </div>

                                            <div className="mb-3">
                                                <label className="form-label">Téléphone :</label>
                                                <textarea
                                                    className="form-control"
                                                    rows="3"
                                                    value={generalForm.general_telephone}
                                                    onChange={e => setGeneralForm({ ...generalForm, general_telephone: e.target.value })}
                                                />
                                                {errors.general_telephone && (
                                                    <p className="text-danger">{errors.general_telephone}</p>
                                                )}
                                            </div>

                                            <button type="submit" className="btn btn-success">
                                                Enregistrer
                                            </button>
                                        </form>
                                    </div>
                                )}

                                {/* ========== USAGES TAB ========== */}
                                {activeTab === 'usages' && (
                                    <div>
                                        <h4 className="mb-4">Gestion des Usages</h4>

                                        <form onSubmit={handleAddUsage} className="mb-4 p-3 border rounded bg-light">
                                            <label className="form-label fw-bold">Ajouter un usage :</label>
                                            <div className="row g-2">
                                                <div className="col-md-4">
                                                    <input
                                                        type="text"
                                                        className="form-control"
                                                        placeholder="Nom"
                                                        value={usageForm.usage_name}
                                                        onChange={e => setUsageForm({ ...usageForm, usage_name: e.target.value })}
                                                    />
                                                    {errors.usage_name && <p className="text-danger">{errors.usage_name}</p>}
                                                </div>
                                                <div className="col-md-4">
                                                    <input
                                                        type="number"
                                                        className="form-control"
                                                        placeholder="Tarif"
                                                        value={usageForm.usage_tarif}
                                                        onChange={e => setUsageForm({ ...usageForm, usage_tarif: e.target.value })}
                                                    />
                                                    {errors.usage_tarif && <p className="text-danger">{errors.usage_tarif}</p>}
                                                </div>
                                                <div className="col-md-4">
                                                    <button type="submit" className="btn btn-success w-100">
                                                        Ajouter
                                                    </button>
                                                </div>
                                            </div>
                                        </form>

                                        <table className="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Nom</th>
                                                    <th>Tarif</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {usages.map(usage => (
                                                    <tr key={usage.ID_USAGE}>
                                                        <td>{usage.NOM}</td>
                                                        <td>{usage.TARIF} FCFA</td>
                                                        <td>
                                                            <button
                                                                className="btn btn-sm btn-primary me-2"
                                                                onClick={() => openEditUsageModal(usage)}
                                                            >
                                                                Modifier
                                                            </button>
                                                            <button
                                                                className="btn btn-sm btn-danger"
                                                                onClick={() => handleDeleteUsage(usage.ID_USAGE)}
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

                                {/* ========== TYPE OPERATIONS TAB ========== */}
                                {activeTab === 'operations' && (
                                    <div>
                                        <h4 className="mb-4">Types d'Opération Trésorerie</h4>

                                        <form onSubmit={handleAddTypeOperation} className="mb-4 p-3 border rounded bg-light">
                                            <label className="form-label fw-bold">Ajouter un type d'opération :</label>
                                            <div className="row g-2">
                                                <div className="col-md-4">
                                                    <input
                                                        type="text"
                                                        className="form-control"
                                                        placeholder="Libellé"
                                                        value={typeOpForm.type_libelle}
                                                        onChange={e => setTypeOpForm({ ...typeOpForm, type_libelle: e.target.value })}
                                                    />
                                                    {errors.type_libelle && <p className="text-danger">{errors.type_libelle}</p>}
                                                </div>
                                                <div className="col-md-4">
                                                    <select
                                                        className="form-select"
                                                        value={typeOpForm.type_is_revenue}
                                                        onChange={e => setTypeOpForm({ ...typeOpForm, type_is_revenue: e.target.value })}
                                                    >
                                                        <option value="">Sélectionner Type</option>
                                                        <option value="1">Revenue</option>
                                                        <option value="0">Dépense</option>
                                                    </select>
                                                    {errors.type_is_revenue && <p className="text-danger">{errors.type_is_revenue}</p>}
                                                </div>
                                                <div className="col-md-4">
                                                    <button type="submit" className="btn btn-success w-100">
                                                        Ajouter
                                                    </button>
                                                </div>
                                            </div>
                                        </form>

                                        <table className="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Libellé</th>
                                                    <th>Type</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {typeOperations.map(typeOp => (
                                                    <tr key={typeOp.ID_TYPEOPERATION}>
                                                        <td>{typeOp.LIBELLE}</td>
                                                        <td>
                                                            <span className={`badge ${typeOp.IS_REVENUE == 1 ? 'bg-success' : 'bg-danger'}`}>
                                                                {typeOp.IS_REVENUE == 1 ? 'Revenue' : 'Dépense'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button
                                                                className="btn btn-sm btn-primary me-2"
                                                                onClick={() => openEditTypeOpModal(typeOp)}
                                                            >
                                                                Modifier
                                                            </button>
                                                            <button
                                                                className="btn btn-sm btn-danger"
                                                                onClick={() => handleDeleteTypeOp(typeOp.ID_TYPEOPERATION)}
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

                                {/* ========== USERS TAB ========== */}
                                {activeTab === 'utilisateurs' && (
                                    <div>
                                        <h4 className="mb-4">Gestion des Utilisateurs</h4>

                                        <table className="table table-striped mb-4">
                                            <thead>
                                                <tr>
                                                    <th>Nom</th>
                                                    <th>Prénom</th>
                                                    <th>Profile</th>
                                                    <th>Téléphone</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {users.map(user => (
                                                    <tr key={user.ID_USER}>
                                                        <td>{user.NOM}</td>
                                                        <td>{user.PRENOM}</td>
                                                        <td>{user.PROFILE}</td>
                                                        <td>{user.TELEPHONE}</td>
                                                        <td>
                                                            <button
                                                                className="btn btn-sm btn-primary me-2"
                                                                onClick={() => openEditUserModal(user)}
                                                            >
                                                                Modifier
                                                            </button>
                                                            <button
                                                                className="btn btn-sm btn-danger"
                                                                onClick={() => handleDeleteUser(user.ID_USER)}
                                                            >
                                                                Supprimer
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>

                                        <div className="p-4 border rounded bg-light">
                                            <h5 className="mb-3">Ajouter un utilisateur</h5>
                                            <form onSubmit={handleAddUser}>
                                                <div className="row g-3">
                                                    <div className="col-md-6">
                                                        <input
                                                            type="text"
                                                            className="form-control"
                                                            placeholder="Nom"
                                                            value={userForm.nom}
                                                            onChange={e => setUserForm({ ...userForm, nom: e.target.value })}
                                                        />
                                                        {errors.nom && <p className="text-danger">{errors.nom}</p>}
                                                    </div>
                                                    <div className="col-md-6">
                                                        <input
                                                            type="text"
                                                            className="form-control"
                                                            placeholder="Prénom"
                                                            value={userForm.prenom}
                                                            onChange={e => setUserForm({ ...userForm, prenom: e.target.value })}
                                                        />
                                                        {errors.prenom && <p className="text-danger">{errors.prenom}</p>}
                                                    </div>
                                                    <div className="col-md-6">
                                                        <input
                                                            type="text"
                                                            className="form-control"
                                                            placeholder="Login"
                                                            value={userForm.login}
                                                            onChange={e => setUserForm({ ...userForm, login: e.target.value })}
                                                        />
                                                        {errors.login && <p className="text-danger">{errors.login}</p>}
                                                    </div>
                                                    <div className="col-md-6">
                                                        <input
                                                            type="password"
                                                            className="form-control"
                                                            placeholder="Password"
                                                            value={userForm.password}
                                                            onChange={e => setUserForm({ ...userForm, password: e.target.value })}
                                                        />
                                                        {errors.password && <p className="text-danger">{errors.password}</p>}
                                                    </div>
                                                    <div className="col-md-6">
                                                        <input
                                                            type="text"
                                                            className="form-control"
                                                            placeholder="Téléphone"
                                                            value={userForm.telephone}
                                                            onChange={e => setUserForm({ ...userForm, telephone: e.target.value })}
                                                        />
                                                    </div>
                                                    <div className="col-md-6">
                                                        <input
                                                            type="text"
                                                            className="form-control"
                                                            placeholder="Adresse"
                                                            value={userForm.adresse}
                                                            onChange={e => setUserForm({ ...userForm, adresse: e.target.value })}
                                                        />
                                                    </div>
                                                    <div className="col-12">
                                                        <label className="form-label fw-bold">Profile :</label>
                                                        <div className="d-flex flex-wrap gap-3">
                                                            {rolesList.map(role => (
                                                                <div key={role.ID_PARAMETRE} className="form-check">
                                                                    <input
                                                                        type="checkbox"
                                                                        className="form-check-input"
                                                                        id={`add_${role.VALUE}`}
                                                                        checked={userForm.profile.includes(role.VALUE)}
                                                                        onChange={() => toggleUserProfile(role.VALUE)}
                                                                    />
                                                                    <label className="form-check-label" htmlFor={`add_${role.VALUE}`}>
                                                                        {role.VALUE.charAt(0).toUpperCase() + role.VALUE.slice(1)}
                                                                    </label>
                                                                </div>
                                                            ))}
                                                            <div className="form-check">
                                                                <input
                                                                    type="checkbox"
                                                                    className="form-check-input"
                                                                    id="add_administrateur"
                                                                    checked={userForm.profile.includes('administrateur')}
                                                                    onChange={() => toggleUserProfile('administrateur')}
                                                                />
                                                                <label className="form-check-label" htmlFor="add_administrateur">
                                                                    Super Administrateur
                                                                </label>
                                                            </div>
                                                        </div>
                                                        {errors.profile && <p className="text-danger">{errors.profile}</p>}
                                                    </div>
                                                    <div className="col-12">
                                                        <button type="submit" className="btn btn-success">
                                                            Ajouter Utilisateur
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                )}

                                {/* ========== PERMISSIONS TAB ========== */}
                                {activeTab === 'permissions' && (
                                    <div>
                                        <h4 className="mb-4">Gestion des Rôles et Permissions</h4>

                                        <form onSubmit={handleAddRole} className="mb-4 p-3 border rounded bg-light">
                                            <label className="form-label fw-bold">Ajouter un rôle :</label>
                                            <div className="row g-2">
                                                <div className="col-md-8">
                                                    <input
                                                        type="text"
                                                        className="form-control"
                                                        placeholder="Nom du rôle"
                                                        value={roleForm.role_name}
                                                        onChange={e => setRoleForm({ role_name: e.target.value })}
                                                    />
                                                    {errors.role_name && <p className="text-danger">{errors.role_name}</p>}
                                                </div>
                                                <div className="col-md-4">
                                                    <button type="submit" className="btn btn-success w-100">
                                                        Ajouter
                                                    </button>
                                                </div>
                                            </div>
                                        </form>

                                        <form onSubmit={handleSavePermissions}>
                                            <div className="permission-grid">
                                                {rolesList.map(role => (
                                                    <div key={role.ID_PARAMETRE} className="mb-5 p-4 border rounded">
                                                        <div className="d-flex justify-content-between align-items-center mb-3">
                                                            <h5 className="mb-0">
                                                                {role.VALUE.charAt(0).toUpperCase() + role.VALUE.slice(1)}
                                                            </h5>
                                                            <button
                                                                type="button"
                                                                className="btn btn-sm btn-danger"
                                                                onClick={() => handleDeleteRole(role.ID_PARAMETRE)}
                                                            >
                                                                Supprimer Rôle
                                                            </button>
                                                        </div>

                                                        <div className="row">
                                                            {ressources.map(ressource => (
                                                                <div key={ressource.ID_PARAMETRE} className="col-md-4 mb-3">
                                                                    <div className="card">
                                                                        <div className="card-header bg-primary text-white">
                                                                            {ressource.VALUE}
                                                                        </div>
                                                                        <div className="card-body">
                                                                            {actions
                                                                                .filter(action => !action.PARENT || action.PARENT === ressource.VALUE)
                                                                                .map(action => {
                                                                                    const isChecked = permissions[role.VALUE]?.[ressource.VALUE]?.[action.VALUE] === 1;
                                                                                    return (
                                                                                        <div key={action.ID_PARAMETRE} className="form-check">
                                                                                            <input
                                                                                                type="checkbox"
                                                                                                className="form-check-input"
                                                                                                id={`perm_${role.ID_PARAMETRE}_${ressource.ID_PARAMETRE}_${action.ID_PARAMETRE}`}
                                                                                                checked={isChecked}
                                                                                                onChange={e => handlePermissionChange(
                                                                                                    role.VALUE,
                                                                                                    ressource.VALUE,
                                                                                                    action.VALUE,
                                                                                                    e.target.checked
                                                                                                )}
                                                                                            />
                                                                                            <label
                                                                                                className="form-check-label"
                                                                                                htmlFor={`perm_${role.ID_PARAMETRE}_${ressource.ID_PARAMETRE}_${action.ID_PARAMETRE}`}
                                                                                            >
                                                                                                {action.VALUE}
                                                                                            </label>
                                                                                        </div>
                                                                                    );
                                                                                })}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>

                                            <button type="submit" className="btn btn-success btn-lg mt-3">
                                                Enregistrer les Permissions
                                            </button>
                                        </form>
                                    </div>
                                )}

                                {/* ========== RÉDUCTIONS TAB ========== */}
                                {activeTab === 'reductions' && (
                                    <div>
                                        <h4 className="mb-4">Gestion des Réductions Ponctuelles</h4>

                                        <div className="p-4 border rounded bg-light mb-4">
                                            <h5 className="mb-3">Créer une réduction</h5>
                                            <form onSubmit={handleAddReduction}>
                                                <div className="row g-3">
                                                    <div className="col-md-6">
                                                        <label className="form-label">Libellé *</label>
                                                        <input
                                                            type="text"
                                                            className="form-control"
                                                            placeholder="Ex: Promotion Nouvel An 2025"
                                                            value={reductionForm.libelle}
                                                            onChange={e => setReductionForm({ ...reductionForm, libelle: e.target.value })}
                                                        />
                                                        {errors.libelle && <p className="text-danger">{errors.libelle}</p>}
                                                    </div>
                                                    <div className="col-md-3">
                                                        <label className="form-label">Pourcentage * (%)</label>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            max="100"
                                                            className="form-control"
                                                            placeholder="15.5"
                                                            value={reductionForm.pourcentage}
                                                            onChange={e => setReductionForm({ ...reductionForm, pourcentage: e.target.value })}
                                                        />
                                                        {errors.pourcentage && <p className="text-danger">{errors.pourcentage}</p>}
                                                    </div>
                                                    <div className="col-md-3">
                                                        <label className="form-label">Statut *</label>
                                                        <select
                                                            className="form-select"
                                                            value={reductionForm.actif}
                                                            onChange={e => setReductionForm({ ...reductionForm, actif: parseInt(e.target.value) })}
                                                        >
                                                            <option value="1">Actif</option>
                                                            <option value="0">Inactif</option>
                                                        </select>
                                                    </div>
                                                    <div className="col-md-6">
                                                        <label className="form-label">Date de début *</label>
                                                        <input
                                                            type="date"
                                                            className="form-control"
                                                            value={reductionForm.date_debut}
                                                            onChange={e => setReductionForm({ ...reductionForm, date_debut: e.target.value })}
                                                        />
                                                        {errors.date_debut && <p className="text-danger">{errors.date_debut}</p>}
                                                    </div>
                                                    <div className="col-md-6">
                                                        <label className="form-label">Date de fin *</label>
                                                        <input
                                                            type="date"
                                                            className="form-control"
                                                            value={reductionForm.date_fin}
                                                            onChange={e => setReductionForm({ ...reductionForm, date_fin: e.target.value })}
                                                        />
                                                        {errors.date_fin && <p className="text-danger">{errors.date_fin}</p>}
                                                    </div>
                                                    <div className="col-12">
                                                        <label className="form-label fw-bold">Types de clients concernés * :</label>
                                                        <div className="d-flex flex-wrap gap-3">
                                                            {typesUsage && typesUsage.map(type => (
                                                                <div key={type.ID_USAGE} className="form-check">
                                                                    <input
                                                                        type="checkbox"
                                                                        className="form-check-input"
                                                                        id={`type_${type.ID_USAGE}`}
                                                                        checked={reductionForm.types_client.includes(type.ID_USAGE)}
                                                                        onChange={() => toggleReductionTypeClient(type.ID_USAGE)}
                                                                    />
                                                                    <label className="form-check-label" htmlFor={`type_${type.ID_USAGE}`}>
                                                                        {type.NOM}
                                                                    </label>
                                                                </div>
                                                            ))}
                                                        </div>
                                                        {errors.types_client && <p className="text-danger">{errors.types_client}</p>}
                                                    </div>
                                                    <div className="col-12">
                                                        <label className="form-label">Description (optionnel)</label>
                                                        <textarea
                                                            className="form-control"
                                                            rows="2"
                                                            placeholder="Ex: Offre valable pour tous les nouveaux abonnés"
                                                            value={reductionForm.description}
                                                            onChange={e => setReductionForm({ ...reductionForm, description: e.target.value })}
                                                        />
                                                    </div>
                                                    <div className="col-12">
                                                        <button type="submit" className="btn btn-success">
                                                            Créer la Réduction
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>

                                        <table className="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Libellé</th>
                                                    <th>Période</th>
                                                    <th>Pourcentage</th>
                                                    <th>Types Clients</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {reductions.map(reduction => (
                                                    <tr key={reduction.ID_REDUCTION}>
                                                        <td>
                                                            <strong>{reduction.LIBELLE}</strong>
                                                            {reduction.DESCRIPTION && (
                                                                <div className="text-muted small">{reduction.DESCRIPTION}</div>
                                                            )}
                                                        </td>
                                                        <td className="text-nowrap">
                                                            {new Date(reduction.DATE_DEBUT).toLocaleDateString('fr-FR')}
                                                            {' → '}
                                                            {new Date(reduction.DATE_FIN).toLocaleDateString('fr-FR')}
                                                        </td>
                                                        <td>
                                                            <span className="badge bg-primary">{reduction.POURCENTAGE}%</span>
                                                        </td>
                                                        <td>
                                                            {reduction.TYPES_CLIENT && reduction.TYPES_CLIENT.length > 0 ? (
                                                                <div className="d-flex flex-wrap gap-1">
                                                                    {reduction.TYPES_CLIENT.map((type, idx) => (
                                                                        <span key={idx} className="badge bg-secondary">{type}</span>
                                                                    ))}
                                                                </div>
                                                            ) : (
                                                                <span className="text-muted">Aucun</span>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <span className={`badge ${reduction.ACTIF == 1 ? 'bg-success' : 'bg-danger'}`}>
                                                                {reduction.ACTIF == 1 ? 'Actif' : 'Inactif'}
                                                            </span>
                                                        </td>
                                                        <td className="text-nowrap">
                                                            <button
                                                                className={`btn btn-sm me-2 ${reduction.ACTIF == 1 ? 'btn-warning' : 'btn-success'}`}
                                                                onClick={() => handleToggleReduction(reduction.ID_REDUCTION)}
                                                                title={reduction.ACTIF == 1 ? 'Désactiver' : 'Activer'}
                                                            >
                                                                {reduction.ACTIF == 1 ? 'Désactiver' : 'Activer'}
                                                            </button>
                                                            <button
                                                                className="btn btn-sm btn-primary me-2"
                                                                onClick={() => openEditReductionModal(reduction)}
                                                            >
                                                                Modifier
                                                            </button>
                                                            <button
                                                                className="btn btn-sm btn-danger"
                                                                onClick={() => handleDeleteReduction(reduction.ID_REDUCTION)}
                                                            >
                                                                Supprimer
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>

                                        {reductions.length === 0 && (
                                            <div className="alert alert-info">
                                                Aucune réduction créée pour le moment.
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Edit Usage */}
            {showEditUsageModal && selectedUsage && (
                <div className="modal fade show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Modifier Usage</h5>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={() => setShowEditUsageModal(false)}
                                ></button>
                            </div>
                            <div className="modal-body">
                                <form onSubmit={handleUpdateUsage}>
                                    <div className="mb-3">
                                        <label className="form-label">Nom :</label>
                                        <input
                                            type="text"
                                            className="form-control"
                                            value={selectedUsage.NOM}
                                            onChange={e => setSelectedUsage({ ...selectedUsage, NOM: e.target.value })}
                                        />
                                        {errors.nom && <p className="text-danger">{errors.nom}</p>}
                                    </div>
                                    <div className="mb-3">
                                        <label className="form-label">Tarif :</label>
                                        <input
                                            type="number"
                                            className="form-control"
                                            value={selectedUsage.TARIF}
                                            onChange={e => setSelectedUsage({ ...selectedUsage, TARIF: e.target.value })}
                                        />
                                        {errors.tarif && <p className="text-danger">{errors.tarif}</p>}
                                    </div>
                                    <div className="d-flex justify-content-end gap-2">
                                        <button
                                            type="button"
                                            className="btn btn-secondary"
                                            onClick={() => setShowEditUsageModal(false)}
                                        >
                                            Annuler
                                        </button>
                                        <button type="submit" className="btn btn-primary">
                                            Modifier
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Edit Type Operation */}
            {showEditTypeOpModal && selectedTypeOp && (
                <div className="modal fade show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Modifier Type Opération</h5>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={() => setShowEditTypeOpModal(false)}
                                ></button>
                            </div>
                            <div className="modal-body">
                                <form onSubmit={handleUpdateTypeOp}>
                                    <div className="mb-3">
                                        <label className="form-label">Libellé :</label>
                                        <input
                                            type="text"
                                            className="form-control"
                                            value={selectedTypeOp.LIBELLE}
                                            onChange={e => setSelectedTypeOp({ ...selectedTypeOp, LIBELLE: e.target.value })}
                                        />
                                        {errors.libelle && <p className="text-danger">{errors.libelle}</p>}
                                    </div>
                                    <div className="mb-3">
                                        <label className="form-label">Type :</label>
                                        <select
                                            className="form-select"
                                            value={selectedTypeOp.IS_REVENUE}
                                            onChange={e => setSelectedTypeOp({ ...selectedTypeOp, IS_REVENUE: e.target.value })}
                                        >
                                            <option value="">Sélectionner</option>
                                            <option value="1">Revenue</option>
                                            <option value="0">Dépense</option>
                                        </select>
                                        {errors.is_revenue && <p className="text-danger">{errors.is_revenue}</p>}
                                    </div>
                                    <div className="d-flex justify-content-end gap-2">
                                        <button
                                            type="button"
                                            className="btn btn-secondary"
                                            onClick={() => setShowEditTypeOpModal(false)}
                                        >
                                            Annuler
                                        </button>
                                        <button type="submit" className="btn btn-primary">
                                            Modifier
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Edit User */}
            {showEditUserModal && selectedUser && (
                <div className="modal fade show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog modal-lg">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Modifier Utilisateur</h5>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={() => setShowEditUserModal(false)}
                                ></button>
                            </div>
                            <div className="modal-body">
                                <form onSubmit={handleUpdateUser}>
                                    <div className="row g-3">
                                        <div className="col-md-6">
                                            <label className="form-label">Nom :</label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                value={selectedUser.NOM}
                                                onChange={e => setSelectedUser({ ...selectedUser, NOM: e.target.value })}
                                            />
                                            {errors.nom && <p className="text-danger">{errors.nom}</p>}
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">Prénom :</label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                value={selectedUser.PRENOM}
                                                onChange={e => setSelectedUser({ ...selectedUser, PRENOM: e.target.value })}
                                            />
                                            {errors.prenom && <p className="text-danger">{errors.prenom}</p>}
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">Login :</label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                value={selectedUser.LOGIN}
                                                onChange={e => setSelectedUser({ ...selectedUser, LOGIN: e.target.value })}
                                            />
                                            {errors.login && <p className="text-danger">{errors.login}</p>}
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">Password (laisser vide pour ne pas changer) :</label>
                                            <input
                                                type="password"
                                                className="form-control"
                                                placeholder="Nouveau mot de passe"
                                                onChange={e => setSelectedUser({ ...selectedUser, PASSWORD: e.target.value })}
                                            />
                                            {errors.password && <p className="text-danger">{errors.password}</p>}
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">Téléphone :</label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                value={selectedUser.TELEPHONE}
                                                onChange={e => setSelectedUser({ ...selectedUser, TELEPHONE: e.target.value })}
                                            />
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">Adresse :</label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                value={selectedUser.ADRESSE}
                                                onChange={e => setSelectedUser({ ...selectedUser, ADRESSE: e.target.value })}
                                            />
                                        </div>
                                        <div className="col-12">
                                            <label className="form-label fw-bold">Profile :</label>
                                            <div className="d-flex flex-wrap gap-3">
                                                {rolesList.map(role => (
                                                    <div key={role.ID_PARAMETRE} className="form-check">
                                                        <input
                                                            type="checkbox"
                                                            className="form-check-input"
                                                            id={`upd_${role.VALUE}`}
                                                            checked={selectedUser.profile.includes(role.VALUE)}
                                                            onChange={() => toggleEditUserProfile(role.VALUE)}
                                                        />
                                                        <label className="form-check-label" htmlFor={`upd_${role.VALUE}`}>
                                                            {role.VALUE.charAt(0).toUpperCase() + role.VALUE.slice(1)}
                                                        </label>
                                                    </div>
                                                ))}
                                                <div className="form-check">
                                                    <input
                                                        type="checkbox"
                                                        className="form-check-input"
                                                        id="upd_administrateur"
                                                        checked={selectedUser.profile.includes('administrateur')}
                                                        onChange={() => toggleEditUserProfile('administrateur')}
                                                    />
                                                    <label className="form-check-label" htmlFor="upd_administrateur">
                                                        Super Administrateur
                                                    </label>
                                                </div>
                                            </div>
                                            {errors.profile && <p className="text-danger">{errors.profile}</p>}
                                        </div>
                                    </div>
                                    <div className="d-flex justify-content-end gap-2 mt-4">
                                        <button
                                            type="button"
                                            className="btn btn-secondary"
                                            onClick={() => setShowEditUserModal(false)}
                                        >
                                            Annuler
                                        </button>
                                        <button type="submit" className="btn btn-primary">
                                            Modifier
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Edit Reduction */}
            {showEditReductionModal && selectedReduction && (
                <div className="modal fade show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog modal-lg">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Modifier Réduction</h5>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={() => setShowEditReductionModal(false)}
                                ></button>
                            </div>
                            <div className="modal-body">
                                <form onSubmit={handleUpdateReduction}>
                                    <div className="row g-3">
                                        <div className="col-md-6">
                                            <label className="form-label">Libellé *</label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                value={selectedReduction.LIBELLE}
                                                onChange={e => setSelectedReduction({ ...selectedReduction, LIBELLE: e.target.value })}
                                            />
                                            {errors.libelle && <p className="text-danger">{errors.libelle}</p>}
                                        </div>
                                        <div className="col-md-3">
                                            <label className="form-label">Pourcentage * (%)</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                className="form-control"
                                                value={selectedReduction.POURCENTAGE}
                                                onChange={e => setSelectedReduction({ ...selectedReduction, POURCENTAGE: e.target.value })}
                                            />
                                            {errors.pourcentage && <p className="text-danger">{errors.pourcentage}</p>}
                                        </div>
                                        <div className="col-md-3">
                                            <label className="form-label">Statut *</label>
                                            <select
                                                className="form-select"
                                                value={selectedReduction.ACTIF}
                                                onChange={e => setSelectedReduction({ ...selectedReduction, ACTIF: parseInt(e.target.value) })}
                                            >
                                                <option value="1">Actif</option>
                                                <option value="0">Inactif</option>
                                            </select>
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">Date de début *</label>
                                            <input
                                                type="date"
                                                className="form-control"
                                                value={selectedReduction.DATE_DEBUT}
                                                onChange={e => setSelectedReduction({ ...selectedReduction, DATE_DEBUT: e.target.value })}
                                            />
                                            {errors.date_debut && <p className="text-danger">{errors.date_debut}</p>}
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">Date de fin *</label>
                                            <input
                                                type="date"
                                                className="form-control"
                                                value={selectedReduction.DATE_FIN}
                                                onChange={e => setSelectedReduction({ ...selectedReduction, DATE_FIN: e.target.value })}
                                            />
                                            {errors.date_fin && <p className="text-danger">{errors.date_fin}</p>}
                                        </div>
                                        <div className="col-12">
                                            <label className="form-label fw-bold">Types de clients concernés * :</label>
                                            <div className="d-flex flex-wrap gap-3">
                                                {typesUsage && typesUsage.map(type => (
                                                    <div key={type.ID_USAGE} className="form-check">
                                                        <input
                                                            type="checkbox"
                                                            className="form-check-input"
                                                            id={`edit_type_${type.ID_USAGE}`}
                                                            checked={selectedReduction.types_client.includes(type.ID_USAGE)}
                                                            onChange={() => toggleEditReductionTypeClient(type.ID_USAGE)}
                                                        />
                                                        <label className="form-check-label" htmlFor={`edit_type_${type.ID_USAGE}`}>
                                                            {type.NOM}
                                                        </label>
                                                    </div>
                                                ))}
                                            </div>
                                            {errors.types_client && <p className="text-danger">{errors.types_client}</p>}
                                        </div>
                                        <div className="col-12">
                                            <label className="form-label">Description (optionnel)</label>
                                            <textarea
                                                className="form-control"
                                                rows="2"
                                                value={selectedReduction.DESCRIPTION || ''}
                                                onChange={e => setSelectedReduction({ ...selectedReduction, DESCRIPTION: e.target.value })}
                                            />
                                        </div>
                                    </div>
                                    <div className="d-flex justify-content-end gap-2 mt-4">
                                        <button
                                            type="button"
                                            className="btn btn-secondary"
                                            onClick={() => setShowEditReductionModal(false)}
                                        >
                                            Annuler
                                        </button>
                                        <button type="submit" className="btn btn-primary">
                                            Modifier
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
