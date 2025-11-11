import React from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
  Calendar, 
  AlertCircle, 
  TrendingUp, 
  TrendingDown, 
  MapPin, 
  Users, 
  Clock,
  Building,
  Plus,
  FileText,
  Settings
} from 'lucide-react';
import { Link } from '@inertiajs/react';

interface Statistic {
  count: number;
  growthRate: number;
  period: string;
  percentage?: number;
  total?: number;
  severity?: string;
}

interface Conflict {
  type: string;
  description: string;
  severity: string;
}

interface Activity {
  id: number;
  type: string;
  description: string;
  created_at: string;
}

interface DailySchedule {
  day: string;
  classes: number;
}

interface VenueUtilization {
  venue: string;
  usage: number;
}

interface QuickAction {
  title: string;
  description: string;
  route: string;
  icon: string;
}

interface Props {
  statistics: {
    totalTimetables: Statistic;
    activeClasses: Statistic;
    conflicts: Statistic;
    venueUtilization: Statistic;
  };
  currentSemester: {
    id: number;
    name: string;
  } | null;
  teachingModeDistribution: {
    physical: number;
    online: number;
    total: number;
  };
  conflictDetails: Conflict[];
  recentActivities: Activity[];
  dailySchedule: DailySchedule[];
  venueUtilization: VenueUtilization[];
  quickActions: QuickAction[];
  error?: string;
}

export default function Dashboard({
  statistics,
  currentSemester,
  teachingModeDistribution,
  conflictDetails,
  recentActivities,
  dailySchedule,
  venueUtilization,
  quickActions,
  error
}: Props) {
  const getIconForAction = (iconName: string) => {
    const iconMap: Record<string, React.ReactNode> = {
      plus: <Plus className="w-5 h-5" />,
      alert: <AlertCircle className="w-5 h-5" />,
      building: <Building className="w-5 h-5" />,
      settings: <Settings className="w-5 h-5" />
    };
    return iconMap[iconName] || <FileText className="w-5 h-5" />;
  };

  const getSeverityColor = (severity?: string) => {
    const colorMap: Record<string, string> = {
      critical: 'bg-red-100 text-red-800 border-red-300',
      high: 'bg-orange-100 text-orange-800 border-orange-300',
      medium: 'bg-yellow-100 text-yellow-800 border-yellow-300',
      none: 'bg-green-100 text-green-800 border-green-300'
    };
    return colorMap[severity || 'none'] || colorMap.none;
  };

  return (
    <AuthenticatedLayout>
      <Head title="Class Timetable Office Dashboard" />

      <div className="p-6 bg-gray-50 min-h-screen">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-3xl font-bold text-gray-900">Class Timetable Office</h1>
          <p className="text-gray-600 mt-1">
            Manage class schedules and resolve conflicts
            {/* {currentSemester && (
              <span className="ml-2">
                • Current Semester: <span className="font-semibold">{currentSemester.name}</span>
              </span>
            )} */}
          </p>
        </div>

        {/* Error Alert */}
        {error && (
          <Alert className="mb-6 border-red-200 bg-red-50">
            <AlertCircle className="h-4 w-4 text-red-600" />
            <AlertDescription className="text-red-700">{error}</AlertDescription>
          </Alert>
        )}

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
          {/* Total Timetables */}
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 mb-1">Total Timetables</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {statistics.totalTimetables.count.toLocaleString()}
                  </h3>
                  <div className="flex items-center mt-2">
                    {statistics.totalTimetables.growthRate >= 0 ? (
                      <TrendingUp className="w-4 h-4 text-green-600 mr-1" />
                    ) : (
                      <TrendingDown className="w-4 h-4 text-red-600 mr-1" />
                    )}
                    <span className={`text-sm ${statistics.totalTimetables.growthRate >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                      {Math.abs(statistics.totalTimetables.growthRate)}%
                    </span>
                    <span className="text-xs text-gray-500 ml-2">
                      {statistics.totalTimetables.period}
                    </span>
                  </div>
                </div>
                <Calendar className="w-12 h-12 text-blue-500" />
              </div>
            </CardContent>
          </Card>

          {/* Active Classes */}
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 mb-1">Active Classes</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {statistics.activeClasses.count.toLocaleString()}
                  </h3>
                  <p className="text-xs text-gray-500 mt-2">
                    {statistics.activeClasses.period}
                  </p>
                </div>
                <Users className="w-12 h-12 text-purple-500" />
              </div>
            </CardContent>
          </Card>

          {/* Conflicts */}
          <Card className={statistics.conflicts.count > 0 ? 'border-2 border-red-300' : ''}>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 mb-1">Scheduling Conflicts</p>
                  <h3 className={`text-3xl font-bold ${statistics.conflicts.count > 0 ? 'text-red-600' : 'text-green-600'}`}>
                    {statistics.conflicts.count.toLocaleString()}
                  </h3>
                  <Badge className={`mt-2 ${getSeverityColor(statistics.conflicts.severity)}`}>
                    {statistics.conflicts.severity || 'None'}
                  </Badge>
                </div>
                <AlertCircle className={`w-12 h-12 ${statistics.conflicts.count > 0 ? 'text-red-500' : 'text-green-500'}`} />
              </div>
            </CardContent>
          </Card>

          {/* Venue Utilization */}
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 mb-1">Venue Utilization</p>
                  <h3 className="text-3xl font-bold text-gray-900">
                    {statistics.venueUtilization.percentage}%
                  </h3>
                  <p className="text-xs text-gray-500 mt-2">
                    {statistics.venueUtilization.count}/{statistics.venueUtilization.total} venues
                  </p>
                </div>
                <MapPin className="w-12 h-12 text-indigo-500" />
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Left Column - 2/3 width */}
          <div className="lg:col-span-2 space-y-6">
            {/* Teaching Mode Distribution */}
            <Card>
              <CardHeader>
                <CardTitle>Teaching Mode Distribution</CardTitle>
                <CardDescription>Physical vs Online Classes</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div>
                    <div className="flex justify-between mb-2">
                      <span className="text-sm font-medium">Physical Classes</span>
                      <span className="text-sm text-gray-600">
                        {teachingModeDistribution.physical} ({teachingModeDistribution.total > 0 
                          ? Math.round((teachingModeDistribution.physical / teachingModeDistribution.total) * 100) 
                          : 0}%)
                      </span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-3">
                      <div 
                        className="bg-green-500 h-3 rounded-full"
                        style={{ 
                          width: `${teachingModeDistribution.total > 0 
                            ? (teachingModeDistribution.physical / teachingModeDistribution.total) * 100 
                            : 0}%` 
                        }}
                      />
                    </div>
                  </div>

                  <div>
                    <div className="flex justify-between mb-2">
                      <span className="text-sm font-medium">Online Classes</span>
                      <span className="text-sm text-gray-600">
                        {teachingModeDistribution.online} ({teachingModeDistribution.total > 0 
                          ? Math.round((teachingModeDistribution.online / teachingModeDistribution.total) * 100) 
                          : 0}%)
                      </span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-3">
                      <div 
                        className="bg-blue-500 h-3 rounded-full"
                        style={{ 
                          width: `${teachingModeDistribution.total > 0 
                            ? (teachingModeDistribution.online / teachingModeDistribution.total) * 100 
                            : 0}%` 
                        }}
                      />
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Daily Schedule Overview */}
            <Card>
              <CardHeader>
                <CardTitle>Weekly Schedule Overview</CardTitle>
                <CardDescription>Classes per day</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {dailySchedule.map((day) => (
                    <div key={day.day} className="flex items-center">
                      <span className="w-24 text-sm font-medium">{day.day}</span>
                      <div className="flex-1 bg-gray-200 rounded-full h-2">
                        <div 
                          className="bg-indigo-500 h-2 rounded-full"
                          style={{ 
                            width: `${dailySchedule.length > 0 
                              ? (day.classes / Math.max(...dailySchedule.map(d => d.classes))) * 100 
                              : 0}%` 
                          }}
                        />
                      </div>
                      <span className="ml-3 text-sm text-gray-600 w-12 text-right">
                        {day.classes}
                      </span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>

            {/* Conflict Details */}
            {conflictDetails.length > 0 && (
              <Card className="border-red-200">
                <CardHeader>
                  <CardTitle className="text-red-700 flex items-center">
                    <AlertCircle className="w-5 h-5 mr-2" />
                    Active Conflicts
                  </CardTitle>
                  <CardDescription>Scheduling conflicts requiring attention</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    {conflictDetails.slice(0, 5).map((conflict, index) => (
                      <Alert key={index} className="border-orange-200 bg-orange-50">
                        <AlertCircle className="h-4 w-4 text-orange-600" />
                        <AlertDescription className="text-orange-800">
                          <span className="font-semibold">{conflict.type}:</span> {conflict.description}
                        </AlertDescription>
                      </Alert>
                    ))}
                  </div>
                  {conflictDetails.length > 5 && (
                    <Link href="/admin/classtimetables/conflicts">
                      <Button variant="outline" className="w-full mt-4">
                        View All {conflictDetails.length} Conflicts
                      </Button>
                    </Link>
                  )}
                </CardContent>
              </Card>
            )}
          </div>

          {/* Right Column - 1/3 width */}
          <div className="space-y-6">
            {/* Quick Actions */}
            <Card>
              <CardHeader>
                <CardTitle>Quick Actions</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                {quickActions.map((action, index) => (
                  <Link key={index} href={action.route}>
                    <Button variant="outline" className="w-full justify-start">
                      {getIconForAction(action.icon)}
                      <div className="ml-3 text-left">
                        <div className="font-medium">{action.title}</div>
                        <div className="text-xs text-gray-500">{action.description}</div>
                      </div>
                    </Button>
                  </Link>
                ))}
              </CardContent>
            </Card>

            {/* Recent Activities */}
            <Card>
              <CardHeader>
                <CardTitle>Recent Activities</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {recentActivities.map((activity) => (
                    <div key={activity.id} className="border-l-2 border-blue-500 pl-3 py-2">
                      <p className="text-sm">{activity.description}</p>
                      <p className="text-xs text-gray-500 mt-1">
                        <Clock className="w-3 h-3 inline mr-1" />
                        {new Date(activity.created_at).toLocaleString()}
                      </p>
                    </div>
                  ))}
                  {recentActivities.length === 0 && (
                    <p className="text-sm text-gray-500">No recent activities</p>
                  )}
                </div>
              </CardContent>
            </Card>

            {/* Top Venues */}
            {venueUtilization.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle>Top Venues</CardTitle>
                  <CardDescription>Most utilized classrooms</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-2">
                    {venueUtilization.slice(0, 5).map((venue, index) => (
                      <div key={index} className="flex items-center justify-between text-sm">
                        <span className="font-medium">{venue.venue}</span>
                        <Badge variant="outline">{venue.usage} classes</Badge>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}