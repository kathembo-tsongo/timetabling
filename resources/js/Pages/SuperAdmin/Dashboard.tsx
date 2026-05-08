import { router } from '@inertiajs/react'
import { useState } from 'react'

const Dashboard = ({ tenants, stats }: any) => {
    const [search, setSearch] = useState('')

    const filtered = tenants.filter((t: any) =>
        t.name.toLowerCase().includes(search.toLowerCase()) ||
        t.slug.toLowerCase().includes(search.toLowerCase())
    )

    const toggle = (tenant: any) => {
        if (confirm(`${tenant.is_active ? 'Deactivate' : 'Activate'} ${tenant.name}?`))
            router.post(`/super-admin/${tenant.id}/toggle`)
    }

    const impersonate = (tenant: any) => router.post(`/super-admin/${tenant.id}/impersonate`)
    const viewDetail = (tenant: any) => router.get(`/super-admin/${tenant.id}`)

    const deleteTenant = (tenant: any) => {
        if (confirm(`PERMANENTLY DELETE ${tenant.name}? This cannot be undone.`))
            router.delete(`/super-admin/${tenant.id}`)
    }

    const planColor: any = {
        free:       'bg-gray-100 text-gray-700',
        pro:        'bg-blue-100 text-blue-700',
        enterprise: 'bg-purple-100 text-purple-700',
    }

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Header */}
            <div className="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">⚡ Super Admin</h1>
                    <p className="text-sm text-gray-500">Manage all universities on the platform</p>
                </div>
                <a href="/super-admin/billing" className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 mr-2">💰 Billing</a>
                <a href="/onboarding/setup"
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    + Add University
                </a>
            </div>

            <div className="px-8 py-6">
                {/* Stats */}
                <div className="grid grid-cols-4 gap-4 mb-6">
                    {[
                        { label: 'Total universities', value: stats.total_tenants,  color: 'text-blue-600'   },
                        { label: 'Active',             value: stats.active_tenants, color: 'text-green-600'  },
                        { label: 'Total users',        value: stats.total_users,    color: 'text-purple-600' },
                        { label: 'Free plan',          value: stats.plans?.free||0, color: 'text-gray-600'   },
                    ].map((s, i) => (
                        <div key={i} className="bg-white rounded-xl border border-gray-200 p-5">
                            <p className="text-sm text-gray-500">{s.label}</p>
                            <p className={`text-3xl font-bold mt-1 ${s.color}`}>{s.value}</p>
                        </div>
                    ))}
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 className="font-semibold text-gray-800">Universities ({tenants.length})</h2>
                        <input value={search} onChange={e => setSearch(e.target.value)}
                            placeholder="Search..."
                            className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64"/>
                    </div>
                    <table className="w-full">
                        <thead className="bg-gray-50">
                            <tr>{['University','Slug','Plan','Users','Schools','Status','Actions'].map(h => (
                                <th key={h} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                            ))}</tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {filtered.map((t: any) => (
                                <tr key={t.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold"
                                                style={{ background: t.primary_color||'#1a56db' }}>
                                                {t.name[0]}
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900 text-sm">{t.name}</p>
                                                <p className="text-xs text-gray-400">{t.domain||'No domain'}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{t.slug}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2.5 py-1 rounded-full text-xs font-medium capitalize ${planColor[t.plan]||planColor.free}`}>
                                            {t.plan}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{t.users_count}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{t.schools_count}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${t.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                            {t.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-2">
                                            <button onClick={() => viewDetail(t)}
                                                className="text-xs px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 font-medium">
                                                View
                                            </button>
                                            <button onClick={() => toggle(t)}
                                                className={`text-xs px-3 py-1.5 rounded-lg font-medium ${t.is_active ? 'bg-yellow-50 text-yellow-600 hover:bg-yellow-100' : 'bg-green-50 text-green-600 hover:bg-green-100'}`}>
                                                {t.is_active ? 'Disable' : 'Enable'}
                                            </button>
                                            <button onClick={() => deleteTenant(t)}
                                                className="text-xs px-3 py-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 font-medium">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {filtered.length === 0 && (
                        <div className="text-center py-12 text-gray-400">No universities found</div>
                    )}
                </div>
            </div>
        </div>
    )
}

Dashboard.layout = (page: any) => page
export default Dashboard
