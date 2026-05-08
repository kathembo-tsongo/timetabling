// ── Shared Programs Management — works for any school ─────────────────────
// Degree types are passed as props from the controller, making this
// component work for any school at any university without code changes.
import { useEffect, useState } from 'react'
import { router, usePage } from '@inertiajs/react'

const ProgramsManagement: React.FC<any> = ({ 
    programs: initialPrograms = [],
    school,
    schoolCode,
    degreeTypes: propDegreeTypes,
    stats,
    filters,
    can,
}) => {
    const { tenant } = usePage<any>().props

    // Use degree types from props (set by controller per school)
    // or fall back to standard types
    const degreeTypes = propDegreeTypes ?? [
        { value: 'all',      label: 'All Degrees' },
        { value: 'Bachelor', label: "Bachelor's Degree" },
        { value: 'Master',   label: "Master's Degree" },
        { value: 'PhD',      label: 'Doctoral Degree (PhD)' },
        { value: 'Diploma',  label: 'Diploma' },
        { value: 'Certificate', label: 'Certificate' },
    ]

    const [programs, setPrograms]         = useState(initialPrograms)
    const [searchTerm, setSearchTerm]     = useState(filters?.search ?? '')
    const [statusFilter, setStatusFilter] = useState(filters?.status ?? 'all')
    const [degreeFilter, setDegreeFilter] = useState('all')
    const [showForm, setShowForm]         = useState(false)
    const [editingProgram, setEditingProgram] = useState<any>(null)
    const [form, setForm] = useState({
        code: '', name: '', degree_type: 'Bachelor',
        duration_years: 4, description: '', is_active: true
    })

    const set = (k: string, v: any) => setForm(f => ({ ...f, [k]: v }))

    const filtered = programs.filter((p: any) => {
        const matchSearch = !searchTerm ||
            p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            p.code.toLowerCase().includes(searchTerm.toLowerCase())
        const matchStatus = statusFilter === 'all' || 
            (statusFilter === 'active' ? p.is_active : !p.is_active)
        const matchDegree = degreeFilter === 'all' || p.degree_type === degreeFilter
        return matchSearch && matchStatus && matchDegree
    })

    const openCreate = () => {
        setEditingProgram(null)
        setForm({ code: '', name: '', degree_type: 'Bachelor', duration_years: 4, description: '', is_active: true })
        setShowForm(true)
    }

    const openEdit = (program: any) => {
        setEditingProgram(program)
        setForm({
            code: program.code, name: program.name,
            degree_type: program.degree_type, duration_years: program.duration_years,
            description: program.description ?? '', is_active: program.is_active
        })
        setShowForm(true)
    }

    const save = () => {
        if (editingProgram) {
            router.put(`/schools/${schoolCode}/programs/${editingProgram.id}`, form, {
                onSuccess: () => setShowForm(false)
            })
        } else {
            router.post(`/schools/${schoolCode}/programs`, form, {
                onSuccess: () => setShowForm(false)
            })
        }
    }

    const deleteProgram = (program: any) => {
        if (confirm(`Delete ${program.name}?`))
            router.delete(`/schools/${schoolCode}/programs/${program.id}`)
    }

    const degreeColor: any = {
        Bachelor: 'bg-blue-100 text-blue-700',
        Master:   'bg-purple-100 text-purple-700',
        MBA:      'bg-indigo-100 text-indigo-700',
        PhD:      'bg-red-100 text-red-700',
        Diploma:  'bg-green-100 text-green-700',
        Certificate: 'bg-yellow-100 text-yellow-700',
    }

    return (
        <div className="p-6 max-w-7xl mx-auto">
            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">
                        {school?.name ?? schoolCode} — Programs
                    </h1>
                    <p className="text-gray-500 text-sm mt-0.5">
                        {tenant?.name} · {stats?.total ?? programs.length} programs
                    </p>
                </div>
                {can?.create && (
                    <button onClick={openCreate}
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        + New Program
                    </button>
                )}
            </div>

            {/* Filters */}
            <div className="flex gap-3 mb-5 flex-wrap">
                <input value={searchTerm} onChange={e => setSearchTerm(e.target.value)}
                    placeholder="Search programs..."
                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64"/>
                <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)}
                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select value={degreeFilter} onChange={e => setDegreeFilter(e.target.value)}
                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    {degreeTypes.map((d: any) => (
                        <option key={d.value} value={d.value}>{d.label}</option>
                    ))}
                </select>
            </div>

            {/* Programs grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {filtered.map((program: any) => (
                    <div key={program.id} className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-sm transition-shadow">
                        <div className="flex items-start justify-between mb-3">
                            <div>
                                <span className="text-xs font-mono text-gray-500">{program.code}</span>
                                <h3 className="font-semibold text-gray-900 text-sm mt-0.5">{program.name}</h3>
                            </div>
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${degreeColor[program.degree_type] || 'bg-gray-100 text-gray-700'}`}>
                                {program.degree_type}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-xs text-gray-500 mt-3">
                            <span>{program.duration_years} years</span>
                            <span className={`px-2 py-0.5 rounded-full font-medium ${program.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                {program.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        {(can?.update || can?.delete) && (
                            <div className="flex gap-2 mt-3 pt-3 border-t border-gray-100">
                                {can?.update && (
                                    <button onClick={() => openEdit(program)}
                                        className="flex-1 text-xs py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600">
                                        Edit
                                    </button>
                                )}
                                {can?.delete && (
                                    <button onClick={() => deleteProgram(program)}
                                        className="flex-1 text-xs py-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100">
                                        Delete
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {filtered.length === 0 && (
                <div className="text-center py-16 text-gray-400">
                    <p className="text-lg">No programs found</p>
                    <p className="text-sm mt-1">Try adjusting your filters or add a new program</p>
                </div>
            )}

            {/* Create/Edit Modal */}
            {showForm && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-2xl w-full max-w-md p-6">
                        <h2 className="text-xl font-bold text-gray-900 mb-4">
                            {editingProgram ? 'Edit Program' : 'New Program'}
                        </h2>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Program code</label>
                                <input value={form.code} onChange={e => set('code', e.target.value.toUpperCase())}
                                    placeholder="e.g. BBIT" maxLength={20}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Program name</label>
                                <input value={form.name} onChange={e => set('name', e.target.value)}
                                    placeholder="e.g. Bachelor of Business IT"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Degree type</label>
                                    <select value={form.degree_type} onChange={e => set('degree_type', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        {degreeTypes.filter((d: any) => d.value !== 'all').map((d: any) => (
                                            <option key={d.value} value={d.value}>{d.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Duration (years)</label>
                                    <input type="number" value={form.duration_years}
                                        onChange={e => set('duration_years', parseInt(e.target.value))}
                                        min={1} max={8}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea value={form.description} onChange={e => set('description', e.target.value)}
                                    rows={2} placeholder="Optional description"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            </div>
                            <div className="flex items-center gap-2">
                                <input type="checkbox" id="is_active" checked={form.is_active}
                                    onChange={e => set('is_active', e.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300 text-blue-600"/>
                                <label htmlFor="is_active" className="text-sm text-gray-700">Active</label>
                            </div>
                        </div>
                        <div className="flex gap-3 mt-6">
                            <button onClick={() => setShowForm(false)}
                                className="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700">
                                Cancel
                            </button>
                            <button onClick={save}
                                className="flex-1 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                {editingProgram ? 'Save changes' : 'Create program'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}

export default ProgramsManagement
