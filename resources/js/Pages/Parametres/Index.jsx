import { Head } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import axios from 'axios';
import MainLayout from '../../Layouts/MainLayout';

// ── Composants réutilisables ──────────────────────────────────────────────────

const Input = ({ label, error, ...props }) => (
    <div>
        {label && <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>}
        <input className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" {...props} />
        {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
);

const Select = ({ label, error, children, ...props }) => (
    <div>
        {label && <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>}
        <select className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" {...props}>
            {children}
        </select>
        {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
);

const Textarea = ({ label, error, ...props }) => (
    <div>
        {label && <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>}
        <textarea className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" {...props} />
        {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
);

const Btn = ({ variant = 'primary', size = 'md', className = '', ...props }) => {
    const variants = {
        primary:   'bg-blue-600 hover:bg-blue-700 text-white',
        success:   'bg-green-600 hover:bg-green-700 text-white',
        danger:    'bg-red-600 hover:bg-red-700 text-white',
        warning:   'bg-yellow-500 hover:bg-yellow-600 text-white',
        secondary: 'bg-gray-200 hover:bg-gray-300 text-gray-700',
    };
    const sizes = { sm: 'px-2 py-1 text-xs', md: 'px-4 py-2 text-sm', lg: 'px-6 py-3 text-base' };
    return <button className={`rounded font-medium transition-colors disabled:opacity-50 ${variants[variant]} ${sizes[size]} ${className}`} {...props} />;
};

const Badge = ({ color = 'blue', children }) => {
    const colors = {
        blue:   'bg-blue-100 text-blue-800',
        green:  'bg-green-100 text-green-800',
        red:    'bg-red-100 text-red-800',
        yellow: 'bg-yellow-100 text-yellow-800',
        gray:   'bg-gray-100 text-gray-700',
        purple: 'bg-purple-100 text-purple-800',
    };
    return <span className={`px-2 py-0.5 rounded text-xs font-medium ${colors[color]}`}>{children}</span>;
};

const Modal = ({ title, onClose, size = 'md', children }) => {
    const sizes = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-3xl' };
    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div className={`bg-white rounded-lg w-full ${sizes[size]} max-h-[90vh] overflow-y-auto`}>
                <div className="flex justify-between items-center px-6 py-4 border-b">
                    <h3 className="text-lg font-semibold">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
                </div>
                <div className="px-6 py-4">{children}</div>
            </div>
        </div>
    );
};

const Table = ({ headers, children, empty }) => (
    <div className="overflow-x-auto rounded border border-gray-200">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
                <tr>
                    {headers.map(h => (
                        <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                    ))}
                </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-100">
                {children}
            </tbody>
        </table>
        {empty && <div className="px-4 py-6 text-center text-gray-400 text-sm">{empty}</div>}
    </div>
);

// ── Page principale ───────────────────────────────────────────────────────────

const TABS = [
    { key: 'general',      label: 'Général' },
    { key: 'usages',       label: 'Usages' },
    { key: 'operations',   label: 'Opération Trésorerie' },
    { key: 'utilisateurs', label: 'Utilisateurs' },
    { key: 'permissions',  label: 'Permissions' },
    { key: 'reductions',   label: 'Réductions' },
];

export default function Parametres({ general, roles, ressources, actions, permissions: initialPermissions, typesUsage }) {
    const [activeTab, setActiveTab] = useState('general');
    const [errors, setErrors]       = useState({});

    // ── General ──
    const [generalForm, setGeneralForm] = useState({
        general_entreprise: general.entreprise || '',
        general_adress:     general.adresse    || '',
        general_telephone:  general.telephone  || '',
    });

    // ── Usages ──
    const [usages, setUsages]                   = useState([]);
    const [usageForm, setUsageForm]             = useState({ usage_name: '', usage_tarif: '' });
    const [showEditUsage, setShowEditUsage]     = useState(false);
    const [selectedUsage, setSelectedUsage]     = useState(null);

    // ── Type Opérations ──
    const [typeOps, setTypeOps]                 = useState([]);
    const [typeOpForm, setTypeOpForm]           = useState({ type_libelle: '', type_is_revenue: '' });
    const [showEditTypeOp, setShowEditTypeOp]   = useState(false);
    const [selectedTypeOp, setSelectedTypeOp]   = useState(null);

    // ── Utilisateurs ──
    const [users, setUsers]                     = useState([]);
    const [userForm, setUserForm]               = useState({ nom: '', prenom: '', login: '', password: '', telephone: '', adresse: '', profile: [] });
    const [showEditUser, setShowEditUser]       = useState(false);
    const [selectedUser, setSelectedUser]       = useState(null);

    // ── Roles & Permissions ──
    const [rolesList, setRolesList]             = useState(roles || []);
    const [roleForm, setRoleForm]               = useState({ role_name: '' });
    const [permissions, setPermissions]         = useState(initialPermissions || {});

    // ── Réductions ──
    const [reductions, setReductions]               = useState([]);
    const [reductionForm, setReductionForm]         = useState({ libelle: '', date_debut: today(), date_fin: today(), pourcentage: '', types_client: [], actif: 1, description: '' });
    const [showEditReduction, setShowEditReduction] = useState(false);
    const [selectedReduction, setSelectedReduction] = useState(null);

    function today() { return new Date().toISOString().split('T')[0]; }

    useEffect(() => {
        if (activeTab === 'usages')       loadUsages();
        if (activeTab === 'operations')   loadTypeOps();
        if (activeTab === 'utilisateurs') loadUsers();
        if (activeTab === 'reductions')   loadReductions();
        setErrors({});
    }, [activeTab]);

    // ── Helpers API ──────────────────────────────────────────────────────────
    const api = async (fn) => { setErrors({}); try { await fn(); } catch (e) { setErrors(e.response?.data?.errors || { general: 'Erreur' }); } };

    // ── General ─────────────────────────────────────────────────────────────
    const saveGeneral = (e) => { e.preventDefault(); api(async () => { const r = await axios.post('/parametres/general', generalForm); if (r.data.success) alert(r.data.message); }); };

    // ── Usages ──────────────────────────────────────────────────────────────
    const loadUsages = async () => { try { const r = await axios.get('/parametres/usages/list'); setUsages(r.data); } catch {} };
    const addUsage   = (e) => { e.preventDefault(); api(async () => { const r = await axios.post('/parametres/usages', usageForm); if (r.data.success) { alert(r.data.message); setUsageForm({ usage_name: '', usage_tarif: '' }); loadUsages(); } }); };
    const updateUsage = (e) => { e.preventDefault(); api(async () => { const r = await axios.put(`/parametres/usages/${selectedUsage.ID_USAGE}`, { nom: selectedUsage.NOM, tarif: selectedUsage.TARIF }); if (r.data.success) { alert(r.data.message); setShowEditUsage(false); loadUsages(); } }); };
    const deleteUsage = (id) => { if (!confirm('Confirmer la suppression ?')) return; api(async () => { const r = await axios.delete(`/parametres/usages/${id}`); if (r.data.success) { alert(r.data.message); loadUsages(); } }); };

    // ── Type Opérations ──────────────────────────────────────────────────────
    const loadTypeOps  = async () => { try { const r = await axios.get('/parametres/typeoperations/list'); setTypeOps(r.data); } catch {} };
    const addTypeOp    = (e) => { e.preventDefault(); api(async () => { const r = await axios.post('/parametres/typeoperations', typeOpForm); if (r.data.success) { alert(r.data.message); setTypeOpForm({ type_libelle: '', type_is_revenue: '' }); loadTypeOps(); } }); };
    const updateTypeOp = (e) => { e.preventDefault(); api(async () => { const r = await axios.put(`/parametres/typeoperations/${selectedTypeOp.ID_TYPEOPERATION}`, { libelle: selectedTypeOp.LIBELLE, is_revenue: selectedTypeOp.IS_REVENUE }); if (r.data.success) { alert(r.data.message); setShowEditTypeOp(false); loadTypeOps(); } }); };
    const deleteTypeOp = (id) => { if (!confirm("Confirmer la suppression ?")) return; api(async () => { const r = await axios.delete(`/parametres/typeoperations/${id}`); if (r.data.success) { alert(r.data.message); loadTypeOps(); } }); };

    // ── Utilisateurs ─────────────────────────────────────────────────────────
    const loadUsers  = async () => { try { const r = await axios.get('/parametres/users/list', { params: { start: 0, length: 100 } }); setUsers(r.data.data); } catch {} };
    const addUser    = (e) => { e.preventDefault(); if (!userForm.profile.length) { setErrors({ profile: ['Sélectionnez au moins un profil'] }); return; } api(async () => { const r = await axios.post('/parametres/users', userForm); if (r.data.success) { alert(r.data.message); setUserForm({ nom: '', prenom: '', login: '', password: '', telephone: '', adresse: '', profile: [] }); loadUsers(); } }); };
    const updateUser = (e) => { e.preventDefault(); if (!selectedUser.profile.length) { setErrors({ profile: ['Sélectionnez au moins un profil'] }); return; } api(async () => { const r = await axios.put(`/parametres/users/${selectedUser.ID_USER}`, { nom: selectedUser.NOM, prenom: selectedUser.PRENOM, login: selectedUser.LOGIN, password: selectedUser.PASSWORD || '', telephone: selectedUser.TELEPHONE, adresse: selectedUser.ADRESSE, profile: selectedUser.profile }); if (r.data.success) { alert(r.data.message); setShowEditUser(false); loadUsers(); } }); };
    const deleteUser = (id) => { if (!confirm('Confirmer la suppression ?')) return; api(async () => { const r = await axios.delete(`/parametres/users/${id}`); if (r.data.success) { alert(r.data.message); loadUsers(); } }); };

    const toggleProfile = (val, form, setForm) => {
        setForm(prev => ({ ...prev, profile: prev.profile.includes(val) ? prev.profile.filter(p => p !== val) : [...prev.profile, val] }));
    };

    // ── Permissions ──────────────────────────────────────────────────────────
    const addRole    = (e) => { e.preventDefault(); api(async () => { const r = await axios.post('/parametres/roles', roleForm); if (r.data.success) { alert(r.data.message); setRoleForm({ role_name: '' }); window.location.reload(); } }); };
    const deleteRole = (id) => { if (!confirm('Confirmer la suppression ?')) return; api(async () => { const r = await axios.delete(`/parametres/roles/${id}`); if (r.data.success) { alert(r.data.message); window.location.reload(); } }); };
    const changePermission = (role, ressource, action, checked) => {
        setPermissions(prev => ({ ...prev, [role]: { ...prev[role], [ressource]: { ...prev[role]?.[ressource], [action]: checked ? 1 : 0 } } }));
    };
    const savePermissions = (e) => { e.preventDefault(); api(async () => { const r = await axios.post('/parametres/permissions', { permissions }); if (r.data.success) alert(r.data.message); }); };

    // ── Réductions ───────────────────────────────────────────────────────────
    const loadReductions  = async () => { try { const r = await axios.get('/parametres/reductions/list'); setReductions(r.data); } catch {} };
    const addReduction    = (e) => { e.preventDefault(); if (!reductionForm.types_client.length) { setErrors({ types_client: ['Sélectionnez au moins un type'] }); return; } api(async () => { const r = await axios.post('/parametres/reductions', reductionForm); if (r.data.success) { alert(r.data.message); setReductionForm({ libelle: '', date_debut: today(), date_fin: today(), pourcentage: '', types_client: [], actif: 1, description: '' }); loadReductions(); } }); };
    const updateReduction = (e) => { e.preventDefault(); if (!selectedReduction.types_client.length) { setErrors({ types_client: ['Sélectionnez au moins un type'] }); return; } api(async () => { const r = await axios.put(`/parametres/reductions/${selectedReduction.ID_REDUCTION}`, { libelle: selectedReduction.LIBELLE, date_debut: selectedReduction.DATE_DEBUT, date_fin: selectedReduction.DATE_FIN, pourcentage: selectedReduction.POURCENTAGE, types_client: selectedReduction.types_client, actif: selectedReduction.ACTIF, description: selectedReduction.DESCRIPTION }); if (r.data.success) { alert(r.data.message); setShowEditReduction(false); loadReductions(); } }); };
    const deleteReduction = (id) => { if (!confirm('Confirmer la suppression ?')) return; api(async () => { const r = await axios.delete(`/parametres/reductions/${id}`); if (r.data.success) { alert(r.data.message); loadReductions(); } }); };
    const toggleReduction = (id) => { api(async () => { const r = await axios.post(`/parametres/reductions/${id}/toggle`); if (r.data.success) { alert(r.data.message); loadReductions(); } }); };
    const toggleTypeClient = (id, form, setForm) => {
        setForm(prev => ({ ...prev, types_client: prev.types_client.includes(id) ? prev.types_client.filter(t => t !== id) : [...prev.types_client, id] }));
    };

    // ── Render ───────────────────────────────────────────────────────────────
    return (
        <MainLayout title="Paramètres">
            <Head title="Paramètres" />

            <div className="bg-white rounded shadow overflow-hidden">
                {/* Onglets */}
                <div className="border-b border-gray-200 overflow-x-auto">
                    <nav className="flex -mb-px">
                        {TABS.map(tab => (
                            <button key={tab.key} onClick={() => setActiveTab(tab.key)}
                                className={`px-5 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                                    activeTab === tab.key
                                        ? 'border-blue-600 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}>
                                {tab.label}
                            </button>
                        ))}
                    </nav>
                </div>

                <div className="p-6">

                    {/* ══ GÉNÉRAL ══════════════════════════════════════════════ */}
                    {activeTab === 'general' && (
                        <form onSubmit={saveGeneral} className="max-w-xl space-y-4">
                            <h4 className="text-lg font-semibold mb-4">Informations Générales</h4>
                            <Textarea label="Entreprise" rows={5} value={generalForm.general_entreprise} onChange={e => setGeneralForm({ ...generalForm, general_entreprise: e.target.value })} error={errors.general_entreprise} />
                            <Textarea label="Adresse" rows={3} value={generalForm.general_adress} onChange={e => setGeneralForm({ ...generalForm, general_adress: e.target.value })} error={errors.general_adress} />
                            <Textarea label="Téléphone" rows={3} value={generalForm.general_telephone} onChange={e => setGeneralForm({ ...generalForm, general_telephone: e.target.value })} error={errors.general_telephone} />
                            <Btn type="submit" variant="success">Enregistrer</Btn>
                        </form>
                    )}

                    {/* ══ USAGES ═══════════════════════════════════════════════ */}
                    {activeTab === 'usages' && (
                        <div>
                            <h4 className="text-lg font-semibold mb-4">Gestion des Usages</h4>
                            <form onSubmit={addUsage} className="bg-gray-50 border rounded p-4 mb-6">
                                <p className="font-medium text-sm mb-3">Ajouter un usage</p>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <Input placeholder="Nom" value={usageForm.usage_name} onChange={e => setUsageForm({ ...usageForm, usage_name: e.target.value })} error={errors.usage_name} />
                                    <Input type="number" placeholder="Tarif (FCFA)" value={usageForm.usage_tarif} onChange={e => setUsageForm({ ...usageForm, usage_tarif: e.target.value })} error={errors.usage_tarif} />
                                    <Btn type="submit" variant="success" className="w-full">Ajouter</Btn>
                                </div>
                            </form>
                            <Table headers={['Nom', 'Tarif', 'Actions']} empty={usages.length === 0 ? 'Aucun usage' : null}>
                                {usages.map(u => (
                                    <tr key={u.ID_USAGE}>
                                        <td className="px-4 py-3">{u.NOM}</td>
                                        <td className="px-4 py-3">{u.TARIF} FCFA</td>
                                        <td className="px-4 py-3 space-x-2">
                                            <Btn size="sm" onClick={() => { setSelectedUsage(u); setShowEditUsage(true); }}>Modifier</Btn>
                                            <Btn size="sm" variant="danger" onClick={() => deleteUsage(u.ID_USAGE)}>Supprimer</Btn>
                                        </td>
                                    </tr>
                                ))}
                            </Table>
                        </div>
                    )}

                    {/* ══ TYPE OPÉRATIONS ══════════════════════════════════════ */}
                    {activeTab === 'operations' && (
                        <div>
                            <h4 className="text-lg font-semibold mb-4">Types d'Opération Trésorerie</h4>
                            <form onSubmit={addTypeOp} className="bg-gray-50 border rounded p-4 mb-6">
                                <p className="font-medium text-sm mb-3">Ajouter un type d'opération</p>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <Input placeholder="Libellé" value={typeOpForm.type_libelle} onChange={e => setTypeOpForm({ ...typeOpForm, type_libelle: e.target.value })} error={errors.type_libelle} />
                                    <Select value={typeOpForm.type_is_revenue} onChange={e => setTypeOpForm({ ...typeOpForm, type_is_revenue: e.target.value })} error={errors.type_is_revenue}>
                                        <option value="">Sélectionner type</option>
                                        <option value="1">Revenue</option>
                                        <option value="0">Dépense</option>
                                    </Select>
                                    <Btn type="submit" variant="success" className="w-full">Ajouter</Btn>
                                </div>
                            </form>
                            <Table headers={['Libellé', 'Type', 'Actions']} empty={typeOps.length === 0 ? 'Aucun type' : null}>
                                {typeOps.map(t => (
                                    <tr key={t.ID_TYPEOPERATION}>
                                        <td className="px-4 py-3">{t.LIBELLE}</td>
                                        <td className="px-4 py-3">
                                            <Badge color={t.IS_REVENUE == 1 ? 'green' : 'red'}>{t.IS_REVENUE == 1 ? 'Revenue' : 'Dépense'}</Badge>
                                        </td>
                                        <td className="px-4 py-3 space-x-2">
                                            <Btn size="sm" onClick={() => { setSelectedTypeOp(t); setShowEditTypeOp(true); }}>Modifier</Btn>
                                            <Btn size="sm" variant="danger" onClick={() => deleteTypeOp(t.ID_TYPEOPERATION)}>Supprimer</Btn>
                                        </td>
                                    </tr>
                                ))}
                            </Table>
                        </div>
                    )}

                    {/* ══ UTILISATEURS ═════════════════════════════════════════ */}
                    {activeTab === 'utilisateurs' && (
                        <div>
                            <h4 className="text-lg font-semibold mb-4">Gestion des Utilisateurs</h4>
                            <Table headers={['Nom', 'Prénom', 'Profile', 'Téléphone', 'Actions']} empty={users.length === 0 ? 'Aucun utilisateur' : null}>
                                {users.map(u => (
                                    <tr key={u.ID_USER}>
                                        <td className="px-4 py-3">{u.NOM}</td>
                                        <td className="px-4 py-3">{u.PRENOM}</td>
                                        <td className="px-4 py-3">{u.PROFILE}</td>
                                        <td className="px-4 py-3">{u.TELEPHONE}</td>
                                        <td className="px-4 py-3 space-x-2">
                                            <Btn size="sm" onClick={() => { setSelectedUser({ ...u, profile: u.PROFILE ? u.PROFILE.split('&') : [] }); setShowEditUser(true); }}>Modifier</Btn>
                                            <Btn size="sm" variant="danger" onClick={() => deleteUser(u.ID_USER)}>Supprimer</Btn>
                                        </td>
                                    </tr>
                                ))}
                            </Table>

                            <div className="mt-6 bg-gray-50 border rounded p-4">
                                <h5 className="font-semibold mb-4">Ajouter un utilisateur</h5>
                                <form onSubmit={addUser}>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <Input label="Nom" value={userForm.nom} onChange={e => setUserForm({ ...userForm, nom: e.target.value })} error={errors.nom} />
                                        <Input label="Prénom" value={userForm.prenom} onChange={e => setUserForm({ ...userForm, prenom: e.target.value })} error={errors.prenom} />
                                        <Input label="Login" value={userForm.login} onChange={e => setUserForm({ ...userForm, login: e.target.value })} error={errors.login} />
                                        <Input label="Password" type="password" value={userForm.password} onChange={e => setUserForm({ ...userForm, password: e.target.value })} error={errors.password} />
                                        <Input label="Téléphone" value={userForm.telephone} onChange={e => setUserForm({ ...userForm, telephone: e.target.value })} />
                                        <Input label="Adresse" value={userForm.adresse} onChange={e => setUserForm({ ...userForm, adresse: e.target.value })} />
                                    </div>
                                    <div className="mb-4">
                                        <p className="text-sm font-medium text-gray-700 mb-2">Profil</p>
                                        <div className="flex flex-wrap gap-4">
                                            {rolesList.map(r => (
                                                <label key={r.ID_PARAMETRE} className="flex items-center gap-2 text-sm cursor-pointer">
                                                    <input type="checkbox" className="rounded" checked={userForm.profile.includes(r.VALUE)} onChange={() => toggleProfile(r.VALUE, userForm, setUserForm)} />
                                                    {r.VALUE.charAt(0).toUpperCase() + r.VALUE.slice(1)}
                                                </label>
                                            ))}
                                            <label className="flex items-center gap-2 text-sm cursor-pointer">
                                                <input type="checkbox" className="rounded" checked={userForm.profile.includes('administrateur')} onChange={() => toggleProfile('administrateur', userForm, setUserForm)} />
                                                Super Administrateur
                                            </label>
                                        </div>
                                        {errors.profile && <p className="text-red-500 text-xs mt-1">{errors.profile}</p>}
                                    </div>
                                    <Btn type="submit" variant="success">Ajouter Utilisateur</Btn>
                                </form>
                            </div>
                        </div>
                    )}

                    {/* ══ PERMISSIONS ══════════════════════════════════════════ */}
                    {activeTab === 'permissions' && (
                        <div>
                            <h4 className="text-lg font-semibold mb-4">Rôles et Permissions</h4>
                            <form onSubmit={addRole} className="bg-gray-50 border rounded p-4 mb-6">
                                <p className="font-medium text-sm mb-3">Ajouter un rôle</p>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div className="md:col-span-2">
                                        <Input placeholder="Nom du rôle" value={roleForm.role_name} onChange={e => setRoleForm({ role_name: e.target.value })} error={errors.role_name} />
                                    </div>
                                    <Btn type="submit" variant="success" className="w-full">Ajouter</Btn>
                                </div>
                            </form>

                            <form onSubmit={savePermissions} className="space-y-6">
                                {rolesList.map(role => (
                                    <div key={role.ID_PARAMETRE} className="border rounded p-4">
                                        <div className="flex justify-between items-center mb-4">
                                            <h5 className="font-semibold">{role.VALUE.charAt(0).toUpperCase() + role.VALUE.slice(1)}</h5>
                                            <Btn size="sm" variant="danger" type="button" onClick={() => deleteRole(role.ID_PARAMETRE)}>Supprimer Rôle</Btn>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            {ressources.map(res => (
                                                <div key={res.ID_PARAMETRE} className="border rounded overflow-hidden">
                                                    <div className="bg-blue-600 text-white px-3 py-2 text-sm font-medium">{res.VALUE}</div>
                                                    <div className="p-3 space-y-2">
                                                        {actions.filter(a => !a.PARENT || a.PARENT === res.VALUE).map(action => (
                                                            <label key={action.ID_PARAMETRE} className="flex items-center gap-2 text-sm cursor-pointer">
                                                                <input type="checkbox" className="rounded"
                                                                    checked={permissions[role.VALUE]?.[res.VALUE]?.[action.VALUE] === 1}
                                                                    onChange={e => changePermission(role.VALUE, res.VALUE, action.VALUE, e.target.checked)} />
                                                                {action.VALUE}
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                <Btn type="submit" variant="success" size="lg">Enregistrer les Permissions</Btn>
                            </form>
                        </div>
                    )}

                    {/* ══ RÉDUCTIONS ═══════════════════════════════════════════ */}
                    {activeTab === 'reductions' && (
                        <div>
                            <h4 className="text-lg font-semibold mb-4">Gestion des Réductions Ponctuelles</h4>
                            <div className="bg-gray-50 border rounded p-4 mb-6">
                                <h5 className="font-semibold mb-4">Créer une réduction</h5>
                                <form onSubmit={addReduction}>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <Input label="Libellé *" placeholder="Ex: Promotion Nouvel An" value={reductionForm.libelle} onChange={e => setReductionForm({ ...reductionForm, libelle: e.target.value })} error={errors.libelle} />
                                        <div className="grid grid-cols-2 gap-3">
                                            <Input label="Pourcentage * (%)" type="number" step="0.01" min="0" max="100" placeholder="15.5" value={reductionForm.pourcentage} onChange={e => setReductionForm({ ...reductionForm, pourcentage: e.target.value })} error={errors.pourcentage} />
                                            <Select label="Statut *" value={reductionForm.actif} onChange={e => setReductionForm({ ...reductionForm, actif: parseInt(e.target.value) })}>
                                                <option value="1">Actif</option>
                                                <option value="0">Inactif</option>
                                            </Select>
                                        </div>
                                        <Input label="Date de début *" type="date" value={reductionForm.date_debut} onChange={e => setReductionForm({ ...reductionForm, date_debut: e.target.value })} error={errors.date_debut} />
                                        <Input label="Date de fin *" type="date" value={reductionForm.date_fin} onChange={e => setReductionForm({ ...reductionForm, date_fin: e.target.value })} error={errors.date_fin} />
                                    </div>
                                    <div className="mb-4">
                                        <p className="text-sm font-medium text-gray-700 mb-2">Types de clients concernés *</p>
                                        <div className="flex flex-wrap gap-4">
                                            {typesUsage?.map(t => (
                                                <label key={t.ID_USAGE} className="flex items-center gap-2 text-sm cursor-pointer">
                                                    <input type="checkbox" className="rounded" checked={reductionForm.types_client.includes(t.ID_USAGE)} onChange={() => toggleTypeClient(t.ID_USAGE, reductionForm, setReductionForm)} />
                                                    {t.NOM}
                                                </label>
                                            ))}
                                        </div>
                                        {errors.types_client && <p className="text-red-500 text-xs mt-1">{errors.types_client}</p>}
                                    </div>
                                    <Textarea label="Description (optionnel)" rows={2} value={reductionForm.description} onChange={e => setReductionForm({ ...reductionForm, description: e.target.value })} />
                                    <Btn type="submit" variant="success" className="mt-4">Créer la Réduction</Btn>
                                </form>
                            </div>

                            <Table headers={['Libellé', 'Période', 'Pourcentage', 'Types Clients', 'Statut', 'Actions']} empty={reductions.length === 0 ? 'Aucune réduction' : null}>
                                {reductions.map(r => (
                                    <tr key={r.ID_REDUCTION}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{r.LIBELLE}</div>
                                            {r.DESCRIPTION && <div className="text-xs text-gray-400">{r.DESCRIPTION}</div>}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">
                                            {new Date(r.DATE_DEBUT).toLocaleDateString('fr-FR')} → {new Date(r.DATE_FIN).toLocaleDateString('fr-FR')}
                                        </td>
                                        <td className="px-4 py-3"><Badge color="blue">{r.POURCENTAGE}%</Badge></td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {r.TYPES_CLIENT?.length > 0
                                                    ? r.TYPES_CLIENT.map((t, i) => <Badge key={i} color="gray">{t}</Badge>)
                                                    : <span className="text-gray-400 text-xs">Aucun</span>}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge color={r.ACTIF == 1 ? 'green' : 'red'}>{r.ACTIF == 1 ? 'Actif' : 'Inactif'}</Badge>
                                        </td>
                                        <td className="px-4 py-3 space-x-1 whitespace-nowrap">
                                            <Btn size="sm" variant={r.ACTIF == 1 ? 'warning' : 'success'} onClick={() => toggleReduction(r.ID_REDUCTION)}>
                                                {r.ACTIF == 1 ? 'Désactiver' : 'Activer'}
                                            </Btn>
                                            <Btn size="sm" onClick={() => { setSelectedReduction({ ...r, types_client: r.TYPES_CLIENT_IDS || [] }); setShowEditReduction(true); }}>Modifier</Btn>
                                            <Btn size="sm" variant="danger" onClick={() => deleteReduction(r.ID_REDUCTION)}>Supprimer</Btn>
                                        </td>
                                    </tr>
                                ))}
                            </Table>
                        </div>
                    )}

                </div>
            </div>

            {/* ── Modal Modifier Usage ── */}
            {showEditUsage && selectedUsage && (
                <Modal title="Modifier Usage" onClose={() => setShowEditUsage(false)}>
                    <form onSubmit={updateUsage} className="space-y-4">
                        <Input label="Nom" value={selectedUsage.NOM} onChange={e => setSelectedUsage({ ...selectedUsage, NOM: e.target.value })} error={errors.nom} />
                        <Input label="Tarif" type="number" value={selectedUsage.TARIF} onChange={e => setSelectedUsage({ ...selectedUsage, TARIF: e.target.value })} error={errors.tarif} />
                        <div className="flex justify-end gap-2 pt-2">
                            <Btn type="button" variant="secondary" onClick={() => setShowEditUsage(false)}>Annuler</Btn>
                            <Btn type="submit">Modifier</Btn>
                        </div>
                    </form>
                </Modal>
            )}

            {/* ── Modal Modifier Type Opération ── */}
            {showEditTypeOp && selectedTypeOp && (
                <Modal title="Modifier Type Opération" onClose={() => setShowEditTypeOp(false)}>
                    <form onSubmit={updateTypeOp} className="space-y-4">
                        <Input label="Libellé" value={selectedTypeOp.LIBELLE} onChange={e => setSelectedTypeOp({ ...selectedTypeOp, LIBELLE: e.target.value })} error={errors.libelle} />
                        <Select label="Type" value={selectedTypeOp.IS_REVENUE} onChange={e => setSelectedTypeOp({ ...selectedTypeOp, IS_REVENUE: e.target.value })} error={errors.is_revenue}>
                            <option value="">Sélectionner</option>
                            <option value="1">Revenue</option>
                            <option value="0">Dépense</option>
                        </Select>
                        <div className="flex justify-end gap-2 pt-2">
                            <Btn type="button" variant="secondary" onClick={() => setShowEditTypeOp(false)}>Annuler</Btn>
                            <Btn type="submit">Modifier</Btn>
                        </div>
                    </form>
                </Modal>
            )}

            {/* ── Modal Modifier Utilisateur ── */}
            {showEditUser && selectedUser && (
                <Modal title="Modifier Utilisateur" onClose={() => setShowEditUser(false)} size="lg">
                    <form onSubmit={updateUser}>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <Input label="Nom" value={selectedUser.NOM} onChange={e => setSelectedUser({ ...selectedUser, NOM: e.target.value })} error={errors.nom} />
                            <Input label="Prénom" value={selectedUser.PRENOM} onChange={e => setSelectedUser({ ...selectedUser, PRENOM: e.target.value })} error={errors.prenom} />
                            <Input label="Login" value={selectedUser.LOGIN} onChange={e => setSelectedUser({ ...selectedUser, LOGIN: e.target.value })} error={errors.login} />
                            <Input label="Password (vide = inchangé)" type="password" placeholder="Nouveau mot de passe" onChange={e => setSelectedUser({ ...selectedUser, PASSWORD: e.target.value })} error={errors.password} />
                            <Input label="Téléphone" value={selectedUser.TELEPHONE} onChange={e => setSelectedUser({ ...selectedUser, TELEPHONE: e.target.value })} />
                            <Input label="Adresse" value={selectedUser.ADRESSE} onChange={e => setSelectedUser({ ...selectedUser, ADRESSE: e.target.value })} />
                        </div>
                        <div className="mb-4">
                            <p className="text-sm font-medium text-gray-700 mb-2">Profil</p>
                            <div className="flex flex-wrap gap-4">
                                {rolesList.map(r => (
                                    <label key={r.ID_PARAMETRE} className="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="checkbox" className="rounded" checked={selectedUser.profile.includes(r.VALUE)} onChange={() => setSelectedUser(prev => ({ ...prev, profile: prev.profile.includes(r.VALUE) ? prev.profile.filter(p => p !== r.VALUE) : [...prev.profile, r.VALUE] }))} />
                                        {r.VALUE.charAt(0).toUpperCase() + r.VALUE.slice(1)}
                                    </label>
                                ))}
                                <label className="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="checkbox" className="rounded" checked={selectedUser.profile.includes('administrateur')} onChange={() => setSelectedUser(prev => ({ ...prev, profile: prev.profile.includes('administrateur') ? prev.profile.filter(p => p !== 'administrateur') : [...prev.profile, 'administrateur'] }))} />
                                    Super Administrateur
                                </label>
                            </div>
                            {errors.profile && <p className="text-red-500 text-xs mt-1">{errors.profile}</p>}
                        </div>
                        <div className="flex justify-end gap-2 pt-2">
                            <Btn type="button" variant="secondary" onClick={() => setShowEditUser(false)}>Annuler</Btn>
                            <Btn type="submit">Modifier</Btn>
                        </div>
                    </form>
                </Modal>
            )}

            {/* ── Modal Modifier Réduction ── */}
            {showEditReduction && selectedReduction && (
                <Modal title="Modifier Réduction" onClose={() => setShowEditReduction(false)} size="lg">
                    <form onSubmit={updateReduction}>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <Input label="Libellé *" value={selectedReduction.LIBELLE} onChange={e => setSelectedReduction({ ...selectedReduction, LIBELLE: e.target.value })} error={errors.libelle} />
                            <div className="grid grid-cols-2 gap-3">
                                <Input label="Pourcentage * (%)" type="number" step="0.01" min="0" max="100" value={selectedReduction.POURCENTAGE} onChange={e => setSelectedReduction({ ...selectedReduction, POURCENTAGE: e.target.value })} error={errors.pourcentage} />
                                <Select label="Statut *" value={selectedReduction.ACTIF} onChange={e => setSelectedReduction({ ...selectedReduction, ACTIF: parseInt(e.target.value) })}>
                                    <option value="1">Actif</option>
                                    <option value="0">Inactif</option>
                                </Select>
                            </div>
                            <Input label="Date de début *" type="date" value={selectedReduction.DATE_DEBUT} onChange={e => setSelectedReduction({ ...selectedReduction, DATE_DEBUT: e.target.value })} error={errors.date_debut} />
                            <Input label="Date de fin *" type="date" value={selectedReduction.DATE_FIN} onChange={e => setSelectedReduction({ ...selectedReduction, DATE_FIN: e.target.value })} error={errors.date_fin} />
                        </div>
                        <div className="mb-4">
                            <p className="text-sm font-medium text-gray-700 mb-2">Types de clients concernés *</p>
                            <div className="flex flex-wrap gap-4">
                                {typesUsage?.map(t => (
                                    <label key={t.ID_USAGE} className="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="checkbox" className="rounded" checked={selectedReduction.types_client.includes(t.ID_USAGE)} onChange={() => setSelectedReduction(prev => ({ ...prev, types_client: prev.types_client.includes(t.ID_USAGE) ? prev.types_client.filter(x => x !== t.ID_USAGE) : [...prev.types_client, t.ID_USAGE] }))} />
                                        {t.NOM}
                                    </label>
                                ))}
                            </div>
                            {errors.types_client && <p className="text-red-500 text-xs mt-1">{errors.types_client}</p>}
                        </div>
                        <Textarea label="Description (optionnel)" rows={2} value={selectedReduction.DESCRIPTION || ''} onChange={e => setSelectedReduction({ ...selectedReduction, DESCRIPTION: e.target.value })} />
                        <div className="flex justify-end gap-2 pt-4">
                            <Btn type="button" variant="secondary" onClick={() => setShowEditReduction(false)}>Annuler</Btn>
                            <Btn type="submit">Modifier</Btn>
                        </div>
                    </form>
                </Modal>
            )}
        </MainLayout>
    );
}