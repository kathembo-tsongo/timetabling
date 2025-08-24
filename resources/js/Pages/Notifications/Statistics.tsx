"use client"
import { Head } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface DailyStats {
  date: string
  total: number
  successful: number
  failed: number
}

interface ChannelStats {
  channel: string
  total: number
  successful: number
  failed: number
}

interface TypeStats {
  notification_type: string
  total: number
  successful: number
  failed: number
}

interface Failure {
  id: number
  notification_type: string
  channel: string
  error_message: string
  created_at: string
}

interface OverallStats {
  total: number
  successful: number
  failed: number
  success_rate: number
}

interface PageProps {
  auth: any
  dailyStats: DailyStats[]
  channelStats: ChannelStats[]
  typeStats: TypeStats[]
  recentFailures: Failure[]
  overallStats: OverallStats
  can: {
    view_stats: boolean
  }
}

export default function Statistics({
  auth,
  dailyStats,
  channelStats,
  typeStats,
  recentFailures,
  overallStats,
  can,
}: PageProps) {
  const formatDate = (dateString: string) => {
    const options: Intl.DateTimeFormatOptions = {
      year: "numeric",
      month: "short",
      day: "numeric",
    }
    return new Date(dateString).toLocaleDateString(undefined, options)
  }

  const formatDateTime = (dateTimeString: string) => {
    const options: Intl.DateTimeFormatOptions = {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }
    return new Date(dateTimeString).toLocaleDateString(undefined, options)
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Notification Statistics</h2>}
    >
      <Head title="Notification Statistics" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          {can.view_stats ? (
            <>
              {/* Overall Stats Cards */}
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                  <div className="text-sm font-medium text-gray-500">Total Notifications</div>
                  <div className="text-3xl font-bold">{overallStats.total}</div>
                </div>
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                  <div className="text-sm font-medium text-gray-500">Successful</div>
                  <div className="text-3xl font-bold text-green-600">{overallStats.successful}</div>
                </div>
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                  <div className="text-sm font-medium text-gray-500">Failed</div>
                  <div className="text-3xl font-bold text-red-600">{overallStats.failed}</div>
                </div>
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                  <div className="text-sm font-medium text-gray-500">Success Rate</div>
                  <div className="text-3xl font-bold">{overallStats.success_rate}%</div>
                </div>
              </div>

              {/* Daily Stats */}
              <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Daily Notification Activity</h3>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Date
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Total
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Successful
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Failed
                        </th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {dailyStats.map((day, index) => (
                        <tr key={index}>
                          <td className="px-6 py-4 whitespace-nowrap">{formatDate(day.date)}</td>
                          <td className="px-6 py-4 whitespace-nowrap">{day.total}</td>
                          <td className="px-6 py-4 whitespace-nowrap text-green-600">{day.successful}</td>
                          <td className="px-6 py-4 whitespace-nowrap text-red-600">{day.failed}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Channel Stats */}
              <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Notifications by Channel</h3>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Channel
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Total
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Successful
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Failed
                        </th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {channelStats.map((channel, index) => (
                        <tr key={index}>
                          <td className="px-6 py-4 whitespace-nowrap capitalize">{channel.channel}</td>
                          <td className="px-6 py-4 whitespace-nowrap">{channel.total}</td>
                          <td className="px-6 py-4 whitespace-nowrap text-green-600">{channel.successful}</td>
                          <td className="px-6 py-4 whitespace-nowrap text-red-600">{channel.failed}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Recent Failures */}
              <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Recent Failures</h3>
                {recentFailures.length > 0 ? (
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Time
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Type
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Channel
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Error
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {recentFailures.map((failure) => (
                          <tr key={failure.id}>
                            <td className="px-6 py-4 whitespace-nowrap">{formatDateTime(failure.created_at)}</td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              {failure.notification_type.replace("App\\Notifications\\", "")}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap capitalize">{failure.channel}</td>
                            <td className="px-6 py-4">
                              <div className="text-sm text-red-600 truncate max-w-xs">{failure.error_message}</div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <div className="text-center py-4 text-gray-500">No recent failures found</div>
                )}
              </div>
            </>
          ) : (
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
              <div className="text-center py-4 text-gray-500">
                You do not have permission to view notification statistics.
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
