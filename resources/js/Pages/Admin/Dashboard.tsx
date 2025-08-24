import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Dashboard({ statistics, currentSemester, systemInfo, recentEnrollments, error }) {
    // Add debug logging to see what props are being received
    console.log('Dashboard props:', {
        statistics,
        currentSemester,
        systemInfo,
        recentEnrollments,
        error
    });

    // Helper function to format growth rate display
    const formatGrowthRate = (rate, period) => {
        const isPositive = rate >= 0;
        const colorClass = isPositive ? 'text-green-600' : 'text-red-600';
        const sign = isPositive ? '+' : '';
        
        return (
            <div className="mt-4">
                <span className={`${colorClass} text-sm font-medium`}>
                    {sign}{rate}%
                </span>
                <span className="text-slate-500 text-sm ml-1">{period}</span>
            </div>
        );
    };

    // Handle error state
    if (error) {
        return (
            <AuthenticatedLayout>
                <Head title="Admin Dashboard" />
                <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div className="flex">
                                <div className="ml-3">
                                    <h3 className="text-sm font-medium text-red-800">
                                        Dashboard Error
                                    </h3>
                                    <div className="mt-2 text-sm text-red-700">
                                        <p>{error}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout>
            <Head title="Admin Dashboard" />
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
                {/* Header Section */}
                <div className="bg-white shadow-sm border-b border-slate-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-3xl font-bold text-slate-800">Admin Dashboard</h1>
                                <p className="text-slate-600 mt-1">
                                    Welcome to the admin dashboard! 
                                    {currentSemester && (
                                        <span className="ml-2 px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm">
                                            {currentSemester.name}
                                        </span>
                                    )}
                                </p>
                                {/* Debug information - remove this after debugging */}
                                <div className="mt-2 text-xs text-gray-500">
                                    Debug: Statistics = {statistics ? 'Present' : 'Missing'}
                                    {statistics && ` | Users: ${statistics.totalUsers?.count || 'undefined'}`}
                                </div>
                            </div>
                            <div className="flex items-center space-x-4">
                                <div className="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg">
                                    <span className="font-semibold">admin001</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Quick Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        {/* Total Users Card */}
                        <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-slate-600 text-sm font-medium">Total Users</p>
                                    <p className="text-2xl font-bold text-slate-800 mt-1">
                                        {statistics?.totalUsers?.count?.toLocaleString() || '0'}
                                    </p>
                                    {/* Debug info */}
                                    <p className="text-xs text-gray-400">
                                        Raw: {JSON.stringify(statistics?.totalUsers?.count)}
                                    </p>
                                </div>
                                <div className="bg-blue-100 p-3 rounded-full">
                                    <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-6.5L12 18l-3-3" />
                                    </svg>
                                </div>
                            </div>
                            {statistics?.totalUsers && formatGrowthRate(
                                statistics.totalUsers.growthRate, 
                                statistics.totalUsers.period
                            )}
                        </div>

                        {/* Active Enrollments Card */}
                        <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-slate-600 text-sm font-medium">Active Enrollments</p>
                                    <p className="text-2xl font-bold text-slate-800 mt-1">
                                        {statistics?.activeEnrollments?.count?.toLocaleString() || '0'}
                                    </p>
                                </div>
                                <div className="bg-emerald-100 p-3 rounded-full">
                                    <svg className="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                </div>
                            </div>
                            {statistics?.activeEnrollments && formatGrowthRate(
                                statistics.activeEnrollments.growthRate, 
                                statistics.activeEnrollments.period
                            )}
                        </div>

                        {/* Active Classes Card */}
                        <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-slate-600 text-sm font-medium">Active Classes</p>
                                    <p className="text-2xl font-bold text-slate-800 mt-1">
                                        {statistics?.activeClasses?.count?.toLocaleString() || '0'}
                                    </p>
                                </div>
                                <div className="bg-purple-100 p-3 rounded-full">
                                    <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                            </div>
                            {statistics?.activeClasses && formatGrowthRate(
                                statistics.activeClasses.growthRate, 
                                statistics.activeClasses.period
                            )}
                        </div>

                        {/* Exam Sessions Card */}
                        <div className="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 hover:shadow-xl transition-all duration-300">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-slate-600 text-sm font-medium">Exam Sessions</p>
                                    <p className="text-2xl font-bold text-slate-800 mt-1">
                                        {statistics?.examSessions?.count?.toLocaleString() || '0'}
                                    </p>
                                </div>
                                <div className="bg-amber-100 p-3 rounded-full">
                                    <svg className="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                </div>
                            </div>
                            {statistics?.examSessions && formatGrowthRate(
                                statistics.examSessions.growthRate, 
                                statistics.examSessions.period
                            )}
                        </div>
                    </div>

                    {/* Rest of your component remains the same... */}
                    {/* Main Content Grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Administration Section */}
                        <div className="lg:col-span-2">
                            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-2xl font-bold text-slate-800">Administration</h2>
                                    <div className="bg-slate-100 p-2 rounded-lg">
                                        <svg className="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <Link href="/users" className="group block p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl hover:from-blue-100 hover:to-blue-200 transition-all duration-300 border border-blue-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-blue-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-6.5L12 18l-3-3" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Users</h3>
                                                <p className="text-slate-600 text-sm">Manage system users</p>
                                            </div>
                                        </div>
                                    </Link>

                                    <Link href="/roles" className="group block p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl hover:from-purple-100 hover:to-purple-200 transition-all duration-300 border border-purple-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-purple-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Roles & Permissions</h3>
                                                <p className="text-slate-600 text-sm">Manage access control</p>
                                            </div>
                                        </div>
                                    </Link>

                                    <Link href="/settings" className="group block p-4 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl hover:from-emerald-100 hover:to-emerald-200 transition-all duration-300 border border-emerald-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-emerald-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Settings</h3>
                                                <p className="text-slate-600 text-sm">System configuration</p>
                                            </div>
                                        </div>
                                    </Link>

                                    <Link href="/schools" className="group block p-4 bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl hover:from-amber-100 hover:to-amber-200 transition-all duration-300 border border-amber-200">
                                        <div className="flex items-center space-x-3">
                                            <div className="bg-amber-500 p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-slate-800">Schools</h3>
                                                <p className="text-slate-600 text-sm">
                                                    Manage academic schools
                                                    {systemInfo?.totalSchools && (
                                                        <span className="ml-1 text-xs bg-amber-100 text-amber-800 px-1 rounded">
                                                            {systemInfo.totalSchools}
                                                        </span>
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                    </Link>
                                </div>
                            </div>                            
                        </div>                            
                        
                        {/* Quick Actions Sidebar */}
                        <div className="space-y-6">
                            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-xl font-bold text-slate-800">Quick Actions</h3>
                                    <div className="bg-green-100 p-2 rounded-lg">
                                        <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                    </div>
                                </div>
                                <div className="space-y-3">
                                    <Link href="/enroll" className="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200">
                                        <span className="text-blue-700 font-medium">Enroll in Units</span>
                                    </Link>
                                    <Link href="/my-enrollments" className="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200">
                                        <span className="text-blue-700 font-medium">Enrollments</span>
                                    </Link>
                                    <Link href="/my-timetable" className="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200">
                                        <span className="text-blue-700 font-medium">Class Timetable</span>
                                    </Link>
                                    <Link href="/my-exams" className="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors duration-200">
                                        <span className="text-blue-700 font-medium">Exam Timetable</span>
                                    </Link>
                                </div>
                            </div>

                            {/* System Information Card */}
                            {systemInfo && (
                                <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-xl font-bold text-slate-800">System Info</h3>
                                        <div className="bg-slate-100 p-2 rounded-lg">
                                            <svg className="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div className="space-y-3">
                                        <div className="flex justify-between items-center">
                                            <span className="text-slate-600">Total Schools</span>
                                            <span className="font-semibold text-slate-800">{systemInfo.totalSchools}</span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-slate-600">Total Semesters</span>
                                            <span className="font-semibold text-slate-800">{systemInfo.totalSemesters}</span>
                                        </div>
                                        {currentSemester && (
                                            <div className="pt-2 border-t border-slate-200">
                                                <div className="text-slate-600 text-sm mb-1">Current Semester</div>
                                                <div className="font-semibold text-slate-800">{currentSemester.name}</div>
                                                {currentSemester.start_date && currentSemester.end_date && (
                                                    <div className="text-xs text-slate-500 mt-1">
                                                        {new Date(currentSemester.start_date).toLocaleDateString()} - 
                                                        {new Date(currentSemester.end_date).toLocaleDateString()}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Recent Enrollments Card */}
                            {recentEnrollments && recentEnrollments.length > 0 && (
                                <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-xl font-bold text-slate-800">Recent Activity</h3>
                                        <div className="bg-blue-100 p-2 rounded-lg">
                                            <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        {recentEnrollments.slice(0, 5).map((enrollment, index) => (
                                            <div key={index} className="text-sm">
                                                <div className="text-slate-800 font-medium">
                                                    New enrollment in {enrollment.unit?.name || 'Unknown Unit'}
                                                </div>
                                                <div className="text-slate-500 text-xs">
                                                    {new Date(enrollment.created_at).toLocaleDateString()}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}