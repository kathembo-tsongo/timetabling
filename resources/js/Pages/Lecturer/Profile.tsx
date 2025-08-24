"use client"

import { Head } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface School {
  id: number
  name: string
}

interface Lecturer {
  id: number
  name: string
  email: string
  code?: string
  phone?: string
  school?: School
  position?: string
  department?: string
  office_location?: string
  office_hours?: string
  bio?: string
}

interface Props {
  lecturer: Lecturer | null
  error?: string
}

const Profile = ({ lecturer, error }: Props) => {
  return (
    <AuthenticatedLayout>
      <Head title="Lecturer Profile" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-semibold">Lecturer Profile</h1>
          <div>
            <a
              href="/lecturer/dashboard"
              className="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                className="h-4 w-4 mr-2"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                />
              </svg>
              Dashboard
            </a>
          </div>
        </div>

        {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{error}</div>}

        {lecturer ? (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="md:col-span-1">
              <div className="bg-gray-50 p-4 rounded-lg border">
                <div className="flex flex-col items-center">
                  <div className="w-32 h-32 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                    <span className="text-4xl font-bold text-blue-600">
                      {lecturer.name
                        .split(" ")
                        .map((n) => n[0])
                        .join("")
                        .toUpperCase()}
                    </span>
                  </div>
                  <h2 className="text-xl font-semibold text-center">{lecturer.name}</h2>
                  <p className="text-gray-600 text-center">{lecturer.position || "Lecturer"}</p>
                  <p className="text-gray-600 text-center">{lecturer.school?.name || "School not specified"}</p>
                </div>
              </div>
            </div>

            <div className="md:col-span-2">
              <div className="bg-gray-50 p-4 rounded-lg border mb-6">
                <h3 className="text-lg font-medium mb-3">Contact Information</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-600">Email</p>
                    <p className="font-medium">{lecturer.email}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Phone</p>
                    <p className="font-medium">{lecturer.phone || "Not provided"}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Office Location</p>
                    <p className="font-medium">{lecturer.office_location || "Not provided"}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Office Hours</p>
                    <p className="font-medium">{lecturer.office_hours || "Not provided"}</p>
                  </div>
                </div>
              </div>

              <div className="bg-gray-50 p-4 rounded-lg border">
                <h3 className="text-lg font-medium mb-3">Academic Information</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-600">Lecturer ID</p>
                    <p className="font-medium">{lecturer.code || "Not provided"}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Department</p>
                    <p className="font-medium">{lecturer.department || "Not provided"}</p>
                  </div>
                  <div className="md:col-span-2">
                    <p className="text-sm text-gray-600">Bio</p>
                    <p className="font-medium">{lecturer.bio || "No bio provided"}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        ) : (
          <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
            <p className="text-yellow-700">
              Unable to load profile information. Please try again later or contact the administrator.
            </p>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}

export default Profile
