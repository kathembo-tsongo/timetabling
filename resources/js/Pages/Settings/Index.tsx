import { useState } from 'react'
import { router } from '@inertiajs/react'

const TABS = ['General', 'Branding', 'Academic', 'Domain']

const Index = ({ tenant, settings }: any) => {
    const [tab, setTab] = useState('General')
    const [success, setSuccess] = useState('')

    // General form
    const [general, setGeneral] = useState({
        university_name:  settings?.university_name  || tenant.name,
        university_motto: settings?.university_motto || '',
        country:          settings?.country          || '',
        academic_year:    settings?.academic_year    || '',
        timezone:         tenant.timezone            || 'Africa/Nairobi',
        locale:           tenant.locale              || 'en',
        currency:         tenant.currency            || 'KES',
    })

    // Branding form
    const [branding, setBranding] = useState({
        primary_color:   tenant.primary_color   || '#1a56db',
        secondary_color: tenant.secondary_color || '#7e3af2',
    })
    const [logoFile, setLogoFile] = useState<File|null>(null)

    // Domain form
    const [domain, setDomain] = useState(tenant.domain || '')

    const saveDomain = () => {
        router.post('/settings/domain', { domain }, {
            onSuccess: () => notify('Domain settings saved!'),
        })
    }

    // Academic form
    const [academic, setAcademic] = useState({
        grading_system:     settings?.grading_system     || 'GPA',
        max_credit_hours:   settings?.max_credit_hours   || 21,
        semester_type:      settings?.semester_type      || 'semester',
        credit_hour_system: settings?.credit_hour_system ?? true,
        exam_gap_days:      settings?.exam_rules?.min_gap_days  || 2,
        max_exams_per_day:  settings?.exam_rules?.max_per_day   || 3,
        allow_weekends:     settings?.exam_rules?.allow_weekends ?? false,
    })

    const notify = (msg: string) => {
        setSuccess(msg)
        setTimeout(() => setSuccess(''), 3000)
    }

    const saveGeneral = () => {
        router.post('/settings/general', general, {
            onSuccess: () => notify('General settings saved!'),
        })
    }

    const saveBranding = () => {
        const data = new FormData()
        data.append('primary_color',   branding.primary_color)
        data.append('secondary_color', branding.secondary_color)
        if (logoFile) data.append('logo', logoFile)
        router.post('/settings/branding', data, {
            onSuccess: () => notify('Branding saved!'),
        })
    }

    const saveAcademic = () => {
        router.post('/settings/academic', academic, {
            onSuccess: () => notify('Academic settings saved!'),
        })
    }

    const Field = ({ label, value, onChange, type='text', placeholder='' }: any) => (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            <input type={type} value={value} onChange={e => onChange(e.target.value)}
                placeholder={placeholder}
                className="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>
    )

    const Select = ({ label, value, onChange, options }: any) => (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            <select value={value} onChange={e => onChange(e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                {options.map(([v,l]: any) => <option key={v} value={v}>{l}</option>)}
            </select>
        </div>
    )

    const Toggle = ({ label, desc, value, onChange }: any) => (
        <div className="flex items-center justify-between py-3 border-b border-gray-100">
            <div>
                <p className="text-sm font-medium text-gray-700">{label}</p>
                {desc && <p className="text-xs text-gray-500 mt-0.5">{desc}</p>}
            </div>
            <button type="button" onClick={() => onChange(!value)}
                className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${value ? 'bg-blue-600' : 'bg-gray-200'}`}>
                <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${value ? 'translate-x-4' : 'translate-x-1'}`}/>
            </button>
        </div>
    )

    return (
        <div className="max-w-3xl mx-auto py-8 px-4">
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">University Settings</h1>
                <p className="text-gray-500 text-sm mt-1">
                    Customize {tenant.name}'s timetabling system
                </p>
            </div>

            {/* Success toast */}
            {success && (
                <div className="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm font-medium">
                    ✅ {success}
                </div>
            )}

            {/* Tabs */}
            <div className="flex border-b border-gray-200 mb-6">
                {TABS.map(t => (
                    <button key={t} onClick={() => setTab(t)}
                        className={`px-5 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                            tab === t
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        }`}>
                        {t}
                    </button>
                ))}
            </div>

            {/* General Tab */}
            {tab === 'General' && (
                <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <h2 className="font-semibold text-gray-800 mb-2">General information</h2>
                    <Field label="University name" value={general.university_name}
                        onChange={(v:string) => setGeneral({...general, university_name: v})}
                        placeholder="e.g. Strathmore University"/>
                    <Field label="University motto" value={general.university_motto}
                        onChange={(v:string) => setGeneral({...general, university_motto: v})}
                        placeholder="e.g. Integrity · Commitment · Excellence"/>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Country" value={general.country}
                            onChange={(v:string) => setGeneral({...general, country: v})}
                            placeholder="Kenya"/>
                        <Field label="Academic year" value={general.academic_year}
                            onChange={(v:string) => setGeneral({...general, academic_year: v})}
                            placeholder="2025/2026"/>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Select label="Timezone" value={general.timezone}
                            onChange={(v:string) => setGeneral({...general, timezone: v})}
                            options={[
                                ['Africa/Nairobi','Africa/Nairobi'],
                                ['Africa/Lagos','Africa/Lagos'],
                                ['Africa/Johannesburg','Africa/Johannesburg'],
                                ['Africa/Cairo','Africa/Cairo'],
                                ['Europe/London','Europe/London'],
                                ['America/New_York','America/New_York'],
                                ['Asia/Kolkata','Asia/Kolkata'],
                            ]}/>
                        <Select label="Currency" value={general.currency}
                            onChange={(v:string) => setGeneral({...general, currency: v})}
                            options={[['KES','KES — Kenyan Shilling'],['USD','USD — US Dollar'],['EUR','EUR — Euro'],['GBP','GBP — British Pound'],['NGN','NGN — Nigerian Naira'],['ZAR','ZAR — South African Rand']]}/>
                    </div>
                    <div className="pt-2">
                        <button onClick={saveGeneral}
                            className="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                            Save general settings
                        </button>
                    </div>
                </div>
            )}

            {/* Branding Tab */}
            {tab === 'Branding' && (
                <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <h2 className="font-semibold text-gray-800 mb-2">Branding & appearance</h2>
                    <div className="grid grid-cols-2 gap-6">
                        {([['primary_color','Primary colour'],['secondary_color','Secondary colour']] as const).map(([key, label]) => (
                            <div key={key}>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{label}</label>
                                <div className="flex items-center gap-3">
                                    <input type="color" value={branding[key]}
                                        onChange={e => setBranding({...branding, [key]: e.target.value})}
                                        className="w-10 h-10 rounded cursor-pointer border border-gray-200"/>
                                    <input type="text" value={branding[key]}
                                        onChange={e => setBranding({...branding, [key]: e.target.value})}
                                        className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Logo upload */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">University logo</label>
                        <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
                            {logoFile ? (
                                <div>
                                    <p className="text-sm text-gray-600">📎 {logoFile.name}</p>
                                    <button onClick={() => setLogoFile(null)}
                                        className="text-xs text-red-500 mt-1 hover:underline">Remove</button>
                                </div>
                            ) : (
                                <div>
                                    <p className="text-sm text-gray-500">Drop your logo here or</p>
                                    <label className="mt-1 text-sm text-blue-600 cursor-pointer hover:underline">
                                        browse files
                                        <input type="file" accept="image/*" className="hidden"
                                            onChange={e => setLogoFile(e.target.files?.[0] || null)}/>
                                    </label>
                                    <p className="text-xs text-gray-400 mt-1">PNG, JPG up to 2MB</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Live preview */}
                    <div>
                        <p className="text-sm font-medium text-gray-700 mb-2">Live preview</p>
                        <div className="rounded-xl p-5 text-white" style={{ background: branding.primary_color }}>
                            <p className="font-bold text-lg">{general.university_name || tenant.name}</p>
                            <p className="text-sm opacity-75 mt-0.5">Timetabling System</p>
                            <div className="mt-3 inline-block px-4 py-1.5 rounded-lg text-sm font-medium"
                                style={{ background: branding.secondary_color }}>
                                View timetable
                            </div>
                        </div>
                    </div>

                    <div className="pt-2">
                        <button onClick={saveBranding}
                            className="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                            Save branding
                        </button>
                    </div>
                </div>
            )}

            {/* Domain Tab */}
            {tab === 'Domain' && (
                <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <h2 className="font-semibold text-gray-800 mb-2">Custom domain</h2>
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                        <p className="font-medium mb-1">How to set up your custom domain:</p>
                        <ol className="list-decimal list-inside space-y-1 text-blue-700">
                            <li>Enter your domain below (e.g. timetable.university.ac.ke)</li>
                            <li>Go to your DNS provider and add a CNAME record</li>
                            <li>Point it to: <code className="bg-blue-100 px-1 rounded">yoursaas.com</code></li>
                            <li>Wait 24-48 hours for DNS propagation</li>
                        </ol>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Custom domain</label>
                        <input value={domain} onChange={e => setDomain(e.target.value)}
                            placeholder="timetable.university.ac.ke"
                            className="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                        <p className="text-xs text-gray-500 mt-1">Leave empty to use {tenant.slug}.yoursaas.com</p>
                    </div>
                    {tenant.domain && (
                        <div className="bg-green-50 border border-green-200 rounded-lg p-3 text-sm">
                            <p className="text-green-700 font-medium">✅ Custom domain active: {tenant.domain}</p>
                            <p className="text-green-600 text-xs mt-0.5">Your system is accessible at https://{tenant.domain}</p>
                        </div>
                    )}
                    <div className="pt-2">
                        <button onClick={saveDomain}
                            className="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                            Save domain
                        </button>
                    </div>
                </div>
            )}

            {/* Academic Tab */}
            {tab === 'Academic' && (
                <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <h2 className="font-semibold text-gray-800 mb-2">Academic configuration</h2>
                    <div className="grid grid-cols-2 gap-4">
                        <Select label="Grading system" value={academic.grading_system}
                            onChange={(v:string) => setAcademic({...academic, grading_system: v})}
                            options={[['GPA','GPA (0.0 - 4.0)'],['percentage','Percentage (0 - 100)'],['letter','Letter grades (A-F)']]}/>
                        <Select label="Semester type" value={academic.semester_type}
                            onChange={(v:string) => setAcademic({...academic, semester_type: v})}
                            options={[['semester','Semester (2/year)'],['trimester','Trimester (3/year)'],['quarter','Quarter (4/year)']]}/>
                    </div>
                    <Field label="Maximum credit hours per semester"
                        value={academic.max_credit_hours} type="number"
                        onChange={(v:string) => setAcademic({...academic, max_credit_hours: parseInt(v)})}/>

                    <div className="border border-gray-200 rounded-lg p-4">
                        <p className="text-sm font-semibold text-gray-700 mb-3">Exam rules</p>
                        <div className="grid grid-cols-2 gap-4 mb-3">
                            <Field label="Minimum gap between exams (days)"
                                value={academic.exam_gap_days} type="number"
                                onChange={(v:string) => setAcademic({...academic, exam_gap_days: parseInt(v)})}/>
                            <Field label="Maximum exams per day"
                                value={academic.max_exams_per_day} type="number"
                                onChange={(v:string) => setAcademic({...academic, max_exams_per_day: parseInt(v)})}/>
                        </div>
                        <Toggle label="Allow weekend exams"
                            desc="Schedule exams on Saturdays and Sundays"
                            value={academic.allow_weekends}
                            onChange={(v:boolean) => setAcademic({...academic, allow_weekends: v})}/>
                        <Toggle label="Credit hour system"
                            desc="Enable credit-based unit weighting"
                            value={academic.credit_hour_system}
                            onChange={(v:boolean) => setAcademic({...academic, credit_hour_system: v})}/>
                    </div>

                    <div className="pt-2">
                        <button onClick={saveAcademic}
                            className="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                            Save academic settings
                        </button>
                    </div>
                </div>
            )}
        </div>
    )
}

export default Index
