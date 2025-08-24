"use client"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import route from "ziggy-js"
import { useState } from "react"

interface Notification {
  id: string
  type: string
  data: any
  created_at: string
  read_at: string | null
}

interface PageProps {
  auth: any
  unreadNotifications: Notification[]
  readNotifications: Notification[]
}

export default function UserNotifications({ auth }: PageProps) {
  const { unreadNotifications, readNotifications } = usePage<PageProps>().props
  const [activeTab, setActiveTab] = useState("unread")

  const handleMarkAsRead = (id: string) => {
    router.post(route("notifications.mark-read", id))
  }

  const handleMarkAllAsRead = () => {
    if (confirm("Mark all notifications as read?")) {
      router.post(route("notifications.mark-all-read"))
    }
  }

  const formatNotificationContent = (notification: Notification) => {
    switch (notification.type) {
      case "Exam_reminder":
        return (
          <div>
            <div className="font-medium">{notification.data.subject}</div>
            <p className="text-sm text-gray-600">{notification.data.message}</p>
            {notification.data.exam_details && (
              <div className="mt-2 text-sm">
                <div>
                  <strong>Unit:</strong> {notification.data.exam_details.unit}
                </div>
                <div>
                  <strong>Date:</strong> {notification.data.exam_details.date}
                </div>
                <div>
                  <strong>Time:</strong> {notification.data.exam_details.time}
                </div>
              </div>
            )}
          </div>
        )

      case "ExamTimetableUpdate":
        return (
          <div>
            <div className="font-medium">{notification.data.subject}</div>
            <p className="text-sm text-gray-600">{notification.data.message}</p>
            {notification.data.changes && (
              <div className="mt-2 text-sm border-l-2 border-yellow-400 pl-2">
                <div className="font-medium">Changes:</div>
                {Object.entries(notification.data.changes).map(([field, values]: [string, any]) => (
                  <div key={field}>
                    <strong>{field}:</strong> Changed from "{values.old}" to "{values.new}"
                  </div>
                ))}
              </div>
            )}
          </div>
        )

      default:
        return (
          <div>
            <div className="font-medium">{notification.data.subject || "Notification"}</div>
            <p className="text-sm text-gray-600">{notification.data.message || JSON.stringify(notification.data)}</p>
          </div>
        )
    }
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Your Notifications</h2>}
    >
      <Head title="Your Notifications" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <div className="flex space-x-4">
                  <button
                    onClick={() => setActiveTab("unread")}
                    className={`px-4 py-2 font-medium rounded-md ${
                      activeTab === "unread" ? "bg-indigo-100 text-indigo-700" : "text-gray-600 hover:bg-gray-100"
                    }`}
                  >
                    Unread ({unreadNotifications.length})
                  </button>
                  <button
                    onClick={() => setActiveTab("read")}
                    className={`px-4 py-2 font-medium rounded-md ${
                      activeTab === "read" ? "bg-indigo-100 text-indigo-700" : "text-gray-600 hover:bg-gray-100"
                    }`}
                  >
                    Read
                  </button>
                </div>

                {unreadNotifications.length > 0 && (
                  <button onClick={handleMarkAllAsRead} className="text-sm text-indigo-600 hover:text-indigo-800">
                    Mark all as read
                  </button>
                )}
              </div>

              {activeTab === "unread" && (
                <>
                  {unreadNotifications.length === 0 ? (
                    <div className="text-center py-8 text-gray-500">
                      <svg
                        className="mx-auto h-12 w-12 text-gray-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={1}
                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                        />
                      </svg>
                      <h3 className="mt-2 text-sm font-medium text-gray-900">No unread notifications</h3>
                      <p className="mt-1 text-sm text-gray-500">You're all caught up!</p>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      {unreadNotifications.map((notification) => (
                        <div
                          key={notification.id}
                          className="border rounded-lg p-4 bg-indigo-50 border-indigo-100 relative"
                        >
                          <div className="flex justify-between">
                            <div className="flex items-center mb-2">
                              <span className="inline-flex items-center justify-center h-8 w-8 rounded-full bg-indigo-100 text-indigo-500 mr-2">
                                {notification.type === "Exam_reminder" ? (
                                  <svg
                                    className="h-5 w-5"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                  >
                                    <path
                                      strokeLinecap="round"
                                      strokeLinejoin="round"
                                      strokeWidth={2}
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                  </svg>
                                ) : notification.type === "ExamTimetableUpdate" ? (
                                  <svg
                                    className="h-5 w-5"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                  >
                                    <path
                                      strokeLinecap="round"
                                      strokeLinejoin="round"
                                      strokeWidth={2}
                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                    />
                                  </svg>
                                ) : (
                                  <svg
                                    className="h-5 w-5"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                  >
                                    <path
                                      strokeLinecap="round"
                                      strokeLinejoin="round"
                                      strokeWidth={2}
                                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                  </svg>
                                )}
                              </span>
                              <span className="font-medium text-sm text-indigo-800">{notification.type}</span>
                            </div>
                            <button
                              onClick={() => handleMarkAsRead(notification.id)}
                              className="text-xs text-indigo-600 hover:text-indigo-800"
                            >
                              Mark as read
                            </button>
                          </div>

                          {formatNotificationContent(notification)}

                          <div className="mt-2 text-xs text-gray-500">{notification.created_at}</div>
                        </div>
                      ))}
                    </div>
                  )}
                </>
              )}

              {activeTab === "read" && (
                <>
                  {readNotifications.length === 0 ? (
                    <div className="text-center py-8 text-gray-500">
                      <p>No read notifications</p>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      {readNotifications.map((notification) => (
                        <div key={notification.id} className="border rounded-lg p-4 bg-gray-50 border-gray-200">
                          <div className="flex items-center mb-2">
                            <span className="inline-flex items-center justify-center h-8 w-8 rounded-full bg-gray-200 text-gray-500 mr-2">
                              {notification.type === "Exam_reminder" ? (
                                <svg
                                  className="h-5 w-5"
                                  xmlns="http://www.w3.org/2000/svg"
                                  fill="none"
                                  viewBox="0 0 24 24"
                                  stroke="currentColor"
                                >
                                  <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                  />
                                </svg>
                              ) : notification.type === "ExamTimetableUpdate" ? (
                                <svg
                                  className="h-5 w-5"
                                  xmlns="http://www.w3.org/2000/svg"
                                  fill="none"
                                  viewBox="0 0 24 24"
                                  stroke="currentColor"
                                >
                                  <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                  />
                                </svg>
                              ) : (
                                <svg
                                  className="h-5 w-5"
                                  xmlns="http://www.w3.org/2000/svg"
                                  fill="none"
                                  viewBox="0 0 24 24"
                                  stroke="currentColor"
                                >
                                  <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                  />
                                </svg>
                              )}
                            </span>
                            <span className="font-medium text-sm text-gray-800">{notification.type}</span>
                          </div>

                          {formatNotificationContent(notification)}

                          <div className="mt-2 text-xs text-gray-500">
                            {notification.created_at} • Read {notification.read_at}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
