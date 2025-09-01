import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';

interface Settings {
    system_name: string;
    system_email: string;
    timezone: string;
    locale: string;
    per_page_default: number;
    session_timeout: number;
    maintenance_mode: boolean;
    current_semester: string;
    academic_year: string;
    registration_enabled: boolean;
    timetable_generation_enabled: boolean;
    email_notifications: boolean;
    sms_notifications: boolean;
    push_notifications: boolean;
    two_factor_auth: boolean;
    password_expiry_days: number;
    max_login_attempts: number;
}

const Settings = () => {
    const { settings } = usePage().props as { settings: Settings };
    
    const [formData, setFormData] = useState<Settings>(settings);
    const [isLoading, setIsLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        const { name, value, type } = e.target;
        
        setFormData(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? (e.target as HTMLInputElement).checked : 
                   type === 'number' ? parseInt(value) : value
        }));
        
        if (errors[name]) {
            setErrors(prev => ({ ...prev, [name]: '' }));
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        
        router.put('/admin/settings', formData, {
            onSuccess: () => {
                toast.success('Settings updated successfully!');
            },
            onError: (errors) => {
                setErrors(errors);
                if (errors.error) toast.error(errors.error);
            },
            onFinish: () => setIsLoading(false)
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="System Settings" />
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
                {/* Header */}
                <div className="bg-white rounded-2xl shadow-xl border p-8 mb-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-4xl font-bold text-slate-800 mb-2">System Settings</h1>
                            <p className="text-slate-600 text-lg">Configure system-wide settings and preferences</p>
                        </div>
                        <div className="flex items-center space-x-2 mt-4 sm:mt-0">
                            <div className="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                            <span className="text-sm text-slate-600">System Online</span>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* General Settings */}
                    <div className="bg-white rounded-2xl shadow-xl border">
                        <div className="p-6 border-b border-slate-200">
                            <h2 className="text-2xl font-bold text-slate-800 mb-2">General Settings</h2>
                            <p className="text-slate-600">Basic system configuration</p>
                        </div>
                        <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-2">System Name</label>
                                <input
                                    type="text"
                                    name="system_name"
                                    value={formData.system_name}
                                    onChange={handleInputChange}
                                    className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                {errors.system_name && <p className="mt-1 text-sm text-red-600">{errors.system_name}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-2">System Email</label>
                                <input
                                    type="email"
                                    name="system_email"
                                    value={formData.system_email}
                                    onChange={handleInputChange}
                                    className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                {errors.system_email && <p className="mt-1 text-sm text-red-600">{errors.system_email}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-2">Timezone</label>
                                <select
                                    name="timezone"
                                    value={formData.timezone}
                                    onChange={handleInputChange}
                                    className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="UTC">UTC</option>
                                    <option value="Africa/Nairobi">Africa/Nairobi</option>
                                    <option value="America/New_York">America/New_York</option>
                                    <option value="Europe/London">Europe/London</option>
                                    <option value="Asia/Tokyo">Asia/Tokyo</option>
                                </select>
                                {errors.timezone && <p className="mt-1 text-sm text-red-600">{errors.timezone}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-2">Default Items Per Page</label>
                                <input
                                    type="number"
                                    name="per_page_default"
                                    value={formData.per_page_default}
                                    onChange={handleInputChange}
                                    min="5"
                                    max="100"
                                    className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                {errors.per_page_default && <p className="mt-1 text-sm text-red-600">{errors.per_page_default}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-2">Session Timeout (minutes)</label>
                                <input
                                    type="number"
                                    name="session_timeout"
                                    value={formData.session_timeout}
                                    onChange={handleInputChange}
                                    min="60"
                                    max="1440"
                                    className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                {errors.session_timeout && <p className="mt-1 text-sm text-red-600">{errors.session_timeout}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Academic Settings */}
                    <div className="bg-white rounded-2xl shadow-xl border">
                        <div className="p-6 border-b border-slate-200">
                            <h2 className="text-2xl font-bold text-slate-800 mb-2">Academic Settings</h2>
                            <p className="text-slate-600">Configuration for academic operations</p>
                        </div>
                        <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-2">Current Semester</label>
                                <input
                                    type="text"
                                    name="current_semester"
                                    value={formData.current_semester}
                                    onChange={handleInputChange}
                                    className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                {errors.current_semester && <p className="mt-1 text-sm text-red-600">{errors.current_semester}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-2">Academic Year</label>
                                <input
                                    type="text"
                                    name="academic_year"
                                    value={formData.academic_year}
                                    onChange={handleInputChange}
                                    className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                {errors.academic_year && <p className="mt-1 text-sm text-red-600">{errors.academic_year}</p>}
                            </div>

                            <div className="flex items-center space-x-3">
                                <input
                                    type="checkbox"
                                    name="registration_enabled"
                                    checked={formData.registration_enabled}
                                    onChange={handleInputChange}
                                    className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                />
                                <label className="text-sm font-semibold text-slate-700">Enable Student Registration</label>
                            </div>

                            <div className="flex items-center space-x-3">
                                <input
                                    type="checkbox"
                                    name="timetable_generation_enabled"
                                    checked={formData.timetable_generation_enabled}
                                    onChange={handleInputChange}
                                    className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                />
                                <label className="text-sm font-semibold text-slate-700">Enable Timetable Generation</label>
                            </div>
                        </div>
                    </div>

                    {/* Notification Settings */}
                    <div className="bg-white rounded-2xl shadow-xl border">
                        <div className="p-6 border-b border-slate-200">
                            <h2 className="text-2xl font-bold text-slate-800 mb-2">Notification Settings</h2>
                            <p className="text-slate-600">Configure notification preferences</p>
                        </div>
                        <div className="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="flex items-center space-x-3">
                                <input
                                    type="checkbox"
                                    name="email_notifications"
                                    checked={formData.email_notifications}
                                    onChange={handleInputChange}
                                    className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                />
                                <label className="text-sm font-semibold text-slate-700">Email Notifications</label>
                            </div>

                            <div className="flex items-center space-x-3">
                                <input
                                    type="checkbox"
                                    name="sms_notifications"
                                    checked={formData.sms_notifications}
                                    onChange={handleInputChange}
                                    className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                />
                                <label className="text-sm font-semibold text-slate-700">SMS Notifications</label>
                            </div>

                            <div className="flex items-center space-x-3">
                                <input
                                    type="checkbox"
                                    name="push_notifications"
                                    checked={formData.push_notifications}
                                    onChange={handleInputChange}
                                    className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                />
                                <label className="text-sm font-semibold text-slate-700">Push Notifications</label>
                            </div>
                        </div>
                    </div>

                    {/* Security Settings */}
                    <div className="bg-white rounded-2xl shadow-xl border">
                        <div className="p-6 border-b border-slate-200">
                            <h2 className="text-2xl font-bold text-slate-800 mb-2">Security Settings</h2>
                            <p className="text-slate-600">Configure security and authentication settings</p>
                        </div>
                        <div className="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="flex items-center space-x-3">
                                <input
                                    type="checkbox"
                                    name="two_factor_auth"
                                    checked={formData.two_factor_auth}
                                    onChange={handleInputChange}
                                    className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                />
                                <label className="text-sm font-semibold text-slate-700">Enable Two-Factor Authentication</label>
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-2">Password Expiry (days)</label>
                                <input
                                    type="number"
                                    name="password_expiry_days"
                                    value={formData.password_expiry_days}
                                    onChange={handleInputChange}
                                    min="30"
                                    max="365"
                                    className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                {errors.password_expiry_days && <p className="mt-1 text-sm text-red-600">{errors.password_expiry_days}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-2">Max Login Attempts</label>
                                <input
                                    type="number"
                                    name="max_login_attempts"
                                    value={formData.max_login_attempts}
                                    onChange={handleInputChange}
                                    min="3"
                                    max="10"
                                    className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                {errors.max_login_attempts && <p className="mt-1 text-sm text-red-600">{errors.max_login_attempts}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Save Button */}
                    <div className="bg-white rounded-2xl shadow-xl border p-6">
                        <div className="flex justify-end space-x-4">
                            <button
                                type="button"
                                onClick={() => setFormData(settings)}
                                disabled={isLoading}
                                className="px-6 py-3 text-slate-700 bg-slate-100 hover:bg-slate-200 font-semibold rounded-xl transition-all duration-200 disabled:opacity-50"
                            >
                                Reset
                            </button>
                            <button
                                type="submit"
                                disabled={isLoading}
                                className="px-8 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-xl hover:from-blue-600 hover:to-blue-700 transform hover:scale-105 transition-all duration-200 disabled:opacity-50 disabled:transform-none"
                            >
                                {isLoading ? (
                                    <div className="flex items-center">
                                        <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                                        Saving...
                                    </div>
                                ) : (
                                    'Save Settings'
                                )}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
};

export default Settings;