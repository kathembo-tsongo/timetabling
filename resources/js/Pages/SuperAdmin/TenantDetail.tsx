import { router } from '@inertiajs/react'

const TenantDetail = ({ tenant, stats }: any) => {

    const impersonate = () => {
        router.post(`/super-admin/${tenant.id}/impersonate`)
    }

    const toggle = () => {
        if (confirm(`${tenant.is_active ? 'Deactivate' : 'Activate'} ${tenant.name}?`))
            router.post(`/super-admin/${tenant.id}/toggle`)
    }

    const deleteTenant = () => {
        if (confirm(`PERMANENTLY DELETE ${tenant.name} and ALL their data?`))
            router.delete(`/super-admin/${tenant.id}`)
    }

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Header */}
            <div className="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <a href="/super-admin" className="text-gray-400 hover:text-gray-600 text-sm">
                        ← Back
                    </a>
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-lg"
                            style={{ background: tenant.primary_color || '#1a56db' }}>
                            {tenant.name[0]}
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">{tenant.name}</h1>
                            <p className="text-sm text-gray-500">{tenant.slug}.yoursaas.com</p>
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <button onClick={toggle}
                        className={`px-4 py-2 rounded-lg text-sm font-medium ${tenant.is_active ? 'bg-yellow-50 text-yellow-600 hover:bg-yellow-100' : 'bg-green-50 text-green-600 hover:bg-green-100'}`}>
                        {tenant.is_active ? 'Deactivate' : 'Activate'}
                    </button>
                    <button onClick={deleteTenant}
                        className="px-4 py-2 bg-red-50 text-red-600 rounded-lg text-sm font-medium hover:bg-red-100">
                        Delete
                    </button>
                    <button onClick={impersonate}
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        🔍 View as this university
                    </button>
                </div>
            </div>

            <div className="px-8 py-6 max-w-6xl">
                {/* Stats */}
                <div className="grid grid-cols-4 gap-4 mb-6">
                    {[
                        { label: 'Users',    value: stats.users,    icon: '👥' },
                        { label: 'Schools',  value: stats.schools,  icon: '🏫' },
                        { label: 'Programs', value: stats.programs, icon: '📚' },
                        { label: 'Units',    value: stats.units,    icon: '📖' },
                    ].map((s, i) => (
                        <div key={i} className="bg-white rounded-xl border border-gray-200 p-5">
                            <p className="text-2xl mb-1">{s.icon}</p>
                            <p className="text-3xl font-bold text-gray-900">{s.value}</p>
                            <p className="text-sm text-gray-500 mt-1">{s.label}</p>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-2 gap-6">
                    {/* Details */}
                    <div className="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="font-semibold text-gray-800 mb-4">University details</h2>
                        <div className="space-y-3">
                            {[
                                ['Name',      tenant.name],
                                ['Slug',      tenant.slug],
                                ['Domain',    tenant.domain || 'Not set'],
                                ['Plan',      tenant.plan],
                                ['Timezone',  tenant.timezone],
                                ['Status',    tenant.is_active ? '✅ Active' : '❌ Inactive'],
                                ['Trial ends', tenant.trial_ends_at || 'N/A'],
                                ['Created',   new Date(tenant.created_at).toLocaleDateString()],
                            ].map(([label, value]) => (
                                <div key={label} className="flex justify-between py-2 border-b border-gray-50 text-sm">
                                    <span className="text-gray-500">{label}</span>
                                    <span className="font-medium text-gray-900 capitalize">{value}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Settings */}
                    <div className="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="font-semibold text-gray-800 mb-4">Configuration</h2>
                        <div className="space-y-3">
                            {tenant.settings && Object.entries(tenant.settings).map(([key, value]: any) => (
                                <div key={key} className="flex justify-between py-2 border-b border-gray-50 text-sm">
                                    <span className="text-gray-500 capitalize">{key.replace(/_/g, ' ')}</span>
                                    <span className="font-medium text-gray-900">
                                        {typeof value === 'boolean' ? (value ? 'Yes' : 'No') : String(value)}
                                    </span>
                                </div>
                            ))}
                        </div>

                        {/* Branding preview */}
                        <div className="mt-4 rounded-lg p-4 text-white text-sm"
                            style={{ background: tenant.primary_color || '#1a56db' }}>
                            <p className="font-medium">{tenant.name}</p>
                            <p className="opacity-75 text-xs mt-0.5">Timetabling System</p>
                            <div className="mt-2 inline-block px-3 py-1 rounded text-xs font-medium"
                                style={{ background: tenant.secondary_color || '#7e3af2' }}>
                                View timetable
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    )
}

TenantDetail.layout = (page: any) => page
export default TenantDetail
