import { useState } from 'react'
import { router } from '@inertiajs/react'

const Billing = ({ invoices, stats, plans }: any) => {
    const [paymentRef, setPaymentRef] = useState<any>({})

    const markPaid = (invoice: any) => {
        router.post(`/super-admin/billing/${invoice.id}/paid`, {
            payment_reference: paymentRef[invoice.id] || invoice.payment_reference
        })
    }

    const suspend = (invoice: any) => {
        if (confirm(`Suspend ${invoice.tenant?.name}?`))
            router.post(`/super-admin/billing/${invoice.tenant_id}/suspend`)
    }

    const statusColor: any = {
        pending: 'bg-yellow-100 text-yellow-700',
        paid:    'bg-green-100 text-green-700',
        overdue: 'bg-red-100 text-red-700',
    }

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <a href="/super-admin" className="text-gray-400 hover:text-gray-600 text-sm">← Back</a>
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">💰 Billing Management</h1>
                        <p className="text-sm text-gray-500">Manage university subscriptions and payments</p>
                    </div>
                </div>
            </div>

            <div className="px-8 py-6">
                {/* Stats */}
                <div className="grid grid-cols-4 gap-4 mb-6">
                    {[
                        { label: 'Total revenue',   value: `KES ${parseFloat(stats.total_revenue||0).toLocaleString()}`,   color: 'text-green-600' },
                        { label: 'Pending revenue', value: `KES ${parseFloat(stats.pending_revenue||0).toLocaleString()}`, color: 'text-yellow-600' },
                        { label: 'Paid invoices',   value: stats.paid_count,    color: 'text-blue-600' },
                        { label: 'Pending invoices',value: stats.pending_count, color: 'text-orange-600' },
                    ].map((s,i) => (
                        <div key={i} className="bg-white rounded-xl border border-gray-200 p-5">
                            <p className="text-sm text-gray-500">{s.label}</p>
                            <p className={`text-2xl font-bold mt-1 ${s.color}`}>{s.value}</p>
                        </div>
                    ))}
                </div>

                {/* Invoices table */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h2 className="font-semibold text-gray-800">All invoices</h2>
                    </div>
                    <table className="w-full">
                        <thead className="bg-gray-50">
                            <tr>{['Invoice','University','Plan','Amount','Payment ref','Due date','Status','Actions'].map(h => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                            ))}</tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {invoices.map((inv: any) => (
                                <tr key={inv.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-4 text-sm font-medium text-gray-900">{inv.invoice_number}</td>
                                    <td className="px-4 py-4 text-sm text-gray-600">{inv.tenant?.name}</td>
                                    <td className="px-4 py-4 text-sm text-gray-600 capitalize">{inv.plan}</td>
                                    <td className="px-4 py-4 text-sm text-gray-600">KES {parseFloat(inv.amount).toLocaleString()}</td>
                                    <td className="px-4 py-4">
                                        {inv.status === 'pending' ? (
                                            <input
                                                value={paymentRef[inv.id] || inv.payment_reference || ''}
                                                onChange={e => setPaymentRef((p: any) => ({...p, [inv.id]: e.target.value}))}
                                                placeholder="Enter ref..."
                                                className="border border-gray-300 rounded px-2 py-1 text-xs w-32 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                        ) : (
                                            <span className="text-xs text-gray-500">{inv.payment_reference || '—'}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-4 text-sm text-gray-600">{new Date(inv.due_date).toLocaleDateString()}</td>
                                    <td className="px-4 py-4">
                                        <span className={`px-2.5 py-1 rounded-full text-xs font-medium capitalize ${statusColor[inv.status]}`}>
                                            {inv.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-4">
                                        <div className="flex gap-2">
                                            {inv.status === 'pending' && (
                                                <button onClick={() => markPaid(inv)}
                                                    className="text-xs px-3 py-1.5 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 font-medium">
                                                    Mark Paid
                                                </button>
                                            )}
                                            {inv.status === 'pending' && (
                                                <button onClick={() => suspend(inv)}
                                                    className="text-xs px-3 py-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 font-medium">
                                                    Suspend
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {invoices.length === 0 && (
                        <div className="text-center py-12 text-gray-400">No invoices yet</div>
                    )}
                </div>
            </div>
        </div>
    )
}

Billing.layout = (page: any) => page
export default Billing
