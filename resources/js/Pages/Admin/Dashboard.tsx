import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
  Users,
  BookOpen,
  GraduationCap,
  Clock,
  TrendingUp,
  TrendingDown,
  Calendar,
  Activity,
  School,
  BarChart3,
  Building,
  MapPin
} from 'lucide-react';

const AdminDashboard = ({ 
  statistics = {
    totalUsers: { count: 0, growthRate: 0, period: 'from last month' },
    activeEnrollments: { count: 0, growthRate: 0, period: 'from last week' },
    activeClasses: { count: 0, growthRate: 0, period: 'from last month' },
    examSessions: { count: 0, growthRate: 0, period: 'from last week' }
  },
  currentSemester = null,
  systemInfo = { totalSchools: 0, totalSemesters: 0, totalBuildings: 0, totalClassrooms: 0, totalPrograms: 0 },
  roleStats = { admins: 0, students: 0, lecturers: 0, faculty_admins: 0, exam_office: 0 },
  recentEnrollments = [],
  weeklyActivity = [],
  enrollmentTrends = []
}) => {
  const [selectedTimeFrame, setSelectedTimeFrame] = useState('week');

  // Use real data from database or empty arrays as fallback
  const chartData = selectedTimeFrame === 'week' 
    ? (weeklyActivity.length > 0 ? weeklyActivity : [])
    : (enrollmentTrends.length > 0 ? enrollmentTrends : []);

  const roleDistribution = [
    { name: 'Students', value: roleStats?.students || 0, color: '#3B82F6' },
    { name: 'Lecturers', value: roleStats?.lecturers || 0, color: '#10B981' },
    { name: 'Admins', value: roleStats?.admins || 0, color: '#8B5CF6' },
    { name: 'Faculty Admins', value: roleStats?.faculty_admins || 0, color: '#F59E0B' },
    { name: 'Exam Office', value: roleStats?.exam_office || 0, color: '#EF4444' }
  ].filter(role => role.value > 0); // Only show roles that have users

  const totalUsers = roleDistribution.reduce((sum, role) => sum + role.value, 0);

  // Enhanced Stat Card Component with gradient backgrounds
  const StatCard = ({ title, value, change, icon: Icon, color, trend, bgGradient }) => (
    <div className={`${bgGradient} rounded-xl shadow-lg border border-white/20 p-4 hover:shadow-xl transition-all duration-300 text-white`}>
      <div className="flex items-center justify-between mb-3">
        <div className="p-2 rounded-lg bg-white/20 backdrop-blur-sm">
          <Icon className="w-5 h-5 text-white" />
        </div>
        <div className="flex items-center space-x-1">
          {trend === 'up' ? (
            <TrendingUp className="w-3 h-3 text-white/80" />
          ) : (
            <TrendingDown className="w-3 h-3 text-white/80" />
          )}
          <span className="text-xs font-medium text-white/90">
            {change > 0 ? '+' : ''}{change}%
          </span>
        </div>
      </div>
      <div>
        <h3 className="text-2xl font-bold text-white">{value.toLocaleString()}</h3>
        <p className="text-sm text-white/80 font-medium">{title}</p>
      </div>
    </div>
  );

  // Enhanced Line Chart Component
  const LineChart = ({ data, height = 120 }) => {
    if (!data || data.length === 0) {
      return (
        <div className="flex items-center justify-center h-32 text-slate-500">
          <div className="text-center">
            <BarChart3 className="w-8 h-8 mx-auto mb-2 opacity-50" />
            <p className="text-sm">No data available</p>
          </div>
        </div>
      );
    }

    const maxValue = Math.max(...data.map(d => d.enrollments || 0));
    if (maxValue === 0) {
      return (
        <div className="flex items-center justify-center h-32 text-slate-500">
          <p className="text-sm">No enrollment data</p>
        </div>
      );
    }

    const width = 300;
    const padding = 20;
    
    const points = data.map((item, index) => {
      const x = (width - 2 * padding) * (index / (data.length - 1)) + padding;
      const y = height - padding - ((item.enrollments || 0) / maxValue) * (height - 2 * padding);
      return `${x},${y}`;
    }).join(' ');

    return (
      <div className="w-full">
        <svg width={width} height={height} className="w-full">
          {/* Grid lines */}
          {[0.25, 0.5, 0.75].map(ratio => (
            <line
              key={ratio}
              x1={padding}
              x2={width - padding}
              y1={height - padding - ratio * (height - 2 * padding)}
              y2={height - padding - ratio * (height - 2 * padding)}
              stroke="#e2e8f0"
              strokeDasharray="2 2"
            />
          ))}
          
          {/* Area under curve */}
          <polygon
            fill="url(#gradient)"
            points={`${padding},${height - padding} ${points} ${width - padding},${height - padding}`}
          />
          
          {/* Line */}
          <polyline
            fill="none"
            stroke="#3B82F6"
            strokeWidth="2"
            points={points}
          />
          
          {/* Points */}
          {data.map((item, index) => {
            const x = (width - 2 * padding) * (index / (data.length - 1)) + padding;
            const y = height - padding - ((item.enrollments || 0) / maxValue) * (height - 2 * padding);
            return (
              <circle
                key={index}
                cx={x}
                cy={y}
                r="3"
                fill="#3B82F6"
                className="hover:r-4 transition-all duration-200"
              />
            );
          })}
          
          {/* X-axis labels */}
          {data.map((item, index) => {
            const x = (width - 2 * padding) * (index / (data.length - 1)) + padding;
            return (
              <text
                key={index}
                x={x}
                y={height - 5}
                textAnchor="middle"
                className="text-xs fill-slate-500"
              >
                {item.name || item.day}
              </text>
            );
          })}
          
          <defs>
            <linearGradient id="gradient" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stopColor="#3B82F6" stopOpacity="0.3"/>
              <stop offset="100%" stopColor="#3B82F6" stopOpacity="0.0"/>
            </linearGradient>
          </defs>
        </svg>
      </div>
    );
  };

  // Enhanced Pie Chart Component
  const SimplePieChart = ({ data }) => {
    if (!data || data.length === 0 || totalUsers === 0) {
      return (
        <div className="flex items-center justify-center w-32 h-32 text-slate-500">
          <div className="text-center">
            <Users className="w-8 h-8 mx-auto mb-2 opacity-50" />
            <p className="text-xs">No user data</p>
          </div>
        </div>
      );
    }

    const size = 120;
    const center = size / 2;
    const radius = 40;
    
    let currentAngle = 0;
    const total = data.reduce((sum, item) => sum + item.value, 0);
    
    return (
      <svg width={size} height={size}>
        {data.map((item, index) => {
          if (item.value === 0) return null;
          
          const angle = (item.value / total) * 2 * Math.PI;
          const startAngle = currentAngle;
          const endAngle = currentAngle + angle;
          
          const x1 = center + radius * Math.cos(startAngle);
          const y1 = center + radius * Math.sin(startAngle);
          const x2 = center + radius * Math.cos(endAngle);
          const y2 = center + radius * Math.sin(endAngle);
          
          const largeArcFlag = angle > Math.PI ? 1 : 0;
          
          const pathData = [
            `M ${center} ${center}`,
            `L ${x1} ${y1}`,
            `A ${radius} ${radius} 0 ${largeArcFlag} 1 ${x2} ${y2}`,
            'Z'
          ].join(' ');
          
          currentAngle += angle;
          
          return (
            <path
              key={index}
              d={pathData}
              fill={item.color}
              className="hover:opacity-80 transition-opacity duration-200"
            />
          );
        })}
        
        {/* Center circle with total */}
        <circle cx={center} cy={center} r={20} fill="white" stroke="#e2e8f0" strokeWidth="1"/>
        <text x={center} y={center + 5} textAnchor="middle" className="text-xs font-bold fill-slate-700">
          {total}
        </text>
      </svg>
    );
  };

  return (
    <AuthenticatedLayout>
      <Head title="Admin Dashboard" />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 p-4 overflow-auto">
        <div className="max-w-7xl mx-auto space-y-6">
          
          {/* Enhanced Header with gradients */}
          <div className="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 rounded-3xl shadow-2xl border border-white/20 p-6 text-white">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-3xl font-bold text-white mb-2">
                  System Dashboard
                </h1>
                <p className="text-blue-100 text-sm">Real-time overview of your timetabling system</p>
              </div>
              <div className="flex items-center space-x-6 text-sm">
                <div className="flex items-center space-x-2 bg-white/20 rounded-lg px-3 py-2">
                  <School className="w-4 h-4 text-white" />
                  <span className="text-white font-medium">{systemInfo?.totalSchools || 0} Schools</span>
                </div>
                <div className="flex items-center space-x-2 bg-white/20 rounded-lg px-3 py-2">
                  <Building className="w-4 h-4 text-white" />
                  <span className="text-white font-medium">{systemInfo?.totalBuildings || 0} Buildings</span>
                </div>
                <div className="flex items-center space-x-2 bg-white/20 rounded-lg px-3 py-2">
                  <Calendar className="w-4 h-4 text-white" />
                  <span className="font-bold text-white">{currentSemester?.name || 'No Active Semester'}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Statistics Cards - Enhanced with gradients */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard
              title="Total Users"
              value={statistics?.totalUsers?.count || 0}
              change={statistics?.totalUsers?.growthRate || 0}
              icon={Users}
              color="bg-blue-500 text-blue-500"
              bgGradient="bg-gradient-to-br from-blue-500 to-blue-700"
              trend={statistics?.totalUsers?.growthRate >= 0 ? 'up' : 'down'}
            />
            <StatCard
              title="Active Enrollments"
              value={statistics?.activeEnrollments?.count || 0}
              change={statistics?.activeEnrollments?.growthRate || 0}
              icon={BookOpen}
              color="bg-green-500 text-green-500"
              bgGradient="bg-gradient-to-br from-emerald-500 to-green-700"
              trend={statistics?.activeEnrollments?.growthRate >= 0 ? 'up' : 'down'}
            />
            <StatCard
              title="Active Classes"
              value={statistics?.activeClasses?.count || 0}
              change={statistics?.activeClasses?.growthRate || 0}
              icon={GraduationCap}
              color="bg-purple-500 text-purple-500"
              bgGradient="bg-gradient-to-br from-purple-500 to-purple-800"
              trend={statistics?.activeClasses?.growthRate >= 0 ? 'up' : 'down'}
            />
            <StatCard
              title="Total Classrooms"
              value={systemInfo?.totalClassrooms || 0}
              change={0}
              icon={MapPin}
              color="bg-orange-500 text-orange-500"
              bgGradient="bg-gradient-to-br from-orange-500 to-red-600"
              trend="up"
            />
          </div>

          {/* Charts and Analytics Section */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {/* Enrollment Trends Chart */}
            <div className="lg:col-span-2 bg-gradient-to-br from-white to-blue-50/50 rounded-3xl shadow-xl border border-slate-200/50 p-6">
              <div className="flex items-center justify-between mb-6">
                <div>
                  <h3 className="text-xl font-bold text-slate-800">Enrollment Trends</h3>
                  <p className="text-sm text-slate-600">Real activity patterns from database</p>
                </div>
                <div className="flex space-x-2">
                  <button
                    onClick={() => setSelectedTimeFrame('week')}
                    className={`px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-sm ${
                      selectedTimeFrame === 'week'
                        ? 'bg-blue-500 text-white shadow-blue-500/30'
                        : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
                    }`}
                  >
                    Week
                  </button>
                  <button
                    onClick={() => setSelectedTimeFrame('month')}
                    className={`px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-sm ${
                      selectedTimeFrame === 'month'
                        ? 'bg-blue-500 text-white shadow-blue-500/30'
                        : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
                    }`}
                  >
                    Month
                  </button>
                </div>
              </div>
              
              <div className="bg-gradient-to-br from-slate-50 to-blue-50 rounded-2xl p-4 mb-4">
                <LineChart data={chartData} height={160} />
              </div>
              
              {/* Real Stats from chart data */}
              {chartData.length > 0 && (
                <div className="grid grid-cols-3 gap-4">
                  <div className="text-center p-3 bg-gradient-to-r from-blue-500/10 to-blue-600/10 rounded-xl border border-blue-200/30">
                    <p className="text-xl font-bold text-blue-600">
                      {chartData.reduce((sum, item) => sum + (item.enrollments || 0), 0)}
                    </p>
                    <p className="text-xs text-blue-600/80 font-medium">Total</p>
                  </div>
                  <div className="text-center p-3 bg-gradient-to-r from-emerald-500/10 to-emerald-600/10 rounded-xl border border-emerald-200/30">
                    <p className="text-xl font-bold text-emerald-600">
                      {chartData.length > 0 ? Math.round(chartData.reduce((sum, item) => sum + (item.enrollments || 0), 0) / chartData.length) : 0}
                    </p>
                    <p className="text-xs text-emerald-600/80 font-medium">Average</p>
                  </div>
                  <div className="text-center p-3 bg-gradient-to-r from-purple-500/10 to-purple-600/10 rounded-xl border border-purple-200/30">
                    <p className="text-xl font-bold text-purple-600">
                      {chartData.length > 0 ? Math.max(...chartData.map(item => item.enrollments || 0)) : 0}
                    </p>
                    <p className="text-xs text-purple-600/80 font-medium">Peak</p>
                  </div>
                </div>
              )}
            </div>

            {/* User Distribution */}
            <div className="bg-gradient-to-br from-white to-purple-50/50 rounded-3xl shadow-xl border border-slate-200/50 p-6">
              <div className="mb-6">
                <h3 className="text-xl font-bold text-slate-800">User Distribution</h3>
                <p className="text-sm text-slate-600">Real role breakdown from database</p>
              </div>
              
              <div className="flex items-center justify-center mb-6 bg-gradient-to-br from-slate-50 to-purple-50 rounded-2xl p-4">
                <SimplePieChart data={roleDistribution} />
              </div>
              
              <div className="space-y-3">
                {roleDistribution.slice(0, 3).map((role, index) => (
                  <div key={index} className="flex items-center justify-between text-sm p-2 bg-gradient-to-r from-slate-50 to-white rounded-lg">
                    <div className="flex items-center space-x-3">
                      <div 
                        className="w-4 h-4 rounded-full shadow-sm" 
                        style={{ backgroundColor: role.color }}
                      ></div>
                      <span className="text-slate-700 font-medium">{role.name}</span>
                    </div>
                    <span className="font-bold text-slate-800">{role.value}</span>
                  </div>
                ))}
                <div className="text-center pt-2 border-t border-slate-200">
                  <p className="text-lg font-bold text-slate-800">{totalUsers}</p>
                  <p className="text-xs text-slate-600">Total Users</p>
                </div>
              </div>
            </div>
          </div>

          {/* Recent Activity */}
          <div className="bg-gradient-to-r from-white via-indigo-50/30 to-purple-50/30 rounded-3xl shadow-xl border border-slate-200/50 p-6">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h3 className="text-xl font-bold text-slate-800">Recent Activity</h3>
                <p className="text-sm text-slate-600">Latest enrollments from database</p>
              </div>
              <div className="flex items-center space-x-2 bg-blue-100 rounded-lg px-3 py-2">
                <Activity className="w-4 h-4 text-blue-600" />
                <span className="text-sm font-semibold text-blue-800">
                  {recentEnrollments?.length || 0} recent
                </span>
              </div>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
              {recentEnrollments && recentEnrollments.length > 0 ? (
                recentEnrollments.slice(0, 5).map((enrollment, index) => (
                  <div key={index} className="p-4 bg-gradient-to-br from-white to-slate-50 rounded-2xl hover:from-blue-50 hover:to-indigo-50 transition-all duration-200 border border-slate-200/50 hover:border-blue-200/50 shadow-sm hover:shadow-md">
                    <div className="flex items-center space-x-2 mb-2">
                      <div className="w-3 h-3 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full"></div>
                      <span className="text-xs text-slate-500 font-medium">#{index + 1}</span>
                    </div>
                    <p className="text-sm font-bold text-slate-900 truncate mb-1">
                      {enrollment.student_name || 'Unknown Student'}
                    </p>
                    <p className="text-xs text-slate-600 truncate mb-2">
                      {enrollment.unit_name || 'Unknown Course'}
                    </p>
                    <p className="text-xs text-slate-500">
                      {enrollment.created_at ? new Date(enrollment.created_at).toLocaleDateString() : 'Recently'}
                    </p>
                  </div>
                ))
              ) : (
                <div className="col-span-full flex items-center justify-center py-12 text-slate-500">
                  <div className="text-center">
                    <BookOpen className="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p className="font-medium">No recent enrollments</p>
                    <p className="text-sm">Activity will appear here as students enroll</p>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
};

export default AdminDashboard;