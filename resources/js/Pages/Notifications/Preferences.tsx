"use client"

import type React from "react"
import { Head, useForm } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface NotificationPreference {
  id: number
  email_enabled: boolean
  sms_enabled: boolean
  push_enabled: boolean
  hours_before: number
  reminder_enabled: boolean
}

interface PageProps {
  auth: any
  preferences: NotificationPreference
}

export default function Preferences({ auth, preferences }: PageProps) {
  const { data, setData, post, processing, errors } = useForm({
    email_enabled: preferences.email_enabled,
    sms_enabled: preferences.sms_enabled,
    push_enabled: preferences.push_enabled,
    hours_before: preferences.hours_before,
    reminder_enabled: preferences.reminder_enabled,
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    post(route("notifications.preferences.update"))
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Notification Preferences</h2>}
    >
      <Head title="Notification Preferences" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <div className="max-w-xl">
              <h2 className="text-lg font-medium text-gray-900">Notification Preferences</h2>
              <p className="mt-1 text-sm text-gray-600">Update your notification preferences for exam reminders.</p>

              <form onSubmit={handleSubmit} className="mt-6 space-y-6">
                {/* Enable/Disable All Notifications */}
                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-gray-700">Enable Exam Reminders</label>
                    <p className="text-sm text-gray-500">Receive notifications about upcoming exams</p>
                  </div>
                  <div className="relative inline-block w-10 mr-2 align-middle select-none">
                    <input
                      type="checkbox"
                      id="reminder_enabled"
                      checked={data.reminder_enabled}
                      onChange={(e) => setData("reminder_enabled", e.target.checked)}
                      className="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                    />
                    <label
                      htmlFor="reminder_enabled"
                      className={`toggle-label block overflow-hidden h-6 rounded-full cursor-pointer ${
                        data.reminder_enabled ? "bg-green-400" : "bg-gray-300"
                      }`}
                    ></label>
                  </div>
                </div>

                <div className="border-t border-gray-200 pt-4">
                  <h3 className="text-md font-medium text-gray-900">Notification Channels</h3>
                  <p className="text-sm text-gray-500">Choose how you want to receive notifications</p>

                  {/* Email Notifications */}
                  <div className="mt-4 flex items-center justify-between">
                    <div>
                      <label className="text-sm font-medium text-gray-700">Email Notifications</label>
                      <p className="text-sm text-gray-500">Receive notifications via email</p>
                    </div>
                    <div className="relative inline-block w-10 mr-2 align-middle select-none">
                      <input
                        type="checkbox"
                        id="email_enabled"
                        checked={data.email_enabled}
                        onChange={(e) => setData("email_enabled", e.target.checked)}
                        className="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                      />
                      <label
                        htmlFor="email_enabled"
                        className={`toggle-label block overflow-hidden h-6 rounded-full cursor-pointer ${
                          data.email_enabled ? "bg-green-400" : "bg-gray-300"
                        }`}
                      ></label>
                    </div>
                  </div>

                  {/* SMS Notifications */}
                  <div className="mt-4 flex items-center justify-between">
                    <div>
                      <label className="text-sm font-medium text-gray-700">SMS Notifications</label>
                      <p className="text-sm text-gray-500">Receive notifications via SMS</p>
                    </div>
                    <div className="relative inline-block w-10 mr-2 align-middle select-none">
                      <input
                        type="checkbox"
                        id="sms_enabled"
                        checked={data.sms_enabled}
                        onChange={(e) => setData("sms_enabled", e.target.checked)}
                        className="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                      />
                      <label
                        htmlFor="sms_enabled"
                        className={`toggle-label block overflow-hidden h-6 rounded-full cursor-pointer ${
                          data.sms_enabled ? "bg-green-400" : "bg-gray-300"
                        }`}
                      ></label>
                    </div>
                  </div>

                  {/* Push Notifications */}
                  <div className="mt-4 flex items-center justify-between">
                    <div>
                      <label className="text-sm font-medium text-gray-700">Push Notifications</label>
                      <p className="text-sm text-gray-500">Receive notifications on your device</p>
                    </div>
                    <div className="relative inline-block w-10 mr-2 align-middle select-none">
                      <input
                        type="checkbox"
                        id="push_enabled"
                        checked={data.push_enabled}
                        onChange={(e) => setData("push_enabled", e.target.checked)}
                        className="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                      />
                      <label
                        htmlFor="push_enabled"
                        className={`toggle-label block overflow-hidden h-6 rounded-full cursor-pointer ${
                          data.push_enabled ? "bg-green-400" : "bg-gray-300"
                        }`}
                      ></label>
                    </div>
                  </div>
                </div>

                {/* Timing Preferences */}
                <div className="border-t border-gray-200 pt-4">
                  <h3 className="text-md font-medium text-gray-900">Timing Preferences</h3>
                  <p className="text-sm text-gray-500">When do you want to receive notifications?</p>

                  <div className="mt-4">
                    <label htmlFor="hours_before" className="block text-sm font-medium text-gray-700">
                      Hours before exam
                    </label>
                    <select
                      id="hours_before"
                      value={data.hours_before}
                      onChange={(e) => setData("hours_before", Number.parseInt(e.target.value))}
                      className="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                    >
                      <option value="1">1 hour before</option>
                      <option value="2">2 hours before</option>
                      <option value="6">6 hours before</option>
                      <option value="12">12 hours before</option>
                      <option value="24">24 hours before (1 day)</option>
                      <option value="48">48 hours before (2 days)</option>
                      <option value="72">72 hours before (3 days)</option>
                    </select>
                  </div>
                </div>

                <div className="flex items-center gap-4 pt-4">
                  <button
                    type="submit"
                    className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    disabled={processing}
                  >
                    {processing ? "Saving..." : "Save Preferences"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
