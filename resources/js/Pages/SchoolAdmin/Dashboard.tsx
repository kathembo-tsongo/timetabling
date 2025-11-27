import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Users, BookOpen, GraduationCap, TrendingUp, Calendar, Award, AlertCircle, Clock, RefreshCw, Activity, BarChart3 } from 'lucide-react';

export default function SchoolAdminDashboard({ 
  schoolName,
  schoolCode,
  currentSemester,
  stats,
  programs,
  recentActivities,
  upcomingEvents,
  pendingApprovals,
  error
}) {
  const [timeRange, setTimeRange] = useState('week');

  // ✅ School-specific themes - ALL 7 SCHOOLS WITH CORRECT BRANDING
  const schoolThemes = {
    SCES: {
      gradient: 'from-indigo-50 via-white to-cyan-50',
      primary: 'blue',
      iconColor: 'text-blue-600',
      titleGradient: 'from-slate-800 via-blue-800 to-indigo-800',
      cardBg: 'bg-blue-50',
      cardBorder: 'border-blue-200',
      name: 'School of Computing & Engineering Sciences'
    },
    SBS: {
      gradient: 'from-amber-50 via-white to-orange-50',
      primary: 'amber',
      iconColor: 'text-amber-600',
      titleGradient: 'from-slate-800 via-amber-700 to-orange-800',
      cardBg: 'bg-amber-50',
      cardBorder: 'border-amber-200',
      name: 'Strathmore Business School'
    },
    SLS: {
      gradient: 'from-slate-50 via-white to-indigo-50',
      primary: 'indigo',
      iconColor: 'text-indigo-700',
      titleGradient: 'from-slate-800 via-indigo-800 to-blue-900',
      cardBg: 'bg-indigo-50',
      cardBorder: 'border-indigo-200',
      name: 'School of Law Studies'
    },
    SHSS: {
      gradient: 'from-purple-50 via-white to-violet-50',
      primary: 'purple',
      iconColor: 'text-purple-600',
      titleGradient: 'from-slate-800 via-purple-700 to-violet-800',
      cardBg: 'bg-purple-50',
      cardBorder: 'border-purple-200',
      name: 'School of Humanities & Social Sciences'
    },
    SMS: {
      gradient: 'from-red-50 via-white to-rose-50',
      primary: 'red',
      iconColor: 'text-red-600',
      titleGradient: 'from-slate-800 via-red-700 to-rose-800',
      cardBg: 'bg-red-50',
      cardBorder: 'border-red-200',
      name: 'Strathmore Medical School'
    },
    STH: {
      gradient: 'from-teal-50 via-white to-emerald-50',
      primary: 'teal',
      iconColor: 'text-teal-600',
      titleGradient: 'from-slate-800 via-teal-700 to-emerald-800',
      cardBg: 'bg-teal-50',
      cardBorder: 'border-teal-200',
      name: 'School of Tourism & Hospitality'
    },
    SI: {
      gradient: 'from-orange-50 via-white to-amber-50',
      primary: 'orange',
      iconColor: 'text-orange-600',
      titleGradient: 'from-slate-800 via-orange-700 to-amber-800',
      cardBg: 'bg-orange-50',
      cardBorder: 'border-orange-200',
      name: 'Strathmore Institute'
    }
  };

  const theme = schoolThemes[schoolCode] || schoolThemes.SCES;

  if (error) {
    return (
      <AuthenticatedLayout>
        <Head title={`${schoolCode} Dashboard - Error`} />
        <div className={`min-h-screen bg-gradient-to-br ${theme.gradient} flex items-center justify-center p-6`}>
          <div className="bg-white rounded-xl shadow-lg p-8 max-w-md">
            <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
            <h2 className="text-xl font-bold text-gray-900 mb-2 text-center">Error Loading Dashboard</h2>
            <p className="text-gray-600 text-center mb-4">{error}</p>
            <button
              onClick={() => window.location.reload()}
              className="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
            >
              Retry
            </button>
          </div>
        </div>
      </AuthenticatedLayout>
    );
  }

  // Safely access nested data
  const totalStudents = stats?.totalStudents || 0;
  const studentsTrend = stats?.studentsTrend || '0%';
  const activePrograms = stats?.activePrograms || 0;
  const programsTrend = stats?.programsTrend || '0%';
  const totalUnits = stats?.totalUnits || 0;
  const unitsTrend = stats?.unitsTrend || '0%';
  const totalLecturers = stats?.totalLecturers || 0;
  const lecturersTrend = stats?.lecturersTrend || '0%';

  const programsList = programs || [];
  const activitiesList = recentActivities || [];
  const eventsList = upcomingEvents || [];
  const approvalsList = pendingApprovals || [];

  return (
    <AuthenticatedLayout>
      <Head title={`${schoolCode} - School Admin Dashboard`} />

      <div className={`min-h-screen bg-gradient-to-br ${theme.gradient} py-8`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div className="flex items-center mb-2">
                    <BarChart3 className={`w-8 h-8 ${theme.iconColor} mr-3`} />
                    <h1 className={`text-4xl font-bold bg-gradient-to-r ${theme.titleGradient} bg-clip-text text-transparent`}>
                      School Admin Dashboard
                    </h1>
                  </div>
                  <h2 className="text-2xl font-semibold text-slate-700 mb-2">
                    {schoolName || theme.name || 'School Dashboard'}
                  </h2>
                  <p className="text-slate-600 text-lg">
                    {schoolCode} School Management Portal
                  </p>
                  {/* {currentSemester && (
                    <div className={`inline-flex items-center px-4 py-2 ${theme.cardBg} border-2 ${theme.cardBorder} rounded-lg mt-3`}>
                      <Calendar className={`w-5 h-5 ${theme.iconColor} mr-2`} />
                      <span className={`text-sm font-bold ${theme.iconColor}`}>
                        Current Semester: {currentSemester.name}
                      </span>
                    </div>
                  )} */}
                </div>
                <button
                  onClick={() => window.location.reload()}
                  className="mt-4 sm:mt-0 inline-flex items-center px-6 py-3 bg-white border-2 border-gray-200 rounded-xl shadow-md hover:shadow-xl hover:border-gray-300 transition-all duration-300 group"
                >
                  <RefreshCw className="w-5 h-5 mr-2 text-gray-600 group-hover:rotate-180 transition-transform duration-500" />
                  <span className="font-semibold text-gray-700">Refresh</span>
                </button>
              </div>
            </div>
          </div>

          {/* Time Range Filter */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-8">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <Activity className={`w-5 h-5 ${theme.iconColor} mr-2`} />
                <span className="text-sm font-semibold text-gray-700">Time Period:</span>
              </div>
              <div className="flex gap-2">
                {['week', 'month', 'semester'].map((range) => (
                  <button
                    key={range}
                    onClick={() => setTimeRange(range)}
                    className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                      timeRange === range
                        ? `bg-${theme.primary}-600 text-white shadow-md`
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    {range.charAt(0).toUpperCase() + range.slice(1)}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Key Metrics Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <MetricCard
              title="Total Students"
              value={totalStudents}
              icon={Users}
              color="bg-blue-500"
              trend={studentsTrend}
              theme={theme}
            />
            <MetricCard
              title="Active Programs"
              value={activePrograms}
              icon={GraduationCap}
              color="bg-purple-500"
              trend={programsTrend}
              theme={theme}
            />
            <MetricCard
              title="Total Units"
              value={totalUnits}
              icon={BookOpen}
              color="bg-green-500"
              trend={unitsTrend}
              theme={theme}
            />
            <MetricCard
              title="Lecturers"
              value={totalLecturers}
              icon={Award}
              color="bg-orange-500"
              trend={lecturersTrend}
              theme={theme}
            />
          </div>

          {/* Programs Performance */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div className="lg:col-span-2 bg-white/95 backdrop-blur-sm rounded-2xl shadow-xl border-2 border-slate-200 p-6">
              <div className="flex items-center justify-between mb-6">
                <div className="flex items-center">
                  <TrendingUp className={`w-6 h-6 ${theme.iconColor} mr-2`} />
                  <h2 className="text-xl font-bold text-gray-900">Program Enrollment</h2>
                </div>
                <span className={`px-3 py-1 ${theme.cardBg} border ${theme.cardBorder} rounded-lg text-xs font-bold ${theme.iconColor}`}>
                  {programsList.length} Programs
                </span>
              </div>
              
              {programsList.length > 0 ? (
                <div className="space-y-6">
                  {programsList.map((program, idx) => (
                    <div key={program.id || idx} className={`p-4 rounded-xl border-2 ${theme.cardBorder} bg-white hover:shadow-md transition-all`}>
                      <div className="flex items-center justify-between mb-3">
                        <div className="flex-1">
                          <h3 className="font-bold text-lg text-gray-900">{program.name || program.code}</h3>
                          <p className="text-sm text-gray-600 mt-1">
                            <span className="font-semibold">{program.enrolledStudents || 0}</span> students • 
                            <span className="font-semibold ml-1">{program.totalUnits || 0}</span> units
                          </p>
                        </div>
                        {program.growth !== undefined && program.growth !== 0 && (
                          <div className={`flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-bold ${
                            program.growth >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                          }`}>
                            <TrendingUp className={`w-4 h-4 ${program.growth < 0 ? 'rotate-180' : ''}`} />
                            {Math.abs(program.growth)}%
                          </div>
                        )}
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-3">
                        <div
                          className={`h-3 rounded-full transition-all duration-500 ${program.colorClass || 'bg-blue-500'}`}
                          style={{ width: `${Math.min((program.enrolledStudents / (totalStudents || 1)) * 100, 100)}%` }}
                        />
                      </div>
                      <div className="flex items-center justify-between mt-3 text-xs text-gray-500">
                        <span className="font-medium">{program.degree || 'Bachelor'} • {program.duration || '4'} years</span>
                        <span className="font-medium">Capacity: {program.capacity || 'N/A'}</span>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12 text-gray-500">
                  <BookOpen className="w-16 h-16 mx-auto mb-3 opacity-50" />
                  <p className="text-lg font-medium">No programs data available</p>
                </div>
              )}
            </div>

            {/* Pending Actions */}
            <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-xl border-2 border-slate-200 p-6">
              <div className="flex items-center justify-between mb-6">
                <div className="flex items-center">
                  <AlertCircle className="w-6 h-6 text-orange-500 mr-2" />
                  <h2 className="text-xl font-bold text-gray-900">Pending Actions</h2>
                </div>
                {approvalsList.length > 0 && (
                  <span className="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-bold">
                    {approvalsList.length}
                  </span>
                )}
              </div>
              
              {approvalsList.length > 0 ? (
                <div className="space-y-4">
                  {approvalsList.map((item, idx) => (
                    <div key={item.id || idx} className="p-4 bg-orange-50 rounded-xl border-2 border-orange-200 hover:shadow-md transition-all">
                      <h3 className="font-bold text-gray-900 mb-1">{item.title}</h3>
                      <p className="text-sm text-gray-600 mb-3">{item.description}</p>
                      <div className="flex items-center justify-between">
                        <span className="text-xs text-gray-500 font-medium">{item.time || item.created_at}</span>
                        <button className="text-sm text-blue-600 hover:text-blue-700 font-bold hover:underline">
                          Review
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12 text-gray-500">
                  <Clock className="w-16 h-16 mx-auto mb-3 opacity-50" />
                  <p className="text-lg font-medium">No pending actions</p>
                </div>
              )}
            </div>
          </div>

          {/* Recent Activities & Upcoming Events */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Recent Activities */}
            <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-xl border-2 border-slate-200 p-6">
              <div className="flex items-center mb-6">
                <Activity className={`w-6 h-6 ${theme.iconColor} mr-2`} />
                <h2 className="text-xl font-bold text-gray-900">Recent Activities</h2>
              </div>
              
              {activitiesList.length > 0 ? (
                <div className="space-y-3">
                  {activitiesList.map((activity, idx) => {
                    const IconComponent = getActivityIcon(activity.type);
                    return (
                      <div key={activity.id || idx} className="flex items-start gap-4 p-4 rounded-xl hover:bg-gray-50 border border-gray-200 transition-all">
                        <div className={`p-2.5 rounded-lg ${getActivityColor(activity.type)}`}>
                          <IconComponent className="w-5 h-5 text-white" />
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-semibold text-gray-900">{activity.message || activity.description}</p>
                          <p className="text-xs text-gray-500 mt-1 font-medium">{activity.time || activity.created_at}</p>
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <div className="text-center py-12 text-gray-500">
                  <Clock className="w-16 h-16 mx-auto mb-3 opacity-50" />
                  <p className="text-lg font-medium">No recent activities</p>
                </div>
              )}
            </div>

            {/* Upcoming Events */}
            <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-xl border-2 border-slate-200 p-6">
              <div className="flex items-center mb-6">
                <Calendar className={`w-6 h-6 ${theme.iconColor} mr-2`} />
                <h2 className="text-xl font-bold text-gray-900">Upcoming Events</h2>
              </div>
              
              {eventsList.length > 0 ? (
                <div className="space-y-3">
                  {eventsList.map((event, idx) => (
                    <div key={event.id || idx} className="flex items-center gap-4 p-4 rounded-xl border-2 border-gray-200 hover:border-blue-300 hover:shadow-md transition-all">
                      <div className="flex-shrink-0">
                        <Calendar className="w-6 h-6 text-blue-600" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <h3 className="font-bold text-gray-900">{event.title}</h3>
                        <p className="text-sm text-gray-600 mt-0.5">{event.date}</p>
                      </div>
                      <div>
                        <span className={`px-3 py-1 rounded-full text-xs font-bold ${getEventStatusColor(event.status)}`}>
                          {event.status}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12 text-gray-500">
                  <Calendar className="w-16 h-16 mx-auto mb-3 opacity-50" />
                  <p className="text-lg font-medium">No upcoming events</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

function MetricCard({ title, value, icon: Icon, color, trend, theme }) {
  return (
    <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-xl border-2 border-slate-200 p-6 hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
      <div className="flex items-center justify-between mb-4">
        <div className={`p-3 rounded-xl ${color} shadow-lg`}>
          <Icon className="w-7 h-7 text-white" />
        </div>
        {trend && (
          <span className={`text-sm font-bold px-3 py-1 rounded-lg ${
            trend.startsWith('+') ? 'bg-green-100 text-green-700' : trend.startsWith('-') ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'
          }`}>
            {trend}
          </span>
        )}
      </div>
      <h3 className="text-gray-600 text-sm font-semibold mb-2">{title}</h3>
      <p className="text-4xl font-bold text-gray-900">{typeof value === 'number' ? value.toLocaleString() : value}</p>
    </div>
  );
}

function getActivityIcon(type) {
  const icons = {
    enrollment: Users,
    unit: BookOpen,
    approval: AlertCircle,
    exam: Calendar,
    lecturer: Award,
    program: GraduationCap
  };
  return icons[type] || Clock;
}

function getActivityColor(type) {
  const colors = {
    enrollment: 'bg-blue-500',
    unit: 'bg-purple-500',
    approval: 'bg-orange-500',
    exam: 'bg-green-500',
    lecturer: 'bg-pink-500',
    program: 'bg-indigo-500'
  };
  return colors[type] || 'bg-gray-500';
}

function getEventStatusColor(status) {
  const colors = {
    urgent: 'bg-red-100 text-red-700',
    upcoming: 'bg-yellow-100 text-yellow-700',
    scheduled: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700'
  };
  return colors[status] || 'bg-gray-100 text-gray-700';
}