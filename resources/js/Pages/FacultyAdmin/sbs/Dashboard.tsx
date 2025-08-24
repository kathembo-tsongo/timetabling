import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface DashboardProps {
    schoolCode: string;
    schoolName: string;
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
        };
    };
}

export default function Dashboard({ schoolCode, schoolName, auth }: DashboardProps) {
    // SBS-specific statistics (higher numbers for business school)
    const statistics = {
        totalStudents: { count: 2100, growthRate: 10.2 },
        totalLecturers: { count: 52, growthRate: 8.7 },
        totalUnits: { count: 95, growthRate: 6.1 },
        activeEnrollments: { count: 2850, growthRate: 12.4 },
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                            {schoolName} Dashboard
                        </h2>
                        <div className="flex items-center gap-2 mt-1">
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {schoolCode}
                            </span>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={`${schoolCode} Dashboard`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Quick Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        {/* Total Students Card - Green theme for SBS */}
                        <div className="bg-white shadow-sm rounded-lg border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                            <div className="px-6 py-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-slate-600 text-sm font-medium">Total Students</p>
                                        <p className="text-2xl font-bold text-slate-800 mt-1">
                                            {statistics.totalStudents.count.toLocaleString()}
                                        </p>
                                    </div>
                                    <div className="bg-green-100 p-3 rounded-full">
                                        <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 14l9-5-9-5-9 5 9 5z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                        </svg>
                                    </div>
                                </div>
                                <div className="mt-2 flex items-center gap-1">
                                    <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span className="text-green-600 text-sm font-medium">
                                        +{statistics.totalStudents.growthRate}%
                                    </span>
                                    <span className="text-slate-500 text-sm">vs last semester</span>
                                </div>
                            </div>
                        </div>

                        {/* Total Lecturers Card */}
                        <div className="bg-white shadow-sm rounded-lg border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                            <div className="px-6 py-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-slate-600 text-sm font-medium">Total Lecturers</p>
                                        <p className="text-2xl font-bold text-slate-800 mt-1">
                                            {statistics.totalLecturers.count.toLocaleString()}
                                        </p>
                                    </div>
                                    <div className="bg-emerald-100 p-3 rounded-full">
                                        <svg className="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div className="mt-2 flex items-center gap-1">
                                    <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span className="text-green-600 text-sm font-medium">
                                        +{statistics.totalLecturers.growthRate}%
                                    </span>
                                    <span className="text-slate-500 text-sm">vs last year</span>
                                </div>
                            </div>
                        </div>

                        {/* Total Units Card */}
                        <div className="bg-white shadow-sm rounded-lg border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                            <div className="px-6 py-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-slate-600 text-sm font-medium">Total Units</p>
                                        <p className="text-2xl font-bold text-slate-800 mt-1">
                                            {statistics.totalUnits.count.toLocaleString()}
                                        </p>
                                    </div>
                                    <div className="bg-purple-100 p-3 rounded-full">
                                        <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                    </div>
                                </div>
                                <div className="mt-2 flex items-center gap-1">
                                    <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span className="text-green-600 text-sm font-medium">
                                        +{statistics.totalUnits.growthRate}%
                                    </span>
                                    <span className="text-slate-500 text-sm">vs last semester</span>
                                </div>
                            </div>
                        </div>

                        {/* Active Enrollments Card */}
                        <div className="bg-white shadow-sm rounded-lg border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                            <div className="px-6 py-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-slate-600 text-sm font-medium">Active Enrollments</p>
                                        <p className="text-2xl font-bold text-slate-800 mt-1">
                                            {statistics.activeEnrollments.count.toLocaleString()}
                                        </p>
                                    </div>
                                    <div className="bg-amber-100 p-3 rounded-full">
                                        <svg className="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div className="mt-2 flex items-center gap-1">
                                    <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span className="text-green-600 text-sm font-medium">
                                        +{statistics.activeEnrollments.growthRate}%
                                    </span>
                                    <span className="text-slate-500 text-sm">vs last semester</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Management Links */}
                    <div className="bg-white shadow-sm rounded-lg border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h3 className="text-lg font-semibold text-gray-900">
                                {schoolCode} Management
                            </h3>
                        </div>
                        <div className="px-6 py-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {/* Students - Green theme for SBS */}
                                <Link
                                    href={route('faculty.students.sbs')}
                                    className="block p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-xl hover:from-green-100 hover:to-green-200 transition-all duration-300 border border-green-200"
                                >
                                    <div className="flex items-center space-x-3">
                                        <div className="bg-green-500 p-2 rounded-lg">
                                            <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 14l9-5-9-5-9 5 9 5z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-slate-800">Students</h3>
                                            <p className="text-slate-600 text-sm">Manage {schoolCode} students</p>
                                        </div>
                                    </div>
                                </Link>

                                {/* Lecturers */}
                                <Link
                                    href={route('faculty.lecturers.sbs')}
                                    className="block p-4 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl hover:from-emerald-100 hover:to-emerald-200 transition-all duration-300 border border-emerald-200"
                                >
                                    <div className="flex items-center space-x-3">
                                        <div className="bg-emerald-500 p-2 rounded-lg">
                                            <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-slate-800">Lecturers</h3>
                                            <p className="text-slate-600 text-sm">Manage {schoolCode} faculty</p>
                                        </div>
                                    </div>
                                </Link>

                                {/* Units */}
                                <Link
                                    href={route('faculty.units.sbs')}
                                    className="block p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl hover:from-purple-100 hover:to-purple-200 transition-all duration-300 border border-purple-200"
                                >
                                    <div className="flex items-center space-x-3">
                                        <div className="bg-purple-500 p-2 rounded-lg">
                                            <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-slate-800">Units</h3>
                                            <p className="text-slate-600 text-sm">Manage {schoolCode} courses</p>
                                        </div>
                                    </div>
                                </Link>

                                {/* Enrollments */}
                                <Link
                                    href={route('faculty.enrollments.sbs')}
                                    className="block p-4 bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl hover:from-amber-100 hover:to-amber-200 transition-all duration-300 border border-amber-200"
                                >
                                    <div className="flex items-center space-x-3">
                                        <div className="bg-amber-500 p-2 rounded-lg">
                                            <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-slate-800">Enrollments</h3>
                                            <p className="text-slate-600 text-sm">Manage {schoolCode} enrollments</p>
                                        </div>
                                    </div>
                                </Link>

                                {/* Timetables */}
                                <Link
                                    href={route('faculty.timetables.sbs')}
                                    className="block p-4 bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl hover:from-indigo-100 hover:to-indigo-200 transition-all duration-300 border border-indigo-200"
                                >
                                    <div className="flex items-center space-x-3">
                                        <div className="bg-indigo-500 p-2 rounded-lg">
                                            <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-slate-800">Timetables</h3>
                                            <p className="text-slate-600 text-sm">Manage {schoolCode} schedules</p>
                                        </div>
                                    </div>
                                </Link>

                                {/* Reports */}
                                <Link
                                    href={route('faculty.reports.sbs')}
                                    className="block p-4 bg-gradient-to-br from-rose-50 to-rose-100 rounded-xl hover:from-rose-100 hover:to-rose-200 transition-all duration-300 border border-rose-200"
                                >
                                    <div className="flex items-center space-x-3">
                                        <div className="bg-rose-500 p-2 rounded-lg">
                                            <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-slate-800">Reports</h3>
                                            <p className="text-slate-600 text-sm">View {schoolCode} analytics</p>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}